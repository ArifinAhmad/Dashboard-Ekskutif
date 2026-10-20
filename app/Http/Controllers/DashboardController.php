<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\DashboardSettingsService;
use App\Services\Simrs\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard, AuditService $audit, DashboardSettingsService $dashboardSettings): View
    {
        try {
            $data = $dashboard->get();
            $connected = true;
        } catch (\Throwable) {
            $connected = false;
            $data = ['registration' => null, 'inpatient' => null, 'payers' => collect()];
        }
        $audit->record($request, 'dashboard.view', 'dashboard', ['source_connected' => $connected]);
        $settings = $dashboardSettings->get();

        return view('dashboard', compact('data', 'connected', 'settings'));
    }
}
