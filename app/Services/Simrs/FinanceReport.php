<?php

namespace App\Services\Simrs;

use Carbon\CarbonImmutable;

class FinanceReport
{
    private const PAYABLES = [
        'hutang-farmasi' => ['orders' => 'pemesanan', 'suppliers' => 'datasuplier', 'payments' => 'bayar_pemesanan'],
        'hutang-nonmedis' => ['orders' => 'ipsrspemesanan', 'suppliers' => 'ipsrssuplier', 'payments' => 'bayar_pemesanan_non_medis'],
        'hutang-dapur' => ['orders' => 'dapurpemesanan', 'suppliers' => 'dapursuplier', 'payments' => 'bayar_pemesanan_dapur'],
        'hutang-inventaris' => ['orders' => 'inventaris_pemesanan', 'suppliers' => 'inventaris_suplier', 'payments' => 'bayar_pemesanan_inventaris'],
    ];

    public function __construct(private ReadOnlySimrs $simrs) {}

    public function get(string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($report) {
            'hutang-farmasi', 'hutang-nonmedis', 'hutang-dapur', 'hutang-inventaris' => $this->payables($report),
            'hutang-lain' => $this->otherPayables(),
            'piutang-pasien' => $this->patientReceivables(),
            'piutang-obat' => $this->medicineReceivables(),
            'rekening-tahun' => $this->annualAccounts($from->year),
            'saldo-bulanan' => $this->monthlyBalances($from->year),
            'laporan-keuangan' => $this->financialStatement($from->year),
            default => ['summary' => [], 'sections' => []],
        };
    }

    private function payables(string $report): array
    {
        $config = self::PAYABLES[$report];
        $rows = $this->simrs->select("SELECT s.kode_suplier, s.nama_suplier, SUM(i.sisa) AS sisa_hutang FROM {$config['suppliers']} s INNER JOIN (SELECT o.kode_suplier, GREATEST(o.tagihan - COALESCE(p.dibayar, 0), 0) AS sisa FROM {$config['orders']} o LEFT JOIN (SELECT no_faktur, SUM(besar_bayar) AS dibayar FROM {$config['payments']} GROUP BY no_faktur) p ON p.no_faktur = o.no_faktur WHERE o.status IN ('Belum Dibayar', 'Belum Lunas')) i ON i.kode_suplier = s.kode_suplier GROUP BY s.kode_suplier, s.nama_suplier HAVING sisa_hutang > 0 ORDER BY s.nama_suplier");

        return $this->moneyReport('Sisa hutang per vendor', $rows, ['kode_suplier' => 'Kode', 'nama_suplier' => 'Vendor', 'sisa_hutang' => 'Sisa Hutang'], 'sisa_hutang');
    }

    private function otherPayables(): array
    {
        $rows = $this->simrs->select("SELECT p.kode_pemberi_hutang, p.nama_pemberi_hutang, SUM(b.sisahutang) AS sisa_hutang FROM beban_hutang_lain b INNER JOIN pemberi_hutang_lain p ON p.kode_pemberi_hutang = b.kode_pemberi_hutang WHERE b.status = 'Belum Lunas' GROUP BY p.kode_pemberi_hutang, p.nama_pemberi_hutang ORDER BY p.nama_pemberi_hutang");

        return $this->moneyReport('Sisa beban hutang lain', $rows, ['kode_pemberi_hutang' => 'Kode', 'nama_pemberi_hutang' => 'Pemberi Hutang', 'sisa_hutang' => 'Sisa Hutang'], 'sisa_hutang');
    }

    private function patientReceivables(): array
    {
        $rows = $this->simrs->select(<<<'SQL'
            SELECT p.kd_pj, p.png_jawab, SUM(d.sisapiutang) AS sisa_piutang
            FROM detail_piutang_pasien d
            INNER JOIN piutang_pasien pi ON pi.no_rawat = d.no_rawat
            INNER JOIN penjab p ON p.kd_pj = d.kd_pj
            WHERE d.sisapiutang >= 1
            GROUP BY p.kd_pj, p.png_jawab
            ORDER BY p.png_jawab
        SQL);

        return $this->moneyReport('Piutang pasien per penjamin', $rows, ['kd_pj' => 'Kode', 'png_jawab' => 'Penjamin', 'sisa_piutang' => 'Sisa Piutang'], 'sisa_piutang');
    }

    private function medicineReceivables(): array
    {
        $rows = $this->simrs->select(<<<'SQL'
            SELECT p.no_rkm_medis, p.nm_pasien,
                SUM(GREATEST(p.sisapiutang - COALESCE(b.dibayar, 0), 0)) AS sisa_piutang
            FROM piutang p
            LEFT JOIN (
                SELECT no_rawat, SUM(besar_cicilan + diskon_piutang + tidak_terbayar) AS dibayar
                FROM bayar_piutang GROUP BY no_rawat
            ) b ON b.no_rawat = p.nota_piutang
            GROUP BY p.no_rkm_medis, p.nm_pasien
            HAVING sisa_piutang > 0
            ORDER BY p.nm_pasien
        SQL);

        return $this->moneyReport('Piutang obat per pasien', $rows, ['no_rkm_medis' => 'No. RM', 'nm_pasien' => 'Pasien', 'sisa_piutang' => 'Sisa Piutang'], 'sisa_piutang');
    }

