# Petunjuk Agen Pengembang

## Tujuan Repositori

Repositori ini ditujukan untuk aplikasi E-Eksekutif SIMRS berbasis Laravel 12 dan PHP 8.2. Aplikasi menyajikan dashboard, analitik, rekonsiliasi, dan ekspor laporan manajemen rumah sakit. Database operasional Khanza SIMRS adalah sumber data, bukan target transaksi aplikasi ini.

Dokumen produk utama berada di `../PRD-LARAVEL-E-EKSEKUTIF.md`. Baca PRD, `TECH.md`, `SECURITY.md`, dan dokumen domain yang relevan sebelum mengubah kode.

## Guardrail Mutlak Database

- Gunakan database aplikasi sebagai koneksi default read-write untuk pengguna, peran, izin, audit, cache, session, queue, pengaturan, dan metadata ekspor.
- Gunakan koneksi bernama `simrs` hanya untuk membaca database Khanza.
- Dilarang menjalankan `INSERT`, `UPDATE`, `DELETE`, `REPLACE`, `TRUNCATE`, `ALTER`, `CREATE`, `DROP`, migration, seeder, atau DDL apa pun pada koneksi `simrs`.
- Dilarang menggunakan akun administratif atau akun yang memiliki hak tulis untuk koneksi `simrs`, termasuk pada development dan CI.
- Jangan menambahkan model SIMRS yang dapat menyimpan perubahan. Bila memakai Eloquent untuk sumber SIMRS, koneksi harus eksplisit dan alur mutasi harus dicegah; Query Builder/query object lebih disukai untuk laporan.
- Semua migration harus menargetkan database aplikasi. Periksa koneksi sebelum menjalankan `php artisan migrate`.
- Perubahan indeks, view, atau skema Khanza bukan bagian aplikasi. Ajukan kebutuhan kepada DBA melalui prosedur perubahan terpisah.
- Query laporan wajib memakai parameter binding. Jangan menggabungkan input pengguna ke SQL mentah.

Jika sebuah tugas mengharuskan penulisan ke SIMRS, hentikan implementasi dan eskalasikan karena bertentangan dengan arsitektur produk.

## Kondisi Repositori Saat Ini

Pada bootstrap awal yang terdokumentasi tanggal 23 September 2026:

- `composer.json` menetapkan PHP `^8.2`, Laravel `^12.0`, dan PHPUnit `^11.5.3`.
- Frontend memakai Vite 6 dan Tailwind CSS 4 sebagai dependensi awal.
- Route autentikasi, dashboard, katalog laporan, dan ekspor CSV telah tersedia.
- `config/database.php` mendefinisikan koneksi `simrs`; service `ReadOnlySimrs` menolak statement mutasi.
- SQLite tetap menjadi default lokal untuk data internal aplikasi; `.env.example` menyediakan variabel koneksi SIMRS terpisah.
- RBAC dan audit diimplementasikan secara internal. Livewire, Chart.js, XLSX, PDF, Redis/Horizon belum menjadi dependensi terverifikasi.
- Dashboard dan laporan rawat jalan, IGD, laboratorium, serta radiologi sudah memiliki query; modul lain masih berupa katalog/roadmap sampai direkonsiliasi.

Jangan menulis dokumentasi atau kode seolah fitur target sudah selesai. Perbarui fakta ini setelah implementasi benar-benar diverifikasi.

## Arsitektur Implementasi

- Route dan middleware: autentikasi, otorisasi, rate limiting, serta binding parameter.
- Form Request: validasi dan normalisasi filter, terutama tanggal ISO `YYYY-MM-DD` dan tahun empat digit.
- Controller atau komponen UI: orkestrasi tipis; tidak menyimpan SQL atau rumus KPI.
- Query Object: query terparameterisasi per keluarga laporan pada koneksi `simrs`.
- Domain Service: rule lintas tabel seperti klasifikasi pendapatan, saldo, hutang/piutang, dan estimasi biaya.
- DTO/View Model: kontrak stabil antara hasil query dan tampilan/ekspor.
- Policy/Gate: izin granular di server, bukan hanya menyembunyikan menu.
- Job: ekspor besar, cache warming, dan pekerjaan aman yang tidak memblokir request.
- Blade: output di-escape secara default; raw HTML hanya setelah review keamanan.

Keluarga laporan yang serupa harus memakai komponen reusable dengan adapter eksplisit. Reuse tidak boleh menyamarkan perbedaan rule bisnis.

## Aturan Data dan KPI

