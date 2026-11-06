@extends('layouts.app')

@section('title', 'Laporan Muatan')

@section('content')
@php
    $fmt = fn ($n, $d = 2) => number_format($n, $d, ',', '.');
    $vehicle = $upload->vehicle;
    $ready = $upload->status === 'Siap Dikirim';
    $over = $upload->ratio > 1;
    $groups = $upload->boxes->groupBy(fn ($b) => $b->cargo_destination . '|' . $b->customer_code . '|' . $b->customer_name);
    $fillClass = $over ? 'is-over' : ($upload->ratio >= config('logistics.ready_ratio', 0.8) ? 'is-ok' : '');
@endphp

<div class="report-head">
    <div>
        <h1>Laporan Muatan</h1>
        <p class="report-meta">
            File {{ $upload->filename }}, diunggah {{ $upload->created_at->translatedFormat('d F Y H:i') }}
            @if ($vehicle)
                , kendaraan {{ $vehicle->name }} ({{ $fmt($vehicle->volume_m3) }} m³ dan {{ $fmt($vehicle->max_payload_kg, 0) }} kg)
            @endif
        </p>
    </div>
    <a class="btn-soft" href="{{ route('packing.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke Cek Kapasitas</a>
</div>

@if ($over)
    <div class="status-banner is-over mb-4" role="status">
        <i class="bi bi-x-octagon-fill status-banner__icon" aria-hidden="true"></i>
        <div class="status-banner__body">
            <div class="status-banner__title">Melebihi kapasitas</div>
            <div class="status-banner__text">Muatan {{ $fmt($upload->ratio * 100, 1) }}% dari kendaraan. Perlu kendaraan tambahan atau kurangi muatan.</div>
        </div>
    </div>
@elseif ($ready)
    <div class="status-banner is-ready mb-4" role="status">
        <i class="bi bi-check-circle-fill status-banner__icon" aria-hidden="true"></i>
        <div class="status-banner__body">
            <div class="status-banner__title">Siap dikirim</div>
            <div class="status-banner__text">Muatan {{ $fmt($upload->ratio * 100, 1) }}% dari kendaraan. Batas minimal 80% sudah terpenuhi.</div>
        </div>
    </div>
@else
    <div class="status-banner is-wait mb-4" role="status">
        <i class="bi bi-hourglass-split status-banner__icon" aria-hidden="true"></i>
        <div class="status-banner__body">
            <div class="status-banner__title">Belum siap dikirim</div>
            <div class="status-banner__text">Muatan baru {{ $fmt($upload->ratio * 100, 1) }}% dari kendaraan. Minimal 80% agar truk berangkat.</div>
        </div>
    </div>
@endif

<div class="stat-grid mb-4">
    <div class="stat">
        <div class="stat__value">{{ $fmt($upload->volume_ratio * 100, 1) }}%</div>
        <div class="stat__label">Volume terisi ({{ $fmt($upload->total_volume) }} m³)</div>
    </div>
    <div class="stat">
        <div class="stat__value">{{ $fmt($upload->weight_ratio * 100, 1) }}%</div>
        <div class="stat__label">Berat terisi ({{ $fmt($upload->total_weight, 1) }} kg)</div>
    </div>
    <div class="stat">
        <div class="stat__value">{{ $upload->binding_constraint === 'weight' ? 'Berat' : 'Volume' }}</div>
        <div class="stat__label">Penentu (yang lebih dulu penuh)</div>
    </div>
    <div class="stat">
        <div class="stat__value">{{ $fmt($upload->boxes->sum('box_count'), 0) }}</div>
        <div class="stat__label">Karton di file ini</div>
    </div>
</div>

<section class="panel">
    <h2 class="mb-1">Rincian per dealer</h2>
    <p class="panel__hint">Volume dan berat Cargo Ready (status 4) dihitung ke muatan. Status 1 sampai 3 hanya ditampilkan sebagai informasi. Ukuran karton dalam milimeter.</p>

    <div class="table-wrap">
        <table class="table-clean">
            <thead>
                <tr>
                    <th>Jalur</th>
                    <th>Tujuan</th>
                    <th>Dealer</th>
                    <th class="num">Cargo Ready (m³)</th>
                    <th class="num">Berat (kg)</th>
                    <th class="num">Picking dan packing (m³)</th>
                    <th class="num">Order diterima (m³)</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groups as $key => $boxes)
                    @php
                        $first = $boxes->first();
                        $ready4 = $boxes->where('status', 4);
                        $routes = $boxes->pluck('route')->filter()->unique()->sort()->implode(', ');
                    @endphp
                    <tr>
                        <td>{{ $routes ?: '-' }}</td>
                        <td>{{ $first->cargo_destination ?: '-' }}</td>
                        <td><strong>{{ $first->customer_code }}</strong><br><small class="text-muted">{{ $first->customer_name }}</small></td>
                        <td class="num">{{ $fmt($ready4->sum('volume'), 4) }}</td>
                        <td class="num">{{ $fmt($ready4->sum('weight_kg'), 1) }}</td>
                        <td class="num">{{ $fmt($boxes->whereIn('status', [2, 3])->sum('volume'), 4) }}</td>
                        <td class="num">{{ $fmt($boxes->where('status', 1)->sum('volume'), 4) }}</td>
                        <td>
                            <button type="button" class="btn-soft" data-bs-toggle="modal" data-bs-target="#detail{{ md5($key) }}">
                                <i class="bi bi-eye" aria-hidden="true"></i> Lihat
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

@foreach ($groups as $key => $boxes)
    @php $first = $boxes->first(); @endphp
    <div class="modal fade" id="detail{{ md5($key) }}" tabindex="-1" aria-labelledby="detailTitle{{ md5($key) }}" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailTitle{{ md5($key) }}">Detail karton: {{ $first->customer_code }} {{ $first->customer_name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="table-wrap">
                        <table class="table-clean">
                            <thead>
                                <tr>
                                    <th>Part</th>
                                    <th>Jenis</th>
                                    <th class="num">Qty</th>
                                    <th>Karton</th>
                                    <th>Ukuran (P × L × T)</th>
                                    <th class="num">Volume (m³)</th>
                                    <th class="num">Berat (kg)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($boxes as $box)
                                    <tr>
                                        <td>{{ $box->part_number }}</td>
                                        <td>{{ $box->packing_type === 'receh' ? 'Receh' : 'Unit' }}</td>
                                        <td class="num">{{ $box->qty }}</td>
                                        <td>{{ $box->box_count }} × No. {{ $box->carton?->number }}</td>
                                        <td>{{ $box->panjang }} × {{ $box->lebar }} × {{ $box->tinggi }}</td>
                                        <td class="num">{{ $fmt($box->volume, 4) }}</td>
                                        <td class="num">{{ $fmt($box->weight_kg, 2) }}</td>
                                        <td>{{ config('logistics.statuses')[$box->status] ?? $box->status }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach
@endsection
