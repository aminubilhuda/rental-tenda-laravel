# PRD — Aplikasi Manajemen Rental Alat Camping ("CampRent")

> Dokumen ini ditulis sebagai acuan utama untuk developer maupun AI coding agent. Semua requirement bersifat eksplisit; bila ada ambiguitas, ikuti bagian **Aturan Bisnis** dan **Acceptance Criteria**.

| Item | Keterangan |
|---|---|
| Nama Produk | CampRent (nama kerja, dapat diganti) |
| Versi Dokumen | 1.0 |
| Tanggal | 11 November 2026 |
| Referensi UI | Screenshot "BASECAMP X360" (login, dashboard, etalase POS, transaksi sewa, form alat, struk thermal) |
| Bahasa Antarmuka | Bahasa Indonesia |
| Mata Uang | Rupiah (IDR), tanpa desimal |
| Zona Waktu | Asia/Jakarta (WIB) |

---

## 1. Ringkasan Produk

CampRent adalah aplikasi web untuk mengelola usaha **penyewaan perlengkapan outdoor** (tenda, carrier, matras, sleeping bag, kompor, lampu camping, peralatan masak, dsb). Aplikasi mencakup **POS Kasir** untuk transaksi sewa, manajemen inventaris alat dan stok, data pelanggan, pencatatan **ganti rugi** (kerusakan/kehilangan), cetak **struk thermal / PDF**, serta **laporan dan analisis bisnis** (omzet, transaksi, nilai aset, alat terpopuler).

### 1.1 Masalah yang Diselesaikan
- Pencatatan sewa manual (buku/chat WhatsApp) rawan salah hitung dan hilang.
- Stok alat tidak real-time sehingga terjadi double booking.
- Kerusakan/kehilangan alat tidak tercatat rapi dan sulit ditagihkan.
- Pemilik sulit memantau omzet dan performa tiap alat.

### 1.2 Tujuan (Goals)
1. Transaksi sewa selesai < 2 menit di POS.
2. Stok akurat otomatis (berkurang saat sewa, kembali saat pengembalian).
3. Semua kerusakan/kehilangan tercatat dan tertaut ke transaksi serta pelanggan.
4. Dashboard dan laporan siap dipakai pemilik tanpa rekap manual.
5. Berjalan baik di laptop kasir dan HP (responsive).

### 1.3 Non-Goals (Versi 1.0)
- Pembayaran online / payment gateway.
- Booking online oleh pelanggan (portal publik).
- Aplikasi mobile native.
- Multi-cabang (disiapkan strukturnya, belum diimplementasikan).

---

## 2. Stakeholder & Peran Pengguna

| Peran | Deskripsi | Hak Akses |
|---|---|---|
| **Owner** | Pemilik usaha | Seluruh fitur, laporan, pengaturan, kelola user, hapus data |
| **Admin** | Pengelola operasional | Master data, alat, transaksi, ganti rugi, laporan; tanpa kelola user Owner |
| **Kasir** | Petugas POS | Etalase/POS, buat transaksi, proses pengembalian, cetak struk, lihat pelanggan; tidak bisa hapus transaksi, tidak bisa ubah harga master |

Implementasi: Role-based access dengan Gate/Policy (boleh memakai paket `spatie/laravel-permission` atau enum role sederhana di tabel `users`). Untuk v1.0 direkomendasikan **enum role di tabel users** agar sederhana.

---

## 3. Tech Stack

| Layer | Teknologi |
|---|---|
| Framework | **Laravel 13** (PHP sesuai requirement minimum Laravel 13; verifikasi di dokumentasi resmi saat setup) |
| Frontend | Blade + **Tailwind CSS** (via Vite), **Alpine.js** untuk interaktivitas ringan |
| Alternatif UI | Livewire 3 untuk POS dan tabel interaktif (opsional, pilih satu pendekatan dan konsisten) |
| Database | **MySQL 8** (produksi) / **SQLite** (development/instalasi ringan) |
| Chart | Chart.js |
| PDF/Struk | `barryvdh/laravel-dompdf` (PDF), CSS `@media print` 58/80mm (thermal) |
| Export | `maatwebsite/excel` (CSV/XLSX) |
| Auth | Laravel Breeze/Fortify (session-based), username + password |
| Image | Storage disk `public`, Intervention Image (opsional, untuk resize) |
| Testing | Pest / PHPUnit |

**Catatan portabilitas DB:** semua migration harus berjalan di MySQL dan SQLite (hindari fitur spesifik satu engine; gunakan tipe `decimal`/`unsignedBigInteger` untuk uang, simpan dalam rupiah integer).

---

