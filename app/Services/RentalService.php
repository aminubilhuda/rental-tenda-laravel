<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DamagePayment;
use App\Models\DamageRecord;
use App\Models\Item;
use App\Models\Rental;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalService
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function checkout(array $data, int $userId): Rental
    {
        return DB::transaction(function () use ($data, $userId): Rental {
            $rentDate = now()->parse($data['rent_date'])->startOfDay();
            $dueDate = now()->parse($data['due_date'])->startOfDay();
            if ($dueDate->lt($rentDate)) {
                throw ValidationException::withMessages(['due_date' => 'Tanggal kembali tidak boleh sebelum tanggal sewa.']);
            }
            $days = max(1, (int) $rentDate->diffInDays($dueDate));
            if (! empty($data['idempotency_key'])) {
                $tokenSequence = 'checkout-'.str_replace('-', '', $data['idempotency_key']);
                DB::table('daily_sequences')->insertOrIgnore(['name' => $tokenSequence, 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('daily_sequences')->where('name', $tokenSequence)->lockForUpdate()->first();
                $existingRental = Rental::query()->where('checkout_token', $data['idempotency_key'])->first();
                if ($existingRental) {
                    return $existingRental->load(['customer', 'items']);
                }
            }
            if (isset($data['customer_id'])) {
                $customer = Customer::query()->lockForUpdate()->findOrFail($data['customer_id']);
            } else {
                $customer = Customer::withTrashed()->where('phone', $data['phone'])->lockForUpdate()->first();
                if (! $customer) {
                    $customer = Customer::create(['phone' => $data['phone'], 'name' => $data['customer_name'], 'address' => $data['address'] ?? null]);
                } elseif ($customer->trashed()) {
                    $customer->restore();
                    $customer->update(['name' => $data['customer_name'], 'address' => $data['address'] ?? null]);
                }
            }
            if ($customer->is_blacklisted && Setting::getValue('block_blacklist', '0') === '1') {
                throw ValidationException::withMessages(['customer_id' => 'Transaksi diblokir: pelanggan masuk daftar hitam.']);
            }
            $selections = collect($data['items'])->groupBy('item_id')->map(fn ($lines, $itemId): array => ['item_id' => (int) $itemId, 'qty' => (int) $lines->sum('qty')])->values();
            $lockedItems = Item::query()->whereIn('id', $selections->pluck('item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($lockedItems->count() !== $selections->pluck('item_id')->unique()->count()) {
                throw ValidationException::withMessages(['items' => 'Alat yang dipilih tidak ditemukan.']);
            }
            $subtotal = 0;
            $lineItems = [];
            foreach ($selections as $selection) {
                $item = $lockedItems->get((int) $selection['item_id']);
                $qty = (int) $selection['qty'];
                if (! $item->is_active || $item->availableStock() < $qty) {
                    throw ValidationException::withMessages(['items' => "Stok {$item->name} tidak cukup."]);
                }
                $lineSubtotal = $item->rental_price_per_day * $qty * $days;
                $subtotal += $lineSubtotal;
                $lineItems[] = ['item_id' => $item->id, 'item_name' => $item->name, 'price_per_day' => $item->rental_price_per_day, 'qty' => $qty, 'subtotal' => $lineSubtotal];
            }
            $discount = (int) ($data['discount'] ?? 0);
            if ($discount > $subtotal) {
                throw ValidationException::withMessages(['discount' => 'Diskon tidak boleh melebihi subtotal.']);
            }
            $sequenceName = 'trx-'.$rentDate->format('Ymd');
            DB::table('daily_sequences')->insertOrIgnore(['name' => $sequenceName, 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = DB::table('daily_sequences')->where('name', $sequenceName)->lockForUpdate()->first();
            $next = $sequence->value + 1;
            DB::table('daily_sequences')->where('name', $sequenceName)->update(['value' => $next, 'updated_at' => now()]);
            $rental = Rental::create(['trx_code' => 'TRX-'.$rentDate->format('Ymd').'-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT), 'checkout_token' => $data['idempotency_key'] ?? null, 'customer_id' => $customer->id, 'user_id' => $userId, 'rent_date' => $rentDate->toDateString(), 'due_date' => $dueDate->toDateString(), 'duration_days' => $days, 'subtotal' => $subtotal, 'discount' => $discount, 'total' => $subtotal - $discount, 'guarantee_type' => $data['guarantee_type'], 'deposit_amount' => (int) ($data['deposit_amount'] ?? 0), 'customer_photo_path' => $data['customer_photo_path'] ?? null, 'status' => 'rented', 'notes' => $data['notes'] ?? null]);
            $rental->items()->createMany($lineItems);
            $this->activityLogger->log('created', 'rentals', "Transaksi {$rental->trx_code} dibuat.", $rental);

            return $rental->load(['customer', 'items']);
        }, 3);
    }

    public function returnRental(Rental $rental, array $data, int $userId): Rental
    {
        return DB::transaction(function () use ($rental, $data, $userId): Rental {
            $rental = Rental::query()->with('items.item')->lockForUpdate()->findOrFail($rental->id);
            if ($rental->status !== 'rented') {
                throw ValidationException::withMessages(['rental' => 'Transaksi ini sudah diproses.']);
            }
            $lateDays = max(0, (int) $rental->due_date->startOfDay()->diffInDays(now()->parse($data['returned_at'])->startOfDay(), false));
            $lateFee = (int) ($data['late_fee'] ?? 0);
            if (! isset($data['late_fee'])) {
                foreach ($rental->items as $rentalItem) {
                    $itemRate = Setting::getValue('late_fee_mode', 'percent') === 'fixed' ? (int) Setting::getValue('late_fee_value', '0') : (int) round($rentalItem->price_per_day * ((int) Setting::getValue('late_fee_value', '100') / 100));
                    $lateFee += $itemRate * $lateDays * $rentalItem->qty;
                }
            }
            $deduction = (int) ($data['deposit_deducted'] ?? 0);
            if ($deduction > $rental->deposit_amount) {
                throw ValidationException::withMessages(['deposit_deducted' => 'Potongan deposit melebihi nilai deposit.']);
            }
            $createdDamages = [];
            foreach ($rental->items as $rentalItem) {
                $result = collect($data['items'])->firstWhere('rental_item_id', $rentalItem->id);
                if (! $result || ((int) $result['good'] + (int) $result['damaged'] + (int) $result['lost']) !== $rentalItem->qty) {
                    throw ValidationException::withMessages(['items' => "Jumlah kondisi {$rentalItem->item_name} harus sama dengan jumlah sewa."]);
                }
                $item = Item::query()->lockForUpdate()->findOrFail($rentalItem->item_id);
                $rentalItem->update(['qty_returned_good' => (int) $result['good'], 'qty_damaged' => (int) $result['damaged'], 'qty_lost' => (int) $result['lost']]);
                if ((int) $result['damaged'] > 0) {
                    $item->increment('stock_damaged', (int) $result['damaged']);
                }
                if ((int) $result['lost'] > 0) {
                    $item->decrement('stock_total', (int) $result['lost']);
                }
                foreach (['damaged' => 'major_damage', 'lost' => 'lost'] as $key => $type) {
                    $qty = (int) $result[$key];
                    if ($qty === 0) {
                        continue;
                    }
                    if ($key === 'damaged') {
                        $type = $result['damage_type'] ?? 'major_damage';
                    }
                    $sequenceName = 'damage-'.today()->format('Ymd');
                    DB::table('daily_sequences')->insertOrIgnore(['name' => $sequenceName, 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);
                    $sequence = DB::table('daily_sequences')->where('name', $sequenceName)->lockForUpdate()->first();
                    $recordCount = $sequence->value + 1;
                    DB::table('daily_sequences')->where('name', $sequenceName)->update(['value' => $recordCount, 'updated_at' => now()]);
                    $chargeInput = $key === 'damaged' ? ($result['charge_damaged'] ?? null) : ($result['charge_lost'] ?? null);
                    $charge = $chargeInput !== null && $chargeInput !== '' ? (int) $chargeInput : ($type === 'minor_damage' ? 0 : $item->purchase_price * $qty);
                    if ($type === 'minor_damage' && $charge < 1) {
                        throw ValidationException::withMessages(['items' => 'Biaya rusak ringan perlu diisi manual.']);
                    }
                    $damage = DamageRecord::create(['code' => 'GR-'.today()->format('Ymd').'-'.str_pad((string) $recordCount, 3, '0', STR_PAD_LEFT), 'rental_id' => $rental->id, 'rental_item_id' => $rentalItem->id, 'customer_id' => $rental->customer_id, 'item_id' => $item->id, 'type' => $type, 'qty' => $qty, 'charge_amount' => $charge, 'status' => 'unpaid', 'resolution' => $key === 'lost' ? 'n_a' : 'pending', 'notes' => $data['notes'] ?? null]);
                    $createdDamages[] = $damage;
                    $this->activityLogger->log('created', 'damages', "Ganti rugi {$damage->code} dibuat otomatis.", $damage);
                }
            }
            $availableDeduction = $lateFee + array_sum(array_map(fn (DamageRecord $damage): int => $damage->charge_amount, $createdDamages));
            if ($deduction > $availableDeduction) {
                throw ValidationException::withMessages(['deposit_deducted' => 'Potongan deposit melebihi total denda dan ganti rugi.']);
            }
            $damageDeduction = max(0, $deduction - $lateFee);
            foreach ($createdDamages as $damage) {
                if ($damageDeduction === 0) {
                    break;
                }
                $paymentAmount = min($damageDeduction, $damage->charge_amount);
                DamagePayment::create(['damage_record_id' => $damage->id, 'amount' => $paymentAmount, 'method' => 'deposit', 'paid_at' => now(), 'user_id' => $userId, 'notes' => "Potongan deposit transaksi {$rental->trx_code}"]);
                $damage->update(['paid_amount' => $paymentAmount, 'status' => $paymentAmount >= $damage->charge_amount ? 'paid' : 'partial']);
                $damageDeduction -= $paymentAmount;
            }
            $rental->update(['returned_at' => $data['returned_at'], 'status' => 'returned', 'late_fee' => $lateFee, 'deposit_deducted' => $deduction, 'deposit_returned' => $rental->deposit_amount - $deduction, 'notes' => $data['notes'] ?? $rental->notes]);
            $this->activityLogger->log('returned', 'rentals', "Pengembalian {$rental->trx_code} diproses.", $rental);

            return $rental->fresh(['customer', 'items', 'damages']);
        }, 3);
    }

    public function extendRental(Rental $rental, string $newDueDate, int $userId): Rental
    {
        return DB::transaction(function () use ($rental, $newDueDate): Rental {
            $rental = Rental::query()->with('items')->lockForUpdate()->findOrFail($rental->id);
            if ($rental->status !== 'rented') {
                throw ValidationException::withMessages(['rental' => 'Hanya transaksi aktif yang dapat diperpanjang.']);
            }
            $newDue = now()->parse($newDueDate)->startOfDay();
            if (! $newDue->gt($rental->due_date->startOfDay())) {
                throw ValidationException::withMessages(['due_date' => 'Tanggal perpanjangan harus melewati tanggal kembali saat ini.']);
            }
            $additionalDays = (int) $rental->due_date->startOfDay()->diffInDays($newDue);
            $additionalCost = 0;
            foreach ($rental->items as $rentalItem) {
                $item = Item::query()->lockForUpdate()->findOrFail($rentalItem->item_id);
                $reservedUnits = (int) DB::table('rental_items')->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')->where('rental_items.item_id', $item->id)->where('rentals.status', 'rented')->where('rentals.id', '!=', $rental->id)->whereDate('rentals.rent_date', '<', $newDue->toDateString())->whereDate('rentals.due_date', '>', $rental->due_date->toDateString())->selectRaw('COALESCE(SUM(qty - qty_returned_good - qty_damaged - qty_lost),0) as units')->value('units');
                if ($reservedUnits + $rentalItem->qty > $item->stock_total - $item->stock_damaged) {
                    throw ValidationException::withMessages(['due_date' => "Stok {$item->name} tidak tersedia sampai tanggal perpanjangan."]);
                }
                $additionalCost += $rentalItem->price_per_day * $rentalItem->qty * $additionalDays;
            }
            $rental->update(['due_date' => $newDue->toDateString(), 'duration_days' => $rental->duration_days + $additionalDays, 'subtotal' => $rental->subtotal + $additionalCost, 'total' => $rental->total + $additionalCost]);
            $this->activityLogger->log('extended', 'rentals', "Sewa {$rental->trx_code} diperpanjang {$additionalDays} hari.", $rental);

            return $rental->fresh(['customer', 'items']);
        }, 3);
    }

    public function cancelRental(Rental $rental, string $reason): Rental
    {
        return DB::transaction(function () use ($rental, $reason): Rental {
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->id);
            if ($rental->status !== 'rented') {
                throw ValidationException::withMessages(['rental' => 'Hanya transaksi aktif yang dapat dibatalkan.']);
            }
            $rental->update(['status' => 'cancelled', 'cancelled_reason' => $reason]);
            $this->activityLogger->log('cancelled', 'rentals', "Transaksi {$rental->trx_code} dibatalkan: {$reason}", $rental);

            return $rental;
        }, 3);
    }
}
