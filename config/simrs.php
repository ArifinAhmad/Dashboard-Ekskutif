<?php

return [
    'connection' => 'simrs',
    'igd_code' => env('SIMRS_IGD_CODE', 'IGDK'),
    'cache_ttl' => (int) env('SIMRS_CACHE_TTL', 300),
    'dashboard_refresh_seconds' => (int) env('SIMRS_DASHBOARD_REFRESH_SECONDS', 600),
    'cache_store' => env('SIMRS_CACHE_STORE', 'redis'),
    'report_cache_version' => env('SIMRS_REPORT_CACHE_VERSION', 'v2'),
    'report_cache_today_ttl' => (int) env('SIMRS_REPORT_CACHE_TODAY_TTL', 120),
    'report_cache_recent_ttl' => (int) env('SIMRS_REPORT_CACHE_RECENT_TTL', 900),
    'report_cache_history_ttl' => (int) env('SIMRS_REPORT_CACHE_HISTORY_TTL', 86400),
    'report_cache_recent_days' => (int) env('SIMRS_REPORT_CACHE_RECENT_DAYS', 7),
    'date_range_max_days' => (int) env('SIMRS_DATE_RANGE_MAX_DAYS', 366),
    'report_page_size' => (int) env('SIMRS_REPORT_PAGE_SIZE', 10),
    'sync_export_max_rows' => (int) env('SIMRS_SYNC_EXPORT_MAX_ROWS', 25000),
    'sync_pdf_max_rows' => (int) env('SIMRS_SYNC_PDF_MAX_ROWS', 5000),
];
