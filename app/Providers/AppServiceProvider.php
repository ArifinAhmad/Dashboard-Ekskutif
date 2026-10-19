<?php

namespace App\Providers;

use App\Services\BrandingService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.app', 'auth.login', 'dashboard'], function ($view): void {
            $branding = app(BrandingService::class);
            $view->with([
                'applicationLogoUrl' => $branding->logoUrl(),
                'hospitalProfile' => $branding->hospitalProfile(),
            ]);
        });
    }
}
