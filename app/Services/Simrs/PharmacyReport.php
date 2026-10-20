<?php

namespace App\Services\Simrs;

use Carbon\CarbonImmutable;

class PharmacyReport
{
    private const TRANSACTIONS = [
        'pengadaan' => ['header' => 'pembelian', 'detail' => 'detailbeli', 'key' => 'no_faktur', 'date' => 'tgl_beli', 'quantity' => 'jumlah2', 'money' => 'total'],
        'penerimaan' => ['header' => 'pemesanan', 'detail' => 'detailpesan', 'key' => 'no_faktur', 'date' => 'tgl_pesan', 'quantity' => 'jumlah2', 'money' => 'total'],
        'hibah' => ['header' => 'hibah_obat_bhp', 'detail' => 'detailhibah_obat_bhp', 'key' => 'no_hibah', 'date' => 'tgl_hibah', 'quantity' => 'jumlah2', 'money' => 'subtotaldiakui'],
        'penjualan' => ['header' => 'penjualan', 'detail' => 'detailjual', 'key' => 'nota_jual', 'date' => 'tgl_jual', 'quantity' => 'jumlah', 'money' => 'total', 'where' => "h.status = 'Sudah Dibayar'"],
        'piutang' => ['header' => 'piutang', 'detail' => 'detailpiutang', 'key' => 'nota_piutang', 'date' => 'tgl_piutang', 'quantity' => 'jumlah', 'money' => 'total'],
        'stok-keluar' => ['header' => 'pengeluaran_obat_bhp', 'detail' => 'detail_pengeluaran_obat_bhp', 'key' => 'no_keluar', 'date' => 'tanggal', 'quantity' => 'jumlah', 'money' => 'total'],
        'retur-supplier' => ['header' => 'returbeli', 'detail' => 'detreturbeli', 'key' => 'no_retur_beli', 'date' => 'tgl_retur', 'quantity' => 'jml_retur2', 'money' => 'total'],
        'retur-pasien' => ['header' => 'returjual', 'detail' => 'detreturjual', 'key' => 'no_retur_jual', 'date' => 'tgl_retur', 'quantity' => 'jml_retur', 'money' => 'subtotal'],
    ];

    public function __construct(private ReadOnlySimrs $simrs) {}

    public function get(string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($report) {
            'sisa-stok' => $this->stock(),
            'darurat-stok' => $this->defecta(),
            'kedaluwarsa' => $this->expired($from, $to),
            'stok-tidak-bergerak' => $this->inactiveStock(),
            'pengadaan', 'penerimaan', 'hibah', 'penjualan', 'piutang', 'stok-keluar', 'retur-supplier', 'retur-pasien' => $this->transaction($report, $from, $to),
            'pemberian-obat' => $this->medicineUsage($from, $to),
            'obat-per-poli' => $this->medicineBy('poli', $from, $to),
            'obat-per-dokter' => $this->medicineBy('dokter', $from, $to),
            'vendor-bulanan' => $this->vendors($from->year),
            default => ['summary' => [], 'sections' => []],
        };
    }

    private function stock(): array
    {
        $rows = $this->simrs->select(<<<'SQL'
            SELECT b.kode_brng, b.nama_brng, b.dasar AS harga, s.satuan,
                j.nama AS jenis, k.nama AS kategori, g.nama AS golongan,
                COALESCE(SUM(st.stok), 0) AS stok,
                COALESCE(SUM(st.stok), 0) * b.dasar AS nilai_aset
            FROM databarang b
            INNER JOIN jenis j ON b.kdjns = j.kdjns
            INNER JOIN kategori_barang k ON b.kode_kategori = k.kode
            INNER JOIN golongan_barang g ON b.kode_golongan = g.kode
            INNER JOIN kodesatuan s ON b.kode_sat = s.kode_sat
            LEFT JOIN gudangbarang st ON st.kode_brng = b.kode_brng
                AND st.no_batch = '' AND st.no_faktur = ''
            WHERE b.status = '1'
            GROUP BY b.kode_brng, b.nama_brng, b.dasar, s.satuan, j.nama, k.nama, g.nama
            ORDER BY b.kode_brng
        SQL);

        return [
            'summary' => ['Jumlah item' => $rows->count(), 'Total nilai aset' => $rows->sum('nilai_aset')],
            'sections' => [[
                'title' => 'Sisa stok dan nilai aset',
                'columns' => ['kode_brng' => 'Kode', 'nama_brng' => 'Nama Barang', 'jenis' => 'Jenis', 'kategori' => 'Kategori', 'golongan' => 'Golongan', 'satuan' => 'Satuan', 'harga' => 'Harga Dasar', 'stok' => 'Stok', 'nilai_aset' => 'Nilai Aset'],
                'formats' => ['harga' => 'currency', 'stok' => 'number', 'nilai_aset' => 'currency'],
                'rows' => $rows,
            ]],
        ];
    }

