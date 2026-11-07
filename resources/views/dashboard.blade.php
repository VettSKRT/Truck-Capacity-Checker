@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', now()->translatedFormat('l, d F Y'))

@section('actions')
    <a class="btn-cta" href="{{ route('packing.index') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Cek Kapasitas Baru</a>
@endsection

@section('content')
@php
    $fmt = fn ($n, $d = 2) => number_format($n, $d, ',', '.');
    $routesWait = $stats['routes_attention'] - $stats['routes_over'];
    $attentionClass = $stats['routes_over'] > 0 ? 'kpi--alert' : ($stats['routes_attention'] > 0 ? 'kpi--warn' : '');
    $trendTotal = array_sum($trend['volume']);
    $trendToday = end($trend['volume']);
@endphp

{{-- Baris KPI: 3 kolom --}}
<div class="grid-12">
    <section class="panel kpi span-md-4">
        <div class="kpi__label"><span class="kpi__icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span> Kloter hari ini</div>
        <div class="kpi__value" data-kpi="batches_today">{{ $stats['batches_today'] }}</div>
        <div class="kpi__sub"><b>{{ $fmt($stats['cartons_today'], 0) }}</b> karton diunggah hari ini</div>
    </section>

    <section class="panel kpi kpi--ok span-md-4">
        <div class="kpi__label"><span class="kpi__icon"><i class="bi bi-truck" aria-hidden="true"></i></span> Jalur siap dikirim hari ini</div>
        <div class="kpi__value" data-kpi="routes_ready">{{ $stats['routes_ready'] }}</div>
        <div class="kpi__sub">Muatan minimal 80% dari truk jalurnya</div>
    </section>

    <section class="panel kpi {{ $attentionClass }} span-md-4">
        <div class="kpi__label"><span class="kpi__icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span> Jalur belum siap atau melebihi</div>
        <div class="kpi__value" data-kpi="routes_attention">{{ $stats['routes_attention'] }}</div>
        <div class="kpi__sub"><b>{{ $stats['routes_over'] }}</b> melebihi truk, <b>{{ max(0, $routesWait) }}</b> belum cukup penuh</div>
    </section>
</div>

{{-- Grafik utama (8 kolom) dan panel peringatan (4 kolom) --}}
<div class="grid-12">
    <section class="panel span-xl-8">
        <div class="panel__head">
            <h2 class="panel__title"><i class="bi bi-graph-up" aria-hidden="true"></i> Muatan Cargo Ready, 14 hari terakhir</h2>
            <div class="chart-legend">
                <span>Hari ini <b>{{ $fmt($trendToday, 2) }} m³</b></span>
                <span>14 hari <b>{{ $fmt($trendTotal, 2) }} m³</b></span>
            </div>
        </div>
        <div class="chart" id="trendChart" role="img"
             aria-label="Grafik volume karton Cargo Ready per hari selama 14 hari terakhir. Hari ini {{ $fmt($trendToday, 2) }} meter kubik."></div>
    </section>

    <section class="panel span-xl-4">
        <div class="panel__head">
            <h2 class="panel__title"><i class="bi bi-bell" aria-hidden="true"></i> Peringatan</h2>
            @if (count($alerts))
                <span class="badge-status {{ collect($alerts)->contains('level', 'over') ? 'is-over' : 'is-wait' }}">{{ count($alerts) }}</span>
            @endif
        </div>
        <ul class="alert-list">
            @forelse ($alerts as $a)
                <li class="alert-item {{ $a['level'] === 'over' ? '' : 'alert-item--warn' }}">
                    <i class="bi {{ $a['icon'] }}" aria-hidden="true"></i>
                    <div>
                        <div class="alert-item__title">{{ $a['title'] }}</div>
                        <div class="alert-item__text">{{ $a['text'] }}</div>
                        @if ($a['url'])<a href="{{ $a['url'] }}">{{ $a['link'] }}</a>@endif
                    </div>
                </li>
            @empty
                <li class="alert-item alert-item--ok">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <div>
                        <div class="alert-item__title">Tidak ada peringatan</div>
                        <div class="alert-item__text">Tidak ada jalur hari ini yang melebihi truk dan tidak ada kloter yang menunggu jadwal kapal.</div>
                    </div>
                </li>
            @endforelse
        </ul>
    </section>
</div>

