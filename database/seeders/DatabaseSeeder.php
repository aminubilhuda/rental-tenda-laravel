<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ([['Pemilik CampRent', 'owner', 'owner'], ['Admin Operasional', 'admin', 'admin'], ['Kasir CampRent', 'kasir', 'kasir']] as [$name, $username, $role]) {
            User::firstOrCreate(['username' => $username], ['name' => $name, 'role' => $role, 'email' => $username.'@camprent.local', 'password' => 'password', 'is_active' => true]);
        }
        foreach (['Tenda', 'Tas Carrier', 'Matras', 'Sleeping Bag', 'Alat Masak', 'Kompor Camping', 'Lampu Camping', 'Lainnya'] as $name) {
            Category::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
        $samples = [['Tenda Kapasitas 4P', 'Tenda', 450000, 65000, 5], ['Carrier 60L', 'Tas Carrier', 800000, 35000, 8], ['Matras Foil', 'Matras', 75000, 10000, 12], ['Sleeping Bag Polar', 'Sleeping Bag', 250000, 20000, 8], ['Kompor Portable Mini', 'Kompor Camping', 180000, 15000, 6], ['Lampu Camping LED', 'Lampu Camping', 125000, 12000, 10]];
        foreach ($samples as $index => [$name, $category, $purchase, $rental, $stock]) {
            Item::firstOrCreate(['code' => 'ALT-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)], ['category_id' => Category::where('name', $category)->value('id'), 'name' => $name, 'purchase_price' => $purchase, 'rental_price_per_day' => $rental, 'stock_total' => $stock, 'is_active' => true]);
        }
        foreach (['business_name' => 'CampRent', 'business_address' => 'Alamat usaha Anda', 'business_phone' => '', 'business_logo_path' => '', 'receipt_width' => '58', 'late_fee_mode' => 'percent', 'late_fee_value' => '100', 'block_blacklist' => '0', 'require_customer_photo' => '0', 'guarantee_types' => 'KTP Asli|SIM Asli|KTM/Kartu Pelajar|Paspor|Lainnya|Uang Jaminan', 'receipt_terms' => 'Keterlambatan dan kerusakan alat akan dikenakan biaya sesuai ketentuan.'] as $key => $value) {
            Setting::putValue($key, $value);
        }
    }
}
