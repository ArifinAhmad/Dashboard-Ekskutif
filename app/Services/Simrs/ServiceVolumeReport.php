<?php

namespace App\Services\Simrs;

use Carbon\CarbonImmutable;

class ServiceVolumeReport
{
    public function __construct(private ReadOnlySimrs $simrs) {}

    public function get(string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($report) {
            'rawat-jalan' => $this->registration($from, $to, '<>'),
            'igd' => $this->registration($from, $to, '='),
            'rawat-inap' => $this->inpatient($from, $to),
            'laboratorium' => $this->ancillary($from, $to, 'periksa_lab', 'jns_perawatan_lab'),
            'radiologi' => $this->ancillary($from, $to, 'periksa_radiologi', 'jns_perawatan_radiologi'),
            default => ['summary' => [], 'rows' => []],
        };
    }

    private function registration(CarbonImmutable $from, CarbonImmutable $to, string $operator): array
    {
        $dates = [$operator === '=' ? config('simrs.igd_code') : config('simrs.igd_code'), $from->toDateString(), $to->addDay()->toDateString()];
        $where = "r.kd_poli {$operator} ? AND r.tgl_registrasi >= ? AND r.tgl_registrasi < ?";
        $payer = $this->simrs->select("SELECT p.png_jawab AS label, COUNT(r.no_rawat) AS total FROM reg_periksa r INNER JOIN penjab p ON p.kd_pj = r.kd_pj WHERE {$where} GROUP BY p.kd_pj, p.png_jawab ORDER BY total DESC", $dates);
        $doctor = $this->simrs->select("SELECT d.nm_dokter AS label, COUNT(r.no_rawat) AS total FROM reg_periksa r INNER JOIN dokter d ON d.kd_dokter = r.kd_dokter WHERE {$where} GROUP BY d.kd_dokter, d.nm_dokter ORDER BY total DESC", $dates);
        $clinic = $operator === '<>' ? $this->simrs->select("SELECT p.nm_poli AS label, COUNT(r.no_rawat) AS total FROM reg_periksa r INNER JOIN poliklinik p ON p.kd_poli = r.kd_poli WHERE {$where} GROUP BY p.kd_poli, p.nm_poli ORDER BY total DESC", $dates) : collect();
        $daily = $this->simrs->select("SELECT DATE_FORMAT(r.tgl_registrasi, '%d-%m-%Y') AS label, COUNT(r.no_rawat) AS total FROM reg_periksa r WHERE {$where} GROUP BY DATE(r.tgl_registrasi) ORDER BY DATE(r.tgl_registrasi)", $dates);
        $status = $this->simrs->select("SELECT CASE WHEN r.stts = 'Batal' THEN 'Batal' WHEN r.stts = 'Dirujuk' THEN 'Dirujuk' WHEN r.stts = 'Meninggal' THEN 'Meninggal' WHEN r.stts = 'Pulang Paksa' THEN 'Pulang Paksa' WHEN r.stts IN ('Belum','Sudah','Berkas Diterima','Dirawat') THEN 'Terlayani' ELSE r.stts END AS label, COUNT(r.no_rawat) AS total FROM reg_periksa r WHERE {$where} GROUP BY label ORDER BY total DESC", $dates);
        $total = (int) $daily->sum('total');

        if ($operator === '<>') {
            return $this->outpatientRegistration($dates, $where, $payer, $doctor, $clinic, $daily, $status, $total);
        }

        return $this->emergencyRegistration($dates, $where, $payer, $doctor, $daily, $status, $total);
    }

