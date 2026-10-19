<?php

namespace App\Services\Simrs;

use Carbon\CarbonImmutable;

class QualityReport
{
    private const REQUEST_TABLES = [
        'lab-pa' => 'permintaan_labpa',
        'lab-pk' => 'permintaan_lab',
        'lab-mb' => 'permintaan_labmb',
        'radiologi' => 'permintaan_radiologi',
    ];

    public function __construct(private ReadOnlySimrs $simrs) {}

    public function get(string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($report) {
            'pelayanan-poli' => $this->clinicDuration($from, $to),
            'pelayanan-ralan' => $this->outpatientDuration($from, $to),
            'pelayanan-apotek' => $this->pharmacyDuration($from, $to),
            'lab-pa', 'lab-pk', 'lab-mb', 'radiologi' => $this->requestDuration($report, $from, $to),
            'perkiraan-biaya-ranap' => $this->inpatientCost(),
            default => ['summary' => [], 'sections' => []],
        };
    }

    private function clinicDuration(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $source = 'SELECT r.no_rawat, d.nm_dokter AS dokter, p.nm_poli AS poli, TIMESTAMPDIFF(SECOND, TIMESTAMP(r.tgl_registrasi, r.jam_reg), MIN(TIMESTAMP(pr.tgl_perawatan, pr.jam_rawat))) / 60.0 AS durasi FROM reg_periksa r INNER JOIN dokter d ON d.kd_dokter = r.kd_dokter INNER JOIN poliklinik p ON p.kd_poli = r.kd_poli INNER JOIN pemeriksaan_ralan pr ON pr.no_rawat = r.no_rawat WHERE r.tgl_registrasi >= ? AND r.tgl_registrasi < ? GROUP BY r.no_rawat, d.nm_dokter, p.nm_poli, r.tgl_registrasi, r.jam_reg';

        return $this->durationReport($source, [$from->toDateString(), $to->addDay()->toDateString()], true);
    }

    private function outpatientDuration(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $source = 'SELECT r.no_rawat, d.nm_dokter AS dokter, p.nm_poli AS poli, TIMESTAMPDIFF(SECOND, TIMESTAMP(r.tgl_registrasi, r.jam_reg), TIMESTAMP(n.tanggal, n.jam)) / 60.0 AS durasi FROM reg_periksa r INNER JOIN dokter d ON d.kd_dokter = r.kd_dokter INNER JOIN poliklinik p ON p.kd_poli = r.kd_poli INNER JOIN nota_jalan n ON n.no_rawat = r.no_rawat WHERE r.tgl_registrasi >= ? AND r.tgl_registrasi < ?';

        return $this->durationReport($source, [$from->toDateString(), $to->addDay()->toDateString()], true);
    }

    private function pharmacyDuration(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->simrs->select(<<<'SQL'
            SELECT
                TIMESTAMPDIFF(SECOND, TIMESTAMP(tgl_peresepan, jam_peresepan), TIMESTAMP(tgl_perawatan, jam)) / 60.0 AS validasi,
                TIMESTAMPDIFF(SECOND, TIMESTAMP(tgl_perawatan, jam), TIMESTAMP(tgl_penyerahan, jam_penyerahan)) / 60.0 AS penyerahan,
                TIMESTAMPDIFF(SECOND, TIMESTAMP(tgl_peresepan, jam_peresepan), TIMESTAMP(tgl_penyerahan, jam_penyerahan)) / 60.0 AS pelayanan
            FROM resep_obat
            WHERE tgl_peresepan >= ? AND tgl_peresepan < ?
                AND tgl_peresepan <> '0000-00-00' AND tgl_perawatan <> '0000-00-00' AND tgl_penyerahan <> '0000-00-00'
        SQL, [$from->toDateString(), $to->addDay()->toDateString()]);

        return $this->multiDurationReport($rows, ['validasi' => 'Peresepan - Validasi', 'penyerahan' => 'Validasi - Penyerahan', 'pelayanan' => 'Peresepan - Penyerahan'], false);
    }

    private function requestDuration(string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $table = self::REQUEST_TABLES[$report];
        $rows = $this->simrs->select("SELECT TIMESTAMPDIFF(SECOND, TIMESTAMP(tgl_permintaan, jam_permintaan), TIMESTAMP(tgl_sampel, jam_sampel)) / 60.0 AS permintaan_sampel, TIMESTAMPDIFF(SECOND, TIMESTAMP(tgl_sampel, jam_sampel), TIMESTAMP(tgl_hasil, jam_hasil)) / 60.0 AS sampel_hasil, TIMESTAMPDIFF(SECOND, TIMESTAMP(tgl_permintaan, jam_permintaan), TIMESTAMP(tgl_hasil, jam_hasil)) / 60.0 AS permintaan_hasil FROM {$table} WHERE tgl_permintaan >= ? AND tgl_permintaan < ? AND tgl_sampel <> '0000-00-00' AND tgl_hasil <> '0000-00-00'", [$from->toDateString(), $to->addDay()->toDateString()]);

        return $this->multiDurationReport($rows, ['permintaan_sampel' => 'Permintaan - Sampel', 'sampel_hasil' => 'Sampel - Hasil', 'permintaan_hasil' => 'Permintaan - Hasil'], true);
    }

