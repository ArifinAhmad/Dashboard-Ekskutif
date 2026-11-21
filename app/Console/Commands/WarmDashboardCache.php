<?php

namespace App\Console\Commands;

use App\Services\Simrs\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class WarmDashboardCache extends Command
{
    protected $signature = 'simrs:warm-dashboard {--date= : Tanggal dashboard dalam format YYYY-MM-DD}';

    protected $description = 'Menghangatkan cache dashboard SIMRS secara read-only';

    public function handle(DashboardService $dashboard): int
    {
        $date = CarbonImmutable::parse($this->option('date') ?: now()->toDateString());

        try {
            $dashboard->get($date, $date);
        } catch (\Throwable $exception) {
            $this->components->error('Cache dashboard gagal dihangatkan: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Cache dashboard '.$date->toDateString().' siap.');

        return self::SUCCESS;
    }
}