## 4. Ruang Lingkup Fitur

### 4.1 Daftar Modul

| # | Modul | Prioritas |
|---|---|---|
| M1 | Autentikasi & Manajemen User | P0 |
| M2 | Master Kategori | P0 |
| M3 | Data Alat Outdoor (Inventaris) | P0 |
| M4 | Etalase & POS Kasir | P0 |
| M5 | Data Transaksi Sewa & Pengembalian | P0 |
| M6 | Data Pelanggan | P0 |
| M7 | Data Ganti Rugi | P0 |
| M8 | Struk (Thermal & PDF) | P0 |
| M9 | Dashboard & Ringkasan Aset | P0 |
| M10 | Laporan & Analisis Bisnis | P1 |
| M11 | Log Aktivitas | P1 |
| M12 | Pengaturan Usaha | P1 |
| M13 | Asisten Bantuan (chatbot panduan) | P2 (opsional) |

---

## 5. Kebutuhan Fungsional Detail

### M1 — Autentikasi & Manajemen User
- **FR-1.1** Login dengan *username* + *password*; tombol menampilkan status "Memproses..." saat submit.
- **FR-1.2** Logout dari menu sidebar ("Keluar").
- **FR-1.3** Throttle login: maksimal 5 percobaan/menit per IP+username.
- **FR-1.4** Halaman user (Owner saja): CRUD user, set role, aktif/nonaktif, reset password.
- **FR-1.5** User nonaktif tidak bisa login.
- **FR-1.6** Header menampilkan nama user, status (mis. "Terverifikasi"), dan jam/tanggal live (WIB).
- **FR-1.7** Ubah password sendiri.

### M2 — Master Kategori
- **FR-2.1** CRUD kategori (contoh: Tenda, Tas Carrier, Matras, Sleeping Bag, Alat Masak, Kompor, Lampu, Lainnya).
- **FR-2.2** Nama kategori unik (case-insensitive).
- **FR-2.3** Kategori yang masih dipakai alat tidak boleh dihapus (tampilkan pesan; tawarkan nonaktifkan).
- **FR-2.4** Pencarian dan pagination.

### M3 — Data Alat Outdoor
- **FR-3.1** CRUD alat dengan field: nama alat, kategori (dropdown dari master), harga beli, harga sewa/hari, stok total, foto, deskripsi (opsional), status aktif.
- **FR-3.2** Upload foto dari HP/laptop (jpg/png/webp, maks 2 MB), tampilkan preview, auto-resize maks 1200px.
- **FR-3.3** Tampilkan kolom **stok tersedia**, **sedang disewa**, **rusak/hilang** (dihitung otomatis; lihat Aturan Bisnis BR-3).
- **FR-3.4** Filter berdasarkan kategori dan status; pencarian nama.
- **FR-3.5** Nilai aset per alat = `harga_beli × stok_total` (ditampilkan di tabel).
- **FR-3.6** Alat yang pernah dipakai di transaksi tidak boleh dihapus permanen (gunakan *soft delete*/nonaktif).
- **FR-3.7** Penyesuaian stok manual (tambah stok pembelian baru / kurangi stok afkir) dengan catatan alasan, tercatat di log.
- **FR-3.8** Kode alat otomatis (mis. `ALT-0001`), unik.

### M4 — Etalase & POS Kasir
Tata letak dua kolom: **katalog** (kiri) dan **keranjang sewa** (kanan), mengikuti referensi.

**Katalog**
- **FR-4.1** Grid kartu alat: foto, badge kategori, nama, harga/hari, sisa stok, tombol "+ Keranjang".
- **FR-4.2** Pencarian nama alat (live, debounce 300 ms) dan filter "Semua Kategori".
- **FR-4.3** Alat dengan stok tersedia 0 ditampilkan nonaktif ("Habis") dan tidak bisa ditambahkan.
- **FR-4.4** Stok yang ditampilkan memperhitungkan **tanggal sewa–kembali** yang dipilih di keranjang (ketersediaan per rentang tanggal, lihat BR-4).

