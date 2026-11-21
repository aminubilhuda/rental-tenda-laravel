@if($rentalDetail)
    <section class="panel form-panel rental-detail-panel">
        <div class="panel-head rental-detail-head">
            <div>
                <h3>Detail transaksi {{ $rentalDetail->trx_code }}</h3>
                <p class="muted">Informasi transaksi dan riwayat perubahan.</p>
            </div>
            <a class="button button-outline" href="{{ route('page', ['section' => 'rentals']) }}">Tutup detail</a>
        </div>

        <div class="rental-meta-grid">
            <div class="rental-meta-card">
                <span>Pelanggan</span>
                <strong>{{ $rentalDetail->customer?->name ?? 'Pelanggan diarsipkan' }}</strong>
                <small>{{ $rentalDetail->customer?->phone ?? '—' }}</small>
                @if($rentalDetail->customer?->address)
                    <small>{{ $rentalDetail->customer->address }}</small>
                @endif
            </div>
            <div class="rental-meta-card">
                <span>Kasir</span>
                <strong>{{ $rentalDetail->cashier?->name ?? 'Kasir diarsipkan' }}</strong>
            </div>
            <div class="rental-meta-card">
                <span>Periode sewa</span>
                <strong>{{ $rentalDetail->rent_date->format('d/m/Y') }} – {{ $rentalDetail->due_date->format('d/m/Y') }}</strong>
                <small>{{ $rentalDetail->duration_days }} hari</small>
            </div>
            <div class="rental-meta-card">
                <span>Jaminan</span>
                <strong>{{ $rentalDetail->guarantee_type }}</strong>
            </div>
            <div class="rental-meta-card">
                <span>Deposit</span>
                <strong>Rp {{ number_format($rentalDetail->deposit_amount, 0, ',', '.') }}</strong>
                <small>Dikembalikan Rp {{ number_format($rentalDetail->deposit_returned, 0, ',', '.') }} · Dipotong Rp {{ number_format($rentalDetail->deposit_deducted, 0, ',', '.') }}</small>
            </div>
            <div class="rental-meta-card">
                <span>Status transaksi</span>
                <strong>{{ ['rented' => 'Disewa', 'returned' => 'Kembali', 'late' => 'Terlambat', 'cancelled' => 'Dibatalkan'][$rentalDetail->display_status] ?? $rentalDetail->display_status }}</strong>
            </div>
        </div>

        @if($rentalDetail->customer_photo_path)
            <p class="rental-customer-photo-link"><a class="button button-outline" href="{{ route('rentals.customer-photo', ['rental' => $rentalDetail->id]) }}" target="_blank">Lihat foto penyewa / jaminan</a></p>
        @endif

        <div class="table-wrap rental-items-table">
            <table>
                <thead><tr><th>ALAT</th><th>JUMLAH</th><th>HARGA / HARI</th><th>SUBTOTAL</th><th>KONDISI</th></tr></thead>
                <tbody>
                    @foreach($rentalDetail->items as $line)
                        <tr>
                            <td>{{ $line->item_name }}</td>
                            <td>{{ $line->qty }}</td>
                            <td>Rp {{ number_format($line->price_per_day, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($line->subtotal, 0, ',', '.') }}</td>
                            <td>{{ $line->qty_returned_good }} baik · {{ $line->qty_damaged }} rusak · {{ $line->qty_lost }} hilang</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="rental-total-summary">
            <span>Subtotal <strong>Rp {{ number_format($rentalDetail->subtotal, 0, ',', '.') }}</strong></span>
            <span>Diskon <strong>Rp {{ number_format($rentalDetail->discount, 0, ',', '.') }}</strong></span>
            <span>Denda <strong>Rp {{ number_format($rentalDetail->late_fee, 0, ',', '.') }}</strong></span>
            <span class="rental-total-final">Total <strong>Rp {{ number_format($rentalDetail->total + $rentalDetail->late_fee, 0, ',', '.') }}</strong></span>
        </div>

        @if($rentalDetail->damages->isNotEmpty())
            <div class="rental-related-damages">
                <h4>Ganti rugi terkait</h4>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>ID</th><th>Jenis</th><th>Tagihan</th><th>Dibayar</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach($rentalDetail->damages as $damage)
                                <tr>
                                    <td>{{ $damage->code }}</td>
                                    <td>{{ $damage->type }} × {{ $damage->qty }}</td>
                                    <td>Rp {{ number_format($damage->charge_amount, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($damage->paid_amount, 0, ',', '.') }}</td>
                                    <td>{{ $damage->status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="rental-history">
            <h4>Riwayat perubahan</h4>
            <ul class="rental-history-list">
                @forelse($rentalHistory as $event)
                    <li><time>{{ $event->created_at->format('d/m/Y H:i') }}</time><span>{{ $event->user?->name ?? 'System' }} · {{ $event->description }}</span></li>
                @empty
                    <li class="rental-history-empty">Belum ada riwayat tambahan.</li>
                @endforelse
            </ul>
        </div>

        <div class="rental-detail-actions">
            <div class="rental-receipt-actions">
                <a class="button button-outline" href="{{ route('rentals.receipt', ['rental' => $rentalDetail->id]) }}" target="_blank">Buka struk</a>
                <a class="button button-soft" href="{{ route('rentals.receipt.pdf', ['rental' => $rentalDetail->id]) }}">Simpan PDF</a>
            </div>
            @if(auth()->user()->isManager() && $rentalDetail->status !== 'rented')
                <form method="POST" action="{{ route('destroy', ['section' => 'rentals', 'id' => $rentalDetail->id]) }}" class="rental-archive-form" data-confirm="Arsip transaksi ini akan dihapus dari daftar. Pastikan transaksi sudah selesai dan alasan sudah benar.">
                    @csrf
                    <input type="hidden" name="confirm_delete" value="1">
                    @method('DELETE')
                    <label>Alasan hapus arsip<input name="reason" required></label>
                    <button class="button button-outline">Hapus transaksi</button>
                </form>
            @endif
        </div>
    </section>
@endif
