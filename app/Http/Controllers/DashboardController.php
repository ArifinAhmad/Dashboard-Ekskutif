<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Services\AuditService;
use App\Services\DashboardSettingsService;
use App\Services\Simrs\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardFilterRequest $request, DashboardService $dashboard, AuditService $audit, DashboardSettingsService $dashboardSettings): View
    {
        $today = now()->toDateString();
        $from = CarbonImmutable::parse($request->validated('from', $today));
        $to = CarbonImmutable::parse($request->validated('to', $today));
        abort_if($from->diffInDays($to) > config('simrs.date_range_max_days'), 422, 'Rentang tanggal terlalu besar.');
        try {
            $data = $dashboard->get($from, $to);
            $connected = true;
        } catch (\Throwable) {
            $connected = false;
            $data = ['registration' => null, 'inpatient' => null, 'payers' => collect()];
        }
        $audit->record($request, 'dashboard.view', 'dashboard', ['source_connected' => $connected, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
        $settings = $dashboardSettings->get();

        return view('dashboard', compact('data', 'connected', 'settings', 'from', 'to'));
    }
}
