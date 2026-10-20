# E-Eksekutif SIMRS

Aplikasi dashboard dan pelaporan manajemen rumah sakit berbasis Laravel 12. Data operasional dibaca dari database Khanza SIMRS melalui koneksi `simrs` yang wajib memiliki privilege `SELECT` saja. Akun, RBAC, audit, session, cache, dan queue disimpan pada database aplikasi terpisah.

## Fitur yang tersedia

- Login session dengan rate limiting, status akun, rotasi session, dan logout POST/CSRF.
- RBAC granular berbasis role dan permission.
- Audit login, logout, akses dashboard/laporan, dan ekspor.
- Security headers dan koneksi SIMRS yang menolak statement mutasi.
- Dashboard registrasi dan rawat inap harian.
- Laporan rawat jalan, IGD, laboratorium, dan radiologi berdasarkan periode.
- Ekspor CSV dengan permission dan perlindungan formula injection.
- Katalog seluruh domain PRD; laporan yang belum direkonsiliasi ditandai sebagai roadmap.
- UI Blade responsif untuk desktop dan mobile.

## Persyaratan

- PHP 8.2 atau lebih baru sesuai `composer.json`.
- Composer 2.
- Node.js 20 dan npm.
- SQLite untuk development atau MySQL/MariaDB untuk database aplikasi.
- MySQL/MariaDB Khanza dengan akun reporting read-only.

## Instalasi lokal

```bash
composer install
npm install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
php artisan migrate --database=sqlite
php artisan db:seed --class=Database\Seeders\DatabaseSeeder --force
npm run build
php artisan serve --no-reload
```

Isi `SIMRS_DB_*` pada `.env` untuk mengaktifkan data dashboard dan laporan. Jangan gunakan akun `root` atau akun dengan privilege tulis.

## Membuat Pengguna

### Super Admin Pertama

Seeder membuat akun super admin pertama dari environment. Salin `.env.example` menjadi `.env`, lalu isi kredensial melalui secret manager atau environment lokal:

```dotenv
SUPER_ADMIN_NAME="Administrator Sistem"
SUPER_ADMIN_EMAIL="admin@rumahsakit.example"
SUPER_ADMIN_PASSWORD="Password-Kuat!2026"
```

Password wajib minimal 12 karakter dan mengandung huruf besar, huruf kecil, angka, serta simbol. Jangan menaruh password asli di repository, dokumentasi, tiket, screenshot, atau log.

Jalankan dari folder yang berisi file `artisan`:

```powershell
cd "D:\MASTER SIMRS\ORI\SIMRS-Khanza\eeksekutif\eeksekutif-laravel"
php artisan migrate --database=sqlite --force
php artisan db:seed --class=Database\Seeders\DatabaseSeeder --force
```

Pada PowerShell, gunakan satu backslash pada nama class. Jika hanya ingin menjalankan seeder super admin setelah role sudah tersedia:

```powershell
php artisan db:seed --class=Database\Seeders\SuperAdminSeeder --force
```

Urutan `DatabaseSeeder` adalah `RbacSeeder`, kemudian `SuperAdminSeeder`. Seeder bersifat idempotent: akun dicari berdasarkan `SUPER_ADMIN_EMAIL`, lalu diaktifkan, diverifikasi, password di-hash, dan role `super-admin` dipasang tanpa membuat duplikasi.

Jika muncul `Target class [Database\\Seeders\\SuperAdminSeeder] does not exist`, jalankan:

```powershell
composer dump-autoload
php artisan optimize:clear
php artisan db:seed --class=Database\Seeders\SuperAdminSeeder --force
```

Pastikan file berikut ada dan namespace-nya tepat:

```text
database/seeders/SuperAdminSeeder.php
namespace Database\Seeders;
```

Migration dan seeder hanya boleh dijalankan pada database aplikasi. Jangan gunakan `--database=simrs`; database Khanza SIMRS harus tetap read-only.

Setelah login pertama berhasil, rotasi atau hapus `SUPER_ADMIN_PASSWORD` dari secret manager sesuai prosedur organisasi.

### Pengguna Berikutnya

Setelah super admin dapat login, pengguna berikutnya dibuat melalui menu:

```text
Administrasi → Pengguna & Role → Tambah Pengguna Baru
```

Password pengguna disimpan sebagai hash dan tidak pernah ditampilkan kembali.

## Verifikasi

```bash
php artisan test
vendor/bin/pint --test
npm run build
composer audit --locked
npm audit --audit-level=high
```

## Status cakupan

Dashboard dan laporan rawat jalan, IGD, laboratorium, serta radiologi telah memiliki query. Modul lain sudah memiliki navigasi, izin, filter, dan state roadmap, tetapi belum dianggap selesai sebelum query dimigrasikan, diuji dengan `EXPLAIN`, dan direkonsiliasi dengan sistem legacy pada database representatif.

## Dokumentasi

- PRD: `../PRD-LARAVEL-E-EKSEKUTIF.md`
- Arsitektur: `TECH.md`
- Keamanan: `SECURITY.md`
- Pengguna dan role: `USERS.md`
- Instruksi agen: `AGENTS.md`
- Runbook rilis: `RELEASE.md`
- Changelog: `CHANGELOGS.md`

Dokumentasi ISO/IEC dan OWASP adalah panduan implementasi dan bukti kontrol, bukan pernyataan sertifikasi.