    private function defecta(): array
    {
        $rows = $this->simrs->select(<<<'SQL'
            SELECT b.kode_brng, b.nama_brng, s.satuan, j.nama AS jenis,
                b.stokminimal AS minimal, COALESCE(SUM(st.stok), 0) AS saat_ini
            FROM databarang b
            INNER JOIN kodesatuan s ON b.kode_sat = s.kode_sat
            INNER JOIN jenis j ON b.kdjns = j.kdjns
            LEFT JOIN gudangbarang st ON st.kode_brng = b.kode_brng
                AND st.no_batch = '' AND st.no_faktur = ''
            WHERE b.status = '1'
            GROUP BY b.kode_brng, b.nama_brng, s.satuan, j.nama, b.stokminimal
            HAVING saat_ini <= minimal
            ORDER BY b.nama_brng
        SQL);

        return [
            'summary' => ['Item perlu perhatian' => $rows->count()],
            'sections' => [[
                'title' => 'Defecta / darurat stok',
                'columns' => ['kode_brng' => 'Kode', 'nama_brng' => 'Nama Barang', 'satuan' => 'Satuan', 'jenis' => 'Jenis', 'minimal' => 'Minimal', 'saat_ini' => 'Saat Ini'],
                'formats' => ['minimal' => 'number', 'saat_ini' => 'number'],
                'rows' => $rows,
            ]],
        ];
    }

    private function expired(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $end = $from->addMonths(3)->toDateString();
        $rows = $this->simrs->select(<<<'SQL'
            SELECT b.no_batch, b.kode_brng, d.nama_brng, s.satuan, b.tgl_beli,
                b.tgl_kadaluarsa, b.asal, b.no_faktur, b.dasar, b.jumlahbeli, b.sisa
            FROM data_batch b
            INNER JOIN databarang d ON b.kode_brng = d.kode_brng
            INNER JOIN kodesatuan s ON d.kode_sat = s.kode_sat
            WHERE b.tgl_kadaluarsa BETWEEN ? AND ?
            ORDER BY b.tgl_kadaluarsa
        SQL, [$from->toDateString(), $end]);

        return [
            'summary' => ['Batch akan kedaluwarsa' => $rows->count(), 'Sisa kuantitas' => $rows->sum('sisa')],
            'sections' => [[
                'title' => 'Kedaluwarsa tiga bulan ke depan',
                'columns' => ['no_batch' => 'No. Batch', 'kode_brng' => 'Kode', 'nama_brng' => 'Nama Barang', 'satuan' => 'Satuan', 'tgl_beli' => 'Tgl Datang', 'tgl_kadaluarsa' => 'Kedaluwarsa', 'asal' => 'Asal', 'no_faktur' => 'No. Faktur', 'dasar' => 'Harga Dasar', 'jumlahbeli' => 'Jumlah Beli', 'sisa' => 'Sisa'],
                'formats' => ['dasar' => 'currency', 'jumlahbeli' => 'number', 'sisa' => 'number'],
                'rows' => $rows,
            ]],
        ];
    }

