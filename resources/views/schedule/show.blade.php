@extends('layouts.app')

@section('title', 'Jadwal ' . $shipment->code)
@section('heading', 'Jadwal ' . $shipment->code . ': ' . $shipment->destination)
@section('subheading', 'Berangkat ' . $shipment->departs_on->translatedFormat('l, d F Y') . ($shipment->is_tentative ? ', tentatif (bisa maju atau mundur)' : '') . ', ' . $shipment->status_label)

@section('actions')
    <a class="btn-soft" href="{{ route('schedule.edit', $shipment) }}"><i class="bi bi-pencil" aria-hidden="true"></i> Ubah</a>
    <a class="btn-soft" href="{{ route('schedule.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Penjadwalan</a>
@endsection

@section('content')
@php
    $fmt = fn ($n, $d = 2) => number_format($n, $d, ',', '.');
    $cap = $analysis['capacity'];
    $vehicle = $shipment->vehicle;
@endphp

{{-- Status MOQ --}}
@if ($analysis['uploads'] === 0)
    <div class="status-banner is-wait">
        <i class="bi bi-inbox status-banner__icon" aria-hidden="true"></i>
        <div class="status-banner__body">
            <div class="status-banner__title">Belum ada kloter</div>
            <div class="status-banner__text">Tautkan kloter yang berisi dealer luar pulau atau Aceh ke jadwal ini.</div>
        </div>
    </div>
@elseif ($cap && $cap['over_capacity'])
    <div class="status-banner is-over">
        <i class="bi bi-x-octagon-fill status-banner__icon" aria-hidden="true"></i>
        <div class="status-banner__body">
            <div class="status-banner__title">Melebihi kapasitas {{ $vehicle->name }}</div>
            <div class="status-banner__text">Muatan {{ $fmt($cap['ratio'] * 100, 1) }}% dari kapasitas. Lepas sebagian kloter atau pindahkan ke jadwal lain.</div>
        </div>
    </div>
@elseif ($analysis['moq_ok'])
    <div class="status-banner is-ready">
        <i class="bi bi-check-circle-fill status-banner__icon" aria-hidden="true"></i>
        <div class="status-banner__body">
            <div class="status-banner__title">MOQ terpenuhi untuk semua dealer</div>
            <div class="status-banner__text">{{ $analysis['met'] }} dealer memenuhi MOQ. Jadwal ini layak diberangkatkan dari sisi volume dealer.</div>
        </div>
    </div>
@else
    <div class="status-banner is-wait">
        <i class="bi bi-hourglass-split status-banner__icon" aria-hidden="true"></i>
        <div class="status-banner__body">
            <div class="status-banner__title">
                @if ($analysis['not_met'] > 0){{ $analysis['not_met'] }} dealer belum memenuhi MOQ @else MOQ belum diisi untuk {{ $analysis['unset'] }} dealer @endif
            </div>
            <div class="status-banner__text">
                @if ($analysis['not_met'] > 0)
                    Tambah kloter dealer tersebut atau geser tanggal berangkat (jadwal tentatif bisa maju atau mundur).
                @endif
                @if ($analysis['unset'] > 0)
                    <a href="{{ route('dealers.index', ['moq' => 'unset']) }}">Isi MOQ dealer</a> agar bisa dinilai.
                @endif
            </div>
        </div>
    </div>
@endif

{{-- Kapasitas --}}
@if ($cap)
    <div class="meter-grid">
        @foreach ([
            ['Volume', 'bi-box-seam', $cap['volume_ratio'], $fmt($analysis['totals']['volume']) . ' dari ' . $fmt($vehicle->volume_m3) . ' m³', 'volume'],
            ['Berat', 'bi-speedometer', $cap['weight_ratio'], $fmt($analysis['totals']['weight'], 1) . ' dari ' . $fmt($vehicle->max_payload_kg, 0) . ' kg', 'weight'],
        ] as [$label, $icon, $ratio, $text, $key])
            <div class="panel meter">
                <div class="meter__head">
                    <span><i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}</span>
                    @if ($cap['binding'] === $key && $analysis['uploads'] > 0)<span class="meter__badge">Penentu</span>@endif
                </div>
                <div class="meter__value"><b>{{ $fmt($ratio * 100, 1) }}%</b><span>{{ $text }}</span></div>
                <div class="meter__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round(min(1, $ratio) * 100) }}" aria-label="{{ $label }} terisi">
                    <div class="meter__fill {{ $ratio > 1 ? 'is-over' : '' }}" style="width: {{ min(1, $ratio) * 100 }}%"></div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-muted">Jadwal ini belum punya kapasitas. <a href="{{ route('schedule.edit', $shipment) }}">Pilih kontainer atau kendaraan</a> untuk melihat keterisian.</p>
@endif

