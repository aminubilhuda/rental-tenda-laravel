<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DamagePayment;
use App\Models\DamageRecord;
use App\Models\Item;
use App\Models\Rental;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function dataset(string $type, string $from, string $to, ?int $categoryId = null, ?string $query = null, ?string $status = null): array
    {
        return match ($type) {
            'revenue' => $this->revenue($from, $to, $categoryId),
            'transactions' => $this->transactions($from, $to, $categoryId, $query, $status),
            'assets' => $this->assets($categoryId),
            'performance' => $this->performance($from, $to, $categoryId),
            'damages' => $this->damages($from, $to, $categoryId),
            'customers' => $this->customers($from, $to, $categoryId),
            'customer-list' => $this->customerList(),
            'late' => $this->late($from, $to, $categoryId),
            default => abort(404),
        };
    }

    private function revenue(string $from, string $to, ?int $categoryId): array
    {
        $rentals = Rental::query()->where('status', '!=', 'cancelled')->whereBetween('rent_date', [$from, $to])->when($categoryId, fn ($builder) => $builder->whereHas('items.item', fn ($item) => $item->where('category_id', $categoryId)))->get(['rent_date', 'total']);
        $lateFees = Rental::query()->whereNotNull('returned_at')->whereBetween(DB::raw('DATE(returned_at)'), [$from, $to])->when($categoryId, fn ($builder) => $builder->whereHas('items.item', fn ($item) => $item->where('category_id', $categoryId)))->get(['returned_at', 'late_fee']);
        $damagePayments = DamagePayment::query()->whereBetween(DB::raw('DATE(paid_at)'), [$from, $to])->get(['paid_at', 'amount']);
        $days = [];
        foreach (CarbonPeriod::create($from, $to) as $date) {
            $key = $date->format('Y-m-d');
            $days[$key] = ['date' => $key, 'rent' => 0, 'late' => 0, 'damage' => 0];
        }
        foreach ($rentals as $rental) {
            $days[$rental->rent_date->format('Y-m-d')]['rent'] += (int) $rental->total;
        }
        foreach ($lateFees as $rental) {
            $days[CarbonImmutable::parse($rental->returned_at)->format('Y-m-d')]['late'] += (int) $rental->late_fee;
        }
        foreach ($damagePayments as $payment) {
            $days[CarbonImmutable::parse($payment->paid_at)->format('Y-m-d')]['damage'] += (int) $payment->amount;
        }
        $rows = array_map(fn (array $day): array => [$day['date'], $day['rent'], $day['late'], $day['damage'], $day['rent'] + $day['late'] + $day['damage']], array_values($days));

        return [['Tanggal', 'Pendapatan sewa', 'Denda terlambat', 'Ganti rugi diterima', 'Total pendapatan'], $rows];
    }

    private function transactions(string $from, string $to, ?int $categoryId, ?string $query, ?string $status): array
    {
        $rows = Rental::query()->with(['customer', 'cashier'])
            ->whereDate('rent_date', '>=', $from)
            ->whereDate('rent_date', '<=', $to)
            ->when($categoryId, fn ($builder) => $builder->whereHas('items.item', fn ($item) => $item->where('category_id', $categoryId)))
            ->when($query, fn ($builder) => $builder->where(fn ($nested) => $nested->where('trx_code', 'like', "%{$query}%")->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$query}%"))))
            ->when($status && $status !== 'late', fn ($builder) => $builder->where('status', $status))
            ->when($status === 'late', fn ($builder) => $builder->where('status', 'rented')->whereDate('due_date', '<', today()))
            ->orderByDesc('rent_date')->get()->map(fn (Rental $rental): array => [
                $rental->trx_code, $rental->customer?->name ?? 'Pelanggan diarsipkan', $rental->customer?->phone ?? '—', $rental->rent_date->format('d/m/Y'), $rental->due_date->format('d/m/Y'), $rental->display_status, $rental->total, $rental->late_fee, $rental->cashier?->name ?? 'Kasir diarsipkan',
            ])->all();

        return [['ID Transaksi', 'Pelanggan', 'Telepon', 'Tanggal Sewa', 'Tanggal Kembali', 'Status', 'Total Sewa', 'Denda', 'Kasir'], $rows];
    }

    private function assets(?int $categoryId): array
    {
        $rows = Item::with('category')->when($categoryId, fn ($builder) => $builder->where('category_id', $categoryId))->orderBy('name')->get()->map(fn (Item $item): array => [
            $item->code, $item->name, $item->category->name, $item->stock_total, $item->rentedUnits(), $item->stock_damaged, $item->availableStock(), $item->purchase_price * $item->stock_total, $item->is_active ? 'Aktif' : 'Nonaktif',
        ])->all();

        return [['Kode', 'Nama alat', 'Kategori', 'Stok total', 'Sedang disewa', 'Rusak', 'Tersedia', 'Nilai aset', 'Status'], $rows];
    }

    private function performance(string $from, string $to, ?int $categoryId): array
    {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        $periodDays = max(1, $start->diffInDays($end) + 1);
        $rows = Item::with(['category', 'rentalItems.rental'])->when($categoryId, fn ($builder) => $builder->where('category_id', $categoryId))->orderBy('name')->get()->map(function (Item $item) use ($start, $end, $periodDays): array {
            $rentedQty = 0;
            $revenue = 0;
            $unitDays = 0;
            foreach ($item->rentalItems as $line) {
                $rental = $line->rental;
                if (! $rental || $rental->status === 'cancelled' || $rental->rent_date->gt($end) || $rental->due_date->lt($start)) {
                    continue;
                }
                $overlapStart = $rental->rent_date->greaterThan($start) ? $rental->rent_date : $start;
                $overlapEnd = $rental->due_date->lessThan($end) ? $rental->due_date : $end;
                $days = max(1, (int) $overlapStart->diffInDays($overlapEnd));
                $rentedQty += (int) $line->qty;
                $revenue += (int) $line->subtotal;
                $unitDays += $days * (int) $line->qty;
            }
            $utilization = $item->stock_total > 0 ? round(($unitDays / ($periodDays * $item->stock_total)) * 100, 2) : 0;
            $assetValue = $item->purchase_price * $item->stock_total;

            return [$item->code, $item->name, $item->category->name, $rentedQty, $revenue, $unitDays, $utilization, $assetValue > 0 ? round(($revenue / $assetValue) * 100, 2) : 0];
        })->all();

        return [['Kode', 'Nama alat', 'Kategori', 'Frekuensi unit disewa', 'Pendapatan sewa', 'Unit-hari tersewa', 'Utilisasi (%)', 'ROI terhadap nilai aset (%)'], $rows];
    }

    private function damages(string $from, string $to, ?int $categoryId): array
    {
        $rows = DamageRecord::with(['customer', 'item', 'rental'])->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->when($categoryId, fn ($builder) => $builder->whereHas('item', fn ($item) => $item->where('category_id', $categoryId)))
            ->orderByDesc('created_at')->get()->map(fn (DamageRecord $damage): array => [
                $damage->code, $damage->created_at->format('d/m/Y'), $damage->rental?->trx_code ?? 'Transaksi diarsipkan', $damage->customer?->name ?? 'Pelanggan diarsipkan', $damage->item?->name ?? 'Alat diarsipkan', $damage->type, $damage->qty, $damage->charge_amount, $damage->paid_amount, max(0, $damage->charge_amount - $damage->paid_amount), $damage->status,
            ])->all();

        return [['ID Ganti rugi', 'Tanggal', 'ID Transaksi', 'Pelanggan', 'Alat', 'Jenis', 'Jumlah', 'Tagihan', 'Terbayar', 'Sisa', 'Status'], $rows];
    }

    private function customers(string $from, string $to, ?int $categoryId): array
    {
        $periodRentals = fn ($builder) => $builder->whereBetween('rent_date', [$from, $to])->when($categoryId, fn ($query) => $query->whereHas('items.item', fn ($item) => $item->where('category_id', $categoryId)));
        $rows = Customer::query()->whereHas('rentals', $periodRentals)
            ->withCount(['rentals as period_transactions' => $periodRentals])
            ->withSum(['rentals as period_spending' => $periodRentals], 'total')
            ->orderByDesc('period_spending')->get()->map(fn (Customer $customer): array => [
                $customer->name, $customer->phone, $customer->period_transactions, (int) $customer->period_spending, $customer->period_transactions > 1 ? 'Repeat' : 'Baru / sekali', $customer->is_blacklisted ? 'Blacklist' : 'Normal',
            ])->all();

        return [['Pelanggan', 'Telepon', 'Transaksi periode', 'Belanja periode', 'Segmentasi', 'Status'], $rows];
    }

    private function customerList(): array
    {
        $rows = Customer::query()->orderBy('name')->get()->map(fn (Customer $customer): array => [
            $customer->name, $customer->phone, $customer->address, $customer->id_type, $customer->id_number, $customer->notes, $customer->is_blacklisted ? 'Blacklist' : 'Normal', $customer->blacklist_reason,
        ])->all();

        return [['Nama', 'Telepon', 'Alamat', 'Jenis identitas', 'Nomor identitas', 'Catatan', 'Status', 'Alasan blacklist'], $rows];
    }

    private function late(string $from, string $to, ?int $categoryId): array
    {
        $rows = Rental::with(['customer', 'cashier'])->where(function ($builder) use ($from, $to): void {
            $builder->where(fn ($query) => $query->where('status', 'rented')->whereDate('due_date', '<', today())->whereBetween('due_date', [$from, $to]))
                ->orWhere(fn ($query) => $query->where('status', 'returned')->whereColumn('returned_at', '>', DB::raw('due_date'))->whereBetween(DB::raw('DATE(returned_at)'), [$from, $to]));
        })->when($categoryId, fn ($builder) => $builder->whereHas('items.item', fn ($item) => $item->where('category_id', $categoryId)))->orderByDesc('due_date')->get()->map(fn (Rental $rental): array => [
            $rental->trx_code, $rental->customer?->name ?? 'Pelanggan diarsipkan', $rental->customer?->phone ?? '—', $rental->due_date->format('d/m/Y'), $rental->returned_at?->format('d/m/Y H:i') ?? 'Belum kembali', max(0, $rental->due_date->diffInDays($rental->returned_at ?? now())), $rental->late_fee, $rental->display_status,
        ])->all();

        return [['ID Transaksi', 'Pelanggan', 'Telepon', 'Jatuh tempo', 'Tanggal kembali', 'Hari terlambat', 'Denda', 'Status'], $rows];
    }
}
