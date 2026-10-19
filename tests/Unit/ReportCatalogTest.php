<?php

namespace Tests\Unit;

use App\Services\Simrs\CashierReport;
use App\Services\Simrs\FinanceReport;
use App\Services\Simrs\LogisticsReport;
use App\Services\Simrs\PharmacyReport;
use App\Services\Simrs\QualityReport;
use App\Services\Simrs\ReportManager;
use App\Services\Simrs\ServiceVolumeReport;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class ReportCatalogTest extends TestCase
{
    #[Test]
    public function every_report_is_enabled_and_has_a_dispatcher(): void
    {
        $dispatchers = [
            'pelayanan' => ServiceVolumeReport::class,
            'farmasi' => PharmacyReport::class,
            'nonmedis' => LogisticsReport::class,
            'dapur' => LogisticsReport::class,
            'mutu' => QualityReport::class,
            'kasir' => CashierReport::class,
            'keuangan' => FinanceReport::class,
        ];

        foreach (config('reports') as $module => $moduleConfig) {
            $this->assertArrayHasKey($module, $dispatchers);
            foreach ($moduleConfig['reports'] as $report => $reportConfig) {
                $this->assertTrue($reportConfig['implemented'] ?? false, "{$module}/{$report} belum diaktifkan.");
            }
        }

        $manager = new ReflectionClass(ReportManager::class);
        $dependencies = collect($manager->getConstructor()?->getParameters())
            ->map(fn ($parameter) => $parameter->getType()?->getName());

        foreach (array_unique($dispatchers) as $dispatcher) {
            $this->assertContains($dispatcher, $dependencies);
        }
    }
}
