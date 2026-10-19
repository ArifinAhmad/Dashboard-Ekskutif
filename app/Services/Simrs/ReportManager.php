<?php

namespace App\Services\Simrs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class ReportManager
{
    public function __construct(
        private ServiceVolumeReport $services,
        private LogisticsReport $logistics,
        private PharmacyReport $pharmacy,
        private FinanceReport $finance,
        private QualityReport $quality,
        private CashierReport $cashier,
    ) {}

    public function get(string $module, string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cache = $this->cache();
        $key = $this->cacheKey($module, $report, $from, $to);
        $cached = $cache->get($key);
        if (is_array($cached)) {
            $cached['_cache']['hit'] = true;

            return $cached;
        }

        $lock = $cache->lock($key.':lock', 120);
        if ($lock->get()) {
            try {
                $cached = $cache->get($key);
                if (is_array($cached)) {
                    $cached['_cache']['hit'] = true;

                    return $cached;
                }

                return $this->resolveAndCache($cache, $key, $module, $report, $from, $to);
            } finally {
                $lock->release();
            }
        }

        return $lock->block(15, function () use ($cache, $key, $module, $report, $from, $to): array {
            $cached = $cache->get($key);

            return is_array($cached) ? array_replace_recursive($cached, ['_cache' => ['hit' => true]]) : $this->resolveAndCache($cache, $key, $module, $report, $from, $to);
        });
    }

    private function resolveAndCache(Repository $cache, string $key, string $module, string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $ttl = $this->ttl($to);
        $result = $this->resolve($module, $report, $from, $to);
        $result['_cache'] = [
            'hit' => false,
            'stored_at' => now()->toIso8601String(),
            'expires_at' => now()->addSeconds($ttl)->toIso8601String(),
            'ttl' => $ttl,
        ];
        $cache->put($key, $result, $ttl);

        return $result;
    }

    public function forget(string $module, string $report, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $this->cache()->forget($this->cacheKey($module, $report, $from, $to));
    }

    public function has(string $module, string $report, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return $this->cache()->has($this->cacheKey($module, $report, $from, $to));
    }

    private function resolve(string $module, string $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($module) {
            'pelayanan' => $this->services->get($report, $from, $to),
            'farmasi' => $this->pharmacy->get($report, $from, $to),
            'nonmedis', 'dapur' => $this->logistics->get($module, $report, $from, $to),
            'keuangan' => $this->finance->get($report, $from, $to),
            'mutu' => $this->quality->get($report, $from, $to),
            'kasir' => $this->cashier->get($report, $from, $to),
            default => ['summary' => [], 'sections' => []],
        };
    }

    private function cache(): Repository
    {
        return Cache::store(config('simrs.cache_store'));
    }

    private function cacheKey(string $module, string $report, CarbonImmutable $from, CarbonImmutable $to): string
    {
        return implode(':', [
            'simrs', 'report', config('simrs.report_cache_version'),
            $module, $report, $from->toDateString(), $to->toDateString(),
        ]);
    }

    private function ttl(CarbonImmutable $to): int
    {
        $today = CarbonImmutable::today(config('app.timezone'));
        if ($to->greaterThanOrEqualTo($today)) {
            return config('simrs.report_cache_today_ttl');
        }
        if ($to->greaterThanOrEqualTo($today->subDays(config('simrs.report_cache_recent_days')))) {
            return config('simrs.report_cache_recent_ttl');
        }

        return config('simrs.report_cache_history_ttl');
    }
}
