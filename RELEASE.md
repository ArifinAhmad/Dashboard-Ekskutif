# Runbook Rilis

## Tujuan

Checklist ini mengendalikan rilis aplikasi E-Eksekutif dari persiapan sampai pemantauan dan rollback. Isi nilai spesifik environment melalui sistem deployment/secret manager, bukan melalui dokumen atau repository.

## Peran dan Persetujuan

- Release owner mengoordinasikan jadwal, checklist, komunikasi, dan keputusan go/no-go.
- Product owner menyetujui scope, UAT, dan dampak bisnis.
- Owner domain menyetujui definisi serta rekonsiliasi KPI terkait.
- Security owner meninjau risiko, kerentanan, akses, dan exception.
- DBA memverifikasi privilege read-only SIMRS, dampak query, kapasitas, dan kesehatan sumber.
- Operator infrastruktur menjalankan deploy, backup, monitoring, dan rollback.
- Service desk menerima panduan dukungan dan jalur eskalasi.

Pemisahan tugas diterapkan pada rilis produksi: pembuat perubahan bukan satu-satunya pihak yang menyetujui dan menjalankan deploy.

## Kriteria Masuk

- [ ] Scope rilis, nomor versi, owner, jadwal, maintenance window, dan rollback window ditetapkan.
- [ ] Branch/tag rilis hanya memuat perubahan yang disetujui.
- [ ] `CHANGELOGS.md`, panduan pengguna, arsitektur, keamanan, dan runbook diperbarui bila relevan.
- [ ] UAT ditandatangani product owner dan owner domain.
- [ ] Rekonsiliasi laporan dalam scope memenuhi toleransi: hitungan selisih 0; uang maksimal Rp1 hanya karena pembulatan yang disetujui.
- [ ] Seluruh route, data, drill-down, dan ekspor diuji terhadap permission.
- [ ] Tidak ada secret atau data pasien dalam repository, artifact, log build, fixture, atau screenshot.
- [ ] Tidak ada kerentanan kritis/tinggi terbuka; exception risiko memiliki owner, alasan, mitigasi, dan tanggal kedaluwarsa.

## Pemeriksaan Build dan Kualitas

