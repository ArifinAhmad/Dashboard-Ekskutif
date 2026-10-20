# Keamanan, Privasi, dan Kepatuhan

## Pernyataan Ruang Lingkup

Dokumen ini adalah pedoman implementasi dan operasi keamanan E-Eksekutif. Pemetaan di bawah membantu organisasi menyiapkan kontrol dan bukti yang relevan terhadap tema ISO/IEC 27001:2022, panduan implementasi ISO/IEC 27002:2022, dan OWASP Top 10 2021.

Dokumen, aplikasi, checklist, dan pemetaan ini **tidak dengan sendirinya menyatakan atau memberikan sertifikasi kepatuhan**. Kepatuhan bergantung pada ruang lingkup ISMS organisasi, penilaian risiko, Statement of Applicability, kebijakan, proses manusia, kontrak, konfigurasi infrastruktur, efektivitas operasional, audit independen, dan tindakan perbaikan. Rujuk standar resmi berlisensi serta penasihat hukum/auditor yang berwenang.

## Prinsip Wajib

- Database aplikasi bersifat read-write untuk state internal; database Khanza SIMRS hanya read-only.
- Akun `simrs` memiliki `SELECT` minimum dan tidak boleh menjalankan migration atau operasi tulis/DDL.
- Deny by default, least privilege, need-to-know, segregation of duties, dan review akses berkala.
- Security dan privacy by design sejak discovery sampai dekomisioning.
- Data pasien diminimalkan pada layar, log, ekspor, test, dan dukungan.
- Secret berada di environment/secret manager, tidak di repository, artifact, tiket, atau log.
- Kredensial bootstrap super admin wajib berasal dari secret manager/environment, memakai password kuat, hanya dijalankan pada database aplikasi, dan dirotasi/dihapus setelah provisioning.
- Seluruh trafik produksi melalui HTTPS; kontrol session dan header keamanan diverifikasi.
- Log aktivitas dan error hanya menyimpan metadata yang diperlukan; password, token, secret, payload request, SQL mentah, dan data pasien tidak boleh dicatat. Tetapkan retensi serta purging `audit_logs` sesuai kebijakan rumah sakit agar volume terkendali.
- Kerentanan kritis/tinggi terbuka memblokir rilis kecuali risk acceptance formal sesuai kebijakan.

## Klasifikasi dan Aset

Klasifikasi final mengikuti kebijakan rumah sakit. Baseline aplikasi:

| Kelas | Contoh | Kontrol minimum |
|---|---|---|
| Publik | Materi publik yang disetujui | Integritas publikasi |
| Internal | Dokumentasi teknis nonrahasia, agregat nonsensitif | Akses staf, change control |
| Rahasia | Konfigurasi, audit, data finansial rinci, metadata keamanan | RBAC, enkripsi, logging, retensi |
| Sangat rahasia/terbatas | Identitas/rekam medis, credential, key, data insiden | Need-to-know, enkripsi, akses/audit ketat, kanal privat |

Inventaris aset harus mencakup source code, database aplikasi, koneksi SIMRS, data pasien, akun, secret, log, audit trail, cache, queue, backup, artifact ekspor, CI/CD, supplier/cloud, endpoint pengguna, dan dokumentasi sensitif. Setiap aset memiliki owner, klasifikasi, lokasi, retensi, backup, serta disposal method.

## Tanggung Jawab

| Peran | Tanggung jawab keamanan utama |
|---|---|
| Manajemen/service owner | Menetapkan risk appetite, sumber daya, owner, dan menerima risiko residual |
| Security owner/CISO | Kebijakan, threat model, monitoring, vulnerability management, incident response, assurance |
| Privacy/DPO/legal | Dasar pemrosesan, hak subjek, retensi, notifikasi, transfer/pihak ketiga |
| Product dan owner domain | Data minimization, definisi KPI, klasifikasi, permission, UAT |
| Tech lead/developer | Secure design/coding, test, dependency, remediation, dokumentasi |
| DBA | Least privilege, read-only SIMRS, query/index review, backup database terkait |
| Operations/SRE | Hardening, secret, deployment, logging, alert, backup/restore, availability |
| QA | Abuse case, authorization, reconciliation, security/performance regression |
| Administrator | Provisioning/deprovisioning berdasarkan approval; review dan bukti akses |
| Pengguna | Menjaga akun, memakai data sesuai tujuan, melaporkan anomali/insiden |
| Internal audit | Assurance independen atas desain dan efektivitas kontrol |
| Supplier | Memenuhi kontrol kontraktual, notifikasi insiden, bukti assurance, exit support |

