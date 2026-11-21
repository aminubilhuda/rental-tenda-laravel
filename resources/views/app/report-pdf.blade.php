<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#111}h1{font-size:18px;margin-bottom:4px}p{color:#555;margin-top:0}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border:1px solid #bbb;padding:5px;text-align:left;vertical-align:top}th{background:#e8eee9}tr:nth-child(even){background:#f7f9f7}</style></head>
<body>
<h1>Laporan {{ ucfirst($type) }}</h1><p>Periode {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }} · Dicetak {{ now()->format('d/m/Y H:i') }} WIB</p>
<table><thead><tr>@foreach($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead><tbody>@forelse($rows as $row)<tr>@foreach($row as $value)<td>{{ is_scalar($value) ? $value : '' }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($headings) }}">Tidak ada data dalam periode ini.</td></tr>@endforelse</tbody></table>
</body></html>
