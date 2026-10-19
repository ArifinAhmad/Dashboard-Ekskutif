@extends('layouts.app')
@section('title', 'Dashboard Eksekutif')
@section('content')
<div data-dashboard-refresh="{{ $settings['refresh_seconds'] }}" hidden></div>
<button class="auto-scroll-toggle" type="button" data-auto-scroll-toggle data-scroll-speed="{{ $settings['scroll_speed'] }}" aria-pressed="false" title="Aktifkan auto scroll berulang"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4h8l3 3-3 3M19 7H8a4 4 0 0 0-4 4M16 20H8l-3-3 3-3M5 17h11a4 4 0 0 0 4-4"/></svg><span><strong>Auto Scroll</strong><small data-auto-scroll-status>Nonaktif</small></span></button>
<svg class="icon-sprite" aria-hidden="true">
    <symbol id="icon-patients" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></symbol>
    <symbol id="icon-stethoscope" viewBox="0 0 24 24"><path d="M4 3v5a5 5 0 0 0 10 0V3M2 3h4M12 3h4M9 13v2a6 6 0 0 0 12 0v-1M18 11a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></symbol>
    <symbol id="icon-emergency" viewBox="0 0 24 24"><path d="M12 3v18M3 12h18M5.64 5.64l12.72 12.72M18.36 5.64 5.64 18.36"/></symbol>
    <symbol id="icon-bed" viewBox="0 0 24 24"><path d="M3 4v16M3 16h18v4M6 13V8h5a3 3 0 0 1 3 3v2M14 10h4a3 3 0 0 1 3 3v3M6 16v-3h15"/></symbol>
    <symbol id="icon-user-plus" viewBox="0 0 24 24"><path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M16 11h6"/></symbol>
    <symbol id="icon-check" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/><circle cx="12" cy="12" r="10"/></symbol>
    <symbol id="icon-arrow-in" viewBox="0 0 24 24"><path d="M9 18 3 12l6-6M3 12h14M17 5h4v14h-4"/></symbol>
    <symbol id="icon-arrow-out" viewBox="0 0 24 24"><path d="m15 18 6-6-6-6M21 12H7M7 5H3v14h4"/></symbol>
    <symbol id="icon-heart" viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/></symbol>
    <symbol id="icon-wallet" viewBox="0 0 24 24"><path d="M4 5h15a2 2 0 0 1 2 2v12H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h13M15 11h6v5h-6a2.5 2.5 0 0 1 0-5Z"/></symbol>
    <symbol id="icon-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
</svg>

<div class="page-heading dashboard-heading">
    @if($applicationLogoUrl)<div class="page-logo"><img src="{{ $applicationLogoUrl }}" alt="Logo {{ config('app.name') }}"></div>@endif
    <div><span class="eyebrow">RINGKASAN HARI INI</span><h1>Operasional rumah sakit</h1><p>Pantau arus pasien, status pelayanan, dan rawat inap dalam satu pandangan.</p></div>
    <span class="status {{ $connected ? 'ok' : 'error' }}">{{ $connected ? 'SIMRS terhubung' : 'SIMRS tidak terhubung' }}</span>
</div>
<div class="dashboard-refresh-banner">Pembaruan otomatis dashboard dalam <strong data-refresh-countdown>{{ sprintf('%02d:00', intdiv($settings['refresh_seconds'], 60)) }}</strong></div>

@if(!$connected)
    <div class="alert warning"><strong>Mode tanpa sumber data.</strong> Isi konfigurasi `SIMRS_DB_*` dengan akun read-only untuk menampilkan indikator.</div>
