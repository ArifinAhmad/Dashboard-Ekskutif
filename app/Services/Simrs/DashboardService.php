<?php

namespace App\Services\Simrs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(private ReadOnlySimrs $simrs) {}

    public function get(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [$from->toDateString(), $to->addDay()->toDateString()];

        return Cache::store(config('simrs.cache_store'))->remember($this->cacheKey($from, $to), config('simrs.cache_ttl'), function () use ($dates, $to) {
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
                WHERE tgl_registrasi >= ? AND tgl_registrasi < ?
            SQL, [config('simrs.igd_code'), config('simrs.igd_code'), ...$dates])->first();

            $inpatient = $this->simrs->select(<<<'SQL'
                SELECT
                    COUNT(DISTINCT CASE WHEN tgl_masuk >= ? AND tgl_masuk < ? AND stts_pulang <> 'Pindah Kamar' THEN no_rawat END) AS masuk,
                    COUNT(DISTINCT CASE WHEN tgl_keluar >= ? AND tgl_keluar < ? AND stts_pulang <> 'Pindah Kamar' THEN no_rawat END) AS keluar,
                    COUNT(DISTINCT CASE WHEN tgl_keluar = '0000-00-00' THEN no_rawat END) AS aktif,
                    COUNT(DISTINCT CASE WHEN tgl_keluar >= ? AND tgl_keluar < ? AND stts_pulang = 'Meninggal' THEN no_rawat END) AS meninggal
                FROM kamar_inap
                WHERE tgl_keluar = '0000-00-00'
                    OR (tgl_masuk >= ? AND tgl_masuk < ?)
                    OR (tgl_keluar >= ? AND tgl_keluar < ?)
            SQL, [...$dates, ...$dates, ...$dates, ...$dates, ...$dates])->first();

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
                WHERE r.tgl_registrasi >= ? AND r.tgl_registrasi < ?
                GROUP BY p.kd_pj, p.png_jawab
                ORDER BY total DESC
                LIMIT 8
            SQL, $dates);

            $ageGroups = $this->simrs->select(<<<'SQL'
                SELECT CASE
                    WHEN p.tgl_lahir IS NULL OR p.tgl_lahir = '0000-00-00' OR p.tgl_lahir > ? THEN 'Tidak diketahui'
                    WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, ?) <= 5 THEN 'Balita (0-5)'
                    WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, ?) <= 12 THEN 'Anak (6-12)'
                    WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, ?) <= 17 THEN 'Remaja (13-17)'
                    WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, ?) <= 59 THEN 'Dewasa (18-59)'
                    ELSE 'Lansia (60+)'
                END AS label, COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
                WHERE r.tgl_registrasi >= ? AND r.tgl_registrasi < ?
                GROUP BY label
                ORDER BY FIELD(label, 'Balita (0-5)', 'Anak (6-12)', 'Remaja (13-17)', 'Dewasa (18-59)', 'Lansia (60+)', 'Tidak diketahui')
            SQL, [$to->toDateString(), $to->toDateString(), $to->toDateString(), $to->toDateString(), $to->toDateString(), ...$dates]);

            $genders = $this->simrs->select(<<<'SQL'
                SELECT CASE p.jk WHEN 'L' THEN 'Laki-laki' WHEN 'P' THEN 'Perempuan' ELSE 'Tidak diketahui' END AS label,
                    COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
                WHERE r.tgl_registrasi >= ? AND r.tgl_registrasi < ?
                GROUP BY p.jk ORDER BY total DESC LIMIT 10
            SQL, $dates);

            $geographies = $this->simrs->select(<<<'SQL'
                SELECT COALESCE(NULLIF(TRIM(k.nm_kab), ''), 'Tidak diketahui') AS label,
                    COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r
                INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
                LEFT JOIN kabupaten k ON k.kd_kab = p.kd_kab
                WHERE r.tgl_registrasi >= ? AND r.tgl_registrasi < ?
                GROUP BY k.kd_kab, k.nm_kab
                ORDER BY total DESC LIMIT 10
            SQL, $dates);

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
                WHERE r.tgl_registrasi >= ? AND r.tgl_registrasi < ?
                GROUP BY label
                ORDER BY total DESC LIMIT 10
            SQL, $dates);

            $educations = $this->simrs->select(<<<'SQL'
                SELECT CASE WHEN p.pnd IS NULL OR TRIM(p.pnd) IN ('', '-') THEN 'Tidak diketahui' ELSE p.pnd END AS label,
                    COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
                WHERE r.tgl_registrasi >= ? AND r.tgl_registrasi < ?
                GROUP BY label ORDER BY total DESC LIMIT 10
            SQL, $dates);

            $occupations = $this->simrs->select(<<<'SQL'
                SELECT COALESCE(NULLIF(TRIM(p.pekerjaan), ''), 'Tidak diketahui') AS label,
                    COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
                WHERE r.tgl_registrasi >= ? AND r.tgl_registrasi < ?
                GROUP BY COALESCE(NULLIF(TRIM(p.pekerjaan), ''), 'Tidak diketahui')
                ORDER BY total DESC LIMIT 10
            SQL, $dates);

            $referrals = $this->simrs->select(<<<'SQL'
                SELECT COALESCE(rm.perujuk, 'Datang Sendiri') AS label, COUNT(DISTINCT r.no_rkm_medis) AS total
                FROM reg_periksa r
                LEFT JOIN rujuk_masuk rm ON rm.no_rawat = r.no_rawat
                WHERE r.tgl_registrasi >= ? AND r.tgl_registrasi < ?
                GROUP BY COALESCE(rm.perujuk, 'Datang Sendiri')
                ORDER BY total DESC LIMIT 10
            SQL, $dates);

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

    private function cacheKey(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return 'simrs:dashboard:v8:'.$from->toDateString().':'.$to->toDateString();
    }
}
