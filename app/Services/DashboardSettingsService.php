<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class DashboardSettingsService
{
    private const CACHE_KEY = 'dashboard.settings.v1';

    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDay(), function (): array {
            $settings = AppSetting::query()->whereIn('key', [
                'dashboard_refresh_seconds', 'dashboard_scroll_speed',
            ])->pluck('value', 'key');

            return [
                'refresh_seconds' => (int) ($settings['dashboard_refresh_seconds'] ?? config('simrs.dashboard_refresh_seconds', 600)),
                'scroll_speed' => (int) ($settings['dashboard_scroll_speed'] ?? 86),
            ];
        });
    }

    public function update(int $refreshMinutes, int $scrollSpeed): void
    {
        AppSetting::query()->upsert([
            ['key' => 'dashboard_refresh_seconds', 'value' => (string) ($refreshMinutes * 60), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'dashboard_scroll_speed', 'value' => (string) $scrollSpeed, 'created_at' => now(), 'updated_at' => now()],
        ], ['key'], ['value', 'updated_at']);
        Cache::forget(self::CACHE_KEY);
    }
}
