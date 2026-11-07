@extends('layouts.app')

@section('title', 'Penjadwalan')
@section('heading', 'Penjadwalan')
@section('subheading', 'Jawa dan Sumatra dikirim harian. Aceh dan luar pulau mengikuti jadwal kapal dan harus memenuhi MOQ dealer.')

@section('actions')
    <a class="btn-soft" href="{{ route('dealers.index') }}"><i class="bi bi-people" aria-hidden="true"></i> Dealer dan MOQ</a>
    <a class="btn-cta" href="{{ route('schedule.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Jadwal Kapal</a>
@endsection

@section('content')
@php
    $fmt = fn ($n, $d = 2) => number_format($n, $d, ',', '.');
    $when = fn ($d) => $d->isToday() ? 'Hari ini' : ($d->isTomorrow() ? 'Besok' : $d->diffForHumans(['parts' => 1]));
@endphp

{{-- Jadwal kapal mendatang --}}
<section class="panel panel--flush">
    <div class="panel__head"><h2 class="panel__title">Jadwal kapal mendatang</h2></div>
    @if ($upcoming->isEmpty())
        <div class="empty-state">
            <i class="bi bi-calendar-plus" aria-hidden="true"></i>
            Belum ada jadwal kapal. <a href="{{ route('schedule.create') }}">Tambah jadwal kapal</a> untuk dealer luar pulau dan Aceh.
        </div>
    @else
        <div class="table-wrap table-wrap--free table-wrap--flush">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Jadwal</th><th>Berangkat</th><th>Tujuan</th><th>Kapasitas</th>
                        <th class="num">Kloter</th><th>Terisi</th><th>MOQ dealer</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($upcoming as $s)
                        @php $a = $summaries[$s->id]; $cap = $a['capacity']; @endphp
                        <tr>
                            <td class="nowrap"><a href="{{ route('schedule.show', $s) }}"><strong>{{ $s->code }}</strong></a></td>
                            <td class="nowrap">
                                {{ $s->departs_on->translatedFormat('D, d M Y') }}
                                @if ($s->is_tentative)<span class="badge-status is-wait ms-1">Tentatif</span>@endif
                                <br><small>{{ $when($s->departs_on) }}</small>
                            </td>
                            <td>{{ $s->destination }}</td>
                            <td>{{ $s->vehicle?->name ?? '-' }}</td>
                            <td class="num">{{ $s->uploads_count }}</td>
                            <td>
                                @if ($cap)
                                    <span class="minibar" title="Volume {{ $fmt($cap['volume_ratio'] * 100, 1) }}%, berat {{ $fmt($cap['weight_ratio'] * 100, 1) }}%">
                                        <span class="minibar__track"><span class="minibar__fill {{ $cap['over_capacity'] ? 'is-over' : '' }}" style="width: {{ min(100, $cap['ratio'] * 100) }}%"></span></span>
                                        {{ $fmt($cap['ratio'] * 100, 1) }}%
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if ($a['uploads'] === 0)
                                    <span class="text-muted">Belum ada kloter</span>
                                @elseif ($a['moq_ok'])
                                    <span class="badge-status is-ready"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Semua memenuhi</span>
                                @else
                                    <span class="badge-status is-wait">{{ $a['met'] }} dari {{ count($a['dealers']) }} memenuhi</span>
                                @endif
                            </td>
                            <td class="actions"><a class="btn-soft btn-soft--sm" href="{{ route('schedule.show', $s) }}">Buka</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- Kloter menunggu jadwal kapal --}}
