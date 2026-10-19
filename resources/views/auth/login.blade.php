<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk | Dashboard {{ $hospitalProfile['name'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
<main class="login-layout">
    <section class="login-story">
        <div class="login-brand">@if($applicationLogoUrl)<span class="login-logo-frame"><img src="{{ $applicationLogoUrl }}" alt="Logo {{ $hospitalProfile['name'] }}"></span>@else<span class="brand-mark login-logo-fallback">DB</span>@endif<span><strong>Dashboard</strong><small>{{ $hospitalProfile['name'] }}</small></span></div>
        <span class="eyebrow">SIMRS COMMAND CENTER</span>
        <h1>Selamat Datang di Dashboard {{ $hospitalProfile['name'] }}.</h1>
        <p>Dashboard pelayanan, mutu, inventori, kasir, dan keuangan dengan sumber data SIMRS yang terkontrol.</p>
        <div class="trust-row"><span>Read-only SIMRS</span><span>RBAC</span><span>Audit trail</span></div>
    </section>
    <section class="login-panel">
        <form method="POST" action="{{ route('login.store') }}" class="login-card">
            @csrf
            <div><span class="eyebrow">AKSES TERKENDALI</span><h2>Masuk ke Dashboard</h2><p>Gunakan akun yang diberikan administrator {{ $hospitalProfile['name'] }}.</p></div>
            @if($errors->any())<div class="alert danger">{{ $errors->first() }}</div>@endif
            <label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
            <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
            <label class="remember-option"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span><strong>Ingat Saya</strong><small>Tetap masuk secara aman pada perangkat pribadi ini. Password tidak disimpan oleh aplikasi.</small></span></label>
            <button class="button primary" type="submit">Masuk</button>
            <small>Akses dan aktivitas sensitif dicatat untuk keamanan.</small>
        </form>
    </section>
</main>
</body>
</html>
