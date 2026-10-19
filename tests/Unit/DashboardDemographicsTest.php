<?php

namespace Tests\Unit;

use App\Services\Simrs\DashboardService;
use App\Services\Simrs\ReadOnlySimrs;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

class DashboardDemographicsTest extends TestCase
{
    public function test_dashboard_returns_all_registered_patient_demographics(): void
    {
        config(['simrs.cache_store' => 'array']);
        Cache::store('array')->flush();
        $this->mock(ReadOnlySimrs::class, function (MockInterface $mock): void {
            $mock->shouldReceive('select')->times(11)->andReturnUsing(function (string $query): Collection {
                if (str_contains($query, 'COUNT(*) AS total')) {
                    return collect([(object) ['total' => 10, 'igd' => 2, 'rawat_jalan' => 8, 'pasien_baru' => 3, 'pasien_lama' => 7, 'batal' => 0, 'terlayani' => 10]]);
                }
                if (str_contains($query, ' AS masuk')) {
                    return collect([(object) ['masuk' => 1, 'keluar' => 1, 'aktif' => 5, 'meninggal' => 0]]);
                }
                if (str_contains($query, "k.status = 'KOSONG'")) {
                    return collect([(object) ['total' => 20, 'kosong' => 8, 'terisi' => 10, 'dibersihkan' => 1, 'dibooking' => 1, 'perbaikan' => 0]]);
                }

                return collect([(object) ['label' => 'Kategori', 'total' => 10]]);
            });
        });

        $result = app(DashboardService::class)->get();

        foreach (['ageGroups', 'genders', 'geographies', 'districtPatients', 'educations', 'occupations', 'referrals'] as $key) {
            $this->assertArrayHasKey($key, $result);
            $this->assertSame(10, $result[$key]->sum('total'));
        }
        $this->assertSame(8, (int) $result['beds']->kosong);
        $this->assertSame(10, (int) $result['beds']->terisi);
    }
}