## Pemetaan ISO/IEC 27001:2022 dan ISO/IEC 27002:2022

Pemetaan ini berorientasi tema kontrol Annex A ISO/IEC 27001:2022 dan tema panduan implementasi ISO/IEC 27002:2022. Nomor kontrol dipakai sebagai referensi tematik, bukan reproduksi teks standar.

### Kontrol Organisasional

| Tema/kontrol rujukan | Implementasi konkret E-Eksekutif | Penanggung jawab | Bukti minimum |
|---|---|---|---|
| Kebijakan, peran, segregasi tugas (5.1-5.4) | Kebijakan secure SDLC, owner sistem/data/KPI, approval terpisah untuk akses dan deploy produksi | Manajemen, security, service owner | Kebijakan disetujui, RACI, approval PR/rilis |
| Threat intelligence (5.7) | Pantau advisory Laravel/PHP, Composer/NPM, OS, database, supplier, dan pola serangan sektor kesehatan | Security, operations | Feed/advisory, tiket triage, SLA remediasi |
| Inventaris dan acceptable use (5.9-5.11) | Inventaris aplikasi, DB, secret, ekspor, backup, endpoint; aturan penggunaan dan pengembalian aset | Asset owner, IT | Register aset, acknowledgement, offboarding record |
| Klasifikasi dan transfer informasi (5.12-5.14) | Label data pasien/keuangan/audit; ekspor privat ber-expiry; kanal transfer disetujui | Data owner, privacy, users | Skema klasifikasi, DLP/access log, approval transfer |
| Identity dan access control (5.15-5.18) | Akun unik, RBAC granular, MFA untuk peran sensitif, JML lifecycle, review akses, session revocation | IAM/admin, owner akses | Matriks izin, tiket provisioning, recertification, audit event |
| Supplier/cloud (5.19-5.23) | Due diligence, DPA/SLA, lokasi data, subprocessor, incident notice, vulnerability/BCP evidence, exit dan secure deletion | Procurement, legal, security | Risk assessment, kontrak, SOC/ISO evidence, exit test |
| Incident management (5.24-5.28) | Playbook triage-containment-eradication-recovery, kontak privat, severity, preservasi bukti, lessons learned | Incident commander, security | Incident ticket, timeline, chain of custody, PIR/CAPA |
| Continuity dan ICT readiness (5.29-5.30) | SLO, fail-safe ketika SIMRS gagal, backup/restore, queue recovery, DR dan komunikasi | Service owner, operations | BIA, DR plan, exercise, restore result, RTO/RPO evidence |
| Legal, privacy, records, independent review (5.31-5.36) | Register kewajiban, privacy assessment, retensi, legal hold, audit internal, compliance review | Legal/privacy, audit | Register hukum, DPIA/PIA, retention schedule, audit report |
| Operating procedures (5.37) | Runbook deploy/rollback, akses, backup, insiden, vulnerability, ekspor, dan dekomisioning | Operations, security | Runbook berversi, exercise dan change record |

### Kontrol Manusia

| Tema/kontrol rujukan | Implementasi konkret | Penanggung jawab | Bukti |
|---|---|---|---|
| Screening dan terms (6.1-6.2) | Pemeriksaan sesuai hukum/risiko; kewajiban kerahasiaan dan penggunaan data | HR, legal | Record terbatas, kontrak/NDA |
| Awareness dan training (6.3) | Pelatihan phishing, data pasien, secure coding, ekspor, insiden, serta KPI | HR, security, manager | Materi, kehadiran, assessment |
| Disciplinary process (6.4) | Pelanggaran akses/data ditangani konsisten sesuai kebijakan | HR, legal | Kebijakan dan case record terbatas |
| Setelah mutasi/terminasi (6.5) | Cabut akun/session/token, review file ekspor, transfer ownership | HR, IAM, manager | Offboarding checklist, timestamp revocation |
| Confidentiality (6.6) | NDA dan kewajiban kerahasiaan untuk staf/supplier | Legal, procurement | Perjanjian aktif |
| Remote working (6.7) | Perangkat terkelola, VPN/zero trust, screen/privacy, larangan storage pribadi | IT, security | Baseline perangkat, access log |
| Event reporting (6.8) | Kanal privat mudah diakses; pengguna wajib segera melapor tanpa menginvestigasi sendiri | Security, service desk | Laporan, acknowledgement, SLA triage |

