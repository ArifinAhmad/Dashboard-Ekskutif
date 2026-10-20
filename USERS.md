# Panduan Pengguna dan Tanggung Jawab Data

## Tentang Aplikasi

E-Eksekutif menyajikan dashboard dan laporan manajemen berdasarkan data operasional Khanza SIMRS. Aplikasi ini bersifat analitik/read-only terhadap data SIMRS dan bukan tempat memperbaiki transaksi klinis, stok, kasir, atau akuntansi. Koreksi data sumber dilakukan oleh pemilik proses melalui modul resmi SIMRS dan prosedur rumah sakit.

Fitur dalam PRD diterapkan bertahap. Menu yang tersedia bergantung pada fase rilis, role, permission, dan scope data pengguna.

## Peran Dasar

| Peran | Tujuan akses | Larangan utama |
|---|---|---|
| `super-admin` | Administrasi aplikasi dan seluruh laporan sesuai mandat | Tidak memakai akun ini untuk pekerjaan rutin; tidak mengubah SIMRS |
| `direksi` | Dashboard dan laporan lintas domain secara read-only | Tidak mengelola konfigurasi teknis tanpa izin terpisah |
| `pelayanan` | Laporan pelayanan, mutu, dan biaya yang disetujui | Tidak mengakses data finansial/pasien di luar tugas |
| `farmasi` | Stok dan analitik farmasi | Tidak memperbaiki stok melalui aplikasi ini |
| `logistik` | Inventori nonmedis dan dapur | Tidak memperbaiki transaksi sumber melalui aplikasi ini |
| `keuangan` | Kasir, hutang, piutang, ledger, dan laporan keuangan | Tidak mengubah jurnal/transaksi sumber melalui aplikasi ini |
| `auditor` | Laporan dan audit trail yang diberikan secara read-only | Tidak mengekspor atau menyebarkan data di luar ruang lingkup audit |

Role adalah baseline; akses sebenarnya diberikan melalui permission granular seperti `dashboard.view`, `service.outpatient.view`, `inventory.pharmacy.view`, `finance.statement.view`, `report.export`, dan `admin.user.manage`. Satu pengguna dapat memiliki beberapa role setelah persetujuan.

## Onboarding

### Provisioning Super Admin Pertama

Akun `super-admin` pertama dibuat melalui `database/seeders/SuperAdminSeeder.php`, bukan melalui menu aplikasi karena menu administrasi membutuhkan permission `admin.manage`.

