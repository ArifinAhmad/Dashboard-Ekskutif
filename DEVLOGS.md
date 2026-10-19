# Log Pengembangan

## 2026-09-23 - Bootstrap Dokumentasi

### Konteks

Repository diperiksa sebagai proyek skeleton Laravel 12 dengan PHP 8.2. PRD menetapkan migrasi aplikasi E-Eksekutif legacy ke aplikasi analitik modern yang membaca data operasional Khanza dan menyimpan data internalnya pada database terpisah.

### Keputusan Bootstrap

- Pertahankan database SIMRS sebagai source of truth data operasional dan gunakan koneksi read-only.
- Pisahkan database aplikasi untuk akun, RBAC, audit, pengaturan, job, cache/session bila relevan, dan metadata ekspor.
- Perlakukan nama koneksi `simrs`, stack UI server-driven, Redis/Horizon, library ekspor, Chart.js, dan library RBAC sebagai target implementasi sampai benar-benar dipasang dan diuji.
- Gunakan Query Object per keluarga laporan agar SQL tidak berada di controller atau Blade.
- Gunakan Domain Service untuk rule lintas tabel dan DTO/View Model untuk kontrak hasil.
- Gunakan satu definisi query/filter bagi layar dan ekspor untuk mencegah perbedaan angka.
- Jadikan permission, audit, klasifikasi sensitivitas, rekonsiliasi, serta performa bagian dari Definition of Done setiap laporan.
- Terapkan migration bertahap: fondasi, MVP pelayanan, inventori, kasir/keuangan, lalu cutover.

### Kondisi Awal yang Ditemukan

- Route web hanya membuka halaman `welcome`.
- Konfigurasi koneksi database masih bawaan Laravel dan belum memiliki koneksi `simrs`.
- `.env.example` masih menggunakan SQLite dan locale Inggris.
- Model domain, report query, policy, audit, dan job ekspor belum ditemukan.
- Test yang tersedia masih test contoh bawaan.
- Dependensi frontend awal adalah Vite dan Tailwind; pilihan Livewire, Alpine, Chart.js, XLSX, dan PDF dari PRD belum terpasang.

### Prinsip Implementasi Berikutnya

- Mulai dari threat model, model izin, kontrak KPI, dan discovery schema sebelum membangun banyak layar.
- Buat mekanisme teknis yang gagal aman jika koneksi `simrs` memiliki privilege tulis atau dipakai untuk migration.
- Gunakan fixture schema dan data sintetis/tersamarkan untuk integration test.
- Verifikasi query dengan `EXPLAIN`, target performa, dan rekonsiliasi terhadap legacy sebelum UAT.
- Catat keputusan arsitektur tahan lama di `TECH.md`; catat perubahan pengguna di `CHANGELOGS.md`.

### Risiko Bootstrap

- Schema Khanza dapat berbeda antar versi atau instalasi.
- Definisi KPI legacy dapat ambigu atau mengandung bug.
- Query analitik dapat membebani database operasional.
- Ekspor dapat memperbesar dampak kebocoran data.
- Dokumentasi target dapat keliru dianggap sebagai fitur yang sudah tersedia; karena itu setiap dokumen membedakan kondisi aktual dan target.

### Langkah Teknis yang Direkomendasikan

1. Tetapkan owner produk, owner keamanan, owner tiap KPI, DBA, dan operator rilis.
2. Selesaikan discovery schema dan katalog data tanpa menyalin data sensitif ke repository.
3. Konfigurasikan database aplikasi dan `simrs` dengan secret manager serta privilege minimum.
4. Tambahkan test yang membuktikan pemisahan koneksi dan penolakan operasi tulis ke SIMRS.
5. Implementasikan autentikasi, RBAC, audit, header keamanan, dan observability.
6. Bangun satu vertical slice laporan prioritas beserta rekonsiliasi dan ekspor.
7. Uji performa, keamanan, aksesibilitas, backup/restore, deploy, dan rollback sebelum memperluas modul.

## Format Entri Berikutnya

Setiap entri sebaiknya berisi tanggal, konteks, keputusan, alternatif yang ditolak, konsekuensi, bukti uji, dan tindak lanjut. Jangan mencatat secret, data pasien, atau detail insiden yang harus tetap privat.

## 2026-09-23 - Vertical Slice Aplikasi

### Implementasi

- Laravel dipasang pada PHP 8.2 dengan aplikasi internal SQLite untuk development.
- Koneksi `simrs` ditambahkan terpisah dan dibungkus `ReadOnlySimrs` yang hanya menerima SELECT/CTE read-only.
- Autentikasi session, rate limit, status akun, RBAC internal, audit, dan security headers diterapkan.
- Dashboard serta empat laporan pelayanan menggunakan query berparameter; seluruh domain lain tersedia dalam katalog dengan status belum diimplementasikan.
- Ekspor CSV memakai query yang sama dengan layar, permission `report.export`, audit, dan formula neutralization.
- UI Blade responsif dibangun tanpa menambah SPA atau paket UI eksternal.

### Bukti Verifikasi Awal

- Migration dan seeder hanya dijalankan pada koneksi aplikasi SQLite.
- `npm install` melaporkan nol vulnerability dan build Vite berhasil.
- Composer audit melaporkan tidak ada advisory.
- Test read-only, autentikasi, akun nonaktif, RBAC, dan security header ditambahkan.

### Batasan

- Belum ada environment SIMRS representatif untuk rekonsiliasi angka dan `EXPLAIN` query.
- Laporan di luar dashboard/rawat jalan/IGD/lab/radiologi belum memiliki query aktif.

## 2026-09-23 - Administrasi Akses

### Masalah

Beberapa pengguna super-admin melihat menu atau akses laporan tidak lengkap ketika pivot permission tidak tersinkron atau role belum diperbarui. Selain itu pembuatan pengguna dan role masih harus dilakukan melalui CLI.

### Keputusan

- Role `super-admin` memiliki akses penuh melalui pemeriksaan role eksplisit, tidak bergantung pada setiap baris pivot permission.
- Halaman `/administrasi/akses` menjadi pusat pembuatan user, pembuatan role, assignment permission, dan aktivasi akun.
- `super-admin` adalah role sistem yang tidak boleh diedit; pemberiannya hanya boleh dilakukan oleh super-admin.
- Akun administrator yang sedang login tidak dapat menonaktifkan dirinya sendiri.
- Menu roadmap tetap terlihat untuk super-admin agar cakupan implementasi dapat dibedakan dari masalah permission.

### Bukti

- Test suite: 16 test dan 37 assertion lulus.
- Route administrasi terdaftar: daftar akses, buat user, update akses user, buat role, dan update role.
- Build Vite berhasil setelah perubahan UI administrasi.

### Koreksi Isolasi Test

- PHPUnit sebelumnya mewarisi database SQLite development karena konfigurasi in-memory masih dikomentari.
- `RefreshDatabase` dapat mengosongkan role dan akun lokal setelah test, sehingga menu super-admin hilang pada sesi berikutnya.
- PHPUnit sekarang memaksa `DB_CONNECTION=sqlite` dan `DB_DATABASE=:memory:` agar test tidak pernah menyentuh database aplikasi lokal.
- XLSX, PDF, ekspor asinkron, Redis/Horizon, MFA, dan administrasi pengguna belum diimplementasikan.