**Keranjang Sewa**
- **FR-4.5** Daftar item dengan qty (+/−), subtotal per item, hapus item; tombol "Bersihkan".
- **FR-4.6** Qty tidak boleh melebihi stok tersedia.
- **FR-4.7** Input tanggal sewa dan tanggal kembali (default: hari ini dan besok); durasi dihitung otomatis dalam hari.
- **FR-4.8** Identitas penyewa: nama lengkap, No WhatsApp/HP, alamat. Ketik nama/HP memunculkan *autocomplete* pelanggan lama; pilih untuk mengisi otomatis. Pelanggan baru otomatis tersimpan.
- **FR-4.9** Jaminan: dropdown (KTP Asli, SIM Asli, KTM/Kartu Pelajar, Paspor, Lainnya, Uang Jaminan) dan nominal **deposit (Rp)**.
- **FR-4.10** Foto penyewa/jaminan: ambil via kamera atau upload file (opsional, dapat diwajibkan lewat pengaturan).
- **FR-4.11** Ringkasan: durasi, subtotal, diskon (opsional, nominal), total bayar.
- **FR-4.12** Tombol **Proses Transaksi** → validasi → simpan → tampilkan modal struk.
- **FR-4.13** Keranjang bertahan jika halaman ter-refresh (draft di sesi/localStorage).

### M5 — Data Transaksi Sewa & Pengembalian
- **FR-5.1** Tabel transaksi: ID transaksi, penyewa & info (nama, WA, jaminan, deposit, foto), detail alat (breakdown item × qty + subtotal), tanggal sewa–kembali, total bayar, status, kasir, aksi.
- **FR-5.2** Format ID transaksi: `TRX-YYYYMMDD-NNN` (urut harian, reset tiap hari; tahan terhadap race condition).
- **FR-5.3** Filter: pencarian nama/ID, rentang tanggal, status; tombol refresh; **Export CSV** (sesuai filter).
- **FR-5.4** Status transaksi: `Disewa`, `Kembali`, `Terlambat` (otomatis bila lewat tanggal kembali & belum kembali), `Dibatalkan`.
- **FR-5.5** Aksi: **Kembali** (proses pengembalian), cetak ulang struk, hapus (Owner/Admin saja, soft delete + alasan).
- **FR-5.6** **Proses pengembalian** membuka modal: tanggal kembali aktual, checklist kondisi per item (Baik / Rusak / Hilang, qty), denda keterlambatan (otomatis, dapat diedit Admin), catatan.
  - Item "Baik" → stok kembali tersedia.
  - Item "Rusak/Hilang" → otomatis membuat draf **Ganti Rugi** (M7).
  - Deposit: tampilkan opsi pengembalian deposit penuh, dipotong (untuk ganti rugi/denda), atau ditahan.
- **FR-5.7** Halaman detail transaksi (read-only) berisi semua data + riwayat perubahan.
- **FR-5.8** Perpanjangan sewa: tambah durasi pada transaksi aktif (cek ketersediaan; tambah biaya).
- **FR-5.9** Pembatalan transaksi sebelum alat diambil: stok dilepas, status `Dibatalkan`.

### M6 — Data Pelanggan
- **FR-6.1** CRUD pelanggan: nama, No WA/HP (unik), alamat, jenis & nomor identitas (opsional), catatan.
- **FR-6.2** Detail pelanggan: riwayat transaksi, total transaksi, total belanja, ganti rugi terkait, status.
- **FR-6.3** Penanda **Blacklist** (dengan alasan) → POS menampilkan peringatan merah saat pelanggan dipilih; transaksi dapat diblokir lewat pengaturan.
- **FR-6.4** Pencarian nama/HP; export CSV.
- **FR-6.5** Data pribadi hanya dapat diakses user terautentikasi.

### M7 — Data Ganti Rugi
- **FR-7.1** Tabel ganti rugi: ID (`GR-YYYYMMDD-NNN`), transaksi terkait, pelanggan, alat, jenis (**Rusak Ringan / Rusak Berat / Hilang**), qty, biaya ganti rugi, dibayar, sisa, status (`Belum Lunas`, `Lunas`, `Dihapuskan`), tanggal, catatan, foto bukti kerusakan.
- **FR-7.2** Dapat dibuat otomatis dari pengembalian (FR-5.6) atau manual.
- **FR-7.3** Saran biaya otomatis: Rusak Ringan = input manual; Rusak Berat/Hilang = `harga_beli × qty` (dapat diedit dengan alasan).
- **FR-7.4** Pembayaran bertahap (cicilan): tiap pembayaran tercatat (tanggal, nominal, metode, catatan). Deposit dapat dipotong sebagai pembayaran.
- **FR-7.5** Dampak stok: **Hilang** → mengurangi stok total; **Rusak** → masuk stok "rusak" (tidak tersedia) sampai ditandai **Sudah Diperbaiki** (kembali tersedia) atau **Afkir** (mengurangi stok total).
- **FR-7.6** Cetak bukti/kwitansi ganti rugi (PDF).
- **FR-7.7** Filter: status, jenis, rentang tanggal, pelanggan; export CSV.
- **FR-7.8** Penghapusan biaya (write-off) hanya Owner/Admin dengan alasan wajib.