- Anggap schema Khanza berbeda antar instalasi sampai terbukti melalui discovery environment target.
- Dokumentasikan tabel, kolom, join, pengecualian, pemilik definisi, freshness, dan versi formula setiap KPI.
- Gunakan `decimal` untuk uang; jangan gunakan `float` untuk kalkulasi finansial.
- Perlakukan tanggal nol seperti `0000-00-00`, timestamp terbalik, null, dan referensi hilang sebagai kondisi domain yang terukur.
- Hindari N+1, fungsi pada kolom tanggal yang merusak indeks, dan full table scan tanpa justifikasi.
- Tinjau query prioritas dengan `EXPLAIN`; pengujian tidak boleh membebani database produksi.
- Filter layar dan ekspor harus memakai definisi/query yang sama.
- Setiap perbedaan hasil dengan legacy harus diklasifikasikan sebagai bug baru, bug legacy, perubahan rule disetujui, atau masalah kualitas data.

## Keamanan dan Privasi

- Jangan pernah membaca, menampilkan, menyalin, atau commit isi `.env`, secret, password, token, atau data pasien nyata.
- Gunakan dataset sintetis atau tersamarkan untuk test, fixture, screenshot, dan log.
- Terapkan least privilege, CSRF, validasi server-side, output encoding, secure session, rate limit, dan audit untuk aksi sensitif.
- Ekspor adalah data sensitif: wajib diotorisasi, diaudit, disimpan privat, memiliki masa kedaluwarsa, dan menetralkan formula spreadsheet.
- Jangan mencatat password, cookie, token, query berisi data pasien, payload penuh, atau kredensial ke log.
- Ikuti `SECURITY.md`; laporkan dugaan insiden melalui jalur privat, bukan issue publik.

## Perintah Kerja

Jalankan dari root proyek ini. Sesuaikan layanan eksternal dengan environment lokal yang disetujui.

```bash
composer install
npm install
php artisan key:generate
php artisan about
composer run dev
```

Pemeriksaan minimum sebelum menyerahkan perubahan:

```bash
php artisan test
./vendor/bin/pint --test
npm run build
composer audit
npm audit
```

Gunakan `./vendor/bin/pint` untuk memperbaiki format. Jalankan migration hanya pada database aplikasi yang telah diverifikasi:

```bash
php artisan migrate --database=<koneksi-aplikasi>
```

Jangan pernah memakai `--database=simrs`. Jangan menjalankan `migrate:fresh`, `db:wipe`, seeder destruktif, atau test yang melakukan refresh database terhadap host SIMRS.

## Strategi Pengujian

- Unit: normalisasi filter, formula, bucket durasi, pembulatan, adapter, dan klasifikasi.
- Feature: autentikasi, RBAC, validasi, pagination, drill-down, audit, ekspor, empty/error state.
- Integration: dua koneksi database, fixture schema Khanza, cache, queue, kegagalan sumber, dan jaminan read-only.
- Rekonsiliasi: parameter identik antara legacy dan aplikasi baru; bandingkan total, subtotal, dimensi, dan pembulatan.
- Nonfungsional: performa, aksesibilitas, dependency audit, static analysis, dan security test.

Test yang menyentuh database harus menggunakan database disposable yang jelas, bukan database SIMRS sebenarnya. Tambahkan regression test untuk setiap bug.

## Definition of Done

- Scope dan acceptance criteria terpenuhi.
- Otorisasi diterapkan pada route, data, dan ekspor.
- Query aman, terparameterisasi, ditinjau performanya, dan tidak menulis ke SIMRS.
- Test relevan lulus dan kegagalan sumber ditangani aman.
- Hasil laporan direkonsiliasi dengan baseline yang disetujui.
- Audit, privacy classification, empty state, error state, dan aksesibilitas ditangani.
- Dokumentasi diperbarui sesuai perubahan yang benar-benar dibuat.
- Tidak ada secret, data pasien, debug output, atau kerentanan kritis/tinggi terbuka.

## Pembaruan Dokumentasi

- `CHANGELOGS.md`: perubahan yang terlihat pengguna/operator pada bagian `Unreleased`.
- `DEVLOGS.md`: keputusan implementasi, eksperimen, atau kendala yang penting bagi pengembang.
- `TECH.md`: keputusan arsitektur dan konsekuensinya.
- `MEMORY.md`: hanya fakta tahan lama yang telah diverifikasi; tanpa rencana, dugaan, atau secret.
- `RELEASE.md`: perubahan langkah deploy, rollback, konfigurasi, atau verifikasi.
- `SECURITY.md`: perubahan kontrol, threat model, pelaporan, atau risiko.
- `USERS.md`: perubahan peran, onboarding, alur penggunaan, atau tanggung jawab data.

Jangan mengubah dokumentasi untuk mengklaim fitur selesai sebelum kode, test, dan bukti verifikasinya tersedia.
