<?php

namespace App\Services\Simrs;

use Carbon\CarbonImmutable;

class LogisticsReport
{
    private const DOMAINS = [
        'nonmedis' => [
            'item' => 'ipsrsbarang', 'type_table' => 'ipsrsjenisbarang', 'type_join' => 'b.jenis = j.kd_jenis', 'type' => 'j.nm_jenis',
            'supplier' => 'ipsrssuplier', 'order' => 'ipsrspemesanan', 'order_detail' => 'ipsrsdetailpesan',
        ],
        'dapur' => [
            'item' => 'dapurbarang', 'type_table' => null, 'type_join' => null, 'type' => 'b.jenis',
            'supplier' => 'dapursuplier', 'order' => 'dapurpemesanan', 'order_detail' => 'dapurdetailpesan',
        ],
    ];

    private const TRANSACTIONS = [
        'pengadaan' => ['header' => ['nonmedis' => 'ipsrspembelian', 'dapur' => 'dapurpembelian'], 'detail' => ['nonmedis' => 'ipsrsdetailbeli', 'dapur' => 'dapurdetailbeli'], 'key' => 'no_faktur', 'date' => 'tgl_beli', 'quantity' => 'jumlah', 'money' => 'total', 'unit' => 'item'],
        'penerimaan' => ['header' => ['nonmedis' => 'ipsrspemesanan', 'dapur' => 'dapurpemesanan'], 'detail' => ['nonmedis' => 'ipsrsdetailpesan', 'dapur' => 'dapurdetailpesan'], 'key' => 'no_faktur', 'date' => 'tgl_pesan', 'quantity' => 'jumlah', 'money' => 'total', 'unit' => 'detail'],
        'hibah' => ['header' => ['nonmedis' => 'ipsrs_hibah', 'dapur' => 'dapur_hibah'], 'detail' => ['nonmedis' => 'ipsrs_detail_hibah', 'dapur' => 'dapur_detail_hibah'], 'key' => 'no_hibah', 'date' => 'tgl_hibah', 'quantity' => 'jumlah', 'money' => 'subtotalhibah', 'unit' => 'detail'],
        'stok-keluar' => ['header' => ['nonmedis' => 'ipsrspengeluaran', 'dapur' => 'dapurpengeluaran'], 'detail' => ['nonmedis' => 'ipsrsdetailpengeluaran', 'dapur' => 'dapurdetailpengeluaran'], 'key' => 'no_keluar', 'date' => 'tanggal', 'quantity' => 'jumlah', 'money' => 'total', 'unit' => 'item'],
        'retur-supplier' => ['header' => ['nonmedis' => 'ipsrsreturbeli', 'dapur' => 'dapurreturbeli'], 'detail' => ['nonmedis' => 'ipsrs_detail_returbeli', 'dapur' => 'dapur_detail_returbeli'], 'key' => 'no_retur_beli', 'date' => 'tgl_retur', 'quantity' => 'jml_retur', 'money' => 'total', 'unit' => 'detail'],
    ];

    public function __construct(private ReadOnlySimrs $simrs) {}

    public function get(string $domain, string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        abort_unless(isset(self::DOMAINS[$domain]), 404);

        return match ($report) {
            'sisa-stok' => $this->stock($domain),
            'vendor-bulanan' => $this->vendors($domain, $from->year),
            default => $this->transaction($domain, $report, $from, $to),
        };
    }

    private function stock(string $domain): array
    {
        $config = self::DOMAINS[$domain];
        $typeJoin = $config['type_table'] ? "INNER JOIN {$config['type_table']} j ON {$config['type_join']}" : '';
        $rows = $this->simrs->select("SELECT b.kode_brng, b.nama_brng, b.kode_sat AS satuan, {$config['type']} AS jenis, b.stok, b.harga, (b.stok * b.harga) AS nilai_aset FROM {$config['item']} b {$typeJoin} WHERE b.status = ? ORDER BY b.kode_brng", ['1']);

        return [
            'summary' => ['Jumlah item' => $rows->count(), 'Total nilai aset' => (float) $rows->sum('nilai_aset')],
            'sections' => [[
                'title' => 'Stok dan nilai aset',
                'columns' => ['kode_brng' => 'Kode', 'nama_brng' => 'Nama Barang', 'jenis' => 'Jenis', 'satuan' => 'Satuan', 'stok' => 'Stok', 'harga' => 'Harga', 'nilai_aset' => 'Nilai Aset'],
                'formats' => ['stok' => 'number', 'harga' => 'currency', 'nilai_aset' => 'currency'],
                'rows' => $rows,
            ]],
        ];
    }