### M8 — Struk Transaksi
- **FR-8.1** Modal "Struk Transaksi Thermal" setelah transaksi: header usaha (nama, alamat, WA), ID TRX, penyewa, HP, alamat, jaminan, deposit, tanggal sewa/kembali, daftar item + subtotal, total, kasir, ucapan terima kasih/pesan.
- **FR-8.2** Tombol: **Tutup**, **Simpan PDF**, **Cetak Struk**.
- **FR-8.3** Lebar kertas dapat dipilih di pengaturan: 58mm atau 80mm; cetak memakai `@media print` dan font monospace.
- **FR-8.4** Struk juga memuat ketentuan singkat (denda terlambat, ganti rugi) dari pengaturan.
- **FR-8.5** Struk dapat dicetak ulang dari Data Transaksi; struk ganti rugi tersedia di M7.
- **FR-8.6** (Opsional) Tombol "Kirim via WhatsApp" membuka `wa.me` dengan ringkasan teks.

### M9 — Dashboard & Ringkasan Aset
Kartu ringkasan (mengikuti referensi):
1. **Total Jenis Alat**
2. **Total Nilai Aset Alat** (Σ harga_beli × stok_total)
3. **Booking Aktif** (jumlah unit sedang disewa)
4. **Nilai Alat Tersewa** (Σ harga_beli unit sedang disewa)
5. **Total Transaksi**
6. **Total Omset/Pendapatan**

Grafik:
- **Tren Omset Penyewaan Bulanan** (line chart Jan–Des, tahun dapat dipilih).
- **Alat Paling Populer** (donut chart, Top 5 berdasarkan jumlah disewa).

Tambahan:
- **FR-9.1** Daftar "Jatuh tempo hari ini" dan "Terlambat" untuk tindak lanjut.
- **FR-9.2** Peringatan stok menipis/alat habis.
- **FR-9.3** Filter periode (hari ini, 7 hari, bulan ini, tahun ini, kustom).
- **FR-9.4** Seluruh angka dihitung dari data (bukan hardcode) dan di-cache singkat (mis. 60 detik) bila perlu.

### M10 — Laporan & Analisis Bisnis
| Laporan | Isi | Export |
|---|---|---|
| Laporan Omzet | Per hari/minggu/bulan/tahun; komponen sewa, denda, ganti rugi terbayar | PDF, XLSX, CSV |
| Laporan Transaksi | Daftar transaksi per periode & status | PDF, XLSX, CSV |
| Laporan Aset Alat | Stok, nilai aset, utilisasi per alat | PDF, XLSX |
| Performa Alat | Frekuensi sewa, total pendapatan per alat, **utilisasi** (% hari tersewa), ROI (pendapatan ÷ harga beli) | PDF, XLSX |
| Laporan Ganti Rugi | Total tagihan, terbayar, sisa, per alat/pelanggan | PDF, XLSX |
| Laporan Pelanggan | Top pelanggan, pelanggan baru vs repeat | XLSX |
| Laporan Keterlambatan | Transaksi terlambat dan denda | PDF, XLSX |

- **FR-10.1** Semua laporan memiliki filter periode dan kategori.
- **FR-10.2** Pemisahan pendapatan: *pendapatan sewa*, *denda*, *ganti rugi diterima*. Deposit **bukan** pendapatan sampai dipotong.

### M11 — Log Aktivitas
- **FR-11.1** Mencatat: login/logout, CRUD data penting, transaksi, pengembalian, perubahan stok, hapus data, perubahan pengaturan.
- **FR-11.2** Kolom: waktu, user, aksi, modul, deskripsi, IP; nilai lama/baru (JSON) untuk perubahan.
- **FR-11.3** Filter user, modul, tanggal; hanya Owner/Admin yang dapat melihat; log tidak dapat diedit/dihapus dari UI.

### M12 — Pengaturan Usaha
Nama usaha, logo, alamat, nomor WA, lebar struk, teks ketentuan struk, **tarif denda keterlambatan** (mode: % dari harga sewa/hari atau nominal/hari), jam default pengembalian, wajib foto penyewa (on/off), blokir blacklist (on/off), daftar jenis jaminan.

### M13 — Asisten Bantuan (Opsional, P2)
Widget mengambang di pojok kanan bawah dengan sapaan, berisi **panduan penggunaan menu** (FAQ statis berbasis kata kunci: Panduan Input Alat, Panduan POS, Omzet, Nilai Aset). Versi lanjutan dapat terhubung ke LLM API dengan data agregat (bukan data pribadi pelanggan). Tidak boleh memblokir fitur inti.

---