    private function inactiveStock(): array
    {
        $rows = $this->simrs->select(<<<'SQL'
            SELECT b.kode_brng, b.nama_brng, s.satuan, j.nama AS jenis, k.nama AS kategori, g.nama AS golongan,
                COALESCE(st.stok, 0) AS stok, b.dasar AS harga, COALESCE(st.stok, 0) * b.dasar AS nilai_aset,
                mv.aktivitas_terakhir
            FROM databarang b INNER JOIN kodesatuan s ON s.kode_sat = b.kode_sat INNER JOIN jenis j ON j.kdjns = b.kdjns
            INNER JOIN kategori_barang k ON k.kode = b.kode_kategori INNER JOIN golongan_barang g ON g.kode = b.kode_golongan
            LEFT JOIN (SELECT gb.kode_brng, SUM(gb.stok) AS stok FROM gudangbarang gb INNER JOIN bangsal ba ON ba.kd_bangsal = gb.kd_bangsal WHERE ba.status = '1' AND gb.no_batch = '' AND gb.no_faktur = '' GROUP BY gb.kode_brng) st ON st.kode_brng = b.kode_brng
            LEFT JOIN (SELECT kode_brng, MAX(tanggal) AS aktivitas_terakhir FROM riwayat_barang_medis WHERE posisi <> 'Opname' GROUP BY kode_brng) mv ON mv.kode_brng = b.kode_brng
            WHERE b.status = '1' AND COALESCE(st.stok, 0) > 0
                AND (mv.aktivitas_terakhir IS NULL OR mv.aktivitas_terakhir < DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))
            ORDER BY aktivitas_terakhir, b.nama_brng
        SQL);

