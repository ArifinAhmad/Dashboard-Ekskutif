<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | Dashboard {{ $hospitalProfile['name'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-session-lifetime="{{ config('session.lifetime') }}" data-login-url="{{ route('login') }}">
<form method="POST" action="{{ route('logout') }}" data-session-logout hidden>@csrf</form>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="mobile-drawer-head"><span>Menu Utama</span><button type="button" data-menu-close aria-label="Tutup menu">×</button></div>
        <a class="brand" href="{{ route('dashboard') }}">
            @if($applicationLogoUrl)<span class="brand-logo"><img src="{{ $applicationLogoUrl }}" alt="Logo {{ $hospitalProfile['name'] }}"></span>@else<span class="brand-mark">DB</span>@endif
            <span><strong>Dashboard</strong><small>{{ $hospitalProfile['name'] }}</small></span>
        </a>
        <nav aria-label="Navigasi utama">
            @if(auth()->user()->hasPermission('dashboard.view'))
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
            @endif
            @foreach(config('reports') as $moduleKey => $module)
                @if(auth()->user()->hasPermission($module['permission']))
                    <details class="nav-group" {{ request()->route('module') === $moduleKey ? 'open' : '' }}>
                        <summary>{{ $module['label'] }}</summary>
                        @foreach($module['reports'] as $reportKey => $report)
                            <a class="nav-link sub {{ request()->route('module') === $moduleKey && request()->route('report') === $reportKey ? 'active' : '' }}" href="{{ route('reports.show', [$moduleKey, $reportKey]) }}"><span>{{ $report['label'] }}</span>@if(!($report['implemented'] ?? false))<small>Roadmap</small>@endif</a>
                        @endforeach
                    </details>
                @endif
            @endforeach
            @if(auth()->user()->hasPermission('admin.manage'))
                <div class="nav-section-label">Administrasi</div>
                <a class="nav-link {{ request()->routeIs('admin.access.*', 'admin.users.*', 'admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.access.index') }}">Pengguna & Role</a>
                @if(auth()->user()->isSuperAdmin())<a class="nav-link {{ request()->routeIs('admin.branding.*') ? 'active' : '' }}" href="{{ route('admin.branding.edit') }}">Branding Aplikasi</a>@endif
                <a class="nav-link {{ request()->routeIs('admin.dashboard-settings.*') ? 'active' : '' }}" href="{{ route('admin.dashboard-settings.edit') }}">Pengaturan Dashboard</a>
                <a class="nav-link {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}" href="{{ route('admin.activity-logs.index') }}">Log Aktivitas & Error</a>
            @endif
        </nav>
        <div class="sidebar-user">
            <span>{{ auth()->user()->name }}</span>
            <small>{{ auth()->user()->email }}</small>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-button" type="submit">Keluar</button></form>
        </div>
    </aside>
    <button class="sidebar-backdrop" type="button" data-menu-close aria-label="Tutup menu"></button>
    <main class="main-content">
        <header class="topbar">
            <button class="menu-button" type="button" data-menu-toggle aria-controls="sidebar" aria-expanded="true" aria-label="Sembunyikan sidebar" title="Sembunyikan / tampilkan sidebar"><span></span><span></span><span></span></button>
            <div><strong>@yield('title', 'Dashboard')</strong><span>{{ now()->translatedFormat('l, d F Y H:i') }} WIB</span></div>
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Ganti mode warna" title="Ganti mode warna"><svg class="sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg><svg class="moon" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg></button>
        </header>
        <section class="page-content">@yield('content')</section>
    </main>
</div>
<nav class="mobile-bottom-nav" aria-label="Navigasi mobile">
    @if(auth()->user()->hasPermission('dashboard.view'))<a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg><span>Beranda</span></a>@else<a href="{{ url()->current() }}" aria-current="page"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg><span>Halaman</span></a>@endif
    <button type="button" data-bottom-menu><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>Menu</span></button>
    <button type="button" data-bottom-theme><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg><span>Tema</span></button>
    <button type="button" data-bottom-logout><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M15 4h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5"/></svg><span>Keluar</span></button>
</nav>
</body>
</html>
