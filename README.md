# CampRent

CampRent adalah aplikasi manajemen penyewaan alat camping, dibuat dengan Laravel 13, Blade, Tailwind CSS 4, Alpine-ready JavaScript, SQLite/MySQL, DomPDF, dan Laravel Excel.

## Menjalankan lokal

Persyaratan: PHP 8.3+, Composer, Node.js, dan npm.

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Buka http://127.0.0.1:8000. Akun demo: `owner/password`, `admin/password`, dan `kasir/password`. Ubah password awal setelah masuk.

Untuk MySQL, atur `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di `.env`, lalu jalankan migrasi.

## Tes dan format

```bash
php artisan test
vendor/bin/pint --format agent
npm run build
```

## Backup database

Jalankan backup SQLite atau MySQL dengan:

```bash
php artisan backup:database
```

File backup disimpan pada disk lokal, default-nya `storage/app/private/backups`. Untuk backup otomatis setiap hari pukul 02.00 WIB, jalankan Laravel scheduler dengan cron satu menit sekali:

```cron
* * * * * cd /path/to/camprent && php artisan schedule:run >> /dev/null 2>&1
```

Backup MySQL memerlukan `mysqldump` tersedia pada PATH server.
