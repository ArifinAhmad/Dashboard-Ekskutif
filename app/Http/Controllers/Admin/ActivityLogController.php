<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->hasPermission('admin.manage'), 403);
        $filters = $request->validate([
            'type' => ['nullable', 'in:all,activity,error'],
            'search' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $logs = AuditLog::query()->with('user:id,name,email')
            ->when(($filters['type'] ?? 'all') === 'error', fn ($query) => $query->where('event', 'system.error'))
            ->when(($filters['type'] ?? 'all') === 'activity', fn ($query) => $query->where('event', '<>', 'system.error'))
            ->when($filters['date'] ?? null, function ($query, $date) {
                $start = CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay();
                $query->where('created_at', '>=', $start)->where('created_at', '<', $start->addDay());
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('event', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.activity-logs.index', compact('logs', 'filters'));
    }
}