## 6. Aturan Bisnis

**BR-1 Perhitungan durasi:** `durasi_hari = max(1, selisih_hari(tgl_kembali, tgl_sewa))`. Sewa dan kembali pada hari yang sama dihitung 1 hari. Tanggal kembali tidak boleh lebih awal dari tanggal sewa.

**BR-2 Perhitungan biaya:**
```
subtotal_item = harga_sewa_per_hari (snapshot) × qty × durasi_hari
subtotal      = Σ subtotal_item
total_bayar   = subtotal − diskon
```
Harga sewa disimpan sebagai **snapshot** di item transaksi, sehingga perubahan harga master tidak mengubah transaksi lama.

**BR-3 Stok:**
```
stok_tersedia = stok_total − unit_disewa_aktif − unit_rusak
```
- Saat transaksi dibuat (status `Disewa`): unit_disewa_aktif bertambah.
- Saat pengembalian kondisi Baik: unit kembali tersedia.
- Rusak: pindah ke unit_rusak; Hilang: stok_total berkurang.
- Perubahan stok dilakukan dalam **DB transaction** dengan *row locking* (`lockForUpdate`) agar tidak terjadi overselling.

**BR-4 Ketersediaan per rentang tanggal:** Alat dianggap tersedia bila jumlah unit pada transaksi aktif yang rentang tanggalnya beririsan dengan rentang yang diminta + qty yang diminta ≤ `stok_total − unit_rusak`. (Untuk v1.0 tanpa booking masa depan, boleh disederhanakan ke stok real-time; struktur data tetap menyimpan tanggal.)

**BR-5 Keterlambatan:** Bila tanggal kembali aktual > tanggal kembali rencana, denda = `hari_terlambat × tarif_denda` (sesuai pengaturan) per item/transaksi; dapat disesuaikan Admin dengan alasan.

**BR-6 Deposit:** Dicatat sebagai kewajiban (bukan pendapatan). Saat pengembalian: dikembalikan penuh, dipotong (denda/ganti rugi), atau ditahan. Sisa deposit dan potongan tercatat.

**BR-7 Ganti rugi:** Lihat FR-7.3 s.d. FR-7.5. Transaksi dengan ganti rugi belum lunas ditandai pada pelanggan dan muncul di dashboard.

**BR-8 Integritas data:** Transaksi yang sudah `Kembali` tidak dapat diubah item/harganya; koreksi lewat transaksi penyesuaian atau oleh Owner dengan log.

**BR-9 Penomoran:** ID transaksi dan ganti rugi dibuat dalam transaksi DB dengan kunci unik untuk mencegah duplikasi.

**BR-10 Soft delete:** Transaksi, pelanggan, alat, dan ganti rugi memakai soft delete; hard delete hanya via perintah artisan khusus Owner.

---

## 7. Model Data (Skema Database)

> Uang: `unsignedBigInteger` (rupiah). Semua tabel memiliki `created_at`, `updated_at`; tabel bertanda ✱ juga `deleted_at`.

### 7.1 Tabel
**users**
`id, name, username (unique), email (nullable), password, role (enum: owner|admin|kasir), is_active (bool, default true), last_login_at, remember_token`

**categories**
`id, name (unique), description (nullable), is_active`

**items** ✱ *(alat)*
`id, code (unique), category_id → categories, name, description, purchase_price, rental_price_per_day, stock_total, stock_damaged (default 0), photo_path, is_active`
> `stock_rented` dapat dihitung dari `rental_items` aktif (disarankan, tanpa kolom redundan) atau disimpan sebagai counter yang dijaga transaksi.

**customers** ✱
`id, name, phone (unique), address, id_type, id_number (nullable), notes, is_blacklisted (bool), blacklist_reason`

**rentals** ✱ *(transaksi)*
`id, trx_code (unique), customer_id → customers, user_id → users (kasir), rent_date, due_date, returned_at (nullable), duration_days, subtotal, discount, total, late_fee (default 0), guarantee_type, deposit_amount, deposit_returned (default 0), deposit_deducted (default 0), customer_photo_path, status (enum: rented|returned|late|cancelled), notes, cancelled_reason`

**rental_items**
`id, rental_id → rentals, item_id → items, item_name (snapshot), price_per_day (snapshot), qty, qty_returned_good (default 0), qty_damaged (default 0), qty_lost (default 0), subtotal`

**damage_records** ✱ *(ganti rugi)*
`id, code (unique), rental_id → rentals, rental_item_id → rental_items, customer_id, item_id, type (enum: minor_damage|major_damage|lost), qty, charge_amount, paid_amount (default 0), status (enum: unpaid|partial|paid|written_off), resolution (enum: pending|repaired|disposed|n_a), photo_path, notes, written_off_reason`

