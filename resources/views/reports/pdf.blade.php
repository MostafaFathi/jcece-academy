<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><style>
body { font-family: dejavusans; color: #333; font-size: 9pt; }
h1 { color: #6b1d32; font-size: 18pt; }
.meta { color: #666; margin-bottom: 14px; }
table { width: 100%; border-collapse: collapse; }
th { background: #6b1d32; color: white; } th, td { border: 1px solid #ddd; padding: 6px; text-align: start; }
tr:nth-child(even) { background: #f3f3f3; }
</style></head>
<body>
@if (is_file((string) config('jcec.certificates.logo_path')))<img src="{{ config('jcec.certificates.logo_path') }}" alt="JCEC Academy" style="height: 45px; margin-bottom: 8px">@endif
<h1>JCEC Academy — {{ trans('reports.report', [], $locale) }}: {{ trans('reports.types.'.$type, [], $locale) }}</h1>
<div class="meta">{{ $filters->from }} — {{ $filters->to }} · Asia/Hebron · {{ now('Asia/Hebron')->format('Y-m-d H:i') }}</div>
@if ($summaryColumns !== [])
<table style="margin-bottom: 16px"><thead><tr>@foreach ($summaryColumns as $column)<th>{{ trans('reports.columns.'.$column, [], $locale) }}</th>@endforeach</tr></thead><tbody>
@foreach ($summaryRows as $row)<tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach
</tbody></table>
@endif
@if ($trendColumns !== [])
<table style="margin-bottom: 16px"><thead><tr>@foreach ($trendColumns as $column)<th>{{ trans('reports.columns.'.$column, [], $locale) }}</th>@endforeach</tr></thead><tbody>
@foreach ($trendRows as $row)<tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach
</tbody></table>
@endif
<table><thead><tr>@foreach ($columns as $column)<th>{{ trans('reports.columns.'.$column, [], $locale) }}</th>@endforeach</tr></thead><tbody>
@forelse ($rows as $row)<tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
@empty <tr><td colspan="{{ count($columns) }}">{{ trans('reports.no_data', [], $locale) }}</td></tr> @endforelse
</tbody></table>
</body></html>
