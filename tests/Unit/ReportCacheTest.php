<?php

namespace Tests\Unit;

use App\Services\Simrs\CashierReport;
use App\Services\Simrs\FinanceReport;
use App\Services\Simrs\LogisticsReport;
use App\Services\Simrs\PharmacyReport;
use App\Services\Simrs\QualityReport;
use App\Services\Simrs\ReportManager;
use App\Services\Simrs\ServiceVolumeReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ReportCacheTest extends TestCase
{
    public function test_report_is_loaded_once_and_then_served_from_local_cache(): void
    {
        config(['simrs.cache_store' => 'array', 'simrs.report_cache_today_ttl' => 120]);
        Cache::store('array')->flush();
        CarbonImmutable::setTestNow('2026-09-24 01:00:00');
        $services = Mockery::mock(ServiceVolumeReport::class);
        $services->shouldReceive('get')->once()->andReturn(['summary' => ['Total' => 10], 'sections' => []]);
        $manager = $this->manager($services);
        $date = CarbonImmutable::parse('2026-09-24');

        $first = $manager->get('pelayanan', 'rawat-jalan', $date, $date);
        $second = $manager->get('pelayanan', 'rawat-jalan', $date, $date);

        $this->assertFalse($first['_cache']['hit']);
        $this->assertTrue($second['_cache']['hit']);
        $this->assertTrue($manager->has('pelayanan', 'rawat-jalan', $date, $date));
        $this->assertSame(120, $second['_cache']['ttl']);
        $this->assertSame(10, $second['summary']['Total']);
        CarbonImmutable::setTestNow();
    }

    public function test_forget_forces_the_report_to_be_fetched_again(): void
    {
        config(['simrs.cache_store' => 'array']);
        Cache::store('array')->flush();
        $services = Mockery::mock(ServiceVolumeReport::class);
        $services->shouldReceive('get')->twice()->andReturn(['summary' => [], 'sections' => []]);
        $manager = $this->manager($services);
        $date = CarbonImmutable::today();

        $manager->get('pelayanan', 'igd', $date, $date);
        $manager->forget('pelayanan', 'igd', $date, $date);
        $result = $manager->get('pelayanan', 'igd', $date, $date);

        $this->assertFalse($result['_cache']['hit']);
    }

    private function manager(ServiceVolumeReport $services): ReportManager
    {
        return new ReportManager(
            $services,
            Mockery::mock(LogisticsReport::class),
            Mockery::mock(PharmacyReport::class),
            Mockery::mock(FinanceReport::class),
            Mockery::mock(QualityReport::class),
            Mockery::mock(CashierReport::class),
        );
    }
}