Jalankan pada artifact/source revision yang akan dirilis:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan test
./vendor/bin/pint --test
composer audit
npm audit
```

- [ ] Static analysis dan security scan CI lulus sesuai baseline proyek.
- [ ] Artifact dibangun sekali, memiliki checksum/provenance, dan dipromosikan tanpa rebuild manual.
- [ ] Konfigurasi produksi divalidasi tanpa mencetak nilai secret.
- [ ] `APP_ENV=production`, debug nonaktif, URL/locale/timezone benar, dan HTTPS wajib.
- [ ] Cache konfigurasi, route, serta view diuji terhadap deployment topology.

## Database dan Data

- [ ] Backup database aplikasi selesai, terenkripsi, terpantau, dan restore point dicatat secara privat.
- [ ] Uji restore terakhir masih dalam periode kebijakan dan memenuhi RPO/RTO.
- [ ] Migration direview, backward-compatible selama rolling deploy bila diperlukan, dan memiliki rencana rollback/roll-forward.
- [ ] Target migration diverifikasi sebagai database aplikasi, bukan `simrs`.
- [ ] Akun `simrs` diverifikasi hanya memiliki `SELECT` pada tabel/view yang diperlukan.
- [ ] Tidak ada migration, seeder, DDL, atau operasi tulis yang diarahkan ke `simrs`.
- [ ] Query baru ditinjau dengan `EXPLAIN` pada dataset representatif dan mendapat persetujuan DBA bila berat.
- [ ] Retensi dan expiry file ekspor telah aktif; storage tidak publik.

Contoh perintah migration harus menyebut koneksi aplikasi yang telah dikonfigurasi:

```bash
php artisan migrate --database=<koneksi-aplikasi> --force
```

Jangan menggunakan `--database=simrs`, `migrate:fresh`, atau `db:wipe` pada proses rilis.

### Bootstrap Super Admin

Sebelum seeding awal, isi kredensial melalui secret manager/environment dan jangan menyimpannya di repository atau log:

```dotenv
SUPER_ADMIN_NAME="Administrator Sistem"
SUPER_ADMIN_EMAIL="alamat-admin-yang-disetujui"
SUPER_ADMIN_PASSWORD="password-kuat-minimal-12-karakter"
```

Jalankan migration dan seeder hanya pada koneksi database aplikasi yang sudah diverifikasi:

```bash
php artisan migrate --database=<koneksi-aplikasi> --force
php artisan db:seed --class=Database\\Seeders\\SuperAdminSeeder --database=<koneksi-aplikasi> --force
```

`DatabaseSeeder` juga menjalankan `RbacSeeder` lalu `SuperAdminSeeder`. Seeder bersifat idempotent, memastikan akun aktif dan role `super-admin` terpasang. Rotasi atau hapus secret bootstrap setelah provisioning sesuai kebijakan secret management.

## Infrastruktur dan Dependensi

- [ ] TLS, sertifikat, DNS, web server, PHP-FPM, dan health check siap.
- [ ] Database aplikasi, koneksi SIMRS read-only, cache, queue, scheduler, storage privat, mail/notifikasi, dan monitoring sehat.
- [ ] Worker queue menggunakan revision aplikasi yang sama dengan web.
- [ ] Scheduler berjalan tunggal sesuai topology dan housekeeping ekspor dijadwalkan.
- [ ] Kapasitas disk, koneksi database, memory, timeout, dan concurrency memenuhi hasil capacity test.
- [ ] Risiko supplier/cloud dan status layanan dependency telah ditinjau.

## Keamanan Pra-Rilis

- [ ] Session cookie memakai `Secure`, `HttpOnly`, dan kebijakan `SameSite` yang disetujui.
- [ ] CSRF, rate limit login, rotasi session, logout POST, dan revocation session diuji.
- [ ] CSP, HSTS, frame protection, `X-Content-Type-Options`, dan referrer policy diverifikasi.
- [ ] Error production tidak memaparkan SQL, path, stack trace, credential, atau data pasien.
- [ ] Audit login/logout, perubahan akses, ekspor sensitif, dan aksi administrasi terbukti tercatat.
- [ ] Formula injection CSV/XLSX, authorization bypass, IDOR, XSS, SQL injection, dan path traversal diuji.
- [ ] Kontak insiden dan prosedur eskalasi aktif selama window rilis.

## Prosedur Deploy

1. Umumkan dimulainya window dan bekukan perubahan di luar scope.
2. Pastikan backup serta bukti preflight telah selesai.
3. Aktifkan maintenance mode hanya bila strategi deploy memerlukannya.
4. Deploy artifact terverifikasi dan dependency production.
5. Jalankan migration database aplikasi dengan koneksi eksplisit.
6. Segarkan cache konfigurasi/route/view dan restart service/worker secara terkontrol.
7. Jalankan smoke test teknis dan bisnis.
8. Nonaktifkan maintenance mode setelah health check stabil.
9. Umumkan hasil rilis dan mulai periode observasi intensif.

## Smoke Test

- [ ] Halaman login terbuka melalui HTTPS dan error tidak membocorkan detail internal.
- [ ] Login, logout, rate limit, serta akun nonaktif bekerja.
- [ ] Pengguna setiap role hanya melihat dan mengakses modul yang diizinkan.
- [ ] Dashboard/laporan prioritas menampilkan timestamp dan angka sampel yang telah direkonsiliasi.
- [ ] Kondisi sumber SIMRS tidak tersedia menghasilkan pesan aman dan alert operasional.
- [ ] Filter, pagination, drill-down, dan format Rupiah/tanggal berfungsi.
- [ ] Ekspor kecil dan job ekspor besar mengikuti permission, audit, storage privat, serta expiry.
- [ ] Queue, scheduler, cache, audit log, metrics, dan alerting berfungsi.

## Observasi Setelah Rilis

- Pantau error rate, P95 latency, saturation worker, queue depth, failed job, cache hit, koneksi database, slow query, dan kapasitas storage.
- Pantau login gagal, penolakan otorisasi, perubahan privilege, ekspor abnormal, serta indikator serangan tanpa mencatat data sensitif berlebihan.
- Bandingkan KPI prioritas dengan baseline selama parallel run.
- Dokumentasikan anomali, owner, severity, keputusan, dan waktu penyelesaian.
- Tutup window hanya setelah metrik stabil dan owner rilis menyetujui.

## Kriteria Rollback

Rollback atau hentikan paparan bila terjadi salah satu kondisi berikut:

- Risiko keselamatan, privasi, atau integritas data.
- Bukti operasi tulis atau privilege tidak semestinya pada SIMRS.
- Bypass autentikasi/otorisasi atau kebocoran data.
- Selisih KPI material yang belum dapat dijelaskan.
- Error rate, latency, beban database, atau backlog queue melewati ambang yang disetujui.
- Migration atau dependency menyebabkan layanan inti tidak stabil.

## Prosedur Rollback

1. Catat waktu, pemicu, dan incident commander; beri tahu stakeholder.
2. Hentikan traffic atau aktifkan maintenance mode bila diperlukan untuk membatasi dampak.
3. Kembalikan artifact/config ke versi terakhir yang diketahui baik.
4. Restart web/worker dan bersihkan cache yang hanya terkait release secara terkontrol.
5. Utamakan roll-forward untuk migration; lakukan down migration/restore hanya jika telah diuji dan disetujui.
6. Jangan mengubah database SIMRS sebagai bagian rollback.
7. Jalankan smoke test versi lama, pantau pemulihan, dan simpan bukti.
8. Buka problem review dan tindak lanjut setelah layanan stabil.

## Bukti Rilis

Simpan secara terkontrol: versi/tag dan checksum artifact, approval, hasil test/scan, hasil rekonsiliasi, catatan migration, bukti backup/restore, smoke test, metrik observasi, insiden, rollback bila ada, dan sign-off penutupan. Jangan menyimpan secret atau data pasien dalam tiket rilis.