**damage_payments**
`id, damage_record_id, amount, method (cash|transfer|deposit), paid_at, user_id, notes`

**stock_adjustments**
`id, item_id, user_id, type (add|reduce|damage|repair|dispose|loss), qty, reason, created_at`

**activity_logs**
`id, user_id (nullable), action, module, description, subject_type, subject_id, old_values (json), new_values (json), ip_address, user_agent, created_at`

**settings**
`id, key (unique), value (text)`

### 7.2 Relasi Utama
```
categories 1─* items
customers  1─* rentals 1─* rental_items *─1 items
rentals    1─* damage_records 1─* damage_payments
users      1─* rentals / activity_logs / stock_adjustments
```

### 7.3 Index
`rentals(trx_code)`, `rentals(status, due_date)`, `rentals(rent_date)`, `rental_items(item_id)`, `customers(phone)`, `items(category_id, is_active)`, `activity_logs(module, created_at)`.

---

## 8. Peta Halaman & Routing

| Menu Sidebar | Route | Akses |
|---|---|---|
| Dashboard | `GET /dashboard` | Semua |
| Etalase & POS Kasir | `GET /pos` | Semua |
| Data Alat Outdoor | `resource /items` | Admin, Owner (Kasir: lihat) |
| Master Kategori | `resource /categories` | Admin, Owner |
| Data Transaksi Sewa | `GET /rentals`, `GET /rentals/{id}`, `POST /rentals/{id}/return`, `POST /rentals/{id}/extend`, `DELETE /rentals/{id}` | Semua (hapus: Admin/Owner) |
| Data Pelanggan | `resource /customers` | Semua (hapus: Admin/Owner) |
| Data Ganti Rugi | `resource /damages`, `POST /damages/{id}/payments` | Admin, Owner (Kasir: buat & bayar) |
| Laporan | `GET /reports/{type}` | Admin, Owner |
| Log Aktivitas | `GET /activity-logs` | Admin, Owner |
| Pengguna | `resource /users` | Owner |
| Pengaturan | `GET/PUT /settings` | Owner |
| Keluar | `POST /logout` | Semua |

Endpoint pendukung (JSON): `GET /api/pos/items` (katalog + ketersediaan), `GET /api/customers/search?q=`, `POST /api/pos/checkout`, `GET /rentals/{id}/receipt` (HTML struk), `GET /rentals/{id}/receipt.pdf`, `GET /api/dashboard/stats`, `GET /api/dashboard/charts`.

---

## 9. Desain Antarmuka (UI/UX)

- **Layout:** sidebar gelap kiri (logo + menu, collapsible via hamburger), topbar (judul halaman, jam live, profil), konten utama latar terang.
- **Palet:** hijau (primer, identitas outdoor), aksen biru/oranye/merah muda pada kartu statistik, sidebar navy gelap. Definisikan sebagai token Tailwind (`brand-500`, `brand-600`, dst).
- **Tipografi:** font sans modern (mis. Plus Jakarta Sans / Inter).
- **Komponen:** kartu bergradien untuk statistik, tabel dengan header kapital kecil, badge status (Disewa = kuning, Kembali = hijau, Terlambat = merah, Dibatalkan = abu), modal untuk form (Tambah Alat, Pengembalian, Struk), toast notifikasi.
- **Responsif:** sidebar menjadi drawer di mobile; POS menumpuk (katalog di atas, keranjang sebagai bottom-sheet).
- **Aksesibilitas:** kontras memadai, label form jelas, fokus keyboard terlihat, tombol utama minimal 44px di mobile.
- **Halaman Login:** kartu tengah dengan header hijau (logo, nama aplikasi, tagline "Sistem Manajemen Persewaan Alat Camping"), field username/password dengan ikon, tombol hijau, teks bantuan kredensial.
- **Empty state & loading:** keranjang kosong ("Keranjang masih kosong"), skeleton saat memuat, pesan kosong pada tabel.

---

## 10. Kebutuhan Non-Fungsional

