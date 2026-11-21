# Changelog

Semua perubahan penting pada proyek ini dicatat dalam dokumen ini.

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan versi rilis mengikuti [Semantic Versioning](https://semver.org/lang/id/). Perubahan yang belum dirilis ditempatkan pada `Unreleased`, lalu dipindahkan ke versi bernomor beserta tanggal `YYYY-MM-DD` saat rilis.

## [Unreleased]

### Added

- Dokumentasi fondasi proyek untuk agen pengembang, memori fakta, changelog, devlog, release runbook, arsitektur, kemampuan tim, template kerja, panduan pengguna, dan keamanan.
- Guardrail dokumenter untuk memisahkan database aplikasi read-write dari koneksi Khanza SIMRS read-only.
- Baseline tata kelola keamanan yang memetakan tema ISO/IEC 27001:2022, panduan ISO/IEC 27002:2022, dan OWASP Top 10 2021 ke kontrol aplikasi.
- Aplikasi Laravel 12 dengan autentikasi session, rate limit login, RBAC internal, audit log, dan security headers.
- Koneksi `simrs` terpisah serta service read-only yang menolak statement mutasi.
- Dashboard eksekutif dan laporan rawat jalan, IGD, laboratorium, serta radiologi berbasis data SIMRS.
- Katalog navigasi seluruh domain PRD dengan status roadmap eksplisit untuk laporan yang belum direkonsiliasi.
- Ekspor CSV berizin dengan netralisasi formula spreadsheet.
- Antarmuka Blade responsif untuk login, dashboard, filter, tabel, dan status koneksi sumber.
- Bypass permission eksplisit untuk role `super-admin` agar selalu memiliki akses penuh.
- Halaman administrasi `/administrasi/akses` untuk membuat pengguna, membuat role, menetapkan permission, dan memperbarui akses pengguna.
- Proteksi agar admin tidak menonaktifkan akun sendiri dan hanya super-admin yang dapat memberikan role super-admin.

### Changed

- Locale aplikasi menggunakan Bahasa Indonesia dan zona waktu `Asia/Jakarta`.
- Cache dashboard SIMRS memakai TTL lima menit, default Redis, dan warming terjadwal setiap lima menit untuk mengurangi query sinkron berulang.
- Agregasi pergerakan rawat inap dashboard menggunakan satu query terikat, bukan empat subquery terpisah.

### Deprecated

- Belum ada.

### Removed

- Belum ada.

### Fixed

- Menu laporan kini tetap menampilkan seluruh katalog untuk super-admin; laporan yang belum memiliki query diberi label `Roadmap` dan tidak disamarkan sebagai data tersedia.
- Database pengujian dipisahkan ke SQLite in-memory agar `RefreshDatabase` tidak lagi menghapus role atau akun pada database development.
- Ekspor XLSX tidak lagi gagal karena penggunaan method header yang tidak tersedia pada `StreamedResponse`.

### Security

- Larangan eksplisit migration dan operasi tulis pada database SIMRS didokumentasikan.
- Persyaratan least privilege, audit, privasi, secure SDLC, pengelolaan kerentanan, backup, logging, dan pelaporan insiden didokumentasikan.
- Session diregenerasi setelah login, logout memakai POST/CSRF, dan respons memakai CSP, HSTS saat HTTPS, anti-framing, nosniff, serta referrer policy.
- Login dilindungi middleware rate limiter bernama dan formula XLSX yang diawali tab atau carriage return dinetralkan.

## Pedoman Entri

- Tulis dampak bagi pengguna atau operator, bukan daftar commit mentah.
- Gunakan kategori `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, dan `Security`.
- Kaitkan issue atau keputusan bila tersedia, tanpa memasukkan data pasien atau informasi sensitif.
- Jangan menyatakan fitur tersedia hanya karena direncanakan dalam PRD.
- Perubahan formula KPI harus menyebut versi definisi dan status persetujuan owner bisnis.
- Perubahan schema database aplikasi, konfigurasi wajib, langkah deploy, dan incompatibility harus dinyatakan dengan jelas.

Contoh format rilis:

```markdown
## [0.1.0] - 2026-10-15

### Added

- Autentikasi akun lokal dan izin `dashboard.view`.

### Security

- Rotasi session setelah login dan rate limiting percobaan login.
```

[Unreleased]: https://example.invalid/eeksekutif/compare/HEAD