<section class="panel">
    <div class="panel__head">
        <h2 class="panel__title"><i class="bi bi-water" aria-hidden="true"></i> Jadwal kapal terdekat</h2>
        <a href="{{ route('schedule.index') }}" class="btn-soft btn-soft--sm">Semua jadwal</a>
    </div>
    @if ($nextShipments->isEmpty())
        <p class="panel__hint">Belum ada jadwal kapal mendatang. <a href="{{ route('schedule.create') }}">Tambah jadwal kapal</a>.</p>
    @else
        <div class="table-wrap table-wrap--free">
            <table class="table-clean">
                <thead>
                    <tr><th>Jadwal</th><th>Berangkat</th><th>Tujuan</th><th>Kendaraan</th><th class="num">Kloter</th></tr>
                </thead>
                <tbody>
                    @foreach ($nextShipments as $s)
                        <tr>
                            <td class="nowrap"><a href="{{ route('schedule.show', $s) }}"><strong>{{ $s->code }}</strong></a></td>
                            <td class="nowrap">{{ $s->departs_on->translatedFormat('D, d M Y') }}@if ($s->is_tentative) <span class="badge-status is-wait ms-1">Tentatif</span>@endif</td>
                            <td>{{ $s->destination }}</td>
                            <td>{{ $s->vehicle?->name ?? '-' }}</td>
                            <td class="num">{{ $s->uploads_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="panel panel--flush">
    <div class="panel__head">
        <h2 class="panel__title"><i class="bi bi-clock-history" aria-hidden="true"></i> Kloter terbaru</h2>
        <a href="{{ route('batches.index') }}" class="btn-soft btn-soft--sm">Lihat semua ({{ $stats['batches_total'] }})</a>
    </div>

    @if ($recent->isEmpty())
        <div class="empty-state">
            <i class="bi bi-inbox" aria-hidden="true"></i>
            Belum ada kloter. Mulai dengan <a href="{{ route('packing.index') }}">Cek Kapasitas</a> dan unggah file order.
        </div>
    @else
        @include('batches._table', ['batches' => $recent, 'summaries' => $summaries, 'compact' => true])
    @endif
</section>
@endsection

@push('scripts')
<script src="https://cdn.plot.ly/plotly-basic-2.35.2.min.js" charset="utf-8"></script>
<script>
    (function () {
        var el = document.getElementById('trendChart');
        if (!el || !window.Plotly) return;
        var t = @json($trend);
        var css = getComputedStyle(document.documentElement);
        var c = function (name) { return css.getPropertyValue(name).trim(); };
        var fmt = function (n, d) { return Number(n).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d }); };

        var hover = t.dates.map(function (_, i) {
            return '<b>' + t.labels[i] + '</b><br>' + fmt(t.volume[i], 2) + ' m³<br>' + fmt(t.weight[i], 0) + ' kg, ' + fmt(t.cartons[i], 0) + ' karton';
        });

        Plotly.newPlot(el, [{
            type: 'scatter', mode: 'lines+markers', x: t.labels, y: t.volume,
            line: { color: c('--accent'), width: 2.5, shape: 'spline', smoothing: 0.4 },
            marker: { color: c('--accent'), size: 6 },
            fill: 'tozeroy',
            fillgradient: { type: 'vertical', colorscale: [[0, 'rgba(48,209,88,0)'], [0.6, 'rgba(48,209,88,0.12)'], [1, 'rgba(0,229,255,0.35)']] },
            text: hover, hoverinfo: 'text', hoverlabel: { bgcolor: '#252525', bordercolor: c('--accent'), font: { color: '#fff', family: 'Inter, sans-serif', size: 13 } },
        }], {
            margin: { l: 56, r: 24, t: 8, b: 40 },
            paper_bgcolor: 'rgba(0,0,0,0)', plot_bgcolor: 'rgba(0,0,0,0)',
            font: { family: 'Inter, sans-serif', color: c('--text-2'), size: 12 },
            xaxis: { showgrid: false, zeroline: false, showline: false, fixedrange: true, ticks: 'outside', ticklen: 8, tickcolor: 'rgba(0,0,0,0)', tickfont: { family: 'JetBrains Mono, monospace', size: 11 } },
            yaxis: { gridcolor: c('--line'), zeroline: false, showline: false, fixedrange: true, rangemode: 'tozero', ticksuffix: ' m³', tickfont: { family: 'JetBrains Mono, monospace', size: 11 } },
            showlegend: false,
        }, { responsive: true, displaylogo: false, displayModeBar: false });
    })();
</script>
@endpush