| Aspek | Requirement |
|---|---|
| Performa | Halaman utama < 2 dtk pada 10.000 transaksi; pagination server-side; eager loading untuk hindari N+1 |
| Keamanan | CSRF, validasi `FormRequest`, hashing bcrypt/argon2, authorization via Policy, rate-limit login, `Storage` untuk file privat (foto identitas tidak publik: gunakan signed route), sanitasi output (XSS), SQL via Eloquent/query binding |
| Privasi | Data identitas pelanggan dibatasi akses; foto jaminan disimpan di disk privat |
| Keandalan | Semua operasi stok/uang dalam DB transaction; idempotensi checkout (cegah double-submit) |
| Backup | Perintah artisan/skrip backup harian (dump MySQL atau salin file SQLite); dokumentasikan |
| Audit | Log aktivitas tidak dapat diubah dari UI |
| Kompatibilitas | Chrome/Edge/Firefox/Safari terbaru; layar ≥ 360px |
| Lokalisasi | `id` sebagai locale default; format tanggal `dd/mm/yyyy`, uang `Rp 1.000.000` |
| Maintainability | PSR-12 (Laravel Pint), service class untuk logika bisnis, test fitur untuk alur kritis |
| Deploy | Berjalan di shared hosting/VPS (PHP + MySQL) atau lokal dengan SQLite |

---

## 11. Arsitektur & Struktur Kode

```
app/
  Enums/            RentalStatus, DamageType, DamageStatus, UserRole
  Http/Controllers/ Dashboard, Pos, Item, Category, Customer, Rental, Damage, Report, ActivityLog, User, Setting
  Http/Requests/    StoreItemRequest, CheckoutRequest, ReturnRentalRequest, ...
  Models/           User, Category, Item, Customer, Rental, RentalItem, DamageRecord, DamagePayment, StockAdjustment, ActivityLog, Setting
  Policies/         ItemPolicy, RentalPolicy, CustomerPolicy, DamagePolicy, ...
  Services/         RentalService (checkout, return, extend, cancel)
                    StockService (availability, adjust)
                    DamageService, ReportService, ReceiptService, ActivityLogger
  Observers/        logging perubahan model
database/
  migrations/ seeders/ (UserSeeder, CategorySeeder, ItemSeeder, SettingSeeder, DemoSeeder)
resources/views/    layouts/, components/, pos/, items/, rentals/, customers/, damages/, reports/, receipts/
tests/Feature/      CheckoutTest, ReturnTest, StockTest, DamageTest, AuthorizationTest, ReportTest
```

**Prinsip:** controller tipis; logika bisnis di `Services`; validasi di `FormRequest`; hak akses di `Policy`; semua operasi mutasi stok/uang lewat service dalam transaksi DB.

---

## 12. Alur Utama (User Flow)

**Alur Sewa (Kasir)**
1. Login → buka **Etalase & POS**.
2. Cari/filter alat → klik "+ Keranjang" (atur qty).
3. Pilih tanggal sewa & kembali.
4. Isi/pilih pelanggan, jaminan, deposit, foto.
5. Klik **Proses Transaksi** → sistem validasi stok → simpan → kurangi stok.
6. Modal struk tampil → **Cetak Struk** / **Simpan PDF** → Tutup.

**Alur Pengembalian**
1. Buka **Data Transaksi Sewa** → klik **Kembali** pada transaksi berstatus Disewa/Terlambat.
2. Tandai kondisi tiap item; sistem hitung denda keterlambatan.
3. Tentukan penanganan deposit → simpan.
4. Item Baik kembali ke stok; item Rusak/Hilang membuat draf **Ganti Rugi**.
5. Status menjadi `Kembali`; struk pengembalian dapat dicetak.

**Alur Ganti Rugi**
1. Draf otomatis/ manual → Admin cek & sesuaikan biaya.
2. Catat pembayaran (tunai/transfer/potong deposit), bisa bertahap.
3. Status berubah otomatis (Belum Lunas → Lunas); alat rusak diperbaiki/afkir.

---

## 13. Data Awal (Seeder)

- User: `owner`, `admin`, `kasir` (password awal wajib diganti saat login pertama).
- Kategori: Tenda, Tas Carrier, Matras, Sleeping Bag, Alat Masak, Kompor, Lampu Camping, Lainnya.
- Contoh alat: Tenda Kapasitas 4P, Carrier 60L, Matras Foil, Sleeping Bag Polar, Kompor Portable Mini, Nesting/Alat Masak, Lampu Camping LED.
- Pengaturan default: nama usaha, lebar struk 58mm, tarif denda 100% harga sewa/hari, jenis jaminan standar.

---

## 14. Rencana Pengembangan (Milestone)