    private function emergencyRegistration(array $dates, string $where, $payer, $doctor, $daily, $status, int $total): array
    {
        $referrers = $this->simrs->select("SELECT COALESCE(rm.perujuk, '-') AS label, COUNT(rm.no_rawat) AS total FROM rujuk_masuk rm INNER JOIN reg_periksa r ON r.no_rawat = rm.no_rawat WHERE {$where} GROUP BY rm.perujuk ORDER BY total DESC", $dates);
        $monthly = $this->simrs->select("SELECT DATE_FORMAT(r.tgl_registrasi, '%m-%Y') AS label, COUNT(r.no_rawat) AS total FROM reg_periksa r WHERE {$where} GROUP BY DATE_FORMAT(r.tgl_registrasi, '%Y-%m'), DATE_FORMAT(r.tgl_registrasi, '%m-%Y') ORDER BY DATE_FORMAT(r.tgl_registrasi, '%Y-%m')", $dates);
        $newDaily = $this->registrationTrend($where, $dates, 'Baru', 'day');
        $newMonthly = $this->registrationTrend($where, $dates, 'Baru', 'month');
        $oldDaily = $this->registrationTrend($where, $dates, 'Lama', 'day');
        $oldMonthly = $this->registrationTrend($where, $dates, 'Lama', 'month');

        return [
            'summary' => [
                'Total kunjungan' => $total,
                'Pasien rujukan' => (int) collect($referrers)->sum('total'),
                'Pasien baru' => (int) collect($newDaily)->sum('total'),
                'Pasien lama' => (int) collect($oldDaily)->sum('total'),
                'Terlayani' => (int) (collect($status)->firstWhere('label', 'Terlayani')->total ?? 0),
            ],
            'sections' => [
                $this->section('Pelayanan Per Cara Bayar', $payer),
                $this->section('Pelayanan Per Dokter', $doctor),
                $this->section('Pelayanan Per Perujuk', $referrers),
                $this->section('Pelayanan Per Tanggal', $daily),
                $this->section('Pelayanan Per Bulan', $monthly),
                $this->section('Pasien Baru Per Tanggal', $newDaily),
                $this->section('Pasien Baru Per Bulan', $newMonthly),
                $this->section('Pasien Lama Per Tanggal', $oldDaily),
                $this->section('Pasien Lama Per Bulan', $oldMonthly),
                $this->section('Status Pelayanan Pasien', $status),
            ],
        ];
    }

    private function registrationTrend(string $where, array $dates, string $registrationStatus, string $period)
    {
        $format = $period === 'day' ? '%d-%m-%Y' : '%m-%Y';
        $group = $period === 'day' ? 'DATE(r.tgl_registrasi)' : "DATE_FORMAT(r.tgl_registrasi, '%Y-%m')";

        return $this->simrs->select("SELECT DATE_FORMAT(r.tgl_registrasi, '{$format}') AS label, COUNT(r.no_rawat) AS total FROM reg_periksa r WHERE {$where} AND r.stts_daftar = ? GROUP BY {$group}, DATE_FORMAT(r.tgl_registrasi, '{$format}') ORDER BY {$group}", [...$dates, $registrationStatus]);
    }

