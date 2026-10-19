<?php

namespace App\Services\Simrs;

use Carbon\CarbonImmutable;

class CashierReport
{
    public function __construct(private ReadOnlySimrs $simrs) {}

    public function get(string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($report) {
            'pembayaran-akun' => $this->paymentsByAccount($from, $to, false),
            'pembayaran-coa' => $this->paymentsByAccount($from, $to, true),
            'piutang-akun' => $this->receivables($from, $to),
            'pendapatan-closing' => $this->revenue($from, $to, false),
            'pendapatan-coa' => $this->revenue($from, $to, true),
            default => ['summary' => [], 'sections' => []],
        };
    }

    private function paymentsByAccount(CarbonImmutable $from, CarbonImmutable $to, bool $coa): array
    {
        $group = $coa ? 'a.kd_rek' : 'd.nama_bayar';
        $label = $coa ? 'r.nm_rek' : 'd.nama_bayar';
        $accountJoin = $coa ? ' INNER JOIN akun_bayar a ON a.nama_bayar = d.nama_bayar INNER JOIN rekening r ON r.kd_rek = a.kd_rek' : '';
        $rows = $this->simrs->select("SELECT {$group} AS kode, {$label} AS akun, SUM(x.rawat_jalan) AS rawat_jalan, SUM(x.rawat_inap) AS rawat_inap, SUM(x.total) AS total FROM (SELECT d.nama_bayar, SUM(d.besar_bayar) AS rawat_jalan, 0 AS rawat_inap, SUM(d.besar_bayar) AS total FROM detail_nota_jalan d INNER JOIN nota_jalan n ON n.no_rawat = d.no_rawat WHERE n.tanggal >= ? AND n.tanggal < ? GROUP BY d.nama_bayar UNION ALL SELECT d.nama_bayar, 0, SUM(d.besar_bayar), SUM(d.besar_bayar) FROM detail_nota_inap d INNER JOIN nota_inap n ON n.no_rawat = d.no_rawat WHERE n.tanggal >= ? AND n.tanggal < ? GROUP BY d.nama_bayar) x INNER JOIN (SELECT nama_bayar FROM akun_bayar GROUP BY nama_bayar) d ON d.nama_bayar = x.nama_bayar {$accountJoin} GROUP BY {$group}, {$label} ORDER BY akun", $this->datesTwice($from, $to));

        return $this->categoryReport($coa ? 'Pembayaran per COA' : 'Pembayaran per akun', $rows, ['kode' => 'Kode', 'akun' => 'Akun', 'rawat_jalan' => 'Rawat Jalan', 'rawat_inap' => 'Rawat Inap', 'total' => 'Total']);
    }

    private function receivables(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->simrs->select(<<<'SQL'
            SELECT d.nama_bayar AS akun,
                SUM(CASE WHEN nj.no_rawat IS NOT NULL THEN d.totalpiutang ELSE 0 END) AS rawat_jalan,
                SUM(CASE WHEN ni.no_rawat IS NOT NULL THEN d.totalpiutang ELSE 0 END) AS rawat_inap,
                SUM(d.totalpiutang) AS total
            FROM detail_piutang_pasien d
            INNER JOIN piutang_pasien p ON p.no_rawat = d.no_rawat
            LEFT JOIN nota_jalan nj ON nj.no_rawat = d.no_rawat
            LEFT JOIN nota_inap ni ON ni.no_rawat = d.no_rawat
            WHERE p.tgl_piutang >= ? AND p.tgl_piutang < ?
            GROUP BY d.nama_bayar ORDER BY d.nama_bayar
        SQL, [$from->toDateString(), $to->addDay()->toDateString()]);

        return $this->categoryReport('Piutang per akun', $rows, ['akun' => 'Akun', 'rawat_jalan' => 'Rawat Jalan', 'rawat_inap' => 'Rawat Inap', 'total' => 'Total']);
    }

    private function revenue(CarbonImmutable $from, CarbonImmutable $to, bool $coa): array
    {
        $group = $coa ? 'a.kd_rek' : 'd.nama_bayar';
        $label = $coa ? 'r.nm_rek' : 'd.nama_bayar';
        $join = $coa ? ' INNER JOIN akun_bayar a ON a.nama_bayar = d.nama_bayar INNER JOIN rekening r ON r.kd_rek = a.kd_rek' : '';
        $rows = $this->simrs->select("SELECT {$group} AS kode, {$label} AS akun, SUM(x.rawat_jalan) AS rawat_jalan, SUM(x.rawat_inap) AS rawat_inap, SUM(x.apotek) AS apotek, SUM(x.deposit) AS deposit, SUM(x.total) AS total FROM (SELECT d.nama_bayar, SUM(d.besar_bayar) rawat_jalan, 0 rawat_inap, 0 apotek, 0 deposit, SUM(d.besar_bayar) total FROM detail_nota_jalan d INNER JOIN tagihan_sadewa t ON t.no_nota = d.no_rawat WHERE t.tgl_bayar >= ? AND t.tgl_bayar < ? GROUP BY d.nama_bayar UNION ALL SELECT d.nama_bayar, 0, SUM(d.besar_bayar), 0, 0, SUM(d.besar_bayar) FROM detail_nota_inap d INNER JOIN tagihan_sadewa t ON t.no_nota = d.no_rawat WHERE t.tgl_bayar >= ? AND t.tgl_bayar < ? GROUP BY d.nama_bayar UNION ALL SELECT p.nama_bayar, 0, 0, SUM(d.total) + SUM(DISTINCT p.ongkir) + SUM(DISTINCT p.ppn), 0, SUM(d.total) + SUM(DISTINCT p.ongkir) + SUM(DISTINCT p.ppn) FROM penjualan p INNER JOIN detailjual d ON d.nota_jual = p.nota_jual INNER JOIN tagihan_sadewa t ON t.no_nota = p.nota_jual WHERE t.tgl_bayar >= ? AND t.tgl_bayar < ? GROUP BY p.nama_bayar UNION ALL SELECT d.nama_bayar, 0, 0, 0, SUM(d.besar_deposit), SUM(d.besar_deposit) FROM deposit d INNER JOIN tagihan_sadewa t ON t.no_nota = d.no_deposit WHERE t.tgl_bayar >= ? AND t.tgl_bayar < ? GROUP BY d.nama_bayar) x INNER JOIN (SELECT nama_bayar FROM akun_bayar GROUP BY nama_bayar) d ON d.nama_bayar = x.nama_bayar {$join} GROUP BY {$group}, {$label} ORDER BY akun", [...$this->datesTwice($from, $to), ...$this->datesTwice($from, $to)]);

        return $this->categoryReport($coa ? 'Pendapatan per COA' : 'Pendapatan per closing', $rows, ['kode' => 'Kode', 'akun' => 'Akun', 'rawat_jalan' => 'Rawat Jalan', 'rawat_inap' => 'Rawat Inap', 'apotek' => 'Apotek', 'deposit' => 'Deposit', 'total' => 'Total']);
    }

    private function datesTwice(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [$from->toDateString(), $to->addDay()->toDateString()];

        return [...$dates, ...$dates];
    }

    private function categoryReport(string $title, $rows, array $columns): array
    {
        $formats = collect($columns)->keys()->reject(fn ($key) => in_array($key, ['kode', 'akun'], true))->mapWithKeys(fn ($key) => [$key => 'currency'])->all();

        return ['summary' => ['Jumlah akun' => $rows->count(), 'Total' => $rows->sum('total')], 'sections' => [['title' => $title, 'columns' => $columns, 'formats' => $formats, 'rows' => $rows]]];
    }
}