### Kontrol Fisik

| Tema/kontrol rujukan | Implementasi konkret | Penanggung jawab | Bukti |
|---|---|---|---|
| Perimeter dan akses fisik (7.1-7.4) | Hosting dan ruang admin dibatasi; visitor/access monitoring | Facilities, provider | Access record, review provider |
| Ancaman lingkungan dan fasilitas (7.5, 7.11-7.13) | Proteksi kebakaran/listrik/suhu, redundancy, maintenance | Facilities, provider | Test UPS/DR, maintenance record |
| Area aman dan clean desk/screen (7.6-7.7) | Layar laporan sensitif tidak terlihat publik; auto-lock; dokumen cetak dikendalikan | Manager, users | Kebijakan, inspection/training |
| Perangkat dan media (7.8-7.10, 7.14) | Full-disk encryption, inventory, secure disposal, larangan media tak disetujui | IT asset owner | MDM report, disposal certificate |

### Kontrol Teknologi

| Tema/kontrol rujukan | Implementasi konkret | Penanggung jawab | Bukti |
|---|---|---|---|
| Endpoint dan privileged access (8.1-8.2) | Perangkat terkelola; admin terpisah; privilege just-in-time bila ada | IT, IAM | MDM, PAM/access log |
| Information access restriction (8.3) | Policy/Gate di server; scope query; deny by default; ekspor dan drill-down diotorisasi | Developer, owner data | Permission test, code review, audit log |
| Source code access (8.4) | Repo privat, SSO/MFA, branch protection, least privilege, review akses | Engineering lead | Repo settings dan access review |
| Authentication (8.5) | Hash adaptif, rate limit, MFA sensitif, session rotation/revocation, cookie aman | Developer, IAM | Feature/security test, config evidence |
| Capacity dan malware (8.6-8.7) | Capacity metric/alert; scanning endpoint, artifact, upload bila fitur ada | Operations, security | Dashboard, alert, scan report |
| Vulnerability/configuration (8.8-8.9) | Inventaris versi, hardened baseline, SAST/DAST/SCA, patch SLA, exception ber-expiry | Security, engineering, ops | SBOM/lockfile, scan, ticket, baseline drift report |
| Secure deletion dan masking/DLP (8.10-8.12) | Expiry ekspor, lifecycle delete, data tersamarkan untuk nonprod, minimisasi download | Privacy, operations | Deletion job log, masked dataset evidence |
| Backup dan redundancy (8.13-8.14) | Backup terenkripsi database aplikasi/config/artifact penting; restore test; redundancy berbasis BIA | Operations, DBA | Backup report, restore evidence, RPO/RTO test |
| Logging dan monitoring (8.15-8.16) | Log terstruktur, audit append-oriented, SIEM/alert, clock sinkron, akses log dibatasi | Operations, security | Dashboard, sample event, alert exercise, access review |
| Clock synchronization (8.17) | NTP tepercaya dan timezone bisnis konsisten untuk bukti/timeline | Operations | NTP status, timestamp test |
| Utility software dan software install (8.18-8.19) | Batasi tool produksi; dependency hanya lewat pipeline/approval | Operations, engineering | Allowlist, pipeline log |
| Network dan web filtering (8.20-8.23) | Segmentasi web/app/db, firewall egress/ingress, TLS, admin path terbatas | Network/security | Diagram, firewall review, TLS scan |
| Cryptography dan key management (8.24) | TLS, enkripsi storage/backup sesuai risiko, key di secret/KMS, rotasi/revocation | Security, operations | Key inventory, rotation and TLS evidence |
| Secure SDLC dan architecture (8.25-8.27) | Security requirement, threat model, trust boundary dua DB, ADR, review abuse case | Tech lead, security | PRD/story, threat model, ADR, review record |
| Secure coding dan testing (8.28-8.29) | Binding SQL, escaping, validation, authorization; unit/feature/security test sebelum rilis | Developer, QA | PR checklist, CI result, penetration report |
| Outsourced development (8.30) | Kontrak secure coding, repo/data access, review dan IP/security obligations | Procurement, engineering | Contract, supplier assessment, code review |
| Environment separation (8.31) | Dev/test/staging/prod terpisah; data nonprod sintetis/tersamarkan; secret terpisah | Operations | Architecture/config inventory, access matrix |
| Change management (8.32) | PR review, test, approval, release/rollback, emergency change review | Release owner | Change ticket, CI, release record |
| Test information (8.33) | Tidak memakai data pasien nyata tanpa dasar, approval, minimisasi, dan proteksi setara | QA, privacy | Dataset record, masking validation |
| Audit testing protection (8.34) | Penetration/audit test disetujui, scope/rate aman, data hasil dibatasi | Security, DBA/ops | Rules of engagement, test report |