    private function outpatientRegistration(array $dates, string $where, $payer, $doctor, $clinic, $daily, $status, int $total): array
    {
        $referrers = $this->simrs->select("SELECT COALESCE(NULLIF(TRIM(rm.perujuk), ''), '-') AS label, COUNT(r.no_rawat) AS total FROM reg_periksa r INNER JOIN rujuk_masuk rm ON rm.no_rawat = r.no_rawat WHERE {$where} GROUP BY COALESCE(NULLIF(TRIM(rm.perujuk), ''), '-') ORDER BY total DESC", $dates);
        $monthly = $this->simrs->select("SELECT DATE_FORMAT(r.tgl_registrasi, '%m-%Y') AS label, COUNT(r.no_rawat) AS total FROM reg_periksa r WHERE {$where} GROUP BY DATE_FORMAT(r.tgl_registrasi, '%Y-%m'), DATE_FORMAT(r.tgl_registrasi, '%m-%Y') ORDER BY DATE_FORMAT(r.tgl_registrasi, '%Y-%m')", $dates);
        $doctorClinic = $this->simrs->select("SELECT p.nm_poli AS poli, d.nm_dokter AS dokter, COUNT(r.no_rawat) AS total FROM reg_periksa r INNER JOIN poliklinik p ON p.kd_poli = r.kd_poli INNER JOIN dokter d ON d.kd_dokter = r.kd_dokter WHERE {$where} GROUP BY p.kd_poli, p.nm_poli, d.kd_dokter, d.nm_dokter ORDER BY p.nm_poli, total DESC", $dates);
        $patientTypeClinic = $this->simrs->select("SELECT p.nm_poli AS poli, SUM(r.status_poli = 'Baru') AS baru, SUM(r.status_poli = 'Lama') AS lama, COUNT(r.no_rawat) AS total FROM reg_periksa r INNER JOIN poliklinik p ON p.kd_poli = r.kd_poli WHERE {$where} GROUP BY p.kd_poli, p.nm_poli ORDER BY total DESC", $dates);
        $genderClinic = $this->simrs->select("SELECT po.nm_poli AS poli, SUM(p.jk = 'L') AS laki_laki, SUM(p.jk = 'P') AS perempuan, COUNT(r.no_rawat) AS total FROM reg_periksa r INNER JOIN poliklinik po ON po.kd_poli = r.kd_poli INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis WHERE {$where} GROUP BY po.kd_poli, po.nm_poli ORDER BY total DESC", $dates);
        $payerClinic = $this->simrs->select("SELECT pj.png_jawab AS cara_bayar, p.nm_poli AS poli, COUNT(r.no_rawat) AS total FROM reg_periksa r INNER JOIN penjab pj ON pj.kd_pj = r.kd_pj INNER JOIN poliklinik p ON p.kd_poli = r.kd_poli WHERE {$where} GROUP BY pj.kd_pj, pj.png_jawab, p.kd_poli, p.nm_poli ORDER BY pj.png_jawab, total DESC", $dates);
        $patientTypeTotals = collect($patientTypeClinic);
        $genderTotals = collect($genderClinic);

        return [
            'summary' => [
                'Total kunjungan' => $total,
                'Pasien rujukan' => (int) collect($referrers)->sum('total'),
                'Pasien baru' => (int) $patientTypeTotals->sum('baru'),
                'Pasien lama' => (int) $patientTypeTotals->sum('lama'),
                'Pasien laki-laki' => (int) $genderTotals->sum('laki_laki'),
                'Pasien perempuan' => (int) $genderTotals->sum('perempuan'),
                'Terlayani' => (int) (collect($status)->firstWhere('label', 'Terlayani')->total ?? 0),
            ],
            'sections' => [
                $this->section('Pelayanan Per Cara Bayar', $payer),
                $this->section('Pelayanan Per Dokter', $doctor),
                $this->section('Pelayanan Per Poli', $clinic),
                $this->section('Pelayanan Per Perujuk', $referrers),
                $this->section('Pelayanan Per Tanggal', $daily),
                $this->section('Pelayanan Per Bulan', $monthly),
                $this->detailSection('Pelayanan Per Dokter Poli', ['poli' => 'Poliklinik', 'dokter' => 'Dokter', 'total' => 'Jumlah'], $doctorClinic),
                $this->detailSection('Pelayanan Pasien Baru & Lama Per Poli', ['poli' => 'Poliklinik', 'baru' => 'Baru', 'lama' => 'Lama', 'total' => 'Jumlah'], $patientTypeClinic),
                $this->detailSection('Pelayanan Pasien Laki & Perempuan Per Poli', ['poli' => 'Poliklinik', 'laki_laki' => 'Laki-laki', 'perempuan' => 'Perempuan', 'total' => 'Jumlah'], $genderClinic),
                $this->detailSection('Pelayanan Per Cara Bayar Per Poli', ['cara_bayar' => 'Cara Bayar', 'poli' => 'Poliklinik', 'total' => 'Jumlah'], $payerClinic),
                $this->section('Status Pelayanan Pasien', $status),
            ],
        ];
    }

    private function ancillary(CarbonImmutable $from, CarbonImmutable $to, string $factTable, string $masterTable): array
    {
        $allowed = ['periksa_lab' => 'jns_perawatan_lab', 'periksa_radiologi' => 'jns_perawatan_radiologi'];
        abort_unless(($allowed[$factTable] ?? null) === $masterTable, 404);

        $bindings = [$from->toDateString(), $to->addDay()->toDateString()];
        [$rows, $outpatient, $inpatient] = $this->ancillaryDimension($factTable, $masterTable, 'treatment', $bindings);
        [$doctors, $outpatientDoctors, $inpatientDoctors] = $this->ancillaryDimension($factTable, $masterTable, 'doctor', $bindings);
        [$payers, $outpatientPayers, $inpatientPayers] = $this->ancillaryDimension($factTable, $masterTable, 'payer', $bindings);

        return [
            'summary' => [
                'Total pemeriksaan' => (int) $rows->sum('total'),
                'Pemeriksaan rawat jalan' => (int) $outpatient->sum('total'),
                'Pemeriksaan rawat inap' => (int) $inpatient->sum('total'),
                'Dokter perujuk' => $doctors->count(),
                'Cara bayar' => $payers->count(),
            ],
            'sections' => [
                $this->section('Pelayanan Per Pemeriksaan', $rows),
                $this->section('Pelayanan Per Pemeriksaan Rawat Jalan', $outpatient),
                $this->section('Pelayanan Per Pemeriksaan Rawat Inap', $inpatient),
                $this->section('Pelayanan Per Dokter Perujuk', $doctors),
                $this->section('Pelayanan Per Dokter Perujuk Rawat Inap', $inpatientDoctors),
                $this->section('Pelayanan Per Dokter Perujuk Rawat Jalan', $outpatientDoctors),
                $this->section('Pelayanan Per Cara Bayar', $payers),
                $this->section('Pelayanan Per Cara Bayar Rawat Inap', $inpatientPayers),
                $this->section('Pelayanan Per Cara Bayar Rawat Jalan', $outpatientPayers),
            ],
        ];
    }