@else
    @php
        $registration = $data['registration'];
        $inpatient = $data['inpatient'];
        $metrics = [
            ['Registrasi', $registration->total ?? 0, 'Pasien hari ini', 'patients', 'orange'],
            ['Rawat jalan', $registration->rawat_jalan ?? 0, 'Kunjungan poli', 'stethoscope', 'green'],
            ['IGD', $registration->igd ?? 0, 'Kunjungan darurat', 'emergency', 'red'],
            ['Rawat inap aktif', $inpatient->aktif ?? 0, 'Pasien dirawat', 'bed', 'teal'],
            ['Pasien baru', $registration->pasien_baru ?? 0, 'Registrasi baru', 'user-plus', 'amber'],
            ['Terlayani', $registration->terlayani ?? 0, 'Selesai dilayani', 'check', 'emerald'],
            ['Sudah bayar', $registration->sudah_bayar ?? 0, 'Rawat jalan hari ini', 'wallet', 'green'],
            ['Belum bayar', $registration->belum_bayar ?? 0, 'Rawat jalan hari ini', 'clock', 'orange'],
        ];
        $serviceTotal = max(1, (int) ($registration->rawat_jalan ?? 0) + (int) ($registration->igd ?? 0));
        $patientTotal = max(1, (int) ($registration->pasien_baru ?? 0) + (int) ($registration->pasien_lama ?? 0));
        $statusTotal = max(1, (int) ($registration->terlayani ?? 0) + (int) ($registration->batal ?? 0));
        $charts = [
            ['Jenis pelayanan', 'Rawat jalan', (int) ($registration->rawat_jalan ?? 0), 'IGD', (int) ($registration->igd ?? 0), round(($registration->rawat_jalan ?? 0) / $serviceTotal * 100)],
            ['Profil pasien', 'Pasien baru', (int) ($registration->pasien_baru ?? 0), 'Pasien lama', (int) ($registration->pasien_lama ?? 0), round(($registration->pasien_baru ?? 0) / $patientTotal * 100)],
            ['Status registrasi', 'Terlayani', (int) ($registration->terlayani ?? 0), 'Batal', (int) ($registration->batal ?? 0), round(($registration->terlayani ?? 0) / $statusTotal * 100)],
        ];
        $genderRows = collect($data['genders'] ?? []);
        $male = (int) ($genderRows->firstWhere('label', 'Laki-laki')->total ?? 0);
        $female = (int) ($genderRows->firstWhere('label', 'Perempuan')->total ?? 0);
        $genderTotal = max(1, $male + $female);
        $charts[] = ['Jenis kelamin', 'Laki-laki', $male, 'Perempuan', $female, round($male / $genderTotal * 100)];
    @endphp

    <section class="dashboard-section" aria-labelledby="overview-title">
        <div class="section-heading"><div><span class="eyebrow">IKHTISAR</span><h2 id="overview-title">Indikator utama</h2></div><span class="dashboard-refresh-status">Diperbarui {{ now()->format('H:i') }} WIB</span></div>
        <div class="metric-grid dashboard-metrics">
            @foreach($metrics as [$label, $value, $hint, $icon, $tone])
                <article class="metric-card metric-card-icon tone-{{ $tone }}">
                    <div class="metric-icon"><svg aria-hidden="true"><use href="#icon-{{ $icon }}"/></svg></div>
                    <div class="metric-copy"><span>{{ $label }}</span><strong>{{ number_format($value, 0, ',', '.') }}</strong><small>{{ $hint }}</small></div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="dashboard-section" aria-labelledby="composition-title">
        <div class="section-heading"><div><span class="eyebrow">KOMPOSISI</span><h2 id="composition-title">Gambaran pelayanan</h2></div><span>Perbandingan data hari ini</span></div>
        <div class="donut-grid">
            @foreach($charts as [$title, $primaryLabel, $primaryValue, $secondaryLabel, $secondaryValue, $percentage])
                <article class="panel donut-card">
                    <div class="donut-chart" style="--percentage: {{ $percentage }}" role="img" aria-label="{{ $primaryLabel }} {{ $percentage }} persen">
                        <div><strong>{{ $percentage }}%</strong><span>{{ $primaryLabel }}</span></div>
                    </div>
                    <div class="donut-info"><h3>{{ $title }}</h3><div class="chart-legend"><div><i class="primary"></i><span>{{ $primaryLabel }}</span><strong>{{ number_format($primaryValue, 0, ',', '.') }}</strong></div><div><i class="secondary"></i><span>{{ $secondaryLabel }}</span><strong>{{ number_format($secondaryValue, 0, ',', '.') }}</strong></div></div></div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="dashboard-section dashboard-detail-grid" aria-label="Detail operasional">
        <article class="panel payer-panel">
            <div class="panel-head"><div><span class="eyebrow">DISTRIBUSI</span><h2>Penjamin hari ini</h2></div><span class="panel-badge">Top 8</span></div>
            <div class="bar-list">
                @php($max = max(1, (int) $data['payers']->max('total')))
                @forelse($data['payers'] as $payer)
                    <div class="bar-row"><div><span>{{ $payer->label }}</span><strong>{{ number_format($payer->total, 0, ',', '.') }}</strong></div><div class="bar-track"><span style="width: {{ round($payer->total / $max * 100) }}%"></span></div></div>
                @empty<p class="empty">Belum ada data.</p>@endforelse
            </div>
        </article>
        <article class="panel inpatient-panel">
            <div class="panel-head"><div><span class="eyebrow">RAWAT INAP</span><h2>Pergerakan hari ini</h2></div><div class="metric-icon tone-green"><svg aria-hidden="true"><use href="#icon-bed"/></svg></div></div>
            <div class="movement-list">
                @foreach([
                    ['Masuk', $inpatient->masuk ?? 0, 'arrow-in', 'green'],
                    ['Keluar', $inpatient->keluar ?? 0, 'arrow-out', 'orange'],
                    ['Meninggal', $inpatient->meninggal ?? 0, 'heart', 'red'],
                ] as [$label, $value, $icon, $tone])
                    <div class="movement-item"><span class="movement-icon tone-{{ $tone }}"><svg aria-hidden="true"><use href="#icon-{{ $icon }}"/></svg></span><div><span>{{ $label }}</span><small>Pasien hari ini</small></div><strong>{{ number_format($value, 0, ',', '.') }}</strong></div>
                @endforeach
            </div>
            <div class="inpatient-total"><span>Total pasien aktif</span><strong>{{ number_format($inpatient->aktif ?? 0, 0, ',', '.') }}</strong></div>
        </article>
    </section>

    <section class="dashboard-section" aria-labelledby="bed-availability-title">
        <div class="section-heading"><div><span class="eyebrow">RAWAT INAP</span><h2 id="bed-availability-title">Ketersediaan tempat tidur</h2></div><span>Kamar dan bangsal aktif saat ini</span></div>
        <div class="bed-availability-grid">
            <article class="panel bed-overview">
                <div class="bed-gauge" style="--occupancy: {{ round(($data['beds']->terisi ?? 0) / max(1, (int) ($data['beds']->total ?? 0)) * 100) }}"><div><strong>{{ round(($data['beds']->terisi ?? 0) / max(1, (int) ($data['beds']->total ?? 0)) * 100) }}%</strong><span>Okupansi</span></div></div>
                <div class="bed-overview-copy"><span class="eyebrow">TOTAL TEMPAT TIDUR</span><strong>{{ number_format($data['beds']->total ?? 0, 0, ',', '.') }}</strong><small>{{ number_format($data['beds']->terisi ?? 0, 0, ',', '.') }} tempat tidur sedang terisi</small></div>
            </article>
            @foreach([
                ['Kosong', $data['beds']->kosong ?? 0, 'Siap digunakan', 'green', 'bed'],
                ['Terisi', $data['beds']->terisi ?? 0, 'Sedang digunakan', 'orange', 'bed'],
                ['Dibersihkan', $data['beds']->dibersihkan ?? 0, 'Dalam persiapan', 'teal', 'clock'],
                ['Dibooking', $data['beds']->dibooking ?? 0, 'Sudah dipesan', 'amber', 'check'],
                ['Perbaikan', $data['beds']->perbaikan ?? 0, 'Tidak tersedia', 'red', 'emergency'],
            ] as [$label, $value, $hint, $tone, $icon])
                <article class="metric-card bed-status-card tone-{{ $tone }}"><div class="metric-icon"><svg aria-hidden="true"><use href="#icon-{{ $icon }}"/></svg></div><div><span>{{ $label }}</span><strong>{{ number_format($value, 0, ',', '.') }}</strong><small>{{ $hint }}</small></div></article>
            @endforeach
        </div>
    </section>

    <section class="dashboard-section" aria-labelledby="patient-profile-title">
        <div class="section-heading"><div><span class="eyebrow">PROFIL PASIEN</span><h2 id="patient-profile-title">Pasien terregistrasi hari ini</h2></div><span>Pasien unik berdasarkan nomor rekam medis</span></div>
        <div class="demographic-grid">
            @foreach([
                ['Kelompok usia', 'Distribusi umur pasien', collect($data['ageGroups'] ?? []), 'usia'],
                ['Geografis pasien', '10 kabupaten/kota terbanyak', collect($data['geographies'] ?? []), 'wilayah'],
                ['Tingkat pendidikan', 'Pendidikan terakhir pasien', collect($data['educations'] ?? []), 'pendidikan'],
                ['Pekerjaan pasien', '10 pekerjaan terbanyak', collect($data['occupations'] ?? []), 'pekerjaan'],
                ['Asal rujukan', '10 sumber rujukan terbanyak', collect($data['referrals'] ?? []), 'rujukan'],
            ] as [$title, $subtitle, $rows, $tone])
                <article class="panel demographic-card tone-{{ $tone }}">
                    <div class="panel-head"><div><span class="eyebrow">{{ strtoupper($subtitle) }}</span><h3>{{ $title }}</h3></div><span class="data-count">{{ number_format($rows->sum('total'), 0, ',', '.') }} pasien</span></div>
                    <div class="demographic-bars">
                        @php($maximum = max(1, (int) $rows->max('total')))
                        @forelse($rows as $row)
                            <div class="demographic-row"><div><span title="{{ $row->label }}">{{ $row->label }}</span><strong>{{ number_format($row->total, 0, ',', '.') }}</strong></div><div class="demographic-track"><span style="width: {{ round($row->total / $maximum * 100) }}%"></span></div></div>
                        @empty<p class="empty">Belum ada data pasien.</p>@endforelse
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="dashboard-section" aria-labelledby="geographic-map-title">
        <div class="section-heading"><div><span class="eyebrow">PETA SEBARAN NTB</span><h2 id="geographic-map-title">Geografis pasien terregistrasi</h2></div><span>Kabupaten/kota NTB dan pasien dari luar NTB</span></div>
        <article class="panel patient-map-card">
            <div class="patient-map" data-patient-map data-areas='@json(collect($data['districtPatients'] ?? [])->map(fn ($row) => ['name' => $row->label, 'total' => (int) $row->total])->values())' role="img" aria-label="Peta batas kabupaten dan kota Indonesia dengan jumlah pasien"></div>
            <aside class="map-region-list">
                <div class="map-list-head"><div><span class="eyebrow">DAFTAR WILAYAH</span><h3>Nama daerah dan pasien</h3></div><strong>{{ number_format(collect($data['districtPatients'] ?? [])->sum('total'), 0, ',', '.') }}</strong></div>
                <div class="map-list-scroll">
                    @forelse(collect($data['districtPatients'] ?? []) as $index => $area)
                        <div class="map-region-row {{ $area->label === 'Luar Nusa Tenggara Barat' ? 'outside-region' : '' }}"><span>{{ $index + 1 }}</span><div><strong>{{ $area->label }}</strong><small>{{ $area->label === 'Luar Nusa Tenggara Barat' ? 'Gabungan seluruh wilayah luar NTB' : 'Kabupaten / kota NTB' }}</small></div><b>{{ number_format($area->total, 0, ',', '.') }}</b></div>
                    @empty<p class="empty">Belum ada data geografis.</p>@endforelse
                </div>
            </aside>
        </article>
    </section>
@endif
@endsection
