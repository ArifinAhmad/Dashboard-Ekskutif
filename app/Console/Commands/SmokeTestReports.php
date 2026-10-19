<?php

namespace App\Console\Commands;

use App\Services\Simrs\ReportManager;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SmokeTestReports extends Command
{
    protected $signature = 'simrs:reports-smoke {--from=} {--to=}';

    protected $description = 'Menjalankan smoke test read-only untuk seluruh query laporan aktif';

    public function handle(ReportManager $reports): int
    {
        $today = now()->toDateString();
        $from = CarbonImmutable::parse($this->option('from') ?: $today);
        $to = CarbonImmutable::parse($this->option('to') ?: $today);
        $failed = false;

        foreach (config('reports') as $module => $moduleConfig) {
            foreach ($moduleConfig['reports'] as $report => $reportConfig) {
                if (! ($reportConfig['implemented'] ?? false)) {
                    continue;
                }

                try {
                    $result = $reports->get($module, $report, $from, $to);
                    $rows = collect($result['sections'])->sum(fn ($section) => collect($section['rows'])->count());
                    $this->components->info("{$module}/{$report}: ".count($result['sections'])." section, {$rows} baris");
                } catch (\Throwable $exception) {
                    $failed = true;
                    $this->components->error("{$module}/{$report}: {$exception->getMessage()}");
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
