@foreach(collect($section['rows']) as $index => $row)
<tr><td class="row-number">{{ ($section['row_offset'] ?? 0) + $index + 1 }}</td>@foreach($section['columns'] as $key => $label)@php($value = data_get($row, $key))<td class="{{ in_array(($section['formats'][$key] ?? null), ['number', 'currency']) ? 'numeric' : '' }}">@if(($section['formats'][$key] ?? null) === 'currency')Rp {{ number_format((float) $value, 0, ',', '.') }}@elseif(($section['formats'][$key] ?? null) === 'number'){{ number_format((float) $value, 0, ',', '.') }}@else{{ $value }}@endif</td>@endforeach</tr>
@endforeach