    private function annualAccounts(int $year): array
    {
        $rows = $this->accountBalances($year);

        return [
            'summary' => ['Jumlah rekening' => $rows->count(), 'Total saldo akhir' => $rows->sum('saldo_akhir')],
            'sections' => [$this->accountSection("Rekening tahun {$year}", $rows)],
        ];
    }

    private function monthlyBalances(int $year): array
    {
        $rows = $this->simrs->select(<<<'SQL'
            SELECT r.kd_rek, r.nm_rek, MONTH(j.tgl_jurnal) AS bulan,
                SUM(d.debet) AS debet, SUM(d.kredit) AS kredit
            FROM rekening r
            INNER JOIN detailjurnal d ON d.kd_rek = r.kd_rek
            INNER JOIN jurnal j ON j.no_jurnal = d.no_jurnal
            WHERE j.tgl_jurnal >= ? AND j.tgl_jurnal < ?
            GROUP BY r.kd_rek, r.nm_rek, MONTH(j.tgl_jurnal)
            ORDER BY r.kd_rek, bulan
        SQL, ["{$year}-01-01", ($year + 1).'-01-01']);

        return [
            'summary' => ['Jumlah rekening' => $rows->unique('kd_rek')->count()],
            'sections' => [[
                'title' => "Mutasi rekening bulanan {$year}",
                'columns' => ['kd_rek' => 'Kode', 'nm_rek' => 'Rekening', 'bulan' => 'Bulan', 'debet' => 'Debet', 'kredit' => 'Kredit'],
                'formats' => ['bulan' => 'number', 'debet' => 'currency', 'kredit' => 'currency'],
                'rows' => $rows,
            ]],
        ];
    }

    private function financialStatement(int $year): array
    {
        $rows = $this->accountBalances($year);
        $sections = collect([
            'Pendapatan' => $rows->where('tipe', 'R')->where('balance', 'K'),
            'Beban' => $rows->where('tipe', 'R')->where('balance', 'D'),
            'Modal' => $rows->where('tipe', 'M'),
            'Aset' => $rows->where('tipe', 'N')->where('balance', 'D'),
            'Kewajiban' => $rows->where('tipe', 'N')->where('balance', 'K'),
        ])->map(fn ($accounts, $title) => $this->accountSection($title, $accounts))->values()->all();
        $income = $rows->where('tipe', 'R')->where('balance', 'K')->sum('saldo_akhir');
        $expense = $rows->where('tipe', 'R')->where('balance', 'D')->sum('saldo_akhir');

        return [
            'summary' => ['Pendapatan' => $income, 'Beban' => $expense, 'Laba / Rugi' => $income - $expense],
            'sections' => $sections,
        ];
    }

    private function accountBalances(int $year)
    {
        return $this->simrs->select(<<<'SQL'
            SELECT r.kd_rek, r.nm_rek, r.tipe, r.balance,
                COALESCE(a.saldo_awal, 0) AS saldo_awal,
                COALESCE(m.debet, 0) AS debet, COALESCE(m.kredit, 0) AS kredit,
                COALESCE(a.saldo_awal, 0) + CASE WHEN r.balance = 'D' THEN COALESCE(m.debet, 0) - COALESCE(m.kredit, 0) ELSE COALESCE(m.kredit, 0) - COALESCE(m.debet, 0) END AS saldo_akhir
            FROM rekening r
            LEFT JOIN (SELECT kd_rek, SUM(saldo_awal) AS saldo_awal FROM rekeningtahun WHERE thn = ? GROUP BY kd_rek) a ON a.kd_rek = r.kd_rek
            LEFT JOIN (SELECT d.kd_rek, SUM(d.debet) AS debet, SUM(d.kredit) AS kredit FROM detailjurnal d INNER JOIN jurnal j ON j.no_jurnal = d.no_jurnal WHERE j.tgl_jurnal >= ? AND j.tgl_jurnal < ? GROUP BY d.kd_rek) m ON m.kd_rek = r.kd_rek
            ORDER BY r.kd_rek
        SQL, [$year, "{$year}-01-01", ($year + 1).'-01-01']);
    }

    private function moneyReport(string $title, $rows, array $columns, string $moneyKey): array
    {
        return ['summary' => ['Jumlah entitas' => $rows->count(), 'Total' => $rows->sum($moneyKey)], 'sections' => [[
            'title' => $title, 'columns' => $columns, 'formats' => [$moneyKey => 'currency'], 'rows' => $rows,
        ]]];
    }

    private function accountSection(string $title, $rows): array
    {
        return [
            'title' => $title,
            'columns' => ['kd_rek' => 'Kode', 'nm_rek' => 'Rekening', 'saldo_awal' => 'Saldo Awal', 'debet' => 'Debet', 'kredit' => 'Kredit', 'saldo_akhir' => 'Saldo Akhir'],
            'formats' => ['saldo_awal' => 'currency', 'debet' => 'currency', 'kredit' => 'currency', 'saldo_akhir' => 'currency'],
            'rows' => collect($rows),
        ];
    }
}
