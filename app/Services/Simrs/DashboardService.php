<?php

namespace App\Services\Simrs;

use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(private ReadOnlySimrs $simrs) {}

    public function get(): array
    {
        return Cache::store(config('simrs.cache_store'))->remember($this->cacheKey(), config('simrs.cache_ttl'), function () {
            $registration = $this->simrs->select(<<<'SQL'
                SELECT COUNT(*) AS total,
                    SUM(kd_poli = ?) AS igd,
                    SUM(kd_poli <> ?) AS rawat_jalan,
                    SUM(stts_daftar = 'Baru') AS pasien_baru,
                    SUM(stts_daftar = 'Lama') AS pasien_lama,
                    SUM(stts = 'Batal') AS batal,
                    SUM(stts IN ('Sudah','Berkas Diterima','Dirawat')) AS terlayani,
                    SUM(status_lanjut = 'Ralan' AND status_bayar = 'Sudah Bayar') AS sudah_bayar,
                    SUM(status_lanjut = 'Ralan' AND status_bayar = 'Belum Bayar') AS belum_bayar
                FROM reg_periksa
                WHERE tgl_registrasi = CURRENT_DATE
            SQL, [config('simrs.igd_code'), config('simrs.igd_code')])->first();

            $inpatient = $this->simrs->select(<<<'SQL'
                SELECT
                    (SELECT COUNT(DISTINCT no_rawat) FROM kamar_inap WHERE tgl_masuk >= CURRENT_DATE AND tgl_masuk < CURRENT_DATE + INTERVAL 1 DAY AND stts_pulang <> 'Pindah Kamar') AS masuk,
                    (SELECT COUNT(DISTINCT no_rawat) FROM kamar_inap WHERE tgl_keluar >= CURRENT_DATE AND tgl_keluar < CURRENT_DATE + INTERVAL 1 DAY AND stts_pulang <> 'Pindah Kamar') AS keluar,
                    (SELECT COUNT(DISTINCT no_rawat) FROM kamar_inap WHERE tgl_keluar = '0000-00-00') AS aktif,
                    (SELECT COUNT(DISTINCT no_rawat) FROM kamar_inap WHERE tgl_keluar >= CURRENT_DATE AND tgl_keluar < CURRENT_DATE + INTERVAL 1 DAY AND stts_pulang = 'Meninggal') AS meninggal
            SQL)->first();

            $beds = $this->simrs->select(<<<'SQL'
                SELECT
                    COUNT(k.kd_kamar) AS total,
                    SUM(k.status = 'KOSONG') AS kosong,
                    SUM(k.status = 'ISI') AS terisi,
                    SUM(k.status = 'DIBERSIHKAN') AS dibersihkan,
                    SUM(k.status = 'DIBOOKING') AS dibooking,
                    SUM(k.status = 'PERBAIKAN') AS perbaikan
                FROM kamar k
                INNER JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
                WHERE k.statusdata = '1' AND b.status = '1'
            SQL)->first();

            $payers = $this->simrs->select(<<<'SQL'
                SELECT p.png_jawab AS label, COUNT(r.no_rawat) AS total
                FROM reg_periksa r
                INNER JOIN penjab p ON p.kd_pj = r.kd_pj
                WHERE r.tgl_registrasi = CURRENT_DATE
                GROUP BY p.kd_pj, p.png_jawab
                ORDER BY total DESC
                LIMIT 8
            SQL);

            $ageGroups = $this->simrs->select(<<<'SQL'
                SELECT CASE
                    WHEN p.tgl_lahir IS NULL OR p.tgl_lahir = '0000-00-00' OR p.tgl_lahir > CURRENT_DATE THEN 'Tidak diketahui'
                    WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, CURRENT_DATE) <= 5 THEN 'Balita (0-5)'
                    WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, CURRENT_DATE) <= 12 THEN 'Anak (6-12)'
                    WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, CURRENT_DATE) <= 17 THEN 'Remaja (13-17)'
                    WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, CURRENT_DATE) <= 59 THEN 'Dewasa (18-59)'
                    ELSE 'Lansia (60+)'
                END AS label, COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
                WHERE r.tgl_registrasi = CURRENT_DATE
                GROUP BY label
                ORDER BY FIELD(label, 'Balita (0-5)', 'Anak (6-12)', 'Remaja (13-17)', 'Dewasa (18-59)', 'Lansia (60+)', 'Tidak diketahui')
            SQL);

            $genders = $this->simrs->select(<<<'SQL'
                SELECT CASE p.jk WHEN 'L' THEN 'Laki-laki' WHEN 'P' THEN 'Perempuan' ELSE 'Tidak diketahui' END AS label,
                    COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
                WHERE r.tgl_registrasi = CURRENT_DATE
                GROUP BY p.jk ORDER BY total DESC LIMIT 10
            SQL);

            $geographies = $this->simrs->select(<<<'SQL'
                SELECT COALESCE(NULLIF(TRIM(k.nm_kab), ''), 'Tidak diketahui') AS label,
                    COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r
                INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
                LEFT JOIN kabupaten k ON k.kd_kab = p.kd_kab
                WHERE r.tgl_registrasi = CURRENT_DATE
                GROUP BY k.kd_kab, k.nm_kab
                ORDER BY total DESC LIMIT 10
            SQL);

            $districtPatients = $this->simrs->select(<<<'SQL'
                SELECT CASE
                    WHEN UPPER(TRIM(pr.nm_prop)) IN ('NUSA TENGGARA BARAT', 'NUSATENGGARA BARAT', 'NTB')
                        THEN COALESCE(NULLIF(TRIM(k.nm_kab), ''), 'NTB - Kabupaten Tidak Diketahui')
                    ELSE 'Luar Nusa Tenggara Barat'
                END AS label,
                    COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r
                INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
                LEFT JOIN kabupaten k ON k.kd_kab = p.kd_kab
                LEFT JOIN propinsi pr ON pr.kd_prop = p.kd_prop
                WHERE r.tgl_registrasi = CURRENT_DATE
                GROUP BY label
                ORDER BY total DESC LIMIT 10
            SQL);

            $educations = $this->simrs->select(<<<'SQL'
                SELECT CASE WHEN p.pnd IS NULL OR TRIM(p.pnd) IN ('', '-') THEN 'Tidak diketahui' ELSE p.pnd END AS label,
                    COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
                WHERE r.tgl_registrasi = CURRENT_DATE
                GROUP BY label ORDER BY total DESC LIMIT 10
            SQL);

            $occupations = $this->simrs->select(<<<'SQL'
                SELECT COALESCE(NULLIF(TRIM(p.pekerjaan), ''), 'Tidak diketahui') AS label,
                    COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
                WHERE r.tgl_registrasi = CURRENT_DATE
                GROUP BY COALESCE(NULLIF(TRIM(p.pekerjaan), ''), 'Tidak diketahui')
                ORDER BY total DESC LIMIT 10
            SQL);

            $referrals = $this->simrs->select(<<<'SQL'
                SELECT COALESCE(rm.perujuk, 'Datang Sendiri') AS label, COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r
                LEFT JOIN rujuk_masuk rm ON rm.no_rawat = r.no_rawat
                WHERE r.tgl_registrasi = CURRENT_DATE
                GROUP BY COALESCE(rm.perujuk, 'Datang Sendiri')
                ORDER BY total DESC LIMIT 10
            SQL);

            $compactRows = static fn ($rows) => collect($rows)->map(fn ($row) => (object) ['label' => (string) $row->label, 'total' => (int) $row->total])->values();

            return [
                'registration' => $registration,
                'inpatient' => $inpatient,
                'beds' => $beds,
                'payers' => $compactRows($payers),
                'ageGroups' => $compactRows($ageGroups),
                'genders' => $compactRows($genders),
                'geographies' => $compactRows($geographies),
                'districtPatients' => $compactRows($districtPatients),
                'educations' => $compactRows($educations),
                'occupations' => $compactRows($occupations),
                'referrals' => $compactRows($referrals),
            ];
        });
    }

    private function cacheKey(): string
    {
        return 'simrs:dashboard:v7:'.now()->toDateString();
    }
}