    private function ancillaryDimension(string $factTable, string $masterTable, string $dimension, array $bindings): array
    {
        $query = match ($dimension) {
            'treatment' => "SELECT m.nm_perawatan AS label, COUNT(f.no_rawat) AS total, SUM(f.status = 'Ralan') AS ralan, SUM(f.status = 'Ranap') AS ranap FROM {$factTable} f INNER JOIN {$masterTable} m ON m.kd_jenis_prw = f.kd_jenis_prw WHERE f.tgl_periksa >= ? AND f.tgl_periksa < ? GROUP BY m.nm_perawatan ORDER BY total DESC",
            'doctor' => "SELECT d.nm_dokter AS label, COUNT(f.no_rawat) AS total, SUM(f.status = 'Ralan') AS ralan, SUM(f.status = 'Ranap') AS ranap FROM {$factTable} f INNER JOIN dokter d ON d.kd_dokter = f.dokter_perujuk WHERE f.tgl_periksa >= ? AND f.tgl_periksa < ? GROUP BY d.kd_dokter, d.nm_dokter ORDER BY total DESC",
            'payer' => "SELECT p.png_jawab AS label, COUNT(f.no_rawat) AS total, SUM(f.status = 'Ralan') AS ralan, SUM(f.status = 'Ranap') AS ranap FROM {$factTable} f INNER JOIN reg_periksa r ON r.no_rawat = f.no_rawat INNER JOIN penjab p ON p.kd_pj = r.kd_pj WHERE f.tgl_periksa >= ? AND f.tgl_periksa < ? GROUP BY p.kd_pj, p.png_jawab ORDER BY total DESC",
            default => abort(500),
        };

        $all = $this->simrs->select($query, $bindings);
        $forStatus = fn (string $key) => $all->filter(fn ($row) => (int) $row->{$key} > 0)->map(fn ($row) => (object) ['label' => $row->label, 'total' => (int) $row->{$key}])->sortByDesc('total')->values();

        return [$all->map(fn ($row) => (object) ['label' => $row->label, 'total' => (int) $row->total]), $forStatus('ralan'), $forStatus('ranap')];
    }

    private function inpatient(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [$from->toDateString(), $to->addDay()->toDateString()];
        $admission = $this->inpatientScope('tgl_masuk', 'Tanggal Masuk', $dates, false);
        $discharge = $this->inpatientScope('tgl_keluar', 'Tanggal Pulang', $dates, true);
        $beds = $this->bedAvailability();

        return [
            'summary' => [
                'Pasien masuk' => $admission['total'],
                'Pasien keluar' => $discharge['total'],
                'Pasien baru masuk' => $admission['new'],
                'Pasien lama masuk' => $admission['old'],
                'Pasien baru pulang' => $discharge['new'],
                'Pasien lama pulang' => $discharge['old'],
                'Tempat tidur tersedia' => $beds['empty'],
                'Tempat tidur terisi' => $beds['occupied'],
                'Total tempat tidur aktif' => $beds['total'],
            ],
            'sections' => [$beds['ward_section'], $beds['class_section'], ...$admission['sections'], ...$discharge['sections']],
        ];
    }