<section class="panel panel--flush">
    <div class="panel__head">
        <h2 class="panel__title">Kloter menunggu jadwal kapal ({{ $waiting->count() }})</h2>
    </div>
    @if ($waiting->isEmpty())
        <div class="empty-state"><i class="bi bi-check-circle" aria-hidden="true"></i> Tidak ada kloter yang menunggu jadwal kapal.</div>
    @else
        <div class="table-wrap table-wrap--free table-wrap--flush">
            <table class="table-clean">
                <thead><tr><th>Kloter</th><th>File</th><th>Dealer kapal</th><th class="num">Volume (m³)</th><th>Tautkan ke jadwal</th></tr></thead>
                <tbody>
                    @foreach ($waiting as $row)
                        @php
                            $u = $row['upload'];
                            $kapalDealers = collect($row['analysis']['dealers'])->where('mode', 'kapal');
                        @endphp
                        <tr>
                            <td class="nowrap"><a href="{{ route('batches.show', $u) }}"><strong>{{ $u->code }}</strong></a></td>
                            <td>{{ \Illuminate\Support\Str::limit($u->filename, 30) }}</td>
                            <td><span class="chip-list">@foreach ($kapalDealers as $d)<span class="chip">{{ $d['code'] }}</span>@endforeach</span></td>
                            <td class="num">{{ $fmt($kapalDealers->sum('volume'), 3) }}</td>
                            <td>
                                @if ($upcoming->isEmpty())
                                    <small class="text-muted">Buat jadwal kapal dulu</small>
                                @else
                                    <form class="inline-form" method="POST" action="{{ route('batches.schedule', $u) }}">
                                        @csrf
                                        <select name="shipment_id" class="form-select" aria-label="Jadwal kapal untuk {{ $u->code }}">
                                            @foreach ($upcoming as $s)
                                                <option value="{{ $s->id }}">{{ $s->code }}, {{ $s->destination }}, {{ $s->departs_on->translatedFormat('d M') }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn-soft btn-soft--sm" type="submit">Tautkan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- Pengiriman harian --}}
<section class="panel panel--flush">
    <div class="panel__head"><h2 class="panel__title">Pengiriman harian ({{ $daily->count() }})</h2></div>
    @if ($daily->isEmpty())
        <div class="empty-state"><i class="bi bi-inbox" aria-hidden="true"></i> Tidak ada kloter harian yang tercatat.</div>
    @else
        <div class="table-wrap table-wrap--free table-wrap--flush">
            <table class="table-clean">
                <thead><tr><th>Kloter</th><th>File</th><th>Rencana kirim</th><th class="num">Dealer</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($daily as $row)
                        @php $u = $row['upload']; $a = $row['analysis']; @endphp
                        <tr>
                            <td class="nowrap"><a href="{{ route('batches.show', $u) }}"><strong>{{ $u->code }}</strong></a></td>
                            <td>{{ \Illuminate\Support\Str::limit($u->filename, 30) }}</td>
                            <td class="nowrap">{{ $a['send_date']?->translatedFormat('D, d M Y') }}<br><small>{{ $a['send_label'] }}</small></td>
                            <td class="num">{{ count($a['dealers']) }}</td>
                            <td>@include('partials.batch-status', ['overall' => $row['overall']])</td>
                            <td class="actions"><a class="btn-soft btn-soft--sm" href="{{ route('batches.show', $u) }}">Buka</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

@if ($undecided->isNotEmpty())
    <div class="notice notice--warn">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div>
            <strong>{{ $undecided->count() }} kloter belum bisa dijadwalkan</strong> karena wilayah dealernya belum diatur:
            {{ $undecided->map(fn ($r) => $r['upload']->code)->implode(', ') }}.
            Atur di <a href="{{ route('areas.index') }}">Pengaturan, Area dan Jalur</a>.
        </div>
    </div>
@endif

{{-- Jadwal lampau atau dibatalkan --}}
@if ($others->isNotEmpty())
    <section class="panel panel--flush">
        <div class="panel__head"><h2 class="panel__title">Jadwal lainnya</h2></div>
        <div class="table-wrap table-wrap--free table-wrap--flush">
            <table class="table-clean">
                <thead><tr><th>Jadwal</th><th>Berangkat</th><th>Tujuan</th><th class="num">Kloter</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($others as $s)
                        <tr>
                            <td class="nowrap"><a href="{{ route('schedule.show', $s) }}"><strong>{{ $s->code }}</strong></a></td>
                            <td class="nowrap">{{ $s->departs_on->translatedFormat('d M Y') }}</td>
                            <td>{{ $s->destination }}</td>
                            <td class="num">{{ $s->uploads_count }}</td>
                            <td>
                                @if ($s->status === 'departed')<span class="badge-status is-ready">{{ $s->status_label }}</span>
                                @elseif ($s->status === 'cancelled')<span class="badge-status is-muted">{{ $s->status_label }}</span>
                                @else<span class="badge-status is-wait">Terlewat, belum diberangkatkan</span>@endif
                            </td>
                            <td class="actions"><a class="btn-soft btn-soft--sm" href="{{ route('schedule.show', $s) }}">Buka</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
