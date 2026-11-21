@if($stats['low_stock'] > 0)
    <div class="alert alert-warning dashboard-alert">⚠ {{ $stats['low_stock'] }} jenis alat stoknya menipis atau habis. <a class="text-link" href="{{ route('page', ['section' => 'items']) }}">Periksa inventaris →</a></div>
@endif

<section class="dashboard-intro">
    <div>
        <p class="eyebrow">{{ now()->translatedFormat('l, d F Y') }}</p>
        <h2>Ringkasan usaha</h2>
        <p class="muted">Pantau penyewaan, stok, dan pendapatan CampRent.</p>
    </div>
    <a class="button button-primary" href="{{ route('pos') }}">＋ Transaksi baru</a>
</section>

<form method="GET" class="dashboard-period-filter">
    <label>Periode<select name="period"><option value="today" @selected($dashboardPeriod === 'today')>Hari ini</option><option value="7days" @selected($dashboardPeriod === '7days')>7 hari</option><option value="month" @selected($dashboardPeriod === 'month')>Bulan ini</option><option value="year" @selected($dashboardPeriod === 'year')>Tahun ini</option><option value="custom" @selected($dashboardPeriod === 'custom')>Kustom</option></select></label>
    <label>Dari<input type="date" name="from" value="{{ $dashboardFrom }}"></label>
    <label>Sampai<input type="date" name="to" value="{{ $dashboardTo }}"></label>
    <label>Tahun grafik<select name="year">@for($yearOption = now()->year - 4; $yearOption <= now()->year + 1; $yearOption++)<option value="{{ $yearOption }}" @selected($chartYear === $yearOption)>{{ $yearOption }}</option>@endfor</select></label>
    <button class="button button-soft">Terapkan</button>
</form>

<section class="dashboard-kpis" aria-label="Ringkasan indikator usaha">
    <article class="dashboard-kpi"><div class="dashboard-kpi-label"><span class="dashboard-kpi-icon kpi-red">↗</span><span>Pendapatan periode ini</span></div><strong>Rp {{ number_format($stats['revenue'], 0, ',', '.') }}</strong><small>Transaksi sewa yang telah kembali</small></article>
    <article class="dashboard-kpi"><div class="dashboard-kpi-label"><span class="dashboard-kpi-icon kpi-green">◈</span><span>Nilai inventaris</span></div><strong>Rp {{ number_format($stats['asset_value'], 0, ',', '.') }}</strong><small>Nilai beli seluruh alat tercatat</small></article>
    <article class="dashboard-kpi"><div class="dashboard-kpi-label"><span class="dashboard-kpi-icon kpi-amber">⇄</span><span>Unit sedang disewa</span></div><strong>{{ number_format($stats['rented_units']) }}</strong><small>{{ number_format($stats['rental_count']) }} transaksi dalam periode ini</small></article>
    <article class="dashboard-kpi"><div class="dashboard-kpi-label"><span class="dashboard-kpi-icon kpi-blue">⌂</span><span>Perlu dikembalikan</span></div><strong>{{ number_format($stats['due_today'] + $stats['overdue']) }}</strong><small>{{ $stats['due_today'] }} hari ini · {{ $stats['overdue'] }} terlambat</small></article>
</section>

