@extends('layouts.app')
@section('title', 'Branding Aplikasi')
@section('content')
<div class="page-heading admin-heading"><div><span class="eyebrow">SUPER ADMIN</span><h1>Branding aplikasi</h1><p>Kelola identitas visual yang ditampilkan pada login, navigasi, dashboard, dan halaman laporan.</p></div><span class="status ok">Pengaturan sistem</span></div>

@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert danger"><strong>Logo tidak dapat disimpan.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="branding-layout">
    <article class="panel branding-preview-card">
        <div class="panel-head"><div><span class="eyebrow">PRATINJAU</span><h2>Logo aktif</h2></div></div>
        <div class="branding-preview">
            @if($logoUrl)<img src="{{ $logoUrl }}" alt="Logo aplikasi aktif">@else<span class="brand-mark preview-mark">EE</span>@endif
            <div><strong>{{ $hospital['name'] }}</strong><span>{{ $logoUrl ? 'Logo kustom sedang digunakan' : 'Logo bawaan sedang digunakan' }}</span><small>{{ $hospital['address'] ?: 'Alamat rumah sakit belum diisi' }}</small></div>
        </div>
    </article>
    <form method="POST" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data" class="panel branding-form">
        @csrf @method('PUT')
        <span class="eyebrow">IDENTITAS RUMAH SAKIT</span><h2>Informasi institusi</h2>
        <p class="form-note">Nama rumah sakit digunakan sebagai judul utama kop ekspor PDF dan Excel.</p>
        <div class="branding-fields"><label>Nama rumah sakit<input type="text" name="name" value="{{ old('name', $hospital['name']) }}" required maxlength="150"></label><label>Nomor telepon<input type="text" name="phone" value="{{ old('phone', $hospital['phone']) }}" maxlength="50"></label><label class="wide">Alamat<textarea name="address" rows="3" maxlength="500">{{ old('address', $hospital['address']) }}</textarea></label><label>Email<input type="email" name="email" value="{{ old('email', $hospital['email']) }}" maxlength="150"></label><label>Website<input type="url" name="website" value="{{ old('website', $hospital['website']) }}" placeholder="https://..." maxlength="200"></label></div>
        <label class="upload-field"><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><span><strong>Ganti logo (opsional)</strong><small>PNG, JPG, atau WebP; minimal 64×64, maksimal 2 MB</small></span></label>
        <div class="card-actions"><button class="button primary" type="submit">Simpan Branding</button></div>
    </form>
</div>
@endsection
