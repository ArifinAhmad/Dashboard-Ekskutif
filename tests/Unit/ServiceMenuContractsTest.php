<?php

namespace Tests\Unit;

use App\Services\Simrs\ReadOnlySimrs;
use App\Services\Simrs\ServiceVolumeReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use Tests\TestCase;

class ServiceMenuContractsTest extends TestCase
{
    public function test_igd_contains_all_legacy_sections(): void
    {
        $report = $this->reportWithQueries(10)->get('igd', $this->date(), $this->date());

        $this->assertSame([
            'Pelayanan Per Cara Bayar', 'Pelayanan Per Dokter', 'Pelayanan Per Perujuk',
            'Pelayanan Per Tanggal', 'Pelayanan Per Bulan', 'Pasien Baru Per Tanggal',
            'Pasien Baru Per Bulan', 'Pasien Lama Per Tanggal', 'Pasien Lama Per Bulan',
            'Status Pelayanan Pasien',
        ], collect($report['sections'])->pluck('title')->all());
    }

    public function test_inpatient_contains_entry_and_discharge_legacy_sections(): void
    {
        $report = $this->reportWithQueries(17)->get('rawat-inap', $this->date(), $this->date());
        $titles = collect($report['sections'])->pluck('title');

        $this->assertCount(26, $titles);
        $this->assertSame('Ketersediaan Tempat Tidur Per Bangsal', $titles->first());
        $this->assertSame('Ketersediaan Tempat Tidur Per Kelas', $titles->get(1));
        $this->assertCount(12, $titles->filter(fn ($title) => str_starts_with($title, 'Tanggal Masuk')));
        $this->assertCount(12, $titles->filter(fn ($title) => str_starts_with($title, 'Tanggal Pulang')));
    }

    public function test_laboratory_and_radiology_contain_all_legacy_sections(): void
    {
        $expected = [
            'Pelayanan Per Pemeriksaan', 'Pelayanan Per Pemeriksaan Rawat Jalan', 'Pelayanan Per Pemeriksaan Rawat Inap',
            'Pelayanan Per Dokter Perujuk', 'Pelayanan Per Dokter Perujuk Rawat Inap', 'Pelayanan Per Dokter Perujuk Rawat Jalan',
            'Pelayanan Per Cara Bayar', 'Pelayanan Per Cara Bayar Rawat Inap', 'Pelayanan Per Cara Bayar Rawat Jalan',
        ];

        foreach (['laboratorium', 'radiologi'] as $menu) {
            $report = $this->reportWithQueries(3)->get($menu, $this->date(), $this->date());
            $this->assertSame($expected, collect($report['sections'])->pluck('title')->all());
        }
    }

    private function reportWithQueries(int $queries): ServiceVolumeReport
    {
        $simrs = $this->mock(ReadOnlySimrs::class, function (MockInterface $mock) use ($queries): void {
            $mock->shouldReceive('select')->times($queries)->andReturnUsing(function (string $query): Collection {
                if (str_contains($query, ' AS baru')) {
                    return collect([(object) ['label' => '23-09-2026', 'total' => 10, 'baru' => 4, 'lama' => 6]]);
                }
                if (str_contains($query, ' AS ralan')) {
                    return collect([(object) ['label' => 'Data', 'total' => 10, 'ralan' => 6, 'ranap' => 4]]);
                }

                return collect([(object) ['label' => 'Data', 'total' => 10]]);
            });
        });

        return new ServiceVolumeReport($simrs);
    }

    private function date(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-09-23');
    }
}