1. Isi `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, dan `SUPER_ADMIN_PASSWORD` pada `.env` atau secret manager.
2. Pastikan database default adalah database aplikasi, bukan koneksi `simrs`.
3. Dari folder aplikasi yang berisi `artisan`, jalankan:

   ```powershell
   php artisan migrate --force
   php artisan db:seed --class=Database\Seeders\DatabaseSeeder --force
   ```

4. Login memakai email bootstrap, lalu rotasi atau hapus secret password bootstrap.

`DatabaseSeeder` menjalankan `RbacSeeder` lalu `SuperAdminSeeder`. Seeder idempotent berdasarkan email dan tidak membuat duplikasi. Jika PowerShell menampilkan `Target class [Database\\Seeders\\SuperAdminSeeder] does not exist`, gunakan satu backslash pada perintah class, lalu jalankan `composer dump-autoload` dan `php artisan optimize:clear` sebelum mencoba lagi. Detail deployment dan guardrail database ada di `RELEASE.md`.

Super-admin dapat membuka menu **Administrasi > Pengguna & Role** atau URL `/administrasi/akses` untuk:

- Membuat pengguna baru dengan password kuat dan satu atau lebih role.
- Mengaktifkan atau menonaktifkan akun.
- Mengubah assignment role pengguna.
- Membuat role baru dan memilih permission yang diberikan.
- Mengubah label dan permission role non-sistem.

Role `super-admin` merupakan role sistem. Role ini selalu memiliki seluruh permission, tidak dapat diedit melalui UI, dan hanya dapat diberikan oleh super-admin lain. Administrator tidak dapat menonaktifkan akunnya sendiri.

1. Atasan atau owner data mengajukan kebutuhan akses, role, scope unit/data, alasan bisnis, serta tanggal berakhir bila sementara.
2. Owner aplikasi menilai least privilege dan konflik segregation of duties.
3. Pengguna menyelesaikan pelatihan keamanan, privasi, interpretasi KPI, dan penggunaan ekspor.
4. Administrator membuat akun baru atau mengaktifkan akun melalui alur yang disetujui. Password reversible dari aplikasi lama tidak dipindahkan.
5. Pengguna menetapkan password unik dan MFA bila diwajibkan untuk peran sensitif.
6. Pengguna memverifikasi menu serta scope data; kelebihan akses harus segera dilaporkan.
7. Akses dicatat, memiliki owner, dan direview berkala.

Dilarang berbagi akun, password, MFA, session, file ekspor, atau perangkat login tanpa kewenangan.

## Cara Menggunakan Laporan

- Pastikan judul, definisi KPI, periode, timezone, filter, unit, dan timestamp pembaruan sebelum mengambil keputusan.
- Gunakan tanggal tampilan Indonesia; URL/request menyimpan tanggal ISO `YYYY-MM-DD` agar konsisten.
- Terapkan filter sesempit yang diperlukan. Rentang besar dapat dialihkan ke ekspor asinkron.
- Baca empty state sebagai "tidak ada data sesuai filter", bukan otomatis sebagai bukti tidak ada aktivitas.
- Bila ada peringatan freshness atau sumber data gagal, jangan menggunakan angka sebagai kondisi terkini tanpa konfirmasi.
- Gunakan drill-down untuk memahami pembentuk total; tetap perhatikan hak akses data detail.
- Chart adalah ringkasan. Gunakan tabel ekuivalen dan definisi KPI untuk verifikasi.
- Format Rupiah dan pembulatan tampilan tidak boleh digunakan menggantikan nilai sumber untuk rekonsiliasi rinci.

## Ekspor

- Ekspor hanya jika diperlukan untuk tugas resmi dan permission tersedia.
- Periksa filter, periode, klasifikasi, tujuan penerima, dan jumlah data sebelum membuat ekspor.
- Unduh hanya ke perangkat dan lokasi penyimpanan yang dikelola organisasi.
- Jangan unggah ke cloud pribadi, email pribadi, aplikasi pesan tidak disetujui, atau media lepas tanpa izin.
- Jangan menghapus proteksi atau mengubah file untuk menghindari pembatasan akses.
- Hapus file saat tujuan selesai sesuai retensi; link/artifact aplikasi juga memiliki masa kedaluwarsa.
- Laporkan salah kirim, kehilangan perangkat, link terbuka, atau akses tidak semestinya sebagai insiden keamanan.

## Tanggung Jawab Data

- Gunakan data minimum yang diperlukan untuk tugas.
- Jangan mencari pasien, pegawai, atau transaksi karena rasa ingin tahu.
- Jangan mengambil screenshot/foto yang memuat identitas pasien kecuali proses resmi mengizinkan dan penyimpanannya aman.
- Verifikasi agregat dan formula sebelum mempresentasikan angka.
- Cantumkan periode, filter, timestamp, dan versi definisi KPI saat membagikan hasil.
- Jangan mengubah angka secara manual tanpa jejak dan penjelasan.
- Koreksi data dilakukan pada sistem sumber oleh petugas berwenang; aplikasi E-Eksekutif tidak menulis ke SIMRS.
- Patuhi kebijakan rumah sakit dan regulasi Indonesia mengenai data pribadi dan rekam medis.

## Interpretasi dan Rekonsiliasi

Jika angka berbeda dari laporan lama:

1. Samakan periode, timezone, filter, scope akses, dan waktu pengambilan data.
2. Periksa definisi KPI, pengecualian, pembulatan, freshness cache, serta status data anomali.
3. Catat nama laporan, parameter, timestamp, expected dan actual tanpa menyertakan data pasien di kanal umum.
4. Ajukan rekonsiliasi kepada owner domain/support.
5. Jangan menyatakan salah satu hasil benar sebelum klasifikasi perbedaan disetujui.

Perbedaan diklasifikasikan sebagai bug baru, bug legacy, perubahan rule yang disetujui, atau kualitas data.

## Offboarding dan Perubahan Jabatan

- Atasan/HR/owner akses melaporkan mutasi, cuti panjang, kontrak berakhir, atau terminasi secepatnya.
- Administrator menonaktifkan akun, mencabut session, token, role, dan akses artifact sesuai SLA organisasi.
- File ekspor dan kepemilikan pekerjaan dialihkan atau dimusnahkan sesuai retensi.
- Perubahan unit/jabatan memicu review ulang; akses lama tidak otomatis dipertahankan.
- Review akses berkala menghapus akses tidak digunakan, berlebih, atau kedaluwarsa.

## Dukungan dan Pelaporan

Untuk masalah fungsi, berikan versi aplikasi, waktu, nama laporan, filter, hasil yang diharapkan/aktual, dan correlation ID bila tampil. Sanitasi screenshot serta log.

Untuk dugaan kebocoran, akses tanpa izin, akun diambil alih, perubahan data, malware, atau ekspor salah tujuan, gunakan kanal insiden privat organisasi secepatnya. Jangan memposting bukti sensitif di issue publik. Prosedur rinci berada di `SECURITY.md`.
