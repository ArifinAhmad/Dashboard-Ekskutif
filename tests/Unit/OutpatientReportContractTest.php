<?php

namespace Tests\Unit;

use App\Services\Simrs\ReadOnlySimrs;
use App\Services\Simrs\ServiceVolumeReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use Tests\TestCase;

class OutpatientReportContractTest extends TestCase
{
    public function test_outpatient_report_contains_all_legacy_sections(): void
    {
        $this->mock(ReadOnlySimrs::class, function (MockInterface $mock): void {
            $mock->shouldReceive('select')->times(11)->andReturnUsing(function (string $query): Collection {
                if (str_contains($query, "r.stts = 'Batal'")) {
                    return collect([(object) ['label' => 'Terlayani', 'total' => 8]]);
                }

                if (str_contains($query, "status_poli = 'Baru'")) {
                    return collect([(object) ['poli' => 'Poli Umum', 'baru' => 3, 'lama' => 5, 'total' => 8]]);
                }

                if (str_contains($query, "p.jk = 'L'")) {
                    return collect([(object) ['poli' => 'Poli Umum', 'laki_laki' => 4, 'perempuan' => 4, 'total' => 8]]);
                }

                return collect([(object) ['label' => 'Data', 'total' => 8]]);
            });
        });

        $result = app(ServiceVolumeReport::class)->get('rawat-jalan', CarbonImmutable::parse('2026-09-23'), CarbonImmutable::parse('2026-09-23'));
        $titles = collect($result['sections'])->pluck('title');

        $this->assertEquals([
            'Pelayanan Per Cara Bayar',
            'Pelayanan Per Dokter',
            'Pelayanan Per Poli',
            'Pelayanan Per Perujuk',
            'Pelayanan Per Tanggal',
            'Pelayanan Per Bulan',
            'Pelayanan Per Dokter Poli',
            'Pelayanan Pasien Baru & Lama Per Poli',
            'Pelayanan Pasien Laki & Perempuan Per Poli',
            'Pelayanan Per Cara Bayar Per Poli',
            'Status Pelayanan Pasien',
        ], $titles->all());
        $this->assertSame(3, $result['summary']['Pasien baru']);
        $this->assertSame(5, $result['summary']['Pasien lama']);
        $this->assertSame(4, $result['summary']['Pasien laki-laki']);
        $this->assertSame(4, $result['summary']['Pasien perempuan']);
        $this->assertSame(8, $result['summary']['Terlayani']);
    }
}
