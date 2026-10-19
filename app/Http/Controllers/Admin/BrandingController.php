<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBrandingRequest;
use App\Services\AuditService;
use App\Services\BrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrandingController extends Controller
{
    public function edit(Request $request, BrandingService $branding): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return view('admin.branding.edit', ['logoUrl' => $branding->logoUrl(), 'hospital' => $branding->hospitalProfile()]);
    }

    public function update(UpdateBrandingRequest $request, BrandingService $branding, AuditService $audit): RedirectResponse
    {
        if ($request->hasFile('logo')) {
            $branding->replaceLogo($request->file('logo'));
        }
        $branding->updateHospitalProfile($request->safe()->only(['name', 'address', 'phone', 'email', 'website']));
        $audit->record($request, 'admin.branding_updated', 'administration');

        return back()->with('status', 'Branding dan informasi rumah sakit berhasil diperbarui.');
    }
}