    private function durationReport(string $source, array $bindings, bool $dimensions): array
    {
        $overall = $this->simrs->select("SELECT 'Keseluruhan' AS label, AVG(durasi) AS rata_rata, SUM(durasi <= 15) AS sampai_15, SUM(durasi > 15 AND durasi <= 30) AS menit_16_30, SUM(durasi > 30 AND durasi <= 60) AS menit_31_60, SUM(durasi > 60) AS lebih_60, SUM(durasi < 0) AS data_tidak_valid FROM ({$source}) x", $bindings);
        $sections = [$this->durationSection('Ringkasan durasi', $overall)];
        if ($dimensions) {
            $byDoctor = $this->simrs->select("SELECT dokter AS label, AVG(durasi) AS rata_rata, SUM(durasi <= 15) AS sampai_15, SUM(durasi > 15 AND durasi <= 30) AS menit_16_30, SUM(durasi > 30 AND durasi <= 60) AS menit_31_60, SUM(durasi > 60) AS lebih_60, SUM(durasi < 0) AS data_tidak_valid FROM ({$source}) x GROUP BY dokter ORDER BY rata_rata DESC", $bindings);
            $byClinic = $this->simrs->select("SELECT poli AS label, AVG(durasi) AS rata_rata, SUM(durasi <= 15) AS sampai_15, SUM(durasi > 15 AND durasi <= 30) AS menit_16_30, SUM(durasi > 30 AND durasi <= 60) AS menit_31_60, SUM(durasi > 60) AS lebih_60, SUM(durasi < 0) AS data_tidak_valid FROM ({$source}) x GROUP BY poli ORDER BY rata_rata DESC", $bindings);
            $sections[] = $this->durationSection('Durasi per dokter', $byDoctor);
            $sections[] = $this->durationSection('Durasi per poliklinik', $byClinic);
        }

        return ['summary' => ['Jumlah layanan' => (int) collect($overall)->first()?->sampai_15 + (int) collect($overall)->first()?->menit_16_30 + (int) collect($overall)->first()?->menit_31_60 + (int) collect($overall)->first()?->lebih_60], 'sections' => $sections];
    }

    private function multiDurationReport($rows, array $series, bool $extended): array
    {
        $summaryRows = collect($series)->map(function ($label, $key) use ($rows, $extended) {
            $values = collect($rows)->pluck($key)->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value);

            return (object) [
                'label' => $label,
                'rata_rata' => $values->avg() ?? 0,
                'sampai_15' => $values->filter(fn ($value) => $value >= 0 && $value <= 15)->count(),
                'menit_16_30' => $values->filter(fn ($value) => $value > 15 && $value <= 30)->count(),
                'menit_31_60' => $values->filter(fn ($value) => $value > 30 && $value <= 60)->count(),
                'menit_61_120' => $extended ? $values->filter(fn ($value) => $value > 60 && $value <= 120)->count() : 0,
                'lebih_60' => $extended ? 0 : $values->filter(fn ($value) => $value > 60)->count(),
                'lebih_120' => $extended ? $values->filter(fn ($value) => $value > 120)->count() : 0,
                'data_tidak_valid' => $values->filter(fn ($value) => $value < 0)->count(),
            ];
        })->values();
        $columns = ['label' => 'Tahapan', 'rata_rata' => 'Rata-rata Menit', 'sampai_15' => '<= 15', 'menit_16_30' => '16-30', 'menit_31_60' => '31-60'];
        $columns += $extended ? ['menit_61_120' => '61-120', 'lebih_120' => '> 120'] : ['lebih_60' => '> 60'];
        $columns['data_tidak_valid'] = 'Tidak Valid';

        $formats = collect($columns)->keys()->reject(fn ($key) => $key === 'label')->mapWithKeys(fn ($key) => [$key => 'number'])->all();

