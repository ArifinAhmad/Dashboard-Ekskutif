@extends('layouts.app')
@section('title', $reportConfig['label'])
@section('content')
<div class="page-heading report-heading"><div><span class="eyebrow">{{ strtoupper($moduleConfig['label']) }}</span><h1>{{ $reportConfig['label'] }}</h1><p>Data periode {{ $from->format('d/m/Y') }} sampai {{ $to->format('d/m/Y') }}, maksimum 10 baris per halaman.</p></div><span class="status {{ $connected ? 'ok' : 'error' }}">{{ $connected ? 'SIMRS terhubung' : 'SIMRS tidak terhubung' }}</span></div>
<form class="filter-bar compact-filter" method="GET"><label>Dari<input type="date" name="from" value="{{ $from->toDateString() }}"></label><label>Sampai<input type="date" name="to" value="{{ $to->toDateString() }}"></label><button class="button primary" type="submit">Tampilkan</button><button class="button secondary" type="submit" name="refresh" value="1">Perbarui Data</button>@if($implemented && auth()->user()->hasPermission('report.export'))<div class="export-actions"><a class="button secondary excel-button" href="{{ route('reports.export', [$module, $report, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Excel</a><a class="button secondary pdf-button" href="{{ route('reports.export.pdf', [$module, $report, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">PDF</a></div>@endif</form>
@if(!$connected)<div class="alert warning">Koneksi SIMRS belum tersedia. Laporan tidak menjalankan query.</div>
@elseif(!$implemented)<div class="roadmap-card"><span class="eyebrow">ROADMAP TERDAFTAR</span><h2>Modul siap untuk tahap implementasi query</h2></div>
@else
    @if(isset($result['_cache']))<div class="cache-status"><span class="cache-dot {{ $result['_cache']['hit'] ? 'hit' : 'fresh' }}"></span><span>{{ $result['_cache']['hit'] ? 'Data dari cache lokal' : 'Data terbaru dari SIMRS' }}</span><small>Disimpan {{ \Carbon\CarbonImmutable::parse($result['_cache']['stored_at'])->translatedFormat('d M Y H:i:s') }}, berlaku sampai {{ \Carbon\CarbonImmutable::parse($result['_cache']['expires_at'])->format('H:i:s') }}</small></div>@endif
    <div class="metric-grid compact report-metrics">
        @foreach($result['summary'] as $label => $value)
            @php
                $normalizedLabel = str($label)->lower();
                $icon = $normalizedLabel->contains('rujuk') ? 'referral' : ($normalizedLabel->contains('baru') ? 'new-patient' : ($normalizedLabel->contains('lama') ? 'history' : ($normalizedLabel->contains('laki') ? 'male' : ($normalizedLabel->contains('perempuan') ? 'female' : ($normalizedLabel->contains('layani') ? 'served' : 'total')))));
            @endphp
            <article class="metric-card report-metric tone-{{ $icon }}"><span class="summary-icon"><svg viewBox="0 0 24 24" aria-hidden="true">
                @if($icon === 'referral')<path d="M4 20v-8a4 4 0 0 1 4-4h11M15 4l4 4-4 4M8 4a3 3 0 1 0 0 6"/>
                @elseif($icon === 'new-patient')<path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M19 8v6M16 11h6"/>
                @elseif($icon === 'history')<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2"/>
                @elseif($icon === 'male')<path d="M10 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10ZM14 4h6v6M14 10l6-6M10 14v7M6 18h8"/>
                @elseif($icon === 'female')<path d="M12 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10ZM12 14v8M8 19h8"/>
                @elseif($icon === 'served')<path d="m4 12 5 5L20 6"/><circle cx="12" cy="12" r="10"/>
                @else<path d="M4 19V9M10 19V5M16 19v-8M22 19V3M2 19h22"/>@endif
            </svg></span><div><span>{{ $label }}</span><strong>{{ number_format($value, 0, ',', '.') }}</strong><small>{{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}</small></div></article>
        @endforeach
    </div>
    <div class="report-sections">
        @foreach($result['sections'] as $section)
            @php($rows = collect($section['rows']))
            <article class="panel report-panel">
                <div class="panel-head report-panel-head"><div><span class="eyebrow">RINCIAN DATA</span><h2>{{ $section['title'] }}</h2></div><div class="table-tools"><form method="GET" class="sort-form">@foreach(request()->except(['section_sort', 'section_direction', 'section_page']) as $key => $value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach @foreach((array) request('section_sort', []) as $key => $value)@if((int) $key !== $loop->parent?->index)<input type="hidden" name="section_sort[{{ $key }}]" value="{{ $value }}">@endif @endforeach @foreach((array) request('section_direction', []) as $key => $value)@if((int) $key !== $loop->parent?->index)<input type="hidden" name="section_direction[{{ $key }}]" value="{{ $value }}">@endif @endforeach<select name="section_sort[{{ $loop->index }}]" aria-label="Kolom urutan"><option value="">Urutan awal</option>@foreach($section['columns'] as $key => $label)<option value="{{ $key }}" @selected(($section['sort_column'] ?? null) === $key)>{{ $label }}</option>@endforeach</select><select name="section_direction[{{ $loop->index }}]" aria-label="Arah urutan"><option value="asc" @selected(($section['sort_direction'] ?? 'asc') === 'asc')>A-Z / Terkecil</option><option value="desc" @selected(($section['sort_direction'] ?? '') === 'desc')>Z-A / Terbesar</option></select><button type="submit">Urutkan</button></form><span class="data-count">{{ number_format($section['total_rows'] ?? $rows->count(), 0, ',', '.') }} data</span></div></div>
                <div class="table-wrap"><table><thead><tr><th class="row-number">No</th>@foreach($section['columns'] as $key => $label)<th class="{{ in_array(($section['formats'][$key] ?? null), ['number', 'currency']) ? 'numeric' : '' }}">{{ $label }}</th>@endforeach</tr></thead><tbody>
                @forelse($rows as $index => $row)<tr><td class="row-number">{{ ($section['row_offset'] ?? 0) + $index + 1 }}</td>@foreach($section['columns'] as $key => $label)@php($value = data_get($row, $key))<td class="{{ in_array(($section['formats'][$key] ?? null), ['number', 'currency']) ? 'numeric' : '' }}">@if(($section['formats'][$key] ?? null) === 'currency')Rp {{ number_format((float) $value, 0, ',', '.') }}@elseif(($section['formats'][$key] ?? null) === 'number'){{ number_format((float) $value, 0, ',', '.') }}@else{{ $value }}@endif</td>@endforeach</tr>@empty<tr><td colspan="{{ count($section['columns']) + 1 }}" class="empty">Tidak ada data pada periode ini.</td></tr>@endforelse
                </tbody></table></div>
                @if(($section['total_rows'] ?? 0) > 0)<div class="table-pagination"><span>{{ ($section['row_offset'] ?? 0) + 1 }}-{{ min(($section['row_offset'] ?? 0) + $rows->count(), $section['total_rows']) }} dari {{ $section['total_rows'] }} data</span><div>@if(($section['page'] ?? 1) > 1)<a href="{{ request()->fullUrlWithQuery(['section_page' => array_replace((array) request('section_page', []), [$loop->index => $section['page'] - 1])]) }}">Sebelumnya</a>@endif<strong>{{ $section['page'] ?? 1 }} / {{ $section['last_page'] ?? 1 }}</strong>@if(($section['page'] ?? 1) < ($section['last_page'] ?? 1))<a href="{{ request()->fullUrlWithQuery(['section_page' => array_replace((array) request('section_page', []), [$loop->index => $section['page'] + 1])]) }}">Berikutnya</a>@endif</div></div>@endif
            </article>
        @endforeach
    </div>
@endif
@endsection
