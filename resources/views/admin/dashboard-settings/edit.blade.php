@extends('layouts.app')
@section('title', 'Pengaturan Dashboard')
@section('content')
<div class="page-heading admin-heading"><div><span class="eyebrow">ADMINISTRASI SISTEM</span><h1>Pengaturan dashboard</h1><p>Atur frekuensi pembaruan data dan kecepatan auto-scroll untuk seluruh pengguna dashboard.</p></div><span class="status ok">Global</span></div>
@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert danger"><strong>Periksa pengaturan:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('admin.dashboard-settings.update') }}" class="panel dashboard-settings-form">@csrf @method('PUT')
    <div class="settings-grid">
        <label class="setting-card"><span class="setting-icon">↻</span><span><strong>Interval Auto-refresh</strong><small>Dashboard mengambil data terbaru sesuai interval ini.</small></span><div><input type="number" name="refresh_minutes" min="1" max="60" value="{{ old('refresh_minutes', intdiv($settings['refresh_seconds'], 60)) }}" required><em>menit</em></div></label>
        <label class="setting-card"><span class="setting-icon">↕</span><span><strong>Kecepatan Auto-scroll</strong><small>Semakin besar angka, semakin cepat pergerakan dashboard.</small></span><div><input type="number" name="scroll_speed" min="20" max="200" value="{{ old('scroll_speed', $settings['scroll_speed']) }}" required><em>px/detik</em></div></label>
    </div>
    <div class="settings-preview"><span>Pratinjau konfigurasi aktif</span><strong>Refresh {{ intdiv($settings['refresh_seconds'], 60) }} menit · Scroll {{ $settings['scroll_speed'] }} px/detik</strong></div>
    <div class="card-actions"><button class="button primary" type="submit">Simpan Pengaturan</button></div>
</form>
@endsection