| Fase | Cakupan | Estimasi |
|---|---|---|
| 0 — Setup | Install Laravel 13, Tailwind/Vite, auth, layout, role, seeder, CI lint/test | 3 hari |
| 1 — Master Data | Kategori, Alat (+ upload foto), Pelanggan | 4 hari |
| 2 — POS & Transaksi | Etalase, keranjang, checkout, stok, struk thermal/PDF | 6 hari |
| 3 — Pengembalian & Ganti Rugi | Return flow, denda, deposit, ganti rugi + pembayaran | 5 hari |
| 4 — Dashboard & Laporan | Kartu, grafik, laporan + export | 5 hari |
| 5 — Log, Pengaturan, Hardening | Activity log, settings, keamanan, optimasi, backup | 3 hari |
| 6 — QA & Rilis | UAT, perbaikan bug, dokumentasi, deploy | 3 hari |
| (Opsional) Asisten bantuan | Widget FAQ / LLM | 2–3 hari |

Estimasi untuk 1 developer penuh waktu; sesuaikan kapasitas.

---

## 15. Acceptance Criteria (Ringkas)

1. **AC-1** Checkout dengan 3 item berbeda menghasilkan satu transaksi, ID `TRX-YYYYMMDD-NNN` unik, stok masing-masing berkurang tepat sesuai qty.
2. **AC-2** Dua kasir yang checkout alat berstok terakhir bersamaan: hanya satu yang berhasil, yang lain menerima pesan stok tidak cukup.
3. **AC-3** Pengembalian dengan 1 item Rusak dan 1 item Hilang membuat dua record ganti rugi dengan biaya saran yang benar dan mengubah stok sesuai BR-3.
4. **AC-4** Mengubah harga sewa di master tidak mengubah total transaksi lama.
5. **AC-5** Transaksi lewat tanggal kembali tampil berstatus `Terlambat` dan denda terhitung sesuai pengaturan.
6. **AC-6** Kasir tidak dapat mengakses laporan, pengaturan, user, maupun menghapus transaksi (HTTP 403).
7. **AC-7** Struk dapat dicetak (58/80mm) dan disimpan PDF dengan isi sesuai FR-8.1.
8. **AC-8** Dashboard menampilkan 6 kartu dan 2 grafik yang angkanya cocok dengan data transaksi/alat (diverifikasi test).
9. **AC-9** Export CSV transaksi mengikuti filter aktif.
10. **AC-10** Aplikasi berjalan dengan `DB_CONNECTION=sqlite` maupun `mysql` tanpa perubahan kode; seluruh test lulus pada keduanya.
11. **AC-11** Setiap aksi mutasi penting tercatat di Log Aktivitas dengan user dan waktu.
12. **AC-12** POS dapat dipakai nyaman pada layar 360px (tanpa scroll horizontal).

---

## 16. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Stok tidak sinkron (race condition) | Overselling | DB transaction + `lockForUpdate`, test konkurensi |
| Perbedaan perilaku MySQL vs SQLite | Bug lintas DB | Hindari fitur spesifik engine, test di keduanya |
| Foto identitas bocor | Pelanggaran privasi | Disk privat, signed URL, batasi role |
| Printer thermal berbeda-beda | Layout struk rusak | Lebar struk configurable, uji pada printer target |
| Perhitungan denda diperdebatkan | Konflik pelanggan | Aturan tertulis di struk, log perubahan manual |
| Data hilang | Kerugian usaha | Backup terjadwal, soft delete |

---

## 17. Pertanyaan Terbuka (untuk Dikonfirmasi ke Pemilik Usaha)

1. Apakah perlu **booking masa depan** (reservasi tanggal) atau hanya sewa langsung?
2. Skema **denda keterlambatan**: persen dari harga sewa atau nominal tetap per hari?
3. Apakah ada **paket sewa** (bundle) atau **diskon** member/durasi panjang?
4. Apakah perlu **multi-cabang** pada rilis berikutnya?
5. Printer thermal yang dipakai (58mm / 80mm, USB/Bluetooth)?
6. Apakah foto penyewa/jaminan **wajib**?
7. Apakah deposit selalu ada, atau opsional?

---

## 18. Instruksi Setup untuk Developer / AI Agent

```bash
composer create-project laravel/laravel camprent
cd camprent
npm install -D tailwindcss @tailwindcss/vite   # sesuaikan dengan versi Tailwind yang dipakai starter kit
php artisan storage:link
cp .env.example .env && php artisan key:generate
# SQLite (dev):  DB_CONNECTION=sqlite  →  touch database/database.sqlite
# MySQL (prod):  isi DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan migrate --seed
npm run dev && php artisan serve
```

**Urutan implementasi yang disarankan untuk AI agent:** migrations & models → enums & policies → auth & layout → master data (kategori, alat, pelanggan) → `StockService` → `RentalService` + POS → struk → pengembalian → ganti rugi → dashboard → laporan → log → pengaturan → test & hardening. Setiap langkah harus disertai test fitur sebelum lanjut.

---

*Akhir dokumen.*
