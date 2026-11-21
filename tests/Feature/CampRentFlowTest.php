<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DamageRecord;
use App\Models\Item;
use App\Models\Rental;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampRentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_snapshots_price_and_decreases_available_stock(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->startOfDay());
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 3, rentalPrice: 25000);

        $response = $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => '2026-11-11', 'due_date' => '2026-11-13', 'customer_name' => 'Rani Putri', 'phone' => '08123456789',
            'guarantee_type' => 'KTP Asli', 'deposit_amount' => 50000, 'discount' => 5000,
            'items' => [['item_id' => $item->id, 'qty' => 2]],
        ]);

        $rental = Rental::firstOrFail();
        $response->assertRedirect(route('page', ['section' => 'rentals', 'receipt' => $rental->id]));
        $this->assertSame('TRX-20261111-001', $rental->trx_code);
        $this->assertSame(100000, $rental->subtotal);
        $this->assertSame(95000, $rental->total);
        $this->assertSame(25000, $rental->items()->first()->price_per_day);
        $this->assertSame(1, $item->fresh()->availableStock());
    }

    public function test_dashboard_renders_redesign_components_with_live_business_metrics(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'name' => 'Pemilik CampRent']);

        $this->actingAs($owner)->get('/dashboard?period=month&year='.now()->year)
            ->assertOk()
            ->assertSee('Ringkasan usaha')
            ->assertSee('Pendapatan periode ini')
            ->assertSee('Nilai inventaris')
            ->assertSee('Unit sedang disewa')
            ->assertSee('Perlu dikembalikan')
            ->assertSee('Pendapatan sewa bulanan')
            ->assertSee('Alat paling populer')
            ->assertSee('Pengembalian berikutnya')
            ->assertSee('Transaksi terkini')
            ->assertSee('name="period"', false)
            ->assertSee('name="q"', false);
    }

    public function test_return_creates_damage_records_and_adjusts_stock_for_damage_and_loss(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->startOfDay());
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 3, purchasePrice: 120000);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => '2026-11-10', 'due_date' => '2026-11-11', 'customer_name' => 'Rani Putri', 'phone' => '08123456789',
            'guarantee_type' => 'KTP Asli', 'deposit_amount' => 100000, 'items' => [['item_id' => $item->id, 'qty' => 3]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        $rentalItem = $rental->items()->firstOrFail();

        $response = $this->post(route('rentals.return', $rental), [
            'returned_at' => '2026-11-11', 'deposit_deducted' => 50000,
            'items' => [['rental_item_id' => $rentalItem->id, 'good' => 1, 'damaged' => 1, 'lost' => 1]],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(2, DamageRecord::count());
        $this->assertSame(120000, DamageRecord::where('type', 'major_damage')->value('charge_amount'));
        $this->assertSame(120000, DamageRecord::where('type', 'lost')->value('charge_amount'));
        $this->assertSame(2, $item->fresh()->stock_total);
        $this->assertSame(1, $item->fresh()->stock_damaged);
        $this->assertSame(1, $item->fresh()->availableStock());
        $this->assertSame('returned', $rental->fresh()->status);
        $this->assertDatabaseHas('damage_payments', ['method' => 'deposit', 'amount' => 50000]);
    }

    public function test_manager_can_override_late_fee_with_reason_from_return_form(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12)->startOfDay());
        $owner = User::factory()->create(['role' => 'owner']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 1, rentalPrice: 20000);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => '2026-11-10', 'due_date' => '2026-11-11', 'customer_name' => 'Dina', 'phone' => '081111111112',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        $rentalItem = $rental->items()->firstOrFail();

        $this->actingAs($cashier)->get('/rentals')->assertOk()->assertDontSee('name="late_fee"', false);
        $this->post(route('rentals.return', $rental), [
            'returned_at' => '2026-11-12', 'late_fee' => 5000, 'late_fee_reason' => 'Potongan goodwill',
            'items' => [['rental_item_id' => $rentalItem->id, 'good' => 1, 'damaged' => 0, 'lost' => 0]],
        ])->assertForbidden();

        $this->actingAs($owner)->get('/rentals')->assertOk()->assertSee('name="late_fee"', false)->assertSee('name="late_fee_reason"', false);
        $this->post(route('rentals.return', $rental), [
            'returned_at' => '2026-11-12', 'late_fee' => 5000,
            'items' => [['rental_item_id' => $rentalItem->id, 'good' => 1, 'damaged' => 0, 'lost' => 0]],
        ])->assertSessionHasErrors('late_fee_reason');
        $this->post(route('rentals.return', $rental), [
            'returned_at' => '2026-11-12', 'late_fee' => 5000, 'late_fee_reason' => 'Potongan goodwill',
            'items' => [['rental_item_id' => $rentalItem->id, 'good' => 1, 'damaged' => 0, 'lost' => 0]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(5000, $rental->fresh()->late_fee);
        $this->assertDatabaseHas('activity_logs', ['action' => 'late_fee_overridden']);
    }

    public function test_rental_receipts_include_customer_address(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 1);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Rani Alamat', 'phone' => '081111111114', 'address' => 'Jl. Mawar No. 7',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();

        $this->get(route('rentals.receipt', $rental))->assertOk()->assertSee('Jl. Mawar No. 7');
        $this->get(route('rentals.receipt.pdf', $rental))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_cashier_cannot_open_reports_or_change_master_item_data(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $this->actingAs($cashier)->get('/reports')->assertForbidden();
        $this->actingAs($cashier)->post('/items', [])->assertForbidden();
    }

    public function test_login_logout_password_change_and_inactive_account_rules(): void
    {
        $user = User::factory()->create(['username' => 'qa-owner', 'role' => 'owner']);
        User::factory()->create(['username' => 'inactive-cashier', 'is_active' => false]);

        $this->get('/login')->assertOk();
        $this->post('/login', ['username' => 'inactive-cashier', 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['username' => 'qa-owner', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->put('/password', ['current_password' => 'password', 'password' => 'strong-password-123', 'password_confirmation' => 'strong-password-123'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertTrue(password_verify('strong-password-123', $user->fresh()->password));
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_throttle_limits_invalid_attempts_to_five_per_minute(): void
    {
        RateLimiter::clear('invalid-user|127.0.0.1');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['username' => 'invalid-user', 'password' => 'wrong-password'])->assertSessionHasErrors('username');
        }

        $this->post('/login', ['username' => 'invalid-user', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['username' => 'Terlalu banyak percobaan. Coba lagi dalam satu menit.']);
    }

    public function test_role_gates_and_last_active_owner_protection(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($cashier)->get('/categories')->assertForbidden();
        $this->actingAs($admin)->get('/settings')->assertForbidden();
        $this->actingAs($admin)->get('/categories')->assertOk();

        $owner = User::factory()->create(['role' => 'owner']);
        $updateRoute = route('update', ['section' => 'users', 'id' => $owner->id]);
        $payload = ['name' => $owner->name, 'email' => $owner->email, 'role' => 'admin', 'password' => '', 'is_active' => 1];
        $this->actingAs($owner)->put($updateRoute, $payload)->assertSessionHasErrors('role');
        $this->assertSame('owner', $owner->fresh()->role);

        User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->put($updateRoute, $payload)->assertSessionHasNoErrors();
        $this->assertSame('admin', $owner->fresh()->role);
    }

    public function test_category_deletion_requires_confirmation_and_only_deactivates_the_category(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->makeItem(stock: 1);
        $category = Category::firstOrFail();
        $route = route('destroy', ['section' => 'categories', 'id' => $category->id]);

        $this->actingAs($owner)->delete($route)->assertSessionHasErrors('confirm_delete');
        $this->assertModelExists($category);

        $this->actingAs($owner)->delete($route, ['confirm_delete' => '1'])->assertSessionHasNoErrors();

        $this->assertModelExists($category);
        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_damage_screen_handles_archived_customer_and_rental_relations(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $owner = User::factory()->create(['role' => 'owner']);
        $item = $this->makeItem(stock: 1);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        $line = $rental->items()->firstOrFail();
        $this->actingAs($cashier)->post(route('rentals.return', $rental), [
            'returned_at' => today()->toDateString(), 'items' => [['rental_item_id' => $line->id, 'good' => 0, 'damaged' => 1, 'lost' => 0]],
        ])->assertSessionHasNoErrors();
        $damage = DamageRecord::firstOrFail();
        $rental->customer()->delete();

        $this->actingAs($owner)->get('/damages')->assertOk()->assertSee('Pelanggan diarsipkan');

        $rental->delete();
        $this->get('/damages')->assertOk()->assertSee('Transaksi diarsipkan');
        $damageExport = $this->get(route('export', ['section' => 'damages', 'from' => today()->toDateString(), 'to' => today()->toDateString(), 'format' => 'csv']));
        $damageExport->assertOk();
        $this->assertStringContainsString('Pelanggan diarsipkan', $damageExport->streamedContent());
        $this->assertStringContainsString('Transaksi diarsipkan', $damageExport->streamedContent());
        $this->get(route('damages.receipt.pdf', $damage))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_rental_list_details_and_receipts_handle_archived_customer(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $owner = User::factory()->create(['role' => 'owner']);
        $item = $this->makeItem(stock: 1);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        $rental->customer()->delete();

        $this->actingAs($owner)->get('/rentals')->assertOk()->assertSee('Pelanggan diarsipkan');
        $this->get('/rentals?detail='.$rental->id)->assertOk()->assertSee('Pelanggan diarsipkan');
        $this->get(route('rentals.receipt', $rental))->assertOk()->assertSee('Pelanggan diarsipkan');
        $this->get(route('rentals.receipt.pdf', $rental))->assertOk()->assertHeader('content-type', 'application/pdf');
        $rentalExport = $this->get(route('export', ['section' => 'transactions', 'from' => today()->toDateString(), 'to' => today()->toDateString(), 'format' => 'csv']));
        $rentalExport->assertOk();
        $this->assertStringContainsString('Pelanggan diarsipkan', $rentalExport->streamedContent());
    }

    public function test_deleting_items_with_rental_history_deactivates_them_and_protects_customer_history(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 1);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Pelanggan Bersejarah', 'phone' => '081234567890',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $customer = Customer::firstOrFail();

        $this->actingAs($owner)->delete(route('destroy', ['section' => 'items', 'id' => $item->id]), ['confirm_delete' => '1'])->assertSessionHasNoErrors();
        $this->assertModelExists($item);
        $this->assertFalse($item->fresh()->is_active);

        $this->actingAs($owner)->delete(route('destroy', ['section' => 'customers', 'id' => $customer->id]), ['confirm_delete' => '1'])->assertSessionHasErrors('customer');
        $this->assertModelExists($customer);
    }

    public function test_editing_an_inactive_item_preserves_or_changes_its_status_explicitly(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $item = $this->makeItem(stock: 1);
        $item->update(['is_active' => false]);

        $this->actingAs($owner)->get('/items')->assertOk()->assertSee('option value="0" selected', false);
        $this->put(route('update', ['section' => 'items', 'id' => $item->id]), [
            'name' => $item->name, 'category_id' => $item->category_id, 'purchase_price' => $item->purchase_price,
            'rental_price_per_day' => $item->rental_price_per_day, 'is_active' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($item->fresh()->is_active);
    }

    public function test_customer_edit_form_can_update_existing_notes(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $customer = Customer::create(['name' => 'Dina', 'phone' => '081111111111', 'notes' => 'Catatan lama']);

        $this->actingAs($owner)->get('/customers')->assertOk()->assertSee('Catatan lama');
        $this->put(route('update', ['section' => 'customers', 'id' => $customer->id]), [
            'name' => 'Dina', 'phone' => '081111111111', 'address' => '', 'id_type' => '', 'id_number' => '',
            'notes' => 'Catatan diperbarui', 'is_blacklisted' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'notes' => 'Catatan diperbarui']);
    }

    public function test_checkout_restores_a_customer_archived_without_transaction_history(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $customer = Customer::create(['name' => 'Nama Lama', 'phone' => '081111111111']);
        $item = $this->makeItem(stock: 1);
        $this->actingAs($owner)->delete(route('destroy', ['section' => 'customers', 'id' => $customer->id]), ['confirm_delete' => '1'])->assertSessionHasNoErrors();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Nama Baru', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('customers', 1);
        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Nama Baru']);
        $this->assertSame($customer->id, Rental::firstOrFail()->customer_id);
    }

    public function test_checkout_rejects_overselling_and_keeps_stock_unchanged(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 1);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 2]],
        ])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('rentals', 0);
        $this->assertSame(1, $item->fresh()->availableStock());
    }

    public function test_dashboard_renders_using_sqlite_queries(): void
    {
        $user = User::factory()->create(['role' => 'owner']);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Nilai inventaris');
    }

    public function test_owner_can_open_each_management_screen(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        foreach (['/pos', '/items', '/categories', '/customers', '/rentals', '/damages', '/reports', '/activity', '/settings', '/users'] as $path) {
            $this->actingAs($owner)->get($path)->assertOk();
        }
    }

    public function test_rental_receipt_can_be_downloaded_as_pdf_and_excel(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 2);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();

        $this->get(route('rentals.receipt.pdf', $rental))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/export/rentals?format=xlsx')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_rental_can_be_extended_and_cancelled_with_stock_released(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 2, rentalPrice: 20000);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();

        $this->actingAs($cashier)->post(route('rentals.extend', $rental), ['due_date' => today()->addDays(3)->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(60000, $rental->fresh()->total);
        $this->assertSame(today()->addDays(3)->toDateString(), $rental->fresh()->due_date->toDateString());
        $this->post(route('rentals.cancel', $rental), ['cancelled_reason' => 'Pelanggan batal sebelum mengambil alat.', 'confirm_action' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $rental->fresh()->status);
        $this->assertSame(2, $item->fresh()->availableStock());
    }

    public function test_owner_can_edit_categories_and_cashier_cannot_manage_users(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $category = Category::create(['name' => 'Tenda']);
        $this->actingAs($owner)->put(route('update', ['section' => 'categories', 'id' => $category->id]), [
            'name' => 'Tenda Kapasitas', 'description' => 'Kategori tenda', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Tenda Kapasitas']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $this->actingAs($cashier)->get('/users')->assertForbidden();
        $this->actingAs($cashier)->put(route('update', ['section' => 'users', 'id' => $owner->id]), [])->assertForbidden();
    }

    public function test_stock_reduction_requires_confirmation_but_addition_does_not(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $item = $this->makeItem(stock: 2);
        $payload = ['item_id' => $item->id, 'type' => 'reduce', 'qty' => 1, 'reason' => 'Stok rusak'];

        $this->actingAs($owner)->post('/stock', $payload)->assertSessionHasErrors('confirm_action');
        $this->assertSame(2, $item->fresh()->stock_total);

        $this->post('/stock', $payload + ['confirm_action' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, $item->fresh()->stock_total);
    }

    public function test_cancelled_rentals_are_not_counted_as_rental_revenue(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 1, rentalPrice: 30000);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        $this->post(route('rentals.cancel', $rental), ['cancelled_reason' => 'Pelanggan batal.', 'confirm_action' => '1'])->assertSessionHasNoErrors();

        [, $rows] = app(ReportService::class)->dataset('revenue', today()->toDateString(), today()->toDateString());

        $this->assertSame(0, $rows[0][1]);
        $this->assertSame(0, $rows[0][4]);
    }

    public function test_damage_payments_support_installments_and_reject_terminal_charges(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $manager = User::factory()->create(['role' => 'admin']);
        $item = $this->makeItem(stock: 3, purchasePrice: 120000);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 3]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        $line = $rental->items()->firstOrFail();
        $this->post(route('rentals.return', $rental), [
            'returned_at' => today()->toDateString(), 'items' => [['rental_item_id' => $line->id, 'good' => 1, 'damaged' => 1, 'lost' => 1]],
        ])->assertSessionHasNoErrors();
        $damages = DamageRecord::orderBy('type')->get()->keyBy('type');
        $damagedRecord = $damages->get('major_damage');
        $lostRecord = $damages->get('lost');

        $this->actingAs($cashier)->post(route('damages.payments', $damagedRecord), ['amount' => 40000, 'method' => 'transfer'])->assertSessionHasNoErrors();
        $this->assertSame('partial', $damagedRecord->fresh()->status);
        $this->assertSame(40000, $damagedRecord->fresh()->paid_amount);
        $this->post(route('damages.payments', $damagedRecord), ['amount' => 80000, 'method' => 'cash'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $damagedRecord->fresh()->status);
        $this->post(route('damages.payments', $damagedRecord), ['amount' => 1, 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->assertSame(2, $damagedRecord->payments()->count());

        $this->actingAs($manager)->post(route('damages.resolution', $lostRecord), ['action' => 'write_off', 'reason' => 'Disepakati pemilik', 'confirm_action' => '1'])->assertSessionHasNoErrors();
        $this->post(route('damages.payments', $lostRecord), ['amount' => 1000, 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->assertSame(0, $lostRecord->payments()->count());
    }

    public function test_archiving_a_completed_rental_requires_confirmation_and_reason(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $owner = User::factory()->create(['role' => 'owner']);
        $item = $this->makeItem(stock: 1);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        $rentalItem = $rental->items()->firstOrFail();
        $this->post(route('rentals.return', $rental), [
            'returned_at' => today()->toDateString(), 'items' => [['rental_item_id' => $rentalItem->id, 'good' => 1, 'damaged' => 0, 'lost' => 0]],
        ])->assertSessionHasNoErrors();
        $route = route('destroy', ['section' => 'rentals', 'id' => $rental->id]);

        $this->actingAs($owner)->delete($route)->assertSessionHasErrors('confirm_delete');
        $this->delete($route, ['confirm_delete' => '1'])->assertSessionHasErrors('reason');
        $this->delete($route, ['confirm_delete' => '1', 'reason' => 'Duplikat arsip'])->assertSessionHasNoErrors();

        $this->assertSoftDeleted('rentals', ['id' => $rental->id]);
    }

    public function test_late_return_applies_configured_daily_penalty(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->startOfDay());
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 1, rentalPrice: 30000);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => '2026-11-08', 'due_date' => '2026-11-09', 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        $rentalItem = $rental->items()->firstOrFail();

        $this->assertSame('late', $rental->display_status);
        $this->actingAs($cashier)->post(route('rentals.return', $rental), [
            'returned_at' => '2026-11-11', 'items' => [['rental_item_id' => $rentalItem->id, 'good' => 1, 'damaged' => 0, 'lost' => 0]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(60000, $rental->fresh()->late_fee);
    }

    public function test_item_photo_upload_is_stored_and_resized_to_1200_pixels(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'owner']);
        $category = Category::create(['name' => 'Tenda']);

        $this->actingAs($owner)->post('/items', [
            'name' => 'Tenda 4P', 'category_id' => $category->id, 'purchase_price' => 500000, 'rental_price_per_day' => 50000,
            'stock_total' => 2, 'photo' => UploadedFile::fake()->image('tenda.png', 1600, 1200),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $item = Item::firstOrFail();
        Storage::disk('public')->assertExists($item->photo_path);
        [$width, $height] = getimagesize(Storage::disk('public')->path($item->photo_path));
        $this->assertSame(1200, $width);
        $this->assertSame(900, $height);
    }

    public function test_editing_an_item_stores_its_replacement_photo_on_the_same_item(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'owner']);
        $category = Category::create(['name' => 'Tenda']);
        $item = Item::create(['code' => 'ALT-0001', 'category_id' => $category->id, 'name' => 'Tenda lama', 'purchase_price' => 300000, 'rental_price_per_day' => 30000, 'stock_total' => 1, 'is_active' => true]);

        $this->actingAs($owner)->put(route('update', ['section' => 'items', 'id' => $item->id]), [
            'name' => 'Tenda baru', 'category_id' => $category->id, 'purchase_price' => 350000, 'rental_price_per_day' => 35000, 'is_active' => 1,
            'photo' => UploadedFile::fake()->image('tenda-baru.png', 600, 400),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('Tenda baru', $item->fresh()->name);
        $this->assertNotEmpty($item->fresh()->photo_path);
        Storage::disk('public')->assertExists($item->fresh()->photo_path);
        $this->assertDatabaseCount('items', 1);
    }

    public function test_manual_damage_updates_stock_and_can_be_repaired(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $manager = User::factory()->create(['role' => 'admin']);
        $item = $this->makeItem(stock: 2, purchasePrice: 150000);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        $line = $rental->items()->firstOrFail();
        $this->actingAs($cashier)->post(route('rentals.return', $rental), [
            'returned_at' => today()->toDateString(), 'items' => [['rental_item_id' => $line->id, 'good' => 1, 'damaged' => 0, 'lost' => 0]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($manager)->post('/damages', [
            'rental_item_id' => $line->id, 'type' => 'minor_damage', 'qty' => 1, 'charge_amount' => 25000, 'notes' => 'Resleting robek',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $damage = DamageRecord::firstOrFail();
        $this->assertSame(1, $item->fresh()->stock_damaged);
        $this->assertSame(0, $line->fresh()->qty_returned_good);
        $this->assertSame(1, $line->fresh()->qty_damaged);
        $this->assertSame(1, $item->fresh()->availableStock());
        $this->actingAs($manager)->post(route('damages.resolution', $damage), ['action' => 'repaired'])->assertSessionHasNoErrors();
        $this->assertSame(0, $item->fresh()->stock_damaged);
        $this->assertSame(1, $line->fresh()->qty_returned_good);
        $this->actingAs($manager)->get(route('damages.receipt.pdf', $damage))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_cashier_cannot_create_edit_or_delete_customer_master_data(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $customer = Customer::create(['name' => 'Dina', 'phone' => '081111111111']);
        $this->actingAs($cashier)->post('/customers', ['name' => 'Baru', 'phone' => '081222222222'])->assertForbidden();
        $this->actingAs($cashier)->put(route('update', ['section' => 'customers', 'id' => $customer->id]), [])->assertForbidden();
        $this->actingAs($cashier)->delete(route('destroy', ['section' => 'customers', 'id' => $customer->id]))->assertForbidden();
        $this->assertSame(1, Customer::count());
    }

    public function test_checkout_is_idempotent_for_repeated_submit_key(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 3);
        $payload = [
            'idempotency_key' => '2abca632-12bc-4d66-b4d5-e83a857bf62a',
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ];
        $this->actingAs($cashier)->post('/pos/checkout', $payload)->assertSessionHasNoErrors();
        $firstCode = Rental::firstOrFail()->trx_code;
        $this->actingAs($cashier)->post('/pos/checkout', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rentals', 1);
        $this->assertSame($firstCode, Rental::firstOrFail()->trx_code);
        $this->assertSame(2, $item->fresh()->availableStock());
    }

    public function test_required_customer_photo_is_private_and_served_only_to_signed_in_users(): void
    {
        Storage::fake('local');
        Setting::putValue('require_customer_photo', '1');
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 1);
        $payload = [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ];
        $this->actingAs($cashier)->post('/pos/checkout', $payload)->assertSessionHasErrors('customer_photo');
        $this->actingAs($cashier)->post('/pos/checkout', $payload + ['customer_photo' => UploadedFile::fake()->image('penyewa.jpg')])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        Storage::disk('local')->assertExists($rental->customer_photo_path);
        $this->actingAs($cashier)->get(route('rentals.customer-photo', $rental))->assertOk();
        auth()->logout();
        $this->get(route('rentals.customer-photo', $rental))->assertRedirect('/login');
    }

    public function test_owner_can_configure_business_logo_and_guarantee_options(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->post('/settings', [
            'business_name' => 'CampRent Outdoor', 'receipt_width' => '58', 'business_logo' => UploadedFile::fake()->image('logo.png', 300, 200), 'guarantee_types' => 'Passport|Kartu Anggota',
        ])->assertSessionHasNoErrors();
        $logoPath = Setting::getValue('business_logo_path');
        $this->assertNotEmpty($logoPath);
        Storage::disk('public')->assertExists($logoPath);
        $logoUrl = Storage::disk('public')->url($logoPath);
        $this->actingAs($owner)->get('/settings')->assertOk()->assertSee('CampRent Outdoor')->assertSee($logoUrl, false)->assertSee('business-logo-preview', false)->assertSee('for="business-logo-input"', false)->assertSee('id="business-logo-input"', false);
        $this->get('/dashboard')->assertOk()->assertSee('CampRent Outdoor')->assertSee('brand-logo', false);
        $item = $this->makeItem(stock: 1);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Rani', 'phone' => '081111111113',
            'guarantee_type' => 'Passport', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        $this->get(route('rentals.receipt.pdf', $rental))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_all_business_report_formats_render(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $types = ['revenue', 'transactions', 'assets', 'performance', 'damages', 'customers', 'late'];
        foreach ($types as $type) {
            foreach (['csv', 'xlsx', 'pdf'] as $format) {
                $this->actingAs($owner)->get(route('export', ['section' => $type, 'from' => today()->toDateString(), 'to' => today()->toDateString(), 'format' => $format]))->assertOk();
            }
        }
    }

    public function test_transaction_export_includes_rentals_on_the_end_date(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12)->startOfDay());
        $owner = User::factory()->create(['role' => 'owner']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 1);

        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => '2026-11-12', 'due_date' => '2026-11-13', 'customer_name' => 'Pelanggan Laporan', 'phone' => '081234567890',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();

        $response = $this->actingAs($owner)->get(route('export', [
            'section' => 'transactions', 'from' => '2026-11-12', 'to' => '2026-11-12', 'format' => 'csv',
        ]));

        $response->assertOk();
        $this->assertStringContainsString($rental->trx_code, $response->streamedContent());
        $this->assertStringContainsString('Pelanggan Laporan', $response->streamedContent());
    }

    public function test_duplicate_cart_lines_are_aggregated_before_stock_validation(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 1);
        $payload = [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Dina', 'phone' => '081111111111',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1], ['item_id' => $item->id, 'qty' => 1]],
        ];
        $this->actingAs($cashier)->post('/pos/checkout', $payload)->assertSessionHasErrors('items');
        $this->assertDatabaseCount('rentals', 0);
        $this->assertSame(1, $item->fresh()->availableStock());
    }

    public function test_rental_and_customer_detail_pages_include_history(): void
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $item = $this->makeItem(stock: 1);
        $this->actingAs($cashier)->post('/pos/checkout', [
            'rent_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(), 'customer_name' => 'Rani Detail', 'phone' => '081234500000',
            'guarantee_type' => 'KTP Asli', 'items' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $rental = Rental::firstOrFail();
        $this->actingAs($cashier)->get(route('rentals.show', $rental))->assertRedirect(route('page', ['section' => 'rentals', 'detail' => $rental->id]));
        $this->get('/rentals?detail='.$rental->id)->assertOk()->assertSee($rental->trx_code)->assertSee('Riwayat perubahan');
        $this->get('/customers?detail='.$rental->customer_id)->assertOk()->assertSee('Rani Detail')->assertSee('Riwayat transaksi');
    }

    private function makeItem(int $stock, int $rentalPrice = 20000, int $purchasePrice = 120000): Item
    {
        $category = Category::create(['name' => 'Tenda']);

        return Item::create(['code' => 'ALT-0001', 'category_id' => $category->id, 'name' => 'Tenda 4P', 'purchase_price' => $purchasePrice, 'rental_price_per_day' => $rentalPrice, 'stock_total' => $stock, 'is_active' => true]);
    }
}
