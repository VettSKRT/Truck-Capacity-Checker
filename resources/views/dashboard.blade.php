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
        <div class="dash-stat__value">{{ $stats['ready_today'] }}</div>
        <div class="dash-stat__label">Siap dikirim</div>
    </div>
    <div class="dash-stat dash-stat--wait">
        <div class="dash-stat__value">{{ $stats['wait_today'] }}</div>
        <div class="dash-stat__label">Perlu tindakan (belum siap atau melebihi)</div>
    </div>
    <div class="dash-stat dash-stat--neutral">
        <div class="dash-stat__value">{{ $fmt($stats['cartons_today'], 0) }}</div>
        <div class="dash-stat__label">Karton hari ini</div>
    </div>
</div>

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
        @include('batches._table', ['batches' => $recent, 'compact' => true])
    @endif
</section>
@endsection
