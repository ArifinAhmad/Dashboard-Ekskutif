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
php artisan db:seed --class=RbacSeeder
php artisan app:create-user
npm run build
php artisan serve --no-reload
```

Isi `SIMRS_DB_*` pada `.env` untuk mengaktifkan data dashboard dan laporan. Jangan gunakan akun `root` atau akun dengan privilege tulis.

## Membuat pengguna

Jalankan `php artisan app:create-user`. Perintah meminta nama, email, password secara tersembunyi, dan role. Tidak ada password default yang disimpan dalam source code atau seeder.

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