        return ['summary' => ['Jumlah layanan' => collect($rows)->count()], 'sections' => [['title' => 'Ringkasan waktu pelayanan', 'columns' => $columns, 'formats' => $formats, 'rows' => $summaryRows]]];
    }

    private function inpatientCost(): array
    {
        $rows = $this->simrs->select(<<<'SQL'
            SELECT ki.no_rawat, r.no_rkm_medis, p.nm_pasien, CONCAT(k.kd_kamar, ' - ', b.nm_bangsal) AS kamar,
                ki.diagnosa_awal, pb.kd_penyakit, pb.tarif,
                r.biaya_reg + COALESCE(lab.total, 0) + COALESCE(rad.total, 0) + COALESCE(obat.total, 0) + COALESCE(tindakan.total, 0) + COALESCE(kamar.total, 0) AS total,
                COALESCE(dep.total, 0) AS deposit,
                pb.tarif - (r.biaya_reg + COALESCE(lab.total, 0) + COALESCE(rad.total, 0) + COALESCE(obat.total, 0) + COALESCE(tindakan.total, 0) + COALESCE(kamar.total, 0)) AS sisa_limit,
                CASE WHEN pb.tarif <= r.biaya_reg + COALESCE(lab.total, 0) + COALESCE(rad.total, 0) + COALESCE(obat.total, 0) + COALESCE(tindakan.total, 0) + COALESCE(kamar.total, 0) THEN 'Tidak Aman' ELSE 'Aman' END AS status
            FROM kamar_inap ki INNER JOIN reg_periksa r ON r.no_rawat = ki.no_rawat INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis INNER JOIN kamar k ON k.kd_kamar = ki.kd_kamar INNER JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal INNER JOIN perkiraan_biaya_ranap pb ON pb.no_rawat = ki.no_rawat
            LEFT JOIN (SELECT no_rawat, SUM(biaya) AS total FROM periksa_lab GROUP BY no_rawat) lab ON lab.no_rawat = ki.no_rawat
            LEFT JOIN (SELECT no_rawat, SUM(biaya) AS total FROM periksa_radiologi GROUP BY no_rawat) rad ON rad.no_rawat = ki.no_rawat
            LEFT JOIN (SELECT no_rawat, SUM(total) AS total FROM detail_pemberian_obat GROUP BY no_rawat) obat ON obat.no_rawat = ki.no_rawat
            LEFT JOIN (SELECT no_rawat, SUM(total) AS total FROM (SELECT no_rawat, SUM(biaya_rawat) AS total FROM rawat_inap_dr GROUP BY no_rawat UNION ALL SELECT no_rawat, SUM(biaya_rawat) FROM rawat_inap_pr GROUP BY no_rawat UNION ALL SELECT no_rawat, SUM(biaya_rawat) FROM rawat_inap_drpr GROUP BY no_rawat) t GROUP BY no_rawat) tindakan ON tindakan.no_rawat = ki.no_rawat
            LEFT JOIN (SELECT no_rawat, SUM(ttl_biaya) AS total FROM kamar_inap GROUP BY no_rawat) kamar ON kamar.no_rawat = ki.no_rawat
            LEFT JOIN (SELECT no_rawat, SUM(besar_deposit) AS total FROM deposit GROUP BY no_rawat) dep ON dep.no_rawat = ki.no_rawat
            WHERE ki.stts_pulang = '-'
            ORDER BY b.nm_bangsal, p.nm_pasien
        SQL);

        return ['summary' => ['Pasien aktif' => $rows->count(), 'Total estimasi' => $rows->sum('total')], 'sections' => [[
            'title' => 'Perkiraan biaya pasien rawat inap',
            'columns' => ['no_rawat' => 'No. Rawat', 'no_rkm_medis' => 'No. RM', 'nm_pasien' => 'Pasien', 'kamar' => 'Kamar', 'diagnosa_awal' => 'Diagnosa', 'kd_penyakit' => 'Kode Penyakit', 'total' => 'Total Biaya', 'deposit' => 'Deposit', 'tarif' => 'Limit', 'sisa_limit' => 'Sisa Limit', 'status' => 'Status'],
            'formats' => ['total' => 'currency', 'deposit' => 'currency', 'tarif' => 'currency', 'sisa_limit' => 'currency'], 'rows' => $rows,
        ]]];
    }

    private function durationSection(string $title, $rows): array
    {
        return ['title' => $title, 'columns' => ['label' => 'Kategori', 'rata_rata' => 'Rata-rata Menit', 'sampai_15' => '<= 15', 'menit_16_30' => '16-30', 'menit_31_60' => '31-60', 'lebih_60' => '> 60', 'data_tidak_valid' => 'Tidak Valid'], 'formats' => ['rata_rata' => 'number', 'sampai_15' => 'number', 'menit_16_30' => 'number', 'menit_31_60' => 'number', 'lebih_60' => 'number', 'data_tidak_valid' => 'number'], 'rows' => collect($rows)];
    }
}