    private function bedAvailability(): array
    {
        $rows = $this->simrs->select(<<<'SQL'
            SELECT b.nm_bangsal AS bangsal, k.kelas,
                COUNT(k.kd_kamar) AS total,
                SUM(k.status = 'KOSONG') AS kosong,
                SUM(k.status = 'ISI') AS terisi,
                SUM(k.status = 'DIBERSIHKAN') AS dibersihkan,
                SUM(k.status = 'DIBOOKING') AS dibooking,
                SUM(k.status = 'PERBAIKAN') AS perbaikan
            FROM kamar k
            INNER JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
            WHERE k.statusdata = '1' AND b.status = '1'
            GROUP BY b.kd_bangsal, b.nm_bangsal, k.kelas
            ORDER BY b.nm_bangsal, k.kelas
        SQL);
        $columns = ['bangsal' => 'Bangsal', 'total' => 'Total Bed', 'kosong' => 'Kosong', 'terisi' => 'Terisi', 'dibersihkan' => 'Dibersihkan', 'dibooking' => 'Dibooking', 'perbaikan' => 'Perbaikan'];
        $formats = array_fill_keys(['total', 'kosong', 'terisi', 'dibersihkan', 'dibooking', 'perbaikan'], 'number');
        $byWard = collect($rows)->groupBy('bangsal')->map(function ($items, $ward) {
            return (object) [
                'bangsal' => $ward,
                'total' => $items->sum('total'),
                'kosong' => $items->sum('kosong'),
                'terisi' => $items->sum('terisi'),
                'dibersihkan' => $items->sum('dibersihkan'),
                'dibooking' => $items->sum('dibooking'),
                'perbaikan' => $items->sum('perbaikan'),
            ];
        })->values();
        $byClass = collect($rows)->groupBy('kelas')->map(function ($items, $class) {
            return (object) [
                'kelas' => $class,
                'total' => $items->sum('total'),
                'kosong' => $items->sum('kosong'),
                'terisi' => $items->sum('terisi'),
                'dibersihkan' => $items->sum('dibersihkan'),
                'dibooking' => $items->sum('dibooking'),
                'perbaikan' => $items->sum('perbaikan'),
            ];
        })->values();

        return [
            'total' => (int) collect($rows)->sum('total'),
            'empty' => (int) collect($rows)->sum('kosong'),
            'occupied' => (int) collect($rows)->sum('terisi'),
            'ward_section' => ['title' => 'Ketersediaan Tempat Tidur Per Bangsal', 'columns' => $columns, 'formats' => $formats, 'rows' => $byWard],
            'class_section' => ['title' => 'Ketersediaan Tempat Tidur Per Kelas', 'columns' => ['kelas' => 'Kelas', ...array_diff_key($columns, ['bangsal' => true])], 'formats' => $formats, 'rows' => $byClass],
        ];
    }

