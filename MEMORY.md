# Memori Proyek Terverifikasi

Dokumen ini hanya menyimpan fakta proyek yang tahan lama dan telah diverifikasi dari repository atau PRD. Dokumen ini bukan backlog, catatan spekulasi, tempat menyimpan kredensial, maupun pengganti keputusan formal.

## Identitas dan Tujuan

- Nama produk target adalah E-Eksekutif SIMRS berbasis Laravel.
- Aplikasi ditujukan sebagai dashboard analitik dan pelaporan manajemen rumah sakit, bukan pengganti transaksi klinis, farmasi, kasir, atau akuntansi Khanza.
- Dokumen kebutuhan produk berada di `../PRD-LARAVEL-E-EKSEKUTIF.md`, berstatus draft versi 1.0 bertanggal 17 September 2026.
- Bahasa antarmuka yang ditetapkan PRD adalah Bahasa Indonesia.
- Zona waktu bisnis yang ditetapkan PRD adalah `Asia/Jakarta`.

## Fakta Teknologi per 23 September 2026

- Proyek dibuat dari skeleton Laravel.
- `composer.json` mensyaratkan PHP `^8.2` dan `laravel/framework` `^12.0`.
- Test runner yang terpasang adalah PHPUnit `^11.5.3`.
- Formatter yang terpasang adalah Laravel Pint `^1.13`.
- Build frontend menggunakan Vite `^6.0.11` dan Tailwind CSS `^4.0.0`.
- Route web menyediakan login/logout, dashboard, laporan generik, dan ekspor CSV.
- Migration aplikasi membuat tabel pengguna, cache, job, peran, izin, pivot RBAC, dan audit log.
- `.env.example` memakai SQLite sebagai default lokal, locale `id`, zona waktu `Asia/Jakarta`, dan variabel koneksi SIMRS terpisah.
- Konfigurasi menyediakan koneksi bernama `simrs`; service `ReadOnlySimrs` menolak statement mutasi selain SELECT/CTE read-only.
- Autentikasi session, rate limiting, RBAC internal, audit login/logout/report, security headers, dan netralisasi formula ekspor CSV telah diimplementasikan.
- Dashboard serta laporan rawat jalan, IGD, laboratorium, dan radiologi memiliki query aktif; laporan lain terdaftar sebagai roadmap dan belum diklaim selesai.

## Fakta Arsitektur Produk

- Database operasional Khanza adalah sumber kebenaran data operasional.
- Aplikasi wajib memiliki database internal terpisah untuk data aplikasi seperti akun, peran, izin, audit, preferensi, cache/session bila dipakai, queue, dan metadata ekspor.
- Koneksi aplikasi ke database Khanza harus menggunakan akun khusus dengan hak `SELECT` saja.
- Migration dan operasi tulis tidak boleh dijalankan terhadap database SIMRS.
- Nama tabel dan kolom aktual harus diverifikasi pada setiap environment target karena variasi instalasi Khanza dimungkinkan.
- Nilai uang dihitung sebagai decimal, bukan float.
- Tanggal legacy kosong atau nol diperlakukan sebagai kondisi domain, bukan dipaksa menjadi tanggal valid.
- Filter tanggal dikirim dalam format ISO `YYYY-MM-DD`, walaupun tampilannya memakai format Indonesia.
- Tabel detail menggunakan pagination server-side; ekspor besar dijalankan asinkron.

## Fakta Akses dan Data

- Model akses minimum dalam PRD mencakup `super-admin`, `direksi`, `pelayanan`, `farmasi`, `logistik`, `keuangan`, dan `auditor`.
- Pengguna dapat memiliki lebih dari satu peran; enforcement harus menggunakan izin granular.
- Data identitas pasien harus diminimalkan dan aksesnya dibatasi berdasarkan kebutuhan tugas.
- Login, logout, perubahan akses, dan ekspor sensitif harus diaudit.
- Password reversible dari aplikasi legacy tidak boleh dimigrasikan; akun harus dibuat ulang atau menjalani reset password.

## Target Produk yang Telah Ditetapkan, Belum Berarti Terimplementasi

- Target rekonsiliasi adalah selisih nol untuk hitungan dan maksimal Rp1 untuk uang akibat pembulatan.
- Target waktu muat dashboard utama adalah P95 paling lama 3 detik setelah cache hangat.
- Target laporan sinkron sampai 31 hari adalah P95 paling lama 5 detik.
- Target ekspor besar adalah selesai dalam 5 menit tanpa memblokir request web.
- Target keberhasilan request bulanan adalah sekurang-kurangnya 99,5% di luar maintenance terjadwal.
- Baseline keamanan aplikasi adalah OWASP ASVS Level 2 menurut PRD.

## Aturan Pemeliharaan Memori

- Tambahkan fakta hanya setelah diverifikasi dari kode, konfigurasi nonrahasia, hasil keputusan yang disetujui, atau artefak pengujian.
- Cantumkan tanggal bila fakta dapat berubah seiring fase proyek.
- Pindahkan rencana atau pekerjaan belum selesai ke backlog/issue, bukan ke dokumen ini sebagai fakta.
- Jangan menulis hostname, alamat IP internal, username, password, key, token, connection string, data pasien, atau detail infrastruktur sensitif.
- Koreksi fakta lama secara langsung jika implementasi berubah; catat perubahan yang terlihat pengguna di `CHANGELOGS.md`.
