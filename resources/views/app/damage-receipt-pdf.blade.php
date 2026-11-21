<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#111;margin:20px}.center{text-align:center}.rule{border-top:1px dashed #555;margin:12px 0}.row{width:100%;margin:6px 0}.right{text-align:right}.small{font-size:10px;color:#444}</style></head>
<body>
<header class="center">@if($businessLogoPath)<img src="{{ $businessLogoPath }}" style="max-width:70px;max-height:45px"><br>@endif<strong style="font-size:18px">{{ $businessName }}</strong><br>{{ $businessAddress }}<br>{{ $businessPhone }}</header>
<div class="rule"></div><h2 class="center">BUKTI GANTI RUGI</h2>
<table class="row"><tr><td>ID Ganti Rugi</td><td class="right"><strong>{{ $damage->code }}</strong></td></tr><tr><td>ID Transaksi</td><td class="right">{{ $damage->rental?->trx_code ?? 'Transaksi diarsipkan' }}</td></tr><tr><td>Tanggal</td><td class="right">{{ $damage->created_at->format('d/m/Y H:i') }}</td></tr><tr><td>Pelanggan</td><td class="right">{{ $damage->customer?->name ?? 'Pelanggan diarsipkan' }} · {{ $damage->customer?->phone ?? '—' }}</td></tr><tr><td>Alat</td><td class="right">{{ $damage->item?->name ?? 'Alat diarsipkan' }} × {{ $damage->qty }}</td></tr><tr><td>Kondisi</td><td class="right">{{ ['minor_damage'=>'Rusak ringan','major_damage'=>'Rusak berat','lost'=>'Hilang'][$damage->type]??$damage->type }}</td></tr></table>
<div class="rule"></div><table class="row"><tr><td>Total tagihan</td><td class="right">Rp {{ number_format($damage->charge_amount,0,',','.') }}</td></tr><tr><td>Sudah dibayar</td><td class="right">Rp {{ number_format($damage->paid_amount,0,',','.') }}</td></tr><tr><td>Sisa tagihan</td><td class="right"><strong>Rp {{ number_format(max(0,$damage->charge_amount-$damage->paid_amount),0,',','.') }}</strong></td></tr></table>
@if($damage->notes)<p>Catatan: {{ $damage->notes }}</p>@endif
<div class="rule"></div><strong>RIWAYAT PEMBAYARAN</strong>
@forelse($damage->payments as $payment)<p>{{ $payment->paid_at->format('d/m/Y H:i') }} · {{ ucfirst($payment->method) }} · Rp {{ number_format($payment->amount,0,',','.') }} · {{ $payment->user->name }}</p>@empty<p class="small">Belum ada pembayaran.</p>@endforelse
<p class="center" style="margin-top:32px">Terima kasih. Simpan bukti ini sebagai arsip.</p>
</body></html>