    private function inpatientScope(string $dateColumn, string $scope, array $dates, bool $requiresDischarge): array
    {
        abort_unless(in_array($dateColumn, ['tgl_masuk', 'tgl_keluar'], true), 500);
        $where = "ki.stts_pulang <> 'Pindah Kamar' AND ki.{$dateColumn} >= ? AND ki.{$dateColumn} < ?";
        if ($requiresDischarge) {
            $where .= " AND ki.tgl_keluar <> '0000-00-00'";
        }
        $payers = $this->simrs->select("SELECT p.png_jawab AS label, COUNT(DISTINCT ki.no_rawat) AS total FROM kamar_inap ki INNER JOIN reg_periksa r ON r.no_rawat = ki.no_rawat INNER JOIN penjab p ON p.kd_pj = r.kd_pj WHERE {$where} GROUP BY p.kd_pj, p.png_jawab ORDER BY total DESC", $dates);
        $wards = $this->simrs->select("SELECT b.nm_bangsal AS label, COUNT(DISTINCT ki.no_rawat) AS total FROM kamar_inap ki INNER JOIN kamar k ON k.kd_kamar = ki.kd_kamar INNER JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal WHERE {$where} GROUP BY b.kd_bangsal, b.nm_bangsal ORDER BY total DESC", $dates);
        $classes = $this->simrs->select("SELECT k.kelas AS label, COUNT(DISTINCT ki.no_rawat) AS total FROM kamar_inap ki INNER JOIN kamar k ON k.kd_kamar = ki.kd_kamar WHERE {$where} GROUP BY k.kelas ORDER BY total DESC", $dates);
        $clinics = $this->simrs->select("SELECT p.nm_poli AS label, COUNT(DISTINCT ki.no_rawat) AS total FROM kamar_inap ki INNER JOIN reg_periksa r ON r.no_rawat = ki.no_rawat INNER JOIN poliklinik p ON p.kd_poli = r.kd_poli WHERE {$where} GROUP BY p.kd_poli, p.nm_poli ORDER BY total DESC", $dates);
        $doctors = $this->simrs->select("SELECT d.nm_dokter AS label, COUNT(DISTINCT ki.no_rawat) AS total FROM kamar_inap ki INNER JOIN reg_periksa r ON r.no_rawat = ki.no_rawat INNER JOIN dokter d ON d.kd_dokter = r.kd_dokter WHERE {$where} GROUP BY d.kd_dokter, d.nm_dokter ORDER BY total DESC", $dates);
        $statusWhere = $where." AND ki.tgl_keluar <> '0000-00-00'";
        $statuses = $this->simrs->select("SELECT ki.stts_pulang AS label, COUNT(DISTINCT ki.no_rawat) AS total FROM kamar_inap ki WHERE {$statusWhere} GROUP BY ki.stts_pulang ORDER BY total DESC", $dates);
        $daily = $this->inpatientTrend($dateColumn, $where, $dates, 'day');
        $monthly = $this->inpatientTrend($dateColumn, $where, $dates, 'month');

        return [
            'total' => (int) collect($daily)->sum('total'),
            'new' => (int) collect($daily)->sum('baru'),
            'old' => (int) collect($daily)->sum('lama'),
            'sections' => [
                $this->section("{$scope} - Pelayanan Per Cara Bayar", $payers),
                $this->section("{$scope} - Pelayanan Per Bangsal", $wards),
                $this->section("{$scope} - Pelayanan Per Kelas", $classes),
                $this->section("{$scope} - Asal Poli Pasien", $clinics),
                $this->section("{$scope} - Asal Dokter Pasien", $doctors),
                $this->section("{$scope} - Status Pulang Pasien", $statuses),
                $this->trendSection("{$scope} - Pelayanan Per Tanggal", $daily, 'total'),
                $this->trendSection("{$scope} - Pelayanan Per Bulan", $monthly, 'total'),
                $this->trendSection("{$scope} - Pasien Baru Per Tanggal", $daily, 'baru'),
                $this->trendSection("{$scope} - Pasien Baru Per Bulan", $monthly, 'baru'),
                $this->trendSection("{$scope} - Pasien Lama Per Tanggal", $daily, 'lama'),
                $this->trendSection("{$scope} - Pasien Lama Per Bulan", $monthly, 'lama'),
            ],
        ];
    }

    private function inpatientTrend(string $dateColumn, string $where, array $dates, string $period)
    {
        $format = $period === 'day' ? '%d-%m-%Y' : '%m-%Y';
        $group = $period === 'day' ? "DATE(ki.{$dateColumn})" : "DATE_FORMAT(ki.{$dateColumn}, '%Y-%m')";

        return $this->simrs->select("SELECT DATE_FORMAT(ki.{$dateColumn}, '{$format}') AS label, COUNT(DISTINCT ki.no_rawat) AS total, COUNT(DISTINCT CASE WHEN r.stts_daftar = 'Baru' THEN ki.no_rawat END) AS baru, COUNT(DISTINCT CASE WHEN r.stts_daftar = 'Lama' THEN ki.no_rawat END) AS lama FROM kamar_inap ki LEFT JOIN reg_periksa r ON r.no_rawat = ki.no_rawat WHERE {$where} GROUP BY {$group}, DATE_FORMAT(ki.{$dateColumn}, '{$format}') ORDER BY {$group}", $dates);
    }

    private function trendSection(string $title, iterable $rows, string $valueKey): array
    {
        return $this->section($title, collect($rows)->map(fn ($row) => (object) ['label' => $row->label, 'total' => $row->{$valueKey}]));
    }

    private function section(string $title, iterable $rows): array
    {
        return [
            'title' => $title,
            'columns' => ['label' => 'Kategori', 'total' => 'Jumlah'],
            'formats' => ['total' => 'number'],
            'rows' => collect($rows),
        ];
    }

    private function detailSection(string $title, array $columns, iterable $rows): array
    {
        return [
            'title' => $title,
            'columns' => $columns,
            'formats' => collect(array_keys($columns))->reject(fn ($key) => in_array($key, ['poli', 'dokter', 'cara_bayar'], true))->mapWithKeys(fn ($key) => [$key => 'number'])->all(),
            'rows' => collect($rows),
        ];
    }
}
