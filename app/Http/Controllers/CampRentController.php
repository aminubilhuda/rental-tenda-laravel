<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DamageRecord;
use App\Models\Item;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\RentalService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class CampRentController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger, private readonly RentalService $rentalService, private readonly ReportService $reportService) {}

    private function manager(Request $request): void
    {
        abort_unless($request->user()->isManager(), 403);
    }

    private function owner(Request $request): void
    {
        abort_unless($request->user()->isOwner(), 403);
    }

    public function page(Request $request, string $section = 'dashboard')
    {
        $managerOnly = ['categories', 'reports', 'activity'];
        if (in_array($section, $managerOnly, true)) {
            $this->manager($request);
        }
        if (in_array($section, ['settings', 'users'], true)) {
            $this->owner($request);
        }
        abort_unless(in_array($section, ['dashboard', 'pos', 'items', 'categories', 'customers', 'rentals', 'damages', 'reports', 'activity', 'settings', 'users'], true), 404);
        $businessName = Setting::getValue('business_name', 'CampRent') ?: 'CampRent';
        $businessLogoPath = Setting::getValue('business_logo_path');
        $businessLogoUrl = $businessLogoPath ? Storage::disk('public')->url($businessLogoPath) : null;
        $dashboardPeriod = 'month';
        $dashboardFrom = now()->startOfMonth()->toDateString();
        $dashboardTo = today()->toDateString();
        $chartYear = now()->year;
        if ($section === 'dashboard') {
            $dashboardFilters = $request->validate(['period' => ['nullable', Rule::in(['today', '7days', 'month', 'year', 'custom'])], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'year' => ['nullable', 'integer', 'min:2000', 'max:2100']]);
            $dashboardPeriod = $dashboardFilters['period'] ?? 'month';
            $chartYear = (int) ($dashboardFilters['year'] ?? now()->year);
            [$dashboardFrom, $dashboardTo] = match ($dashboardPeriod) {
                'today' => [today()->toDateString(), today()->toDateString()],
                '7days' => [today()->subDays(6)->toDateString(), today()->toDateString()],
                'year' => [Carbon::create($chartYear, 1, 1)->toDateString(), Carbon::create($chartYear, 12, 31)->toDateString()],
                'custom' => [$dashboardFilters['from'] ?? now()->startOfMonth()->toDateString(), $dashboardFilters['to'] ?? today()->toDateString()],
                default => [now()->startOfMonth()->toDateString(), today()->toDateString()],
            };
        }
        $query = trim((string) $request->query('q', ''));
        $activeRentalUnits = DB::table('rental_items')->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')->where('rentals.status', 'rented')->whereNull('rentals.deleted_at')->whereColumn('rental_items.item_id', 'items.id')->selectRaw('COALESCE(SUM(qty - qty_returned_good - qty_damaged - qty_lost),0)');
        $rows = match ($section) {
            'items' => Item::with('category')->select('items.*')->selectSub($activeRentalUnits, 'rented_units_cache')->when($query, fn ($builder) => $builder->where('name', 'like', "%{$query}%"))->when($request->query('category_id'), fn ($builder, $categoryId) => $builder->where('category_id', $categoryId))->when($request->query('status') === 'active', fn ($builder) => $builder->where('is_active', true))->when($request->query('status') === 'inactive', fn ($builder) => $builder->where('is_active', false))->latest()->paginate(10)->withQueryString(),
            'categories' => Category::withCount('items')->when($query, fn ($builder) => $builder->where('name', 'like', "%{$query}%"))->latest()->paginate(10),
            'customers' => Customer::withCount('rentals')->when($query, fn ($builder) => $builder->where('name', 'like', "%{$query}%")->orWhere('phone', 'like', "%{$query}%"))->latest()->paginate(10),
            'rentals' => Rental::with(['customer', 'cashier', 'items'])->when($query, fn ($builder) => $builder->where(fn ($queryBuilder) => $queryBuilder->where('trx_code', 'like', "%{$query}%")->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$query}%"))))->when($request->query('status'), fn ($builder, $status) => $status === 'late' ? $builder->where('status', 'rented')->whereDate('due_date', '<', today()) : $builder->where('status', $status))->when($request->query('from'), fn ($builder, $from) => $builder->whereDate('rent_date', '>=', $from))->when($request->query('to'), fn ($builder, $to) => $builder->whereDate('rent_date', '<=', $to))->latest()->paginate(10)->withQueryString(),
            'damages' => DamageRecord::with(['customer', 'item', 'rental'])->when($query, fn ($builder) => $builder->where('code', 'like', "%{$query}%")->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$query}%")))->latest()->paginate(10),
            'users' => User::latest()->paginate(10),
            'activity' => ActivityLog::with('user')->when($query, fn ($builder) => $builder->where('description', 'like', "%{$query}%"))->when($request->query('user_id'), fn ($builder, $userId) => $builder->where('user_id', $userId))->when($request->query('module'), fn ($builder, $module) => $builder->where('module', $module))->when($request->query('from'), fn ($builder, $from) => $builder->whereDate('created_at', '>=', $from))->when($request->query('to'), fn ($builder, $to) => $builder->whereDate('created_at', '<=', $to))->latest('created_at')->paginate(20)->withQueryString(),
            default => collect(),
        };
        $items = Item::with('category')->select('items.*')->selectSub($activeRentalUnits, 'rented_units_cache')->where('is_active', true)->get()->map(fn (Item $item) => ['id' => $item->id, 'code' => $item->code, 'name' => $item->name, 'category' => $item->category->name, 'price' => $item->rental_price_per_day, 'stock' => $item->availableStock(), 'photo' => $item->photo_path ? Storage::disk('public')->url($item->photo_path) : null]);
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $stats = [
            'item_types' => Item::count(), 'asset_value' => (int) Item::selectRaw('COALESCE(SUM(purchase_price * stock_total),0) as total')->value('total'),
            'rented_units' => (int) DB::table('rental_items')->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')->where('rentals.status', 'rented')->selectRaw('COALESCE(SUM(qty - qty_returned_good - qty_damaged - qty_lost),0) as total')->value('total'),
            'rented_value' => (int) DB::table('rental_items')->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')->join('items', 'items.id', '=', 'rental_items.item_id')->where('rentals.status', 'rented')->selectRaw('COALESCE(SUM(items.purchase_price * (qty - qty_returned_good - qty_damaged - qty_lost)),0) as total')->value('total'),
            'rental_count' => Rental::whereBetween('rent_date', [$dashboardFrom, $dashboardTo])->count(), 'revenue' => (int) Rental::where('status', 'returned')->whereBetween(DB::raw('DATE(returned_at)'), [$dashboardFrom, $dashboardTo])->sum(DB::raw('total + late_fee')), 'due_today' => Rental::where('status', 'rented')->whereDate('due_date', today())->count(), 'overdue' => Rental::where('status', 'rented')->whereDate('due_date', '<', today())->count(),
        ];
        $stats['low_stock'] = $items->filter(fn (array $item): bool => $item['stock'] <= 2)->count();
        $monthly = array_fill(1, 12, 0);
        $monthlyPrevious = array_fill(1, 12, 0);
        $comparisonStart = Carbon::create($chartYear - 1, 1, 1)->toDateString();
        $comparisonEnd = Carbon::create($chartYear, 12, 31)->toDateString();
        Rental::query()->where('status', 'returned')->whereBetween(DB::raw('DATE(returned_at)'), [$comparisonStart, $comparisonEnd])->get(['returned_at', 'total', 'late_fee'])->each(function (Rental $rental) use (&$monthly, &$monthlyPrevious, $chartYear): void {
            $month = $rental->returned_at->month;
            $target = $rental->returned_at->year === $chartYear ? 'current' : 'previous';
            if ($target === 'current') {
                $monthly[$month] += $rental->total + $rental->late_fee;
            } else {
                $monthlyPrevious[$month] += $rental->total + $rental->late_fee;
            }
        });
        $popular = DB::table('rental_items')->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')->where('rentals.status', '!=', 'cancelled')->whereBetween('rentals.rent_date', [$dashboardFrom, $dashboardTo])->select('item_name', DB::raw('SUM(qty) as quantity'))->groupBy('item_name')->orderByDesc('quantity')->limit(5)->get();
        $popularTotal = (int) $popular->sum('quantity');
        $popularSegments = [];
        $donutOffset = 0.0;
        $donutColors = ['#258459', '#438cc4', '#f1a544', '#875cc4', '#e36b66'];
        foreach ($popular as $index => $popularItem) {
            $segmentLength = $popularTotal > 0 ? ((int) $popularItem->quantity / $popularTotal) * 314.159 : 0;
            $popularSegments[] = ['name' => $popularItem->item_name, 'quantity' => (int) $popularItem->quantity, 'percent' => $popularTotal > 0 ? round(((int) $popularItem->quantity / $popularTotal) * 100) : 0, 'dasharray' => $segmentLength.' '.(314.159 - $segmentLength), 'dashoffset' => -$donutOffset, 'color' => $donutColors[$index % count($donutColors)]];
            $donutOffset += $segmentLength;
        }
        $rentalForReceipt = $request->query('receipt') ? Rental::with(['customer', 'cashier', 'items'])->find($request->query('receipt')) : null;
        $rentalDetail = $section === 'rentals' && $request->query('detail') ? Rental::with(['customer', 'cashier', 'items', 'damages.payments'])->findOrFail($request->query('detail')) : null;
        $rentalHistory = $rentalDetail ? ActivityLog::with('user')->where('subject_type', Rental::class)->where('subject_id', $rentalDetail->id)->oldest('created_at')->get() : collect();
        $customerDetail = $section === 'customers' && $request->query('detail') ? Customer::with(['rentals.items', 'damages.item'])->findOrFail($request->query('detail')) : null;
        $activityUsers = $section === 'activity' ? User::orderBy('name')->get(['id', 'name']) : collect();
        $damageCandidates = $section === 'damages' ? RentalItem::with(['rental.customer', 'item'])->whereHas('rental', fn ($query) => $query->where('status', 'returned'))->where('qty_returned_good', '>', 0)->get() : collect();
        $upcomingReturns = $section === 'dashboard' ? Rental::with('customer')->where('status', 'rented')->orderBy('due_date')->limit(6)->get() : collect();
        $recentRentals = $section === 'dashboard' ? Rental::with('customer')->latest('rent_date')->limit(6)->get() : collect();

        return view('app.index', compact('section', 'rows', 'items', 'categories', 'stats', 'monthly', 'monthlyPrevious', 'popular', 'popularTotal', 'popularSegments', 'rentalForReceipt', 'query', 'damageCandidates', 'rentalDetail', 'rentalHistory', 'customerDetail', 'activityUsers', 'dashboardPeriod', 'dashboardFrom', 'dashboardTo', 'chartYear', 'businessName', 'businessLogoUrl', 'upcomingReturns', 'recentRentals'));
    }

    public function save(Request $request, string $section)
    {
        try {
            return match ($section) {
                'categories' => $this->saveCategory($request), 'items' => $this->saveItem($request), 'customers' => $this->saveCustomer($request),
                'users' => $this->saveUser($request), 'settings' => $this->saveSettings($request), 'stock' => $this->adjustStock($request), 'damages' => $this->saveDamage($request),
                default => abort(404),
            };
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '19'], true)) {
                throw ValidationException::withMessages(['name' => 'Data duplikat: periksa kembali nama, kode, atau nomor yang dimasukkan.']);
            }
            throw $exception;
        }
    }

    private function saveCategory(Request $request)
    {
        $this->manager($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string']]);
        $exists = Category::whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['name' => 'Nama kategori sudah digunakan.']);
        }
        $category = Category::create($data + ['is_active' => true]);
        $this->logger->log('created', 'categories', "Kategori {$category->name} ditambahkan.", $category);

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    private function saveItem(Request $request)
    {
        $this->manager($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'category_id' => ['required', 'exists:categories,id'], 'purchase_price' => ['required', 'integer', 'min:0'], 'rental_price_per_day' => ['required', 'integer', 'min:0'], 'stock_total' => ['required', 'integer', 'min:0'], 'description' => ['nullable', 'string'], 'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $photoPath = isset($data['photo']) ? $data['photo']->store('items', 'public') : null;
        if (isset($data['photo']) && $photoPath) {
            $this->resizePhoto($data['photo']->getRealPath(), $photoPath, (string) $data['photo']->getClientOriginalExtension());
        }
        unset($data['photo']);
        $item = Item::create($data + ['code' => 'ALT-'.str_pad((string) ((Item::withTrashed()->max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT), 'photo_path' => $photoPath, 'is_active' => true]);
        $this->logger->log('created', 'items', "Alat {$item->name} ditambahkan.", $item);

        return back()->with('success', 'Alat berhasil ditambahkan.');
    }

    private function resizePhoto(string $temporaryPath, string $storedPath, string $extension): void
    {
        $image = @imagecreatefromstring((string) file_get_contents($temporaryPath));
        if (! $image) {
            return;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 1200 / max($width, $height));
        if ($scale >= 1.0) {
            imagedestroy($image);

            return;
        }
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if (in_array(strtolower($extension), ['png', 'webp'], true)) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        ob_start();
        match (strtolower($extension)) {
            'png' => imagepng($resized), 'webp' => imagewebp($resized, null, 85), default => imagejpeg($resized, null, 85),
        };
        Storage::disk('public')->put($storedPath, (string) ob_get_clean());
        imagedestroy($image);
        imagedestroy($resized);
    }

    private function saveCustomer(Request $request)
    {
        $this->manager($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'phone' => ['required', 'string', 'max:30', Rule::unique('customers', 'phone')], 'address' => ['nullable', 'string'], 'id_type' => ['nullable', 'string', 'max:50'], 'id_number' => ['nullable', 'string', 'max:80'], 'notes' => ['nullable', 'string'], 'is_blacklisted' => ['nullable', 'boolean'], 'blacklist_reason' => ['nullable', 'required_if:is_blacklisted,1', 'string']]);
        $customer = Customer::create($data);
        $this->logger->log('created', 'customers', "Pelanggan {$customer->name} ditambahkan.", $customer);

        return back()->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    private function saveUser(Request $request)
    {
        $this->owner($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'username' => ['required', 'alpha_dash', 'max:60', 'unique:users,username'], 'role' => ['required', Rule::in(['owner', 'admin', 'kasir'])], 'password' => ['required', 'string', 'min:8'], 'email' => ['nullable', 'email', 'unique:users,email']]);
        $user = User::create($data + ['is_active' => true]);
        $this->logger->log('created', 'users', "Pengguna {$user->name} dibuat.", $user);

        return back()->with('success', 'Pengguna berhasil dibuat.');
    }

    private function saveSettings(Request $request)
    {
        $this->owner($request);
        $data = $request->validate(['business_name' => ['sometimes', 'required', 'string', 'max:160'], 'business_address' => ['nullable', 'string'], 'business_phone' => ['nullable', 'string', 'max:40'], 'business_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'receipt_width' => ['sometimes', 'required', Rule::in(['58', '80'])], 'late_fee_mode' => ['sometimes', 'required', Rule::in(['percent', 'fixed'])], 'late_fee_value' => ['sometimes', 'required', 'integer', 'min:0'], 'block_blacklist' => ['nullable', 'boolean'], 'require_customer_photo' => ['nullable', 'boolean'], 'guarantee_types' => ['nullable', 'string', 'max:1000'], 'receipt_terms' => ['nullable', 'string']]);
        if (isset($data['business_logo'])) {
            $oldLogo = Setting::getValue('business_logo_path');
            $data['business_logo_path'] = $data['business_logo']->store('business', 'public');
            unset($data['business_logo']);
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
        }
        foreach (['block_blacklist', 'require_customer_photo'] as $booleanSetting) {
            if ($request->exists($booleanSetting)) {
                $data[$booleanSetting] = $request->boolean($booleanSetting) ? '1' : '0';
            }
        }
        foreach ($data as $key => $value) {
            Setting::putValue($key, (string) $value);
        }
        $this->logger->log('updated', 'settings', 'Pengaturan usaha diperbarui.');

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }

    private function adjustStock(Request $request)
    {
        $this->manager($request);
        $data = $request->validate(['item_id' => ['required', 'exists:items,id'], 'type' => ['required', Rule::in(['add', 'reduce'])], 'qty' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:500'], 'confirm_action' => ['exclude_unless:type,reduce', 'required', 'accepted']]);
        DB::transaction(function () use ($data, $request): void {
            $item = Item::lockForUpdate()->findOrFail($data['item_id']);
            if ($data['type'] === 'reduce' && $item->availableStock() < $data['qty']) {
                throw ValidationException::withMessages(['qty' => 'Stok tersedia tidak cukup untuk dikurangi.']);
            }
            $item->update(['stock_total' => $item->stock_total + ($data['type'] === 'add' ? $data['qty'] : -$data['qty'])]);
            DB::table('stock_adjustments')->insert(['item_id' => $item->id, 'user_id' => $request->user()->id, 'type' => $data['type'], 'qty' => $data['qty'], 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
            $this->logger->log('stock_adjusted', 'items', "Stok {$item->name} disesuaikan: {$data['reason']}.", $item);
        });

        return back()->with('success', 'Stok berhasil disesuaikan.');
    }

    private function saveDamage(Request $request)
    {
        $this->manager($request);
        $data = $request->validate(['rental_item_id' => ['required', 'exists:rental_items,id'], 'type' => ['required', Rule::in(['minor_damage', 'major_damage', 'lost'])], 'qty' => ['required', 'integer', 'min:1'], 'charge_amount' => ['nullable', 'integer', 'min:0'], 'notes' => ['nullable', 'string'], 'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);

        DB::transaction(function () use ($data): void {
            $line = RentalItem::with('rental')->lockForUpdate()->findOrFail($data['rental_item_id']);
            if ($line->rental->status !== 'returned') {
                throw ValidationException::withMessages(['rental_item_id' => 'Ganti rugi manual hanya dapat dibuat dari transaksi yang sudah dikembalikan.']);
            }
            if ($line->qty_returned_good < $data['qty']) {
                throw ValidationException::withMessages(['qty' => 'Jumlah melebihi unit baik yang belum pernah dicatat sebagai ganti rugi.']);
            }
            $item = Item::lockForUpdate()->findOrFail($line->item_id);
            $type = $data['type'];
            $charge = array_key_exists('charge_amount', $data) && $data['charge_amount'] !== null
                ? (int) $data['charge_amount']
                : ($type === 'minor_damage' ? 0 : $item->purchase_price * (int) $data['qty']);
            if ($type === 'minor_damage' && $charge < 1) {
                throw ValidationException::withMessages(['charge_amount' => 'Biaya rusak ringan wajib diisi.']);
            }
            $photoPath = isset($data['photo']) ? $data['photo']->store('damage-proofs', 'public') : null;
            $sequenceName = 'damage-'.today()->format('Ymd');
            DB::table('daily_sequences')->insertOrIgnore(['name' => $sequenceName, 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = DB::table('daily_sequences')->where('name', $sequenceName)->lockForUpdate()->first();
            $next = $sequence->value + 1;
            DB::table('daily_sequences')->where('name', $sequenceName)->update(['value' => $next, 'updated_at' => now()]);
            $damage = DamageRecord::create(['code' => 'GR-'.today()->format('Ymd').'-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT), 'rental_id' => $line->rental_id, 'rental_item_id' => $line->id, 'customer_id' => $line->rental->customer_id, 'item_id' => $item->id, 'type' => $type, 'qty' => $data['qty'], 'charge_amount' => $charge, 'status' => 'unpaid', 'resolution' => $type === 'lost' ? 'n_a' : 'pending', 'photo_path' => $photoPath, 'notes' => $data['notes'] ?? null]);
            $line->increment($type === 'lost' ? 'qty_lost' : 'qty_damaged', (int) $data['qty']);
            $line->decrement('qty_returned_good', (int) $data['qty']);
            if ($type === 'lost') {
                $item->decrement('stock_total', (int) $data['qty']);
            } else {
                $item->increment('stock_damaged', (int) $data['qty']);
            }
            $this->logger->log('created', 'damages', "Ganti rugi manual {$damage->code} dicatat.", $damage);
        });

        return back()->with('success', 'Ganti rugi berhasil dicatat.');
    }

    public function checkout(Request $request)
    {
        $token = $request->validate(['idempotency_key' => ['nullable', 'uuid']])['idempotency_key'] ?? null;
        if ($token) {
            $existingRental = Rental::query()->where('checkout_token', $token)->first();
            if ($existingRental) {
                abort_unless($existingRental->user_id === $request->user()->id, 409);

                return redirect()->route('page', ['section' => 'rentals', 'receipt' => $existingRental->id])->with('success', "Transaksi {$existingRental->trx_code} sudah berhasil dibuat.");
            }
        }
        $photoRule = Setting::getValue('require_customer_photo', '0') === '1' ? 'required' : 'nullable';
        $guaranteeTypes = array_values(array_filter(array_map('trim', explode('|', Setting::getValue('guarantee_types', 'KTP Asli|SIM Asli|KTM/Kartu Pelajar|Paspor|Lainnya|Uang Jaminan')))));
        if ($guaranteeTypes === []) {
            $guaranteeTypes = ['KTP Asli', 'SIM Asli', 'KTM/Kartu Pelajar', 'Paspor', 'Lainnya', 'Uang Jaminan'];
        }
        $data = $request->validate(['idempotency_key' => ['nullable', 'uuid'], 'rent_date' => ['required', 'date'], 'due_date' => ['required', 'date', 'after_or_equal:rent_date'], 'customer_id' => ['nullable', 'exists:customers,id'], 'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:160'], 'phone' => ['required_without:customer_id', 'nullable', 'string', 'max:30'], 'address' => ['nullable', 'string'], 'guarantee_type' => ['required', Rule::in($guaranteeTypes)], 'deposit_amount' => ['nullable', 'integer', 'min:0'], 'discount' => ['nullable', 'integer', 'min:0'], 'customer_photo' => [$photoRule, 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'items' => ['required', 'array', 'min:1'], 'items.*.item_id' => ['required', 'exists:items,id'], 'items.*.qty' => ['required', 'integer', 'min:1']]);
        if ($request->hasFile('customer_photo')) {
            $data['customer_photo_path'] = $request->file('customer_photo')->store('renter-photos', 'local');
        }
        unset($data['customer_photo']);
        $rental = $this->rentalService->checkout($data, $request->user()->id);

        return redirect()->route('page', ['section' => 'rentals', 'receipt' => $rental->id])->with('success', "Transaksi {$rental->trx_code} berhasil dibuat.");
    }

    public function returnRental(Request $request, Rental $rental)
    {
        $data = $request->validate(['returned_at' => ['required', 'date'], 'late_fee' => ['nullable', 'integer', 'min:0'], 'late_fee_reason' => ['required_with:late_fee', 'nullable', 'string', 'max:500'], 'deposit_deducted' => ['nullable', 'integer', 'min:0'], 'notes' => ['nullable', 'string'], 'items' => ['required', 'array'], 'items.*.rental_item_id' => ['required', 'distinct', 'exists:rental_items,id'], 'items.*.good' => ['required', 'integer', 'min:0'], 'items.*.damaged' => ['required', 'integer', 'min:0'], 'items.*.lost' => ['required', 'integer', 'min:0'], 'items.*.damage_type' => ['nullable', Rule::in(['minor_damage', 'major_damage'])], 'items.*.charge_damaged' => ['nullable', 'integer', 'min:0'], 'items.*.charge_lost' => ['nullable', 'integer', 'min:0']]);
        if ($request->filled('late_fee')) {
            $this->manager($request);
        }
        $this->rentalService->returnRental($rental, $data, $request->user()->id);
        if ($request->filled('late_fee')) {
            $this->logger->log('late_fee_overridden', 'rentals', "Denda transaksi {$rental->trx_code} disesuaikan menjadi Rp ".number_format((int) $data['late_fee'], 0, ',', '.').": {$data['late_fee_reason']}", $rental);
        }

        return back()->with('success', 'Pengembalian berhasil diproses.');
    }

    public function showRental(Request $request, Rental $rental)
    {
        return redirect()->route('page', ['section' => 'rentals', 'detail' => $rental->id]);
    }

    public function showCustomer(Request $request, Customer $customer)
    {
        return redirect()->route('page', ['section' => 'customers', 'detail' => $customer->id]);
    }

    public function customerPhoto(Request $request, Rental $rental)
    {
        abort_unless($rental->customer_photo_path && Storage::disk('local')->exists($rental->customer_photo_path), 404);

        return Storage::disk('local')->response($rental->customer_photo_path);
    }

    public function extendRental(Request $request, Rental $rental)
    {
        $data = $request->validate(['due_date' => ['required', 'date', 'after:'.$rental->due_date->toDateString()]]);
        $updated = $this->rentalService->extendRental($rental, $data['due_date'], $request->user()->id);

        return back()->with('success', "Sewa {$updated->trx_code} diperpanjang. Biaya tambahan: Rp ".number_format($updated->total - $rental->total, 0, ',', '.').'.');
    }

    public function cancelRental(Request $request, Rental $rental)
    {
        $data = $request->validate(['cancelled_reason' => ['required', 'string', 'max:500'], 'confirm_action' => ['required', 'accepted']]);
        $this->rentalService->cancelRental($rental, $data['cancelled_reason']);

        return back()->with('success', 'Transaksi berhasil dibatalkan.');
    }

    public function damagePayment(Request $request, DamageRecord $damage)
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1'], 'method' => ['required', Rule::in(['cash', 'transfer'])], 'notes' => ['nullable', 'string']]);
        DB::transaction(function () use ($damage, $data, $request): void {
            $record = DamageRecord::lockForUpdate()->findOrFail($damage->id);
            if (in_array($record->status, ['paid', 'written_off'], true)) {
                throw ValidationException::withMessages(['amount' => 'Tagihan lunas atau sudah dihapuskan tidak dapat menerima pembayaran.']);
            }
            $remaining = $record->charge_amount - $record->paid_amount;
            if ($data['amount'] > $remaining) {
                throw ValidationException::withMessages(['amount' => 'Pembayaran melebihi sisa tagihan.']);
            }
            $record->payments()->create($data + ['paid_at' => now(), 'user_id' => $request->user()->id]);
            $record->increment('paid_amount', $data['amount']);
            $record->update(['status' => $record->paid_amount >= $record->charge_amount ? 'paid' : 'partial']);
            $this->logger->log('payment', 'damages', "Pembayaran ganti rugi {$record->code} dicatat.", $record);
        }, 3);

        return back()->with('success', 'Pembayaran berhasil dicatat.');
    }

    public function resolveDamage(Request $request, DamageRecord $damage)
    {
        $this->manager($request);
        $data = $request->validate(['action' => ['required', Rule::in(['repaired', 'disposed', 'write_off'])], 'reason' => ['required_if:action,write_off', 'nullable', 'string', 'max:500'], 'confirm_action' => ['exclude_unless:action,disposed,write_off', 'required', 'accepted']]);
        DB::transaction(function () use ($damage, $data): void {
            $record = DamageRecord::lockForUpdate()->findOrFail($damage->id);
            $old = $record->only(['status', 'resolution', 'written_off_reason']);
            if ($data['action'] === 'write_off') {
                if (in_array($record->status, ['paid', 'written_off'], true)) {
                    throw ValidationException::withMessages(['action' => 'Tagihan lunas atau sudah dihapuskan tidak dapat dihapuskan lagi.']);
                }
                $record->update(['status' => 'written_off', 'written_off_reason' => $data['reason']]);
            } else {
                if ($record->type === 'lost' || $record->resolution !== 'pending') {
                    throw ValidationException::withMessages(['action' => 'Catatan ini tidak memiliki alat rusak yang menunggu resolusi.']);
                }
                $item = Item::lockForUpdate()->findOrFail($record->item_id);
                $line = RentalItem::lockForUpdate()->findOrFail($record->rental_item_id);
                if ($record->qty > $item->stock_damaged || $record->qty > $line->qty_damaged) {
                    throw ValidationException::withMessages(['action' => 'Jumlah stok rusak tidak konsisten; periksa stok sebelum menyelesaikan.']);
                }
                if ($data['action'] === 'disposed' && $item->stock_total - $record->qty < $item->rentedUnits()) {
                    throw ValidationException::withMessages(['action' => 'Stok total tidak dapat dikurangi karena masih ada unit yang sedang disewa.']);
                }
                $item->decrement('stock_damaged', $record->qty);
                $line->decrement('qty_damaged', $record->qty);
                if ($data['action'] === 'repaired') {
                    $line->increment('qty_returned_good', $record->qty);
                } else {
                    $item->decrement('stock_total', $record->qty);
                }
                $record->update(['resolution' => $data['action']]);
            }
            $this->logger->log($data['action'], 'damages', "Ganti rugi {$record->code}: {$data['action']}.", $record, $old, $record->only(array_keys($old)));
        }, 3);

        return back()->with('success', 'Ganti rugi berhasil diperbarui.');
    }

    public function damageReceiptPdf(Request $request, DamageRecord $damage)
    {
        $damage->load(['customer', 'item', 'rental', 'payments.user']);
        $logo = Setting::getValue('business_logo_path');
        $pdf = Pdf::loadView('app.damage-receipt-pdf', ['damage' => $damage, 'businessName' => Setting::getValue('business_name', 'CampRent'), 'businessAddress' => Setting::getValue('business_address', ''), 'businessPhone' => Setting::getValue('business_phone', ''), 'businessLogoPath' => $logo ? Storage::disk('public')->path($logo) : null])->setPaper('a5');

        return $pdf->download($damage->code.'.pdf');
    }

    public function destroy(Request $request, string $section, int $id)
    {
        $modelClass = match ($section) {
            'items' => Item::class, 'categories' => Category::class, 'customers' => Customer::class, 'rentals' => Rental::class, 'damages' => DamageRecord::class, default => abort(404)
        };
        $model = $modelClass::findOrFail($id);
        if (in_array($section, ['items', 'categories', 'customers', 'rentals', 'damages'], true)) {
            $this->manager($request);
        }
        $request->validate(['confirm_delete' => ['required', 'accepted']]);
        if ($section === 'rentals' && ! in_array($model->status, ['returned', 'cancelled'], true)) {
            throw ValidationException::withMessages(['rental' => 'Transaksi aktif harus dikembalikan atau dibatalkan terlebih dahulu.']);
        }
        if ($section === 'customers' && ($model->rentals()->exists() || $model->damages()->exists())) {
            throw ValidationException::withMessages(['customer' => 'Pelanggan tidak dapat dihapus karena memiliki riwayat transaksi atau ganti rugi.']);
        }
        if ($section === 'damages' && in_array($model->status, ['paid', 'written_off'], true)) {
            throw ValidationException::withMessages(['damage' => 'Tagihan lunas atau yang sudah dihapuskan tidak dapat dihapus lagi.']);
        }
        if ($section === 'rentals') {
            $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
            $model->update(['cancelled_reason' => $reason]);
        }
        if ($section === 'damages') {
            $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
            $model->update(['status' => 'written_off', 'written_off_reason' => $reason]);
        }
        if ($section === 'categories') {
            $model->update(['is_active' => false]);
        } elseif ($section === 'items' && $model->rentalItems()->exists()) {
            $model->update(['is_active' => false]);
        } else {
            $model->delete();
        }
        $this->logger->log('deleted', $section, "Data {$section} #{$id} dihapus atau dinonaktifkan.", $model);

        return back()->with('success', 'Data berhasil dihapus atau dinonaktifkan.');
    }

    public function update(Request $request, string $section, int $id)
    {
        $modelClass = match ($section) {
            'categories' => Category::class, 'items' => Item::class, 'customers' => Customer::class, 'users' => User::class, default => abort(404),
        };
        if (in_array($section, ['categories', 'items', 'customers'], true)) {
            $this->manager($request);
        }
        if ($section === 'users') {
            $this->owner($request);
        }
        $model = $modelClass::findOrFail($id);
        $old = $model->only(match ($section) {
            'categories' => ['name', 'description', 'is_active'], 'items' => ['name', 'category_id', 'purchase_price', 'rental_price_per_day', 'description', 'is_active', 'photo_path'],
            'customers' => ['name', 'phone', 'address', 'is_blacklisted', 'blacklist_reason'], 'users' => ['name', 'email', 'role', 'is_active'],
        });
        if ($section === 'categories') {
            $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string'], 'is_active' => ['required', 'boolean']]);
            if (Category::whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->whereKeyNot($id)->exists()) {
                throw ValidationException::withMessages(['name' => 'Nama kategori sudah digunakan.']);
            }
        } elseif ($section === 'items') {
            $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'category_id' => ['required', 'exists:categories,id'], 'purchase_price' => ['required', 'integer', 'min:0'], 'rental_price_per_day' => ['required', 'integer', 'min:0'], 'description' => ['nullable', 'string'], 'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'is_active' => ['required', 'boolean']]);
            if (isset($data['photo'])) {
                $oldPhoto = $model->photo_path;
                $photoPath = $data['photo']->store('items', 'public');
                $this->resizePhoto($data['photo']->getRealPath(), $photoPath, (string) $data['photo']->getClientOriginalExtension());
                $data['photo_path'] = $photoPath;
                unset($data['photo']);
                if ($oldPhoto) {
                    Storage::disk('public')->delete($oldPhoto);
                }
            }
        } elseif ($section === 'customers') {
            $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'phone' => ['required', 'string', 'max:30', Rule::unique('customers', 'phone')->ignore($id)], 'address' => ['nullable', 'string'], 'id_type' => ['nullable', 'string', 'max:50'], 'id_number' => ['nullable', 'string', 'max:80'], 'notes' => ['nullable', 'string'], 'is_blacklisted' => ['required', 'boolean'], 'blacklist_reason' => ['nullable', 'required_if:is_blacklisted,1', 'string']]);
        } else {
            $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($id)], 'role' => ['required', Rule::in(['owner', 'admin', 'kasir'])], 'password' => ['nullable', 'string', 'min:8'], 'is_active' => ['required', 'boolean']]);
            if ($request->filled('password')) {
                $data['password'] = $request->input('password');
            } else {
                unset($data['password']);
            }
            if ($model->id === $request->user()->id && ! $data['is_active']) {
                throw ValidationException::withMessages(['is_active' => 'Akun yang sedang digunakan tidak dapat dinonaktifkan.']);
            }
            if ($model->id === $request->user()->id && $model->role === 'owner' && $data['role'] !== 'owner' && ! User::query()->where('role', 'owner')->where('is_active', true)->whereKeyNot($model->id)->exists()) {
                throw ValidationException::withMessages(['role' => 'Akun owner aktif terakhir tidak dapat diturunkan perannya.']);
            }
        }
        $model->update($data);
        $this->logger->log('updated', $section, "Data {$section} #{$id} diperbarui.", $model, $old, $model->only(array_keys($old)));

        return back()->with('success', 'Perubahan berhasil disimpan.');
    }

    public function receipt(Request $request, Rental $rental)
    {
        $rental->load(['customer', 'cashier', 'items']);

        $logo = Setting::getValue('business_logo_path');

        return view('app.receipt', ['rental' => $rental, 'width' => Setting::getValue('receipt_width', '58'), 'businessName' => Setting::getValue('business_name', 'CampRent'), 'businessAddress' => Setting::getValue('business_address', ''), 'businessPhone' => Setting::getValue('business_phone', ''), 'businessLogoUrl' => $logo ? Storage::disk('public')->url($logo) : null]);
    }

    public function receiptPdf(Request $request, Rental $rental)
    {
        $rental->load(['customer', 'cashier', 'items']);
        $width = Setting::getValue('receipt_width', '58') === '80' ? 226.77 : 164.41;
        $logo = Setting::getValue('business_logo_path');
        $pdf = Pdf::loadView('app.receipt-pdf', ['rental' => $rental, 'businessName' => Setting::getValue('business_name', 'CampRent'), 'businessAddress' => Setting::getValue('business_address', ''), 'businessPhone' => Setting::getValue('business_phone', ''), 'businessLogoPath' => $logo ? Storage::disk('public')->path($logo) : null, 'terms' => Setting::getValue('receipt_terms', '')])->setPaper([0, 0, $width, 680]);

        return $pdf->download($rental->trx_code.'.pdf');
    }

    public function export(Request $request, string $section)
    {
        $this->manager($request);
        abort_unless(in_array($section, ['rentals', 'revenue', 'transactions', 'assets', 'performance', 'damages', 'customers', 'customer-list', 'late'], true), 404);
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'category_id' => ['nullable', 'integer', 'exists:categories,id'], 'q' => ['nullable', 'string', 'max:160'], 'status' => ['nullable', Rule::in(['rented', 'returned', 'late', 'cancelled'])], 'format' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])]]);
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? today()->toDateString();
        $type = $section === 'rentals' ? 'transactions' : $section;
        [$headings, $rows] = $this->reportService->dataset($type, $from, $to, isset($filters['category_id']) ? (int) $filters['category_id'] : null, $filters['q'] ?? null, $filters['status'] ?? null);
        $format = $filters['format'] ?? 'csv';
        $filename = 'laporan-'.$type.'-'.$from.'-'.$to;
        if ($format === 'xlsx') {
            return Excel::download(new ReportExport($headings, $rows), $filename.'.xlsx');
        }
        if ($format === 'pdf') {
            return Pdf::loadView('app.report-pdf', compact('headings', 'rows', 'type', 'from', 'to'))->setPaper('a4', 'landscape')->download($filename.'.pdf');
        }

        return response()->streamDownload(function () use ($headings, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            } fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function searchCustomers(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        return Customer::where(fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))->limit(8)->get(['id', 'name', 'phone', 'address', 'is_blacklisted', 'blacklist_reason']);
    }
}
