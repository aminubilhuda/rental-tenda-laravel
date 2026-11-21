@if($popularSegments)
    <div class="popular-chart">
        <div class="donut-chart" role="img" aria-label="Komposisi lima alat paling populer">
            <svg viewBox="0 0 128 128" aria-hidden="true"><circle class="donut-track" cx="64" cy="64" r="50"></circle>@foreach($popularSegments as $segment)<circle class="donut-segment" cx="64" cy="64" r="50" stroke="{{ $segment['color'] }}" stroke-dasharray="{{ $segment['dasharray'] }}" stroke-dashoffset="{{ $segment['dashoffset'] }}"></circle>@endforeach</svg>
            <div class="donut-center"><strong>{{ $popularTotal }}</strong><small>unit sewa</small></div>
        </div>
        <div class="popular-legend">@foreach($popularSegments as $segment)<div class="legend-row"><span style="background:{{ $segment['color'] }}"></span><strong>{{ $segment['name'] }}</strong><small>{{ $segment['quantity'] }} unit · {{ $segment['percent'] }}%</small></div>@endforeach</div>
    </div>
@else
    <div class="empty-state">Belum ada data penyewaan.</div>
@endif
