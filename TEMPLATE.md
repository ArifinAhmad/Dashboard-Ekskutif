# Template Kerja

Gunakan template berikut pada issue tracker atau pull request. Hapus bagian yang tidak relevan tanpa menghapus kontrol wajib. Jangan memasukkan secret atau data pasien nyata.

## Template Fitur/Laporan

```markdown
# [Fitur] Judul singkat

## Masalah dan Nilai
- Pengguna/persona:
- Masalah:
- Hasil yang diharapkan:
- Owner bisnis:

## Scope
- Termasuk:
- Tidak termasuk:
- Dependensi:

## Data dan KPI
- ID/versi KPI:
- Definisi/formula:
- Tabel/kolom sumber:
- Filter dan timezone:
- Pengecualian/null/tanggal nol:
- Pembulatan dan satuan:
- Freshness/TTL:
- Klasifikasi data:

## Akses dan Keamanan
- Permission:
- Scope data:
- Audit event:
- Threat/misuse case:
- Kontrol privasi:
- Dampak ekspor:

## Acceptance Criteria
- [ ] Perilaku utama
- [ ] Empty/error/loading state
- [ ] Desktop/mobile dan aksesibilitas
- [ ] Rekonsiliasi dan toleransi
- [ ] Target performa

## Test dan Bukti
- Unit:
- Feature/integration:
- Security/accessibility:
- Rekonsiliasi:
- UAT/sign-off:

## Operasi
- Metrics/log/alert:
- Deployment/migration database aplikasi:
- Rollback:
```

## Template Bug

```markdown
# [Bug] Judul singkat

## Ringkasan
- Versi/environment:
- Ditemukan pada:
- Severity/dampak:
- Modul dan permission pengguna:

## Reproduksi
1. Prasyarat dengan data sintetis/tersamarkan
2. Langkah
3. Hasil aktual
4. Hasil yang diharapkan

## Bukti Aman
- Correlation ID/timestamp:
- Screenshot/log yang telah disanitasi:
- Frekuensi:

## Analisis
- Lapisan dugaan penyebab:
- Dampak KPI/cache/ekspor:
- Dampak keamanan/privasi:

## Perbaikan
- Solusi:
- Regression test:
- Rekonsiliasi ulang:
- Risiko dan rollback:
```

Jika bug mengindikasikan akses tanpa izin, kebocoran, perubahan data, secret terekspos, atau serangan, jangan gunakan issue publik; gunakan template insiden keamanan dan jalur privat.

## Template Pull Request

```markdown
# Ringkasan
- Apa yang berubah:
- Mengapa:
- Issue/keputusan terkait:

## Batas Perubahan
- File/komponen utama:
- Di luar scope:

## Database dan Data
- [ ] Tidak ada operasi tulis/migration/DDL ke `simrs`
- [ ] Migration hanya untuk database aplikasi atau tidak ada migration
- [ ] Query memakai binding dan sorting/grouping allowlist
- [ ] Data pasien diminimalkan; fixture/log/screenshot aman
- Hasil EXPLAIN/dampak performa:

## Keamanan
- Permission dan scope:
- Audit event:
- Threat model/perubahan attack surface:
- Dependency/secret/privacy review:

## Pengujian
- [ ] Unit
- [ ] Feature/integration
- [ ] Rekonsiliasi
- [ ] Security/accessibility
- [ ] Build/formatter/static analysis
- Perintah dan hasil ringkas:

## UI
- Desktop/mobile:
- Keyboard/screen reader/contrast:
- Empty/loading/error state:

## Deploy dan Rollback
- Konfigurasi/migration/job:
- Monitoring:
- Rollback/roll-forward:

## Dokumentasi
- [ ] CHANGELOGS/DEVLOGS/TECH/MEMORY/RELEASE/SECURITY/USERS diperbarui bila relevan
```

## Template Rekonsiliasi Laporan

```markdown
# Rekonsiliasi: <laporan/KPI>

## Identitas
- ID/versi KPI:
- Owner domain:
- Reviewer:
- Environment/dataset tersamarkan:
- Tanggal pengujian:

## Parameter
- Periode/timezone:
- Filter/dimensi:
- Rule pengecualian:
- Versi aplikasi/commit:

## Baseline
- Sumber legacy/query reference aman:
- Expected total/subtotal:
- Aturan pembulatan:

## Hasil Baru
- Actual total/subtotal:
- Durasi/query plan:
- Cache aktif/nonaktif:

## Selisih
| Dimensi | Expected | Actual | Selisih | Klasifikasi | Keputusan |
|---|---:|---:|---:|---|---|
| | | | | bug baru/bug legacy/rule/kualitas data | |

## Kesimpulan
- [ ] Hitungan selisih 0
- [ ] Selisih uang <= Rp1 hanya karena pembulatan
- [ ] Exception telah disetujui
- Status/sign-off:
```

## Template Insiden Keamanan

Simpan hanya pada sistem insiden privat dengan akses terbatas.

```markdown
# Insiden Keamanan <ID privat>

## Pelaporan Awal
- Waktu ditemukan dan timezone:
- Pelapor/kontak privat:
- Incident commander:
- Severity sementara:
- Sistem/versi/environment:
- Cara deteksi:

## Dampak
- Confidentiality/integrity/availability:
- Jenis data dan perkiraan subjek terdampak:
- Akun/role/supplier terdampak:
- Status paparan/eksploitasi:
- Dampak operasional/klinis:

## Containment
- Tindakan dan waktu:
- Akses/session/key yang dicabut/dirotasi:
- Preservasi bukti dan chain of custody:
- Pembatasan komunikasi:

## Timeline
| Waktu | Kejadian | Sumber bukti | Pelaksana |
|---|---|---|---|
| | | | |

## Analisis dan Pemulihan
- Root cause awal/final:
- IOC dan scope investigasi:
- Eradication:
- Restore/validasi integritas:
- Monitoring tambahan:

## Notifikasi
- Security/privacy/legal/manajemen:
- Regulator/subjek/pihak ketiga bila diwajibkan:
- Waktu, dasar keputusan, dan approver:

## Penutupan
- Corrective/preventive actions, owner, due date:
- Kerentanan/CVE terkait:
- Lessons learned:
- Persetujuan penutupan:
```

## Template Perubahan Akses

```markdown
# Permintaan Perubahan Akses
- Pengguna/unit:
- Atasan/owner pemberi persetujuan:
- Role/permission dan scope data:
- Alasan bisnis:
- Mulai/berakhir:
- Konflik segregation of duties:
- Training/komitmen kerahasiaan selesai:
- Provisioning oleh dan waktu:
- Verifikasi pengguna:
- Tanggal review/recertification:
```

## Template Keputusan Arsitektur

```markdown
# ADR-NNN: Judul
- Status: diusulkan/diterima/diganti/ditolak
- Tanggal dan pemilik:
- Konteks:
- Keputusan:
- Alternatif:
- Konsekuensi positif/negatif:
- Dampak keamanan, privasi, operasi, dan data:
- Bukti/tautan:
```
