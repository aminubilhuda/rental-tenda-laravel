@if(auth()->user()->isManager())
    <section class="panel form-panel" style="margin-bottom:20px">
        <h3>Catat ganti rugi manual</h3>
        <p class="muted">Gunakan untuk kerusakan atau kehilangan yang ditemukan setelah pengembalian. Stok diperbarui saat disimpan.</p>
        <form method="POST" action="{{ route('save', ['section' => 'damages']) }}" enctype="multipart/form-data" class="stack-form">
            @csrf
            <label>Transaksi dan alat
                <select name="rental_item_id" required>
                    @forelse($damageCandidates as $line)
                        <option value="{{ $line->id }}">{{ $line->rental?->trx_code ?? 'Transaksi diarsipkan' }} · {{ $line->rental?->customer?->name ?? 'Pelanggan diarsipkan' }} · {{ $line->item?->name ?? $line->item_name }} ({{ $line->qty_returned_good }} unit baik)</option>
                    @empty
                        <option value="">Tidak ada unit baik dari transaksi selesai</option>
                    @endforelse
                </select>
            </label>
            <div class="date-grid">
                <label>Jenis<select name="type"><option value="minor_damage">Rusak ringan</option><option value="major_damage">Rusak berat</option><option value="lost">Hilang</option></select></label>
                <label>Jumlah unit<input type="number" name="qty" min="1" value="1" required></label>
            </div>
            <label>Biaya ganti rugi (kosongkan untuk harga beli × jumlah; rusak ringan wajib diisi)<input type="number" name="charge_amount" min="0"></label>
            <label>Foto bukti<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>
            <label>Catatan<textarea name="notes" rows="2"></textarea></label>
            <button class="button button-primary" @disabled($damageCandidates->isEmpty())>Simpan ganti rugi</button>
        </form>
    </section>
@endif

<section class="panel data-panel">
    <div class="panel-head"><div><h3>Tagihan ganti rugi</h3><p class="muted">Catat pembayaran dan pantau sisa tagihan.</p></div><form method="GET" class="inline-search"><input name="q" value="{{ $query }}" placeholder="Cari ID atau pelanggan..."><button class="button button-soft">Cari</button></form></div>
    <div class="table-wrap"><table><thead><tr><th>ID / ALAT</th><th>PELANGGAN</th><th>JENIS</th><th>TAGIHAN</th><th>TERBAYAR / SISA</th><th>STATUS / KONDISI</th><th>AKSI</th></tr></thead><tbody>
        @forelse($rows as $damage)
            <tr>
                <td><strong>{{ $damage->code }}</strong><small class="table-sub">{{ $damage->item?->name ?? 'Alat diarsipkan' }} · {{ $damage->rental?->trx_code ?? 'Transaksi diarsipkan' }}</small></td>
                <td>{{ $damage->customer?->name ?? 'Pelanggan diarsipkan' }}<small class="table-sub">{{ $damage->customer?->phone ?? '—' }}</small></td>
                <td>{{ ['minor_damage'=>'Rusak ringan','major_damage'=>'Rusak berat','lost'=>'Hilang'][$damage->type]??$damage->type }}<small class="table-sub">{{ $damage->qty }} unit</small></td>
                <td>Rp {{ number_format($damage->charge_amount,0,',','.') }}</td>
                <td>Rp {{ number_format($damage->paid_amount,0,',','.') }}<small class="table-sub">Sisa Rp {{ number_format(max(0,$damage->charge_amount-$damage->paid_amount),0,',','.') }}</small></td>
                <td><span class="pill {{ $damage->status==='paid'?'pill-green':($damage->status==='written_off'?'pill-red':'pill-orange') }}">{{ ['unpaid'=>'Belum lunas','partial'=>'Sebagian','paid'=>'Lunas','written_off'=>'Dihapuskan'][$damage->status] }}</span><small class="table-sub">{{ ['pending'=>'Menunggu','repaired'=>'Sudah diperbaiki','disposed'=>'Afkir','n_a'=>'Tidak berlaku'][$damage->resolution]??$damage->resolution }}</small></td>
                <td class="action-cell">
                    <a class="button button-outline small-button" href="{{ route('damages.receipt.pdf',['damage'=>$damage->id]) }}" target="_blank">Kwitansi PDF</a>
                    @if(!in_array($damage->status,['paid','written_off'],true))
                        <form method="POST" action="{{ route('damages.payments',['damage'=>$damage->id]) }}" class="payment-form">@csrf<input type="number" name="amount" min="1" max="{{ $damage->charge_amount-$damage->paid_amount }}" placeholder="Nominal" required><select name="method"><option value="cash">Tunai</option><option value="transfer">Transfer</option></select><button class="button button-soft small-button">Catat</button></form>
                    @endif
                    @if(auth()->user()->isManager() && $damage->resolution==='pending' && $damage->type!=='lost')
                        <form method="POST" action="{{ route('damages.resolution',['damage'=>$damage->id]) }}" class="payment-form" data-confirm-action="disposed">@csrf<input type="hidden" name="confirm_action" value="1"><label class="sr-only">Resolusi kerusakan</label><select name="action"><option value="repaired">Sudah diperbaiki</option><option value="disposed">Afkir</option></select><button class="button button-soft small-button">Selesaikan</button></form>
                    @endif
                    @if(auth()->user()->isManager() && !in_array($damage->status,['paid','written_off'],true))
                        <form method="POST" action="{{ route('damages.resolution',['damage'=>$damage->id]) }}" class="stack-form" data-confirm="Hapuskan tagihan ganti rugi ini? Alasan penghapusan wajib dicatat dan statusnya tidak dapat dipulihkan.">@csrf<input type="hidden" name="confirm_action" value="1"><input type="hidden" name="action" value="write_off"><input name="reason" placeholder="Alasan penghapusan tagihan" required><button class="button button-outline small-button">Hapuskan tagihan</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state">Belum ada catatan ganti rugi.</div></td></tr>
        @endforelse
    </tbody></table></div>{{ $rows->links() }}
</section>