## Pemetaan OWASP Top 10 2021

| Risiko | Kontrol aplikasi konkret | Test dan bukti | Owner |
|---|---|---|---|
| A01 Broken Access Control | Deny by default; middleware+Policy/Gate; permission granular; query scope; object ownership; otorisasi ulang unduh ekspor; admin terpisah | Permission matrix, negative feature test, IDOR test, audit akses | Developer, QA, owner akses |
| A02 Cryptographic Failures | HTTPS/HSTS; secure cookie; secret manager; enkripsi backup/storage sesuai risiko; hashing password adaptif; minimisasi data | TLS scan, config test, key rotation evidence, data flow review | Operations, security |
| A03 Injection | Query binding; allowlist kolom sort/group; validasi; Blade escaping; tanpa shell command dari input; netralisasi formula CSV/XLSX | SAST/DAST, injection test, code review, export test | Developer, QA |
| A04 Insecure Design | Threat model, misuse case, trust boundary, read-only SIMRS, least privilege, rate/capacity limit, privacy by design | Threat model, ADR, abuse-case tests, design approval | Tech lead, security, product |
| A05 Security Misconfiguration | Hardened production, debug off, security headers, private storage, CORS minimum, directory listing off, environment separation | Configuration scan, header test, baseline/drift review | Operations |
| A06 Vulnerable and Outdated Components | Lockfile, dependency/SBOM inventory, Composer/NPM audit, advisory monitoring, patch SLA, removal unused packages | CI scan, remediation ticket, exception expiry | Engineering, security |
| A07 Identification and Authentication Failures | Akun unik, no legacy reversible password migration, rate limit/lockout, MFA sensitif, session rotation/revocation, secure reset | Feature test, brute-force/session test, access review | Developer, IAM |
| A08 Software and Data Integrity Failures | Protected branch, reviewed CI/CD, artifact checksum/provenance, trusted package source, signed/verified update bila tersedia, queue payload trust boundary | Pipeline config, approval, artifact record, integrity test | Engineering, operations |
| A09 Security Logging and Monitoring Failures | Audit login/logout/access changes/sensitive export; structured security event; correlation ID; alert and incident linkage; clock sync | Log coverage test, alert exercise, retention/access review | Security, operations |
| A10 Server-Side Request Forgery | Tidak menerima URL arbitrer; allowlist scheme/host; DNS/IP validation; egress firewall; block metadata/private network; timeout/size limit | SSRF test, firewall evidence, code review | Developer, network/security |

## Secure SDLC

### Perencanaan dan Desain

- Setiap feature memiliki security/privacy requirement, klasifikasi data, permission, audit, dan abuse case.
- Threat model diperbarui untuk boundary baru, integrasi, upload/download, atau perubahan auth.
- Dependency baru dinilai maintenance, lisensi, advisory, transitive risk, dan kebutuhan aksesnya.
- Desain query mempertimbangkan beban database operasional sebagai risiko availability.

### Implementasi

- Gunakan komponen Laravel standar untuk hashing, CSRF, validation, authorization, encryption, rate limiting, dan storage.
- Larang dynamic SQL dari input; identifier menggunakan allowlist.
- Jangan menggunakan `{!! !!}` pada data tak tepercaya.
- Error publik generik; detail hanya pada log terlindungi dan sudah disanitasi.
- Jangan mencatat secret, token, cookie, password, payload medis penuh, atau SQL dengan nilai sensitif.
- Code review minimal satu reviewer; area berisiko tinggi memerlukan security/DBA/domain review.

### Verifikasi dan Rilis

- CI menjalankan unit/feature/integration, formatter, static analysis, SAST/SCA, secret scan, serta build.
- DAST/penetration test dilakukan sesuai risiko dan sebelum milestone utama.
- Test eksplisit mencakup IDOR, privilege escalation, injection, XSS, CSRF, session, rate limit, formula injection, file access, dan error leakage.
- Security acceptance pada `RELEASE.md` harus lulus; exception didokumentasikan dengan owner dan expiry.

