<?php

use App\Http\Controllers\Admin\AccessManagementController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BrandingController;
use App\Http\Controllers\Admin\DashboardSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/branding/logo', BrandingAssetController::class)->name('branding.logo');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/laporan/{module}/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/laporan/{module}/{report}/data', [ReportController::class, 'data'])->name('reports.data');
    Route::get('/laporan/{module}/{report}/ekspor', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/laporan/{module}/{report}/ekspor-pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::prefix('administrasi')->middleware('permission:admin.manage')->name('admin.')->group(function () {
        Route::get('/akses', [AccessManagementController::class, 'index'])->name('access.index');
        Route::post('/pengguna', [AccessManagementController::class, 'storeUser'])->name('users.store');
        Route::put('/pengguna/{user}/akses', [AccessManagementController::class, 'updateUser'])->name('users.access.update');
        Route::delete('/pengguna/{user}', [AccessManagementController::class, 'destroyUser'])->name('users.destroy');
        Route::post('/role', [AccessManagementController::class, 'storeRole'])->name('roles.store');
        Route::put('/role/{role}', [AccessManagementController::class, 'updateRole'])->name('roles.update');
        Route::delete('/role/{role}', [AccessManagementController::class, 'destroyRole'])->name('roles.destroy');
        Route::get('/branding', [BrandingController::class, 'edit'])->name('branding.edit');
        Route::put('/branding', [BrandingController::class, 'update'])->name('branding.update');
        Route::get('/pengaturan-dashboard', [DashboardSettingsController::class, 'edit'])->name('dashboard-settings.edit');
        Route::put('/pengaturan-dashboard', [DashboardSettingsController::class, 'update'])->name('dashboard-settings.update');
        Route::get('/log-aktivitas', ActivityLogController::class)->name('activity-logs.index');
    });
});