{{-- MOQ per dealer --}}
<section class="panel panel--flush">
    <div class="panel__head">
        <h2 class="panel__title">MOQ per dealer (volume Cargo Ready di jadwal ini)</h2>
        <a class="btn-soft btn-soft--sm" href="{{ route('dealers.index') }}">Atur MOQ</a>
    </div>
    @if (empty($analysis['dealers']))
        <div class="empty-state"><i class="bi bi-inbox" aria-hidden="true"></i> Belum ada dealer. Tautkan kloter di bawah.</div>
    @else
        <div class="table-wrap table-wrap--free table-wrap--flush">
            <table class="table-clean">
                <thead><tr><th>Dealer</th><th>Wilayah</th><th class="num">Karton</th><th class="num">Volume (m³)</th><th class="num">MOQ (m³)</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($analysis['dealers'] as $d)
                        <tr>
                            <td><strong>{{ $d['code'] }}</strong> <small>{{ $d['name'] }}</small></td>
                            <td>{{ $d['region_label'] ?? 'Belum diatur' }}</td>
                            <td class="num">{{ $fmt($d['cartons'], 0) }}</td>
                            <td class="num">{{ $fmt($d['volume'], 3) }}</td>
                            <td class="num">{{ $d['moq'] !== null ? $fmt($d['moq'], 3) : '-' }}</td>
                            <td>
                                @if ($d['moq_met'] === true)<span class="moq-ok"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Memenuhi</span>
                                @elseif ($d['moq_met'] === false)<span class="moq-bad"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> Kurang {{ $fmt($d['shortfall'], 3) }} m³</span>
                                @else<span class="moq-unset"><i class="bi bi-dash-circle" aria-hidden="true"></i> MOQ belum diatur</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- Kloter tertaut --}}
<section class="panel panel--flush">
    <div class="panel__head"><h2 class="panel__title">Kloter di jadwal ini ({{ $uploads->count() }})</h2></div>
    @if ($uploads->isNotEmpty())
        <div class="table-wrap table-wrap--free table-wrap--flush">
            <table class="table-clean">
                <thead><tr><th>Kloter</th><th>File</th><th class="num">Karton</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($uploads as $u)
                        <tr>
                            <td class="nowrap"><a href="{{ route('batches.show', $u) }}"><strong>{{ $u->code }}</strong></a></td>
                            <td>{{ \Illuminate\Support\Str::limit($u->filename, 34) }}</td>
                            <td class="num">{{ $fmt($u->carton_total ?? 0, 0) }}</td>
                            <td>@include('partials.batch-status', ['overall' => $summaries[$u->id]['overall']])</td>
                            <td class="actions">
                                <form method="POST" action="{{ route('schedule.detach', [$shipment, $u]) }}" class="d-inline"
                                      data-confirm="Kloter {{ $u->code }} kembali menjadi belum dijadwalkan dan tidak dihitung lagi di MOQ jadwal ini."
                                      data-confirm-title="Lepas {{ $u->code }} dari {{ $shipment->code }}?" data-confirm-ok="Lepas kloter" data-confirm-variant="warn" data-confirm-icon="bi-link-45deg">
                                    @csrf @method('DELETE')
                                    <button class="btn-soft btn-soft--sm" type="submit">Lepas</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($shipment->isActive())
        <div class="panel__body {{ $uploads->isNotEmpty() ? 'border-top' : '' }}">
            @if ($available->isEmpty())
                <span class="text-muted">Tidak ada kloter lain yang menunggu jadwal kapal.</span>
            @else
                <form method="POST" action="{{ route('schedule.attach', $shipment) }}" class="d-flex gap-2 flex-wrap align-items-end">
                    @csrf
                    <div>
                        <label class="field-label" for="upload_id">Tautkan kloter</label>
                        <select class="form-select" id="upload_id" name="upload_id">
                            @foreach ($available as $row)
                                <option value="{{ $row['upload']->id }}">{{ $row['upload']->code }}, {{ \Illuminate\Support\Str::limit($row['upload']->filename, 30) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn-cta" type="submit"><i class="bi bi-link-45deg" aria-hidden="true"></i> Tautkan</button>
                </form>
            @endif
        </div>
    @endif
</section>

@if ($shipment->notes)
    <section class="panel"><h2 class="panel__title mb-2">Catatan</h2><p class="mb-0">{{ $shipment->notes }}</p></section>
@endif

<form method="POST" action="{{ route('schedule.destroy', $shipment) }}"
      data-confirm="Kloter yang tertaut tidak ikut terhapus, hanya kembali menjadi belum dijadwalkan."
      data-confirm-title="Hapus jadwal {{ $shipment->code }}?" data-confirm-ok="Hapus jadwal" data-loading="Menghapus jadwal">
    @csrf @method('DELETE')
    <button class="btn-soft btn-soft--danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Hapus jadwal ini</button>
</form>
@endsection