        return ['summary' => ['Item tidak bergerak' => $rows->count(), 'Nilai aset' => $rows->sum('nilai_aset')], 'sections' => [[
            'title' => 'Stok tidak bergerak lebih dari satu bulan',
            'columns' => ['kode_brng' => 'Kode', 'nama_brng' => 'Nama Barang', 'jenis' => 'Jenis', 'kategori' => 'Kategori', 'golongan' => 'Golongan', 'satuan' => 'Satuan', 'stok' => 'Stok', 'harga' => 'Harga', 'nilai_aset' => 'Nilai Aset', 'aktivitas_terakhir' => 'Aktivitas Terakhir'],
            'formats' => ['stok' => 'number', 'harga' => 'currency', 'nilai_aset' => 'currency'], 'rows' => $rows,
        ]]];
    }

    private function transaction(string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $config = self::TRANSACTIONS[$report];
        $extraWhere = isset($config['where']) ? " AND {$config['where']}" : '';
        $rows = $this->simrs->select("SELECT d.kode_brng, b.nama_brng, s.satuan, j.nama AS jenis, g.nama AS golongan, k.nama AS kategori, SUM(d.{$config['quantity']}) AS jumlah, SUM(d.{$config['money']}) AS total FROM {$config['header']} h INNER JOIN {$config['detail']} d ON d.{$config['key']} = h.{$config['key']} INNER JOIN databarang b ON b.kode_brng = d.kode_brng INNER JOIN kodesatuan s ON s.kode_sat = b.kode_sat INNER JOIN jenis j ON j.kdjns = b.kdjns INNER JOIN golongan_barang g ON g.kode = b.kode_golongan INNER JOIN kategori_barang k ON k.kode = b.kode_kategori WHERE h.{$config['date']} >= ? AND h.{$config['date']} < ? {$extraWhere} GROUP BY d.kode_brng, b.nama_brng, s.satuan, j.nama, g.nama, k.nama ORDER BY b.nama_brng", [$from->toDateString(), $to->addDay()->toDateString()]);

        return $this->itemReport('Rincian transaksi', $rows);
    }

    private function medicineUsage(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->simrs->select(<<<'SQL'
            SELECT d.kode_brng, b.nama_brng, s.satuan, j.nama AS jenis, g.nama AS golongan, k.nama AS kategori,
                SUM(d.jml) AS jumlah, SUM(d.total) AS total
            FROM detail_pemberian_obat d INNER JOIN databarang b ON b.kode_brng = d.kode_brng
            INNER JOIN kodesatuan s ON s.kode_sat = b.kode_sat INNER JOIN jenis j ON j.kdjns = b.kdjns
            INNER JOIN golongan_barang g ON g.kode = b.kode_golongan INNER JOIN kategori_barang k ON k.kode = b.kode_kategori
            WHERE d.tgl_perawatan >= ? AND d.tgl_perawatan < ?
            GROUP BY d.kode_brng, b.nama_brng, s.satuan, j.nama, g.nama, k.nama ORDER BY b.nama_brng
        SQL, [$from->toDateString(), $to->addDay()->toDateString()]);

        return $this->itemReport('Pemberian obat, alkes, dan BHP', $rows);
    }

    private function medicineBy(string $dimension, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $isClinic = $dimension === 'poli';
        $join = $isClinic ? 'INNER JOIN poliklinik x ON x.kd_poli = r.kd_poli' : 'INNER JOIN dokter x ON x.kd_dokter = r.kd_dokter';
        $key = $isClinic ? 'x.kd_poli' : 'x.kd_dokter';
        $label = $isClinic ? 'x.nm_poli' : 'x.nm_dokter';
        $statusSelect = $isClinic ? "SUM(d.status = 'Ralan') AS ralan, SUM(d.status <> 'Ralan' OR d.status IS NULL) AS ranap_lain" : "SUM(d.status = 'Ralan') AS ralan, SUM(d.status = 'Ranap') AS ranap";
        $measure = fn (string $condition) => "SUM(CASE WHEN {$condition} THEN d.jml ELSE 0 END) AS jumlah_{$condition}, SUM(CASE WHEN {$condition} THEN d.total - d.embalase - d.tuslah ELSE 0 END) AS biaya_{$condition}, SUM(CASE WHEN {$condition} THEN d.embalase ELSE 0 END) AS embalase_{$condition}, SUM(CASE WHEN {$condition} THEN d.tuslah ELSE 0 END) AS tuslah_{$condition}, SUM(CASE WHEN {$condition} THEN d.total ELSE 0 END) AS total_{$condition}";
        $rows = $this->simrs->select("SELECT {$key} AS kode, {$label} AS unit, b.kode_brng, b.nama_brng, SUM(d.jml) AS jumlah, SUM(d.total - d.embalase - d.tuslah) AS biaya_obat, SUM(d.embalase) AS embalase, SUM(d.tuslah) AS tuslah, SUM(d.total) AS total, {$measure("d.status = 'Ralan'")}, {$measure("d.status = 'Ranap'")}, {$statusSelect} FROM detail_pemberian_obat d INNER JOIN reg_periksa r ON r.no_rawat = d.no_rawat {$join} INNER JOIN databarang b ON b.kode_brng = d.kode_brng WHERE r.tgl_registrasi >= ? AND r.tgl_registrasi < ? GROUP BY {$key}, {$label}, b.kode_brng, b.nama_brng ORDER BY unit, b.nama_brng", [$from->toDateString(), $to->addDay()->toDateString()]);
        $all = $isClinic ? $rows->filter(fn ($row) => (int) $row->ralan > 0)->map(fn ($row) => $this->medicineRow($row, 'ralan'))->values() : $rows->map(fn ($row) => $this->medicineRow($row, 'total'))->values();
        $datasets = $isClinic ? ['Obat per poliklinik' => $all] : [
            'Obat per dokter - Semua' => $all,
            'Obat per dokter - Rawat Jalan' => $rows->filter(fn ($row) => (int) $row->ralan > 0)->map(fn ($row) => $this->medicineRow($row, 'ralan'))->values(),
            'Obat per dokter - Rawat Inap' => $rows->filter(fn ($row) => (int) $row->ranap > 0)->map(fn ($row) => $this->medicineRow($row, 'ranap'))->values(),
        ];
        $columns = ['kode' => 'Kode', 'unit' => $isClinic ? 'Poliklinik' : 'Dokter', 'kode_brng' => 'Kode Barang', 'nama_brng' => 'Nama Barang', 'jumlah' => 'Jumlah', 'biaya_obat' => 'Biaya Obat', 'embalase' => 'Embalase', 'tuslah' => 'Tuslah', 'total' => 'Total'];
        $formats = ['jumlah' => 'number', 'biaya_obat' => 'currency', 'embalase' => 'currency', 'tuslah' => 'currency', 'total' => 'currency'];
        $sections = collect($datasets)->map(fn ($rows, $title) => ['title' => $title, 'columns' => $columns, 'formats' => $formats, 'rows' => $rows])->values()->all();

        return ['summary' => ['Jumlah item-unit' => $all->count(), 'Total' => $all->sum('total')], 'sections' => $sections];
    }

    private function medicineRow(object $row, string $quantityKey): object
    {
        if ($quantityKey === 'total') {
            return $row;
        }
        $source = $quantityKey === 'ralan' ? 'd_status___ralan' : 'd_status___ranap';
        $suffix = $quantityKey === 'ralan' ? 'd_status___ralan' : 'd_status___ranap';
        foreach (['jumlah', 'biaya_obat', 'embalase', 'tuslah', 'total'] as $key) {
            $row->{$key} = (float) $row->{$key.'_'.$suffix};
        }

        return $row;
    }

    private function vendors(int $year): array
    {
        $monthCases = collect(range(1, 12))->map(fn ($month) => "COALESCE(SUM(CASE WHEN a.bulan = {$month} THEN a.total ELSE 0 END), 0) AS bulan_{$month}")->implode(', ');
        $rows = $this->simrs->select("SELECT s.kode_suplier, s.nama_suplier, {$monthCases}, COALESCE(SUM(a.total), 0) AS total FROM datasuplier s LEFT JOIN (SELECT p.kode_suplier, MONTH(p.tgl_pesan) AS bulan, SUM(d.total) AS total FROM pemesanan p INNER JOIN detailpesan d ON d.no_faktur = p.no_faktur WHERE p.tgl_pesan >= ? AND p.tgl_pesan < ? GROUP BY p.kode_suplier, MONTH(p.tgl_pesan)) a ON a.kode_suplier = s.kode_suplier GROUP BY s.kode_suplier, s.nama_suplier ORDER BY s.nama_suplier", ["{$year}-01-01", ($year + 1).'-01-01']);
        $columns = ['kode_suplier' => 'Kode', 'nama_suplier' => 'Vendor'];
        $formats = ['total' => 'currency'];
        foreach (['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'] as $index => $label) {
            $columns['bulan_'.($index + 1)] = $label;
            $formats['bulan_'.($index + 1)] = 'currency';
        }
        $columns['total'] = 'Total';

        return ['summary' => ['Jumlah vendor' => $rows->count(), 'Total penerimaan' => $rows->sum('total')], 'sections' => [['title' => "Penerimaan vendor {$year}", 'columns' => $columns, 'formats' => $formats, 'rows' => $rows]]];
    }

    private function itemReport(string $title, $rows): array
    {
        $columns = ['kode_brng' => 'Kode', 'nama_brng' => 'Nama Barang', 'jenis' => 'Jenis', 'kategori' => 'Kategori', 'golongan' => 'Golongan', 'satuan' => 'Satuan', 'jumlah' => 'Jumlah', 'total' => 'Total'];
        $formats = ['jumlah' => 'number', 'total' => 'currency'];

        $subtotal = fn (string $key) => $rows->groupBy($key)->map(fn ($items, $label) => (object) ['label' => $label, 'total' => $items->sum('total')])->sortByDesc('total')->values();
        $subtotalColumns = ['label' => 'Kategori', 'total' => 'Total'];

        return ['summary' => ['Jumlah item' => $rows->count(), 'Total kuantitas' => $rows->sum('jumlah'), 'Total nilai' => $rows->sum('total')], 'sections' => [
            ['title' => $title, 'columns' => $columns, 'formats' => $formats, 'rows' => $rows],
            ['title' => 'Nilai berdasarkan jenis', 'columns' => $subtotalColumns, 'formats' => ['total' => 'currency'], 'rows' => $subtotal('jenis')],
            ['title' => 'Nilai berdasarkan golongan', 'columns' => $subtotalColumns, 'formats' => ['total' => 'currency'], 'rows' => $subtotal('golongan')],
            ['title' => 'Nilai berdasarkan kategori', 'columns' => $subtotalColumns, 'formats' => ['total' => 'currency'], 'rows' => $subtotal('kategori')],
            ['title' => '10 kuantitas tertinggi', 'columns' => $columns, 'formats' => $formats, 'rows' => $rows->sortByDesc('jumlah')->take(10)->values()],
            ['title' => '10 nilai tertinggi', 'columns' => $columns, 'formats' => $formats, 'rows' => $rows->sortByDesc('total')->take(10)->values()],
        ]];
    }
}