    private function transaction(string $domain, string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        abort_unless(isset(self::TRANSACTIONS[$report]), 404);
        $domainConfig = self::DOMAINS[$domain];
        $config = self::TRANSACTIONS[$report];
        $header = $config['header'][$domain];
        $detail = $config['detail'][$domain];
        $typeJoin = $domainConfig['type_table'] ? "INNER JOIN {$domainConfig['type_table']} j ON {$domainConfig['type_join']}" : '';
        $unitKey = $config['unit'] === 'detail' ? 'd.kode_sat' : 'b.kode_sat';
        $rows = $this->simrs->select("SELECT d.kode_brng, b.nama_brng, {$domainConfig['type']} AS jenis, s.satuan, SUM(d.{$config['quantity']}) AS jumlah, SUM(d.{$config['money']}) AS total FROM {$header} h INNER JOIN {$detail} d ON h.{$config['key']} = d.{$config['key']} INNER JOIN {$domainConfig['item']} b ON d.kode_brng = b.kode_brng {$typeJoin} INNER JOIN kodesatuan s ON {$unitKey} = s.kode_sat WHERE h.{$config['date']} >= ? AND h.{$config['date']} < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY d.kode_brng, b.nama_brng, {$domainConfig['type']}, s.satuan ORDER BY b.nama_brng, d.kode_brng", [$from->toDateString(), $to->toDateString()]);
        $quantityTop = $rows->sortByDesc(fn ($row) => (float) $row->jumlah)->take(10)->values();
        $valueTop = $rows->sortByDesc(fn ($row) => (float) $row->total)->take(10)->values();

        return [
            'summary' => ['Jumlah item' => $rows->count(), 'Total kuantitas' => (float) $rows->sum('jumlah'), 'Total nilai' => (float) $rows->sum('total')],
            'sections' => [
                $this->itemSection('Rincian transaksi', $rows),
                $this->itemSection('10 item kuantitas tertinggi', $quantityTop),
                $this->itemSection('10 item nilai tertinggi', $valueTop),
            ],
        ];
    }

    private function vendors(string $domain, int $year): array
    {
        $config = self::DOMAINS[$domain];
        $monthCases = collect(range(1, 12))->map(fn ($month) => "COALESCE(SUM(CASE WHEN a.bulan = {$month} THEN a.total ELSE 0 END), 0) AS bulan_{$month}")->implode(', ');
        $rows = $this->simrs->select("SELECT s.kode_suplier, s.nama_suplier, {$monthCases}, COALESCE(SUM(a.total), 0) AS total FROM {$config['supplier']} s LEFT JOIN (SELECT h.kode_suplier, MONTH(h.tgl_pesan) AS bulan, SUM(d.total) AS total FROM {$config['order']} h INNER JOIN {$config['order_detail']} d ON h.no_faktur = d.no_faktur INNER JOIN {$config['item']} b ON d.kode_brng = b.kode_brng WHERE h.tgl_pesan >= ? AND h.tgl_pesan < ? GROUP BY h.kode_suplier, MONTH(h.tgl_pesan)) a ON a.kode_suplier = s.kode_suplier GROUP BY s.kode_suplier, s.nama_suplier ORDER BY s.nama_suplier, s.kode_suplier", ["{$year}-01-01", ($year + 1).'-01-01']);
        $columns = ['kode_suplier' => 'Kode', 'nama_suplier' => 'Vendor'];
        $formats = ['total' => 'currency'];
        foreach (['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'] as $index => $label) {
            $columns['bulan_'.($index + 1)] = $label;
            $formats['bulan_'.($index + 1)] = 'currency';
        }
        $columns['total'] = 'Total';

        return [
            'summary' => ['Jumlah vendor' => $rows->count(), 'Total penerimaan' => (float) $rows->sum('total')],
            'sections' => [['title' => "Nilai penerimaan vendor {$year}", 'columns' => $columns, 'formats' => $formats, 'rows' => $rows]],
        ];
    }

    private function itemSection(string $title, iterable $rows): array
    {
        return [
            'title' => $title,
            'columns' => ['kode_brng' => 'Kode', 'nama_brng' => 'Nama Barang', 'jenis' => 'Jenis', 'satuan' => 'Satuan', 'jumlah' => 'Jumlah', 'total' => 'Nilai'],
            'formats' => ['jumlah' => 'number', 'total' => 'currency'],
            'rows' => collect($rows),
        ];
    }
}