### Operasi dan Dekomisioning

- Pantau advisory, log, alert, privilege, supplier, kapasitas, dan schema sumber.
- Patch mengikuti SLA severity; emergency change tetap mendapat review sesudahnya.
- Saat dekomisioning, cabut DNS/credential/integrasi, hapus data sesuai retensi, arsipkan bukti, dan verifikasi supplier deletion.

## Akses dan Identitas

- Provisioning memerlukan approval atasan/owner data; admin tidak mengesahkan permintaannya sendiri.
- Role/permission didesain berdasarkan tugas, tidak berdasarkan kenyamanan.
- Akses sensitif memiliki expiry atau review lebih sering; akun dormant dinonaktifkan.
- Terminasi/mutasi memicu pencabutan akun, session, token, dan akses artifact.
- Service account tidak dipakai interaktif, memiliki owner, scope minimum, rotasi, dan monitoring.
- Break-glass account disegel secara prosedural, dipantau, diuji, dan direview setiap penggunaan.
- Autentikasi/otorisasi dilakukan server-side untuk setiap request, termasuk endpoint ekspor dan job result.

## Privasi dan Perlindungan Data

- Tetapkan tujuan, dasar pemrosesan, owner, kategori subjek/data, penerima, lokasi, retensi, dan hak yang berlaku melalui proses privasi organisasi.
- Lakukan PIA/DPIA ketika risiko tinggi, cakupan data pasien meluas, monitoring baru, supplier/cloud baru, atau ekspor massal diperkenalkan.
- Tampilkan identitas pasien hanya ketika perlu; agregasi/pseudonimisasi lebih disukai.
- Environment nonproduksi menggunakan data sintetis atau tersamarkan dan diuji terhadap kemungkinan re-identifikasi.
- Ekspor bersifat privat, ber-expiry, permission-bound, diaudit, dan tidak memakai URL publik permanen.
- Log/audit menyimpan identitas minimum untuk accountability; akses dan retensinya dibatasi.
- Permintaan hak subjek, legal hold, koreksi sumber, dan penghapusan ditangani melalui prosedur organisasi serta mempertimbangkan kewajiban rekam medis/audit.
- Insiden privasi dinilai bersama privacy/legal untuk menentukan notifikasi regulator/subjek berdasarkan hukum Indonesia dan kontrak yang berlaku.

## Logging, Monitoring, dan Audit

Catat event keamanan yang bermakna: login berhasil/gagal, logout, lockout, reset/MFA, perubahan akun/role/permission/setting, penolakan akses penting, akses/ekspor data sensitif, perubahan konfigurasi, kegagalan job, dan tindakan administrator.

Field minimum: timestamp tersinkron, environment/service, event type, outcome, actor ID, target/scope, correlation ID, source network yang diperlakukan sesuai privasi, dan alasan/error code aman. Jangan mencatat credential, token, session cookie, data medis penuh, atau response body sensitif.

- Audit trail bersifat append-oriented, akses terbatas, integrity-protected, dan memiliki retensi formal.
- Alert memiliki severity, threshold, owner, runbook, dan jalur eskalasi.
- Deteksi mencakup brute force, privilege change, ekspor abnormal, akses massal, error/latency spike, failed job, query lambat, dan storage/backup failure.
- Lakukan exercise untuk membuktikan alert sampai ke petugas dan menghasilkan respons, bukan hanya tersimpan.

## Vulnerability Management

1. Inventaris komponen, versi, asset owner, exposure, dan dukungan vendor.
2. Deteksi melalui advisory, SAST, SCA, secret scan, DAST, pentest, konfigurasi, dan laporan pihak ketiga.
3. Triage berdasarkan exploitability, exposure, data, privilege, dampak keselamatan/operasional, dan bukti eksploitasi.
4. Remediasi dan regression test sesuai SLA organisasi; kritis/tinggi sebelum rilis.
5. Risk acceptance hanya oleh pejabat berwenang, dengan mitigasi, owner, alasan, dan expiry.
6. Verifikasi penutupan melalui rescan/test; simpan bukti.
7. Komunikasikan advisory pelanggan/supplier secara terkontrol jika relevan.

Jangan melakukan scanning agresif atau penetration test terhadap produksi/SIMRS tanpa rules of engagement, approval, window, rate limit, dan pemantauan DBA/operations.

