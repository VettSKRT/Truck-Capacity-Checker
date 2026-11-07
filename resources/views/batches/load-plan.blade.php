@extends('layouts.app')

@php
    $fmt = fn ($n, $d = 2) => number_format($n, $d, ',', '.');
    $label = $summary['label'] . ($summary['name'] ? ' (' . $summary['name'] . ')' : '');
    $stateText = ['ready' => 'Siap dikirim', 'wait' => 'Belum siap', 'over' => 'Melebihi kapasitas', 'none' => 'Belum ada barang siap'][$summary['state']] ?? '';
    $totalCartons = 0; $totalVolume = 0; $totalWeight = 0;
@endphp

@section('title', 'Rencana Muat ' . $summary['label'])
@section('heading', 'Rencana Muat ' . $label)
@section('subheading', 'Kloter ' . $upload->code . ', ' . $upload->filename)

@section('actions')
    <button type="button" class="btn-cta" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> Cetak</button>
    <a class="btn-soft" href="{{ route('batches.show', $upload) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kloter</a>
@endsection

@section('content')
<section class="panel plan-head">
    <dl class="kv">
        <dt>Kloter</dt><dd><strong>{{ $upload->code }}</strong>, diunggah {{ $upload->created_at->translatedFormat('d F Y H:i') }}</dd>
        <dt>Jalur</dt><dd><strong>{{ $label }}</strong></dd>
        <dt>Truk</dt><dd>{{ $summary['vehicle']['name'] ?? '-' }}, kapasitas {{ $fmt($summary['vehicle']['volume_m3'] ?? 0) }} m³ dan {{ $fmt($summary['vehicle']['max_payload_kg'] ?? 0, 0) }} kg</dd>
        <dt>Muatan siap</dt>
        <dd>
            {{ $fmt($summary['volume']) }} m³ ({{ $fmt($summary['volume_ratio'] * 100, 1) }}%) dan {{ $fmt($summary['weight'], 1) }} kg ({{ $fmt($summary['weight_ratio'] * 100, 1) }}%),
            {{ $fmt($summary['cartons'], 0) }} karton, {{ $summary['dealers'] }} dealer
        </dd>
        <dt>Status</dt>
        <dd><span class="badge-status is-{{ $summary['state'] === 'none' ? 'muted' : $summary['state'] }}">{{ $stateText }}</span></dd>
    </dl>
</section>

@foreach ($dealers as $d)
    <section class="panel panel--flush plan-dealer">
        <div class="p-3 pb-2">
            <h2 class="panel__title">{{ $d['code'] }} <span class="text-muted fw-normal">{{ $d['name'] }}{{ $d['destination'] ? ', ' . $d['destination'] : '' }}</span></h2>
        </div>

        @if ($d['ready']->isNotEmpty())
            <div class="table-wrap table-wrap--free table-wrap--flush">
                <table class="table-clean">
                    <thead>
                        <tr>
                            <th>Part</th><th>Jenis</th><th class="num">Qty (pcs)</th><th>Karton</th>
                            <th>Ukuran P × L × T (mm)</th><th class="num">Volume (m³)</th><th class="num">Berat (kg)</th><th>Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($d['ready'] as $b)
                            @php $totalCartons += $b->box_count; $totalVolume += $b->volume; $totalWeight += $b->weight_kg; @endphp
                            <tr>
                                <td><strong>{{ $b->part_number }}</strong></td>
                                <td>{{ $b->packing_type === 'receh' ? 'Receh' : 'Unit' }}</td>
                                <td class="num">{{ $fmt($b->qty, 0) }}</td>
                                <td>{{ $fmt($b->box_count, 0) }} × No. {{ $b->carton?->number }}</td>
                                <td>{{ $b->panjang }} × {{ $b->lebar }} × {{ $b->tinggi }}</td>
                                <td class="num">{{ $fmt($b->volume, 3) }}</td>
                                <td class="num">{{ $fmt($b->weight_kg, 1) }}</td>
                                <td>{{ $b->category === 'heavy' ? 'Berat / besar' : 'Kecil' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="panel__hint px-3 pb-3 mb-0">Belum ada karton berstatus 4 (Cargo Ready) untuk dealer ini.</p>
        @endif

        @if ($d['pending']->isNotEmpty())
            <p class="panel__hint px-3 py-2 mb-0 border-top">
                Belum masuk muatan (status 1 sampai 3): {{ $d['pending']->map(fn ($b) => $b->part_number . ' x' . $b->box_count)->implode(', ') }}.
            </p>
        @endif
    </section>
@endforeach

<section class="panel plan-total">
    <strong>Total muatan {{ $summary['label'] }}:</strong>
    {{ $fmt($totalCartons, 0) }} karton, {{ $fmt($totalVolume) }} m³, {{ $fmt($totalWeight, 1) }} kg
</section>

<section class="panel plan-sign">
    <div class="row text-center">
        <div class="col-4"><div class="plan-sign__line"></div>Disiapkan oleh (gudang)</div>
        <div class="col-4"><div class="plan-sign__line"></div>Sopir</div>
        <div class="col-4"><div class="plan-sign__line"></div>Diperiksa oleh</div>
    </div>
</section>
@endsection
