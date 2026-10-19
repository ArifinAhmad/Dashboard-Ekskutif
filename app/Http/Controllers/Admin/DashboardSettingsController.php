<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDashboardSettingsRequest;
use App\Services\AuditService;
use App\Services\DashboardSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardSettingsController extends Controller
{
    public function edit(Request $request, DashboardSettingsService $settings): View
    {
        abort_unless($request->user()->hasPermission('admin.manage'), 403);

        return view('admin.dashboard-settings.edit', ['settings' => $settings->get()]);
    }

    public function update(UpdateDashboardSettingsRequest $request, DashboardSettingsService $settings, AuditService $audit): RedirectResponse
    {
        $settings->update((int) $request->validated('refresh_minutes'), (int) $request->validated('scroll_speed'));
        $audit->record($request, 'admin.dashboard_settings_updated', 'administration', [
            'refresh_minutes' => (int) $request->validated('refresh_minutes'),
            'scroll_speed' => (int) $request->validated('scroll_speed'),
        ]);

        return back()->with('status', 'Pengaturan dashboard berhasil diperbarui.');
    }
}