## Backup, Restore, dan Ketahanan

- Tetapkan RPO/RTO dari BIA dan klasifikasi data, bukan asumsi teknis.
- Backup database aplikasi, konfigurasi yang dapat dipulihkan, audit penting, serta artifact yang diwajibkan; jangan mengandalkan backup database SIMRS yang berada di luar ownership aplikasi.
- Backup dienkripsi, aksesnya least privilege, dipisahkan secara logis/fisik sesuai risiko, dipantau, dan memiliki lifecycle/retensi.
- Test restore berkala harus membuktikan integritas, waktu pemulihan, dependency, key availability, serta langkah rekonsiliasi.
- Queue/job harus idempotent atau memiliki strategi deduplikasi; kegagalan SIMRS menghasilkan fail-safe UI dan alert.
- DR/tabletop mencakup kehilangan database aplikasi, cache/queue, storage ekspor, credential compromise, supplier outage, dan koneksi SIMRS gagal.

## Risiko Supplier dan Cloud

Sebelum onboarding dan secara berkala, nilai security posture, data location, subprocessor, tenancy/isolation, IAM, encryption/key ownership, log availability, backup/DR, vulnerability disclosure, incident notification, SLA, right to audit, secure development, deletion/return data, portability, dan exit plan.

- Kontrak menetapkan tujuan pemrosesan, kerahasiaan, kontrol minimum, waktu notifikasi insiden, bantuan investigasi, bukti assurance, serta penghapusan aman.
- Akses supplier bersifat bernama, minimum, MFA, time-bound, logged, dan dicabut setelah pekerjaan selesai.
- Outage atau compromise supplier masuk threat model, monitoring, BCP, dan incident response.
- Sertifikat supplier tidak menggantikan penilaian risiko konfigurasi dan penggunaan aktual oleh rumah sakit.

## Pelaporan dan Respons Insiden

### Cara Melapor

Laporkan segera melalui kanal privat security/service desk yang ditetapkan organisasi. Jika kanal utama dicurigai terdampak, gunakan kanal alternatif yang disetujui. Jangan membuat issue publik atau menempelkan data pasien, credential, log mentah, atau exploit.

Sertakan seminimal mungkin: waktu dan timezone, sistem/versi, apa yang diamati, langkah aman yang telah dilakukan, correlation ID, serta kontak pelapor. Jangan menyelidiki dengan memperluas akses atau mengubah/menghapus bukti.

### Alur Respons

1. Triage dan severity: validasi laporan, tunjuk incident commander, batasi akses informasi.
2. Containment: blokir akses/route, cabut session/token, rotasi secret, isolasi komponen, hentikan ekspor bila perlu.
3. Preservation: jaga log, snapshot, timestamp, hash, dan chain of custody sesuai hukum/kebijakan.
4. Investigation: tentukan initial access, scope, data/akun terdampak, persistence, supplier, dan timeline.
5. Eradication dan recovery: patch, pulihkan dari sumber tepercaya, validasi integritas, rekonsiliasi data, serta tingkatkan monitoring.
6. Notification: security, privacy, legal, manajemen, regulator, subjek data, dan pihak ketiga sesuai kewajiban serta keputusan terdokumentasi.
7. Post-incident: root cause, lessons learned, corrective/preventive action, owner/due date, dan pengujian efektivitas.

Prioritas pertama adalah keselamatan, containment, pemeliharaan bukti, dan komunikasi yang benar. Jangan mengubah database SIMRS untuk "memperbaiki" insiden aplikasi.

## Bukti dan Assurance

Bukti minimum dikelola dengan akses dan retensi yang sesuai: risk register, Statement of Applicability organisasi, kebijakan, inventaris, data flow/threat model, matriks izin, access review, training, PR/CI/test, SBOM/scan, pentest, config baseline, key rotation, audit/log alert exercise, backup/restore, supplier review, incident/PIR, release approval, dan internal audit.

Keberadaan dokumen atau screenshot bukan bukti efektivitas tunggal. Gunakan kombinasi desain kontrol, konfigurasi, sample operasional, pengujian, metrik, exception, dan review independen.

## Review Dokumen

Review sekurang-kurangnya setiap tahun dan setelah insiden material, perubahan regulasi/standar, arsitektur, supplier/cloud, klasifikasi data, autentikasi, atau ekspor. Setiap perubahan memiliki owner, approval, tanggal berlaku, komunikasi, dan bukti implementasi.