@php($chartMaximum = max(1, ...array_merge($monthly, $monthlyPrevious)))
<section class="dashboard-analytics">
    <article class="panel dashboard-sales-card">
        <div class="dashboard-panel-heading"><div><p class="eyebrow">PERBANDINGAN TAHUNAN</p><h3>Pendapatan sewa bulanan</h3><p class="muted">Berdasarkan tanggal pengembalian transaksi.</p></div><span class="dashboard-period-chip">{{ $chartYear }} dan {{ $chartYear - 1 }}</span></div>
        <div class="sales-chart-legend"><span><i class="legend-actual"></i>{{ $chartYear }}</span><span><i class="legend-previous"></i>{{ $chartYear - 1 }}</span></div>
        <div class="sales-bar-chart" role="img" aria-label="Grafik perbandingan pendapatan sewa bulanan tahun {{ $chartYear }} dan {{ $chartYear - 1 }}">
            @foreach(['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'] as $index => $monthLabel)
                @php($currentValue = $monthly[$index + 1])
                @php($previousValue = $monthlyPrevious[$index + 1])
                <div class="sales-month-group" aria-label="{{ $monthLabel }}: Rp {{ number_format($currentValue, 0, ',', '.') }} tahun {{ $chartYear }}, Rp {{ number_format($previousValue, 0, ',', '.') }} tahun {{ $chartYear - 1 }}">
                    <div class="sales-bar-pair"><span class="sales-bar sales-bar-current" style="--bar-height:{{ round($currentValue / $chartMaximum * 100, 1) }}%" title="{{ $chartYear }} · Rp {{ number_format($currentValue, 0, ',', '.') }}"></span><span class="sales-bar sales-bar-previous" style="--bar-height:{{ round($previousValue / $chartMaximum * 100, 1) }}%" title="{{ $chartYear - 1 }} · Rp {{ number_format($previousValue, 0, ',', '.') }}"></span></div>
                    <span class="sales-month-label">{{ $monthLabel }}</span>
                </div>
            @endforeach
        </div>
        <div class="sales-chart-scale"><span>Rp {{ number_format($chartMaximum, 0, ',', '.') }}</span><span>Rp 0</span></div>
    </article>
    <article class="panel dashboard-product-card">
        <div class="dashboard-panel-heading"><div><p class="eyebrow">KOMPOSISI PENYEWAAN</p><h3>Alat paling populer</h3><p class="muted">{{ $dashboardFrom === $dashboardTo ? \Carbon\Carbon::parse($dashboardFrom)->format('d M Y') : \Carbon\Carbon::parse($dashboardFrom)->format('d M') . ' – ' . \Carbon\Carbon::parse($dashboardTo)->format('d M Y') }}</p></div><a class="text-link" href="{{ route('page', ['section' => 'items']) }}">Inventaris →</a></div>
        @include('app.popular-chart')
    </article>
</section>

<section class="dashboard-lists">
    <article class="panel dashboard-list-card">
        <div class="dashboard-panel-heading"><div><p class="eyebrow">JADWAL OPERASIONAL</p><h3>Pengembalian berikutnya</h3><p class="muted">Transaksi aktif berdasarkan tanggal kembali.</p></div><a class="text-link" href="{{ route('page', ['section' => 'rentals']) }}">Semua transaksi →</a></div>
        <div class="dashboard-table-wrap"><table class="dashboard-table"><thead><tr><th>PELANGGAN</th><th>TANGGAL KEMBALI</th><th>STATUS</th><th></th></tr></thead><tbody>
            @forelse($upcomingReturns as $rental)
                @php($isOverdue = $rental->due_date->lt(today()))
                <tr><td><strong>{{ $rental->customer?->name ?? 'Pelanggan diarsipkan' }}</strong><small>{{ $rental->trx_code }}</small></td><td>{{ $rental->due_date->format('d M Y') }}</td><td><span class="dashboard-status {{ $isOverdue ? 'status-risk' : ($rental->due_date->isToday() ? 'status-warn' : 'status-ok') }}">{{ $isOverdue ? 'Terlambat' : ($rental->due_date->isToday() ? 'Hari ini' : 'Terjadwal') }}</span></td><td><a class="dashboard-row-link" href="{{ route('rentals.show', ['rental' => $rental->id]) }}" aria-label="Lihat transaksi {{ $rental->trx_code }}">→</a></td></tr>
            @empty
                <tr><td colspan="4"><div class="dashboard-empty">Tidak ada transaksi sewa aktif.</div></td></tr>
            @endforelse
        </tbody></table></div>
    </article>
    <article class="panel dashboard-list-card">
        <div class="dashboard-panel-heading"><div><p class="eyebrow">AKTIVITAS TERBARU</p><h3>Transaksi terkini</h3><p class="muted">Sewa yang baru dicatat.</p></div><a class="text-link" href="{{ route('page', ['section' => 'rentals']) }}">Lihat semua →</a></div>
        <div class="dashboard-table-wrap"><table class="dashboard-table"><thead><tr><th>TRANSAKSI</th><th>TANGGAL SEWA</th><th>TOTAL</th><th></th></tr></thead><tbody>
            @forelse($recentRentals as $rental)
                <tr><td><strong>{{ $rental->customer?->name ?? 'Pelanggan diarsipkan' }}</strong><small>{{ $rental->trx_code }}</small></td><td>{{ $rental->rent_date->format('d M Y') }}</td><td>Rp {{ number_format($rental->total + $rental->late_fee, 0, ',', '.') }}</td><td><a class="dashboard-row-link" href="{{ route('rentals.show', ['rental' => $rental->id]) }}" aria-label="Lihat transaksi {{ $rental->trx_code }}">→</a></td></tr>
            @empty
                <tr><td colspan="4"><div class="dashboard-empty">Belum ada transaksi.</div></td></tr>
            @endforelse
        </tbody></table></div>
    </article>
</section>
