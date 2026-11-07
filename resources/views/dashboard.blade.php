@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', now()->translatedFormat('l, d F Y'))

@section('actions')
    <a class="btn-cta" href="{{ route('packing.index') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Cek Kapasitas Baru</a>
@endsection

@section('content')
@php $fmt = fn ($n, $d = 2) => number_format($n, $d, ',', '.'); @endphp

<div class="dash-grid">
    <div class="dash-stat">
        <div class="dash-stat__value">{{ $stats['batches_today'] }}</div>
        <div class="dash-stat__label">Kloter hari ini</div>
    </div>
    <div class="dash-stat dash-stat--ok">
        <div class="dash-stat__value">{{ $stats['routes_ready'] }}</div>
        <div class="dash-stat__label">Jalur siap dikirim hari ini</div>
    </div>
    <div class="dash-stat dash-stat--wait">
        <div class="dash-stat__value">{{ $stats['routes_attention'] }}</div>
        <div class="dash-stat__label">Jalur belum siap atau melebihi</div>
    </div>
    <div class="dash-stat dash-stat--neutral">
        <div class="dash-stat__value">{{ $fmt($stats['cartons_today'], 0) }}</div>
        <div class="dash-stat__label">Karton hari ini</div>
    </div>
</div>

@if ($waitingForShipment > 0)
    <div class="notice notice--warn">
        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
        <div><strong>{{ $waitingForShipment }} kloter</strong> berisi dealer luar pulau atau Aceh dan belum ditautkan ke jadwal kapal.
            <a href="{{ route('schedule.index') }}">Buka Penjadwalan</a></div>
    </div>
@endif

<section class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Jadwal kapal terdekat</h2>
        <a href="{{ route('schedule.index') }}" class="btn-soft btn-soft--sm">Semua jadwal</a>
    </div>
    @if ($nextShipments->isEmpty())
        <p class="panel__hint">Belum ada jadwal kapal mendatang. <a href="{{ route('schedule.create') }}">Tambah jadwal kapal</a>.</p>
    @else
        <div class="table-wrap table-wrap--free">
            <table class="table-clean">
                <tbody>
                    @foreach ($nextShipments as $s)
                        <tr>
                            <td class="nowrap"><a href="{{ route('schedule.show', $s) }}"><strong>{{ $s->code }}</strong></a></td>
                            <td class="nowrap">{{ $s->departs_on->translatedFormat('D, d M Y') }}@if ($s->is_tentative) <span class="badge-status is-wait ms-1">Tentatif</span>@endif</td>
                            <td>{{ $s->destination }}</td>
                            <td>{{ $s->vehicle?->name ?? '-' }}</td>
                            <td class="num">{{ $s->uploads_count }} kloter</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="panel panel--flush">
    <div class="panel__head p-3 pb-0 mb-2">
        <h2 class="panel__title">Kloter terbaru</h2>
        <a href="{{ route('batches.index') }}" class="btn-soft btn-soft--sm">Lihat semua ({{ $stats['batches_total'] }})</a>
    </div>

    @if ($recent->isEmpty())
        <div class="empty-state">
            <i class="bi bi-inbox" style="color: var(--blue-600)" aria-hidden="true"></i>
            Belum ada kloter. Mulai dengan <a href="{{ route('packing.index') }}">Cek Kapasitas</a> dan unggah file order.
        </div>
    @else
        @include('batches._table', ['batches' => $recent, 'summaries' => $summaries, 'compact' => true])
    @endif
</section>
@endsection
