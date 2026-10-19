<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Services\AuditService;
use App\Services\BrandingService;
use App\Services\ReportExcelService;
use App\Services\Simrs\ReadOnlySimrs;
use App\Services\Simrs\ReportManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function show(ReportFilterRequest $request, string $module, string $report, ReadOnlySimrs $simrs, ReportManager $reports, AuditService $audit): View
    {
        [$moduleConfig, $reportConfig] = $this->configuration($module, $report);
        abort_unless($request->user()->hasPermission($moduleConfig['permission']), 403);

        $today = now()->toDateString();
        $from = CarbonImmutable::parse($request->validated('from', $today));
        $to = CarbonImmutable::parse($request->validated('to', $today));
        abort_if($from->diffInDays($to) > config('simrs.date_range_max_days'), 422, 'Rentang tanggal terlalu besar.');

        $implemented = (bool) ($reportConfig['implemented'] ?? false);
        if ($request->boolean('refresh')) {
            $reports->forget($module, $report, $from, $to);
        }
        $cached = $implemented && $reports->has($module, $report, $from, $to);
        $connected = $cached || $simrs->healthy();
        $result = $connected && $implemented ? $reports->get($module, $report, $from, $to) : ['summary' => [], 'sections' => []];
        if ($connected && $implemented) {
            $result['sections'] = $this->paginateSections($result['sections'], $request->query());
        }
        $audit->record($request, 'report.view', $module, compact('report', 'from', 'to', 'implemented'));

        return view('reports.show', compact('module', 'report', 'moduleConfig', 'reportConfig', 'from', 'to', 'connected', 'implemented', 'result'));
    }

    public function export(ReportFilterRequest $request, string $module, string $report, ReadOnlySimrs $simrs, ReportManager $reports, AuditService $audit, ReportExcelService $excel): StreamedResponse
    {
        [$moduleConfig, $reportConfig] = $this->configuration($module, $report);
        abort_unless($request->user()->hasPermission($moduleConfig['permission']) && $request->user()->hasPermission('report.export'), 403);
        abort_unless($reportConfig['implemented'] ?? false, 503);
        $today = now()->toDateString();
        $from = CarbonImmutable::parse($request->validated('from', $today));
        $to = CarbonImmutable::parse($request->validated('to', $today));
        abort_if($from->diffInDays($to) > config('simrs.date_range_max_days'), 422, 'Rentang tanggal terlalu besar.');
        abort_unless($reports->has($module, $report, $from, $to) || $simrs->healthy(), 503);
        $result = $reports->get($module, $report, $from, $to);
        $totalRows = collect($result['sections'])->sum(fn ($section) => collect($section['rows'])->count());
        abort_if($totalRows > config('simrs.sync_export_max_rows'), 422, 'Data terlalu besar untuk ekspor langsung. Persempit rentang tanggal.');
        $audit->record($request, 'report.export', $module, compact('report', 'from', 'to'));

        $filename = "{$module}-{$report}-{$from->toDateString()}-{$to->toDateString()}.xlsx";

        return response()->streamDownload(function () use ($excel, $result, $moduleConfig, $reportConfig, $from, $to, $filename): void {
            $excel->download($result['sections'], $moduleConfig['label'], $reportConfig['label'], $from, $to, $filename);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function exportPdf(ReportFilterRequest $request, string $module, string $report, ReadOnlySimrs $simrs, ReportManager $reports, AuditService $audit, BrandingService $branding): Response
    {
        [$moduleConfig, $reportConfig] = $this->configuration($module, $report);
        abort_unless($request->user()->hasPermission($moduleConfig['permission']) && $request->user()->hasPermission('report.export'), 403);
        abort_unless($reportConfig['implemented'] ?? false, 503);
        $today = now()->toDateString();
        $from = CarbonImmutable::parse($request->validated('from', $today));
        $to = CarbonImmutable::parse($request->validated('to', $today));
        abort_if($from->diffInDays($to) > config('simrs.date_range_max_days'), 422, 'Rentang tanggal terlalu besar.');
        abort_unless($reports->has($module, $report, $from, $to) || $simrs->healthy(), 503);
        $result = $reports->get($module, $report, $from, $to);
        $totalRows = collect($result['sections'])->sum(fn ($section) => collect($section['rows'])->count());
        abort_if($totalRows > config('simrs.sync_pdf_max_rows'), 422, 'Data terlalu besar untuk PDF langsung. Persempit rentang tanggal.');
        $audit->record($request, 'report.export_pdf', $module, compact('report', 'from', 'to'));

        return Pdf::loadView('reports.pdf', [
            'moduleConfig' => $moduleConfig,
            'reportConfig' => $reportConfig,
            'from' => $from,
            'to' => $to,
            'result' => $result,
            'logoDataUri' => $branding->logoDataUri(),
            'hospital' => $branding->hospitalProfile(),
        ])->setPaper('a4', 'landscape')->download("{$module}-{$report}-{$from->toDateString()}-{$to->toDateString()}.pdf");
    }

    private function configuration(string $module, string $report): array
    {
        $moduleConfig = config("reports.{$module}");
        $reportConfig = $moduleConfig['reports'][$report] ?? null;
        abort_unless($moduleConfig && $reportConfig, 404);

        return [$moduleConfig, $reportConfig];
    }

    private function paginateSections(array $sections, array $query): array
    {
        $perPage = config('simrs.report_page_size');

        return collect($sections)->map(function (array $section, int $index) use ($query, $perPage): array {
            $rows = collect($section['rows']);
            $sortableColumns = array_keys($section['columns']);
            $sortColumn = data_get($query, "section_sort.{$index}");
            $sortDirection = data_get($query, "section_direction.{$index}", 'asc');
            if (in_array($sortColumn, $sortableColumns, true) && in_array($sortDirection, ['asc', 'desc'], true)) {
                $rows = $rows->sort(function ($left, $right) use ($sortColumn, $sortDirection): int {
                    $leftValue = data_get($left, $sortColumn);
                    $rightValue = data_get($right, $sortColumn);
                    $comparison = is_numeric($leftValue) && is_numeric($rightValue)
                        ? (float) $leftValue <=> (float) $rightValue
                        : strnatcasecmp((string) $leftValue, (string) $rightValue);

                    return $sortDirection === 'desc' ? -$comparison : $comparison;
                })->values();
            } else {
                $sortColumn = null;
                $sortDirection = 'asc';
            }
            $lastPage = max(1, (int) ceil($rows->count() / $perPage));
            $page = min($lastPage, max(1, (int) data_get($query, "section_page.{$index}", 1)));
            $section['total_rows'] = $rows->count();
            $section['page'] = $page;
            $section['last_page'] = $lastPage;
            $section['row_offset'] = ($page - 1) * $perPage;
            $section['sort_column'] = $sortColumn;
            $section['sort_direction'] = $sortDirection;
            $section['rows'] = $rows->slice($section['row_offset'], $perPage)->values();

            return $section;
        })->all();
    }
}
