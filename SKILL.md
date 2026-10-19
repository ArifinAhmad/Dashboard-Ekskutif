# Kapabilitas Tim dan Alur Kerja

Dokumen ini menjelaskan kemampuan manusia/tim yang dibutuhkan untuk membangun dan mengoperasikan E-Eksekutif. Dokumen ini bukan konfigurasi agen atau tool.

## Kapabilitas Inti

### Product dan Domain Rumah Sakit

- Memahami alur registrasi, rawat jalan, IGD, rawat inap, lab, radiologi, farmasi, logistik, kasir, dan akuntansi.
- Menetapkan definisi KPI, pengecualian, toleransi, owner, dan acceptance criteria.
- Membedakan bug legacy, kualitas data, dan perubahan rule bisnis.
- Memimpin UAT serta menyetujui hasil rekonsiliasi.

### Laravel dan PHP

- Laravel 12, PHP 8.2+, routing, middleware, Form Request, service container, policy/gate, queue, scheduler, cache, storage, notification, dan testing.
- PSR-12, type safety, exception handling, dependency management, dan profiling.
- Pemisahan controller, query object, domain service, DTO/View Model, dan job.

### SQL dan Khanza SIMRS

- MySQL/MariaDB, query agregasi, CTE/subquery, join, indeks, `EXPLAIN`, timeout, serta analisis slow query.
- Discovery variasi schema Khanza dan pemetaan tabel domain.
- Desain query read-only yang sargable serta aman terhadap input.
- Rekonsiliasi query legacy dan baru tanpa mengubah data sumber.

### Frontend dan Aksesibilitas

- Blade, Tailwind, progressive enhancement, responsive table/filter, dan visualisasi data yang tepat.
- WCAG 2.1 AA: keyboard, focus, label, contrast, semantic HTML, error feedback, dan alternatif tabel untuk chart.
- Format Indonesia untuk tanggal, angka, dan Rupiah tanpa mengubah kontrak data ISO.

### Quality Engineering

- Unit, feature, integration, contract, reconciliation, performance, accessibility, dan security testing.
- Fixture sintetis/tersamarkan, test isolation, database disposable, dan regression test.
- Analisis failure mode, boundary value, permission matrix, serta evidence-based sign-off.

### Application Security dan Privasi

- Threat modeling, OWASP ASVS/Top 10, secure session, RBAC, CSRF, XSS, injection, SSRF, file/export security, secrets, dan dependency risk.
- Data minimization, klasifikasi, retention, pseudonymization, access review, incident response, dan secure SDLC.
- Pemetaan kontrol dan bukti terhadap kebijakan organisasi tanpa mengklaim sertifikasi.

### DevOps, SRE, dan DBA

- CI/CD, immutable artifact, TLS, secret manager, PHP runtime, queue/scheduler, Redis, private storage, metrics/log/trace, backup/restore, dan rollback.
- Capacity planning, SLI/SLO, alert design, incident command, serta post-incident review.
- Least privilege database, read replica, statistik/index review, dan perubahan schema melalui change management.

## Matriks Tanggung Jawab Ringkas

| Aktivitas | Accountable | Responsible/Consulted |
|---|---|---|
| Prioritas dan acceptance | Product owner | Owner domain, tech lead, QA |
| Definisi KPI | Owner domain | Data analyst, developer, auditor |
| Arsitektur | Tech lead | Developer, DBA, security, operations |
| Query dan performa | Developer laporan | DBA, owner domain, QA |
| Keamanan dan privasi | Security/privacy owner | Semua anggota tim |
| Pengujian dan rekonsiliasi | QA lead | Developer, owner domain |
| Rilis dan rollback | Release owner | Operations, DBA, developer |
| Operasi dan insiden | Service owner | Operations, security, service desk |

## Workflow Fitur/Laporan

1. Discovery: tetapkan problem, pengguna, izin, klasifikasi data, owner KPI, sumber data, dan risiko.
2. Kontrak: dokumentasikan formula, input/filter, output, null/tanggal nol, timezone, pembulatan, freshness, dan acceptance criteria.
3. Threat model: petakan actor, asset, trust boundary, misuse case, dan kontrol.
4. Query spike: gunakan schema nonproduksi/fixture, parameter binding, dan `EXPLAIN`; jangan menulis ke SIMRS.
5. Implementasi: controller tipis, query object, domain service, DTO, policy, audit, serta UI aksesibel.
6. Test: unit, feature, integration, permission matrix, failure state, performance, dan security.
7. Rekonsiliasi: bandingkan baseline legacy dengan parameter sama dan klasifikasikan selisih.
8. Review: domain, kode, SQL/DBA, security/privacy, accessibility, dan operability.
9. UAT dan rilis: sign-off, checklist `RELEASE.md`, monitoring, serta parallel run.
10. Operasi: pantau SLO, feedback, kerentanan, perubahan schema sumber, dan akurasi KPI.

## Workflow Bug

1. Triage severity, dampak data/privasi, modul, versi, dan reproducibility.
2. Bila ada dugaan insiden keamanan, pindahkan ke prosedur privat di `SECURITY.md`.
3. Reproduksi dengan data sintetis atau tersamarkan.
4. Tentukan lapisan penyebab: UI, rule, query, kualitas data, cache, permission, queue, atau environment.
5. Tambahkan regression test sebelum/bersamaan dengan perbaikan.
6. Rekonsiliasi kembali laporan terdampak dan periksa cache/ekspor.
7. Catat dampak pengguna di changelog dan lakukan problem review untuk defect besar.

## Workflow Rekonsiliasi

- Bekukan ID/versi definisi KPI dan parameter periode.
- Simpan query/hasil baseline secara aman; jangan commit data pasien.
- Bandingkan total, subtotal, dimensi, rounding, dan daftar exception.
- Klasifikasikan setiap selisih: bug baru, bug legacy, rule disetujui, atau kualitas data.
- Minta keputusan owner domain untuk perubahan rule; jangan "memperbaiki" formula secara sepihak.
- Simpan bukti reviewer dan sign-off pada sistem artefak/tiket yang aksesnya sesuai.

## Praktik Pairing dan Review

- Perubahan query finansial/klinis harus direview developer kedua dan owner domain; query berat juga oleh DBA.
- Perubahan auth, permission, ekspor, logging, atau dependency sensitif memerlukan security review.
- Reviewer memeriksa correctness, denial-by-default, data minimization, performance, testability, dan rollback.
- Penulis tidak boleh menjadi satu-satunya approver perubahan produksi berisiko tinggi.

## Pengembangan Kompetensi

- Tabletop exercise insiden keamanan dan restore backup minimal sesuai jadwal organisasi.
- Review berkala OWASP Top 10, ASVS, perubahan Laravel/PHP, advisory dependency, dan variasi schema Khanza.
- Sesi kalibrasi KPI dengan owner domain untuk mencegah pengetahuan hanya berada pada satu orang.
- Latihan accessibility dan privacy-by-design pada setiap vertical slice.

## Kriteria Kesiapan Anggota Tim

Anggota yang mengubah laporan secara mandiri harus mampu menjelaskan sumber data, formula, permission, risiko privasi, rencana test, hasil `EXPLAIN`, rekonsiliasi, dan rollback. Akses produksi diberikan terpisah, berbatas waktu bila memungkinkan, serta berdasarkan kebutuhan tugas.
