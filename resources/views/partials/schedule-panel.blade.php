{{-- Panel jadwal di detail kloter. Variabel: $upload, $schedule, $shipments --}}
@php $fmtS = fn ($n, $d = 2) => number_format($n, $d, ',', '.'); @endphp
<section class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Jadwal pengiriman</h2>
        @include('partials.mode-badge', ['mode' => $schedule['mode']])
    </div>

    @if ($schedule['mode'] === 'belum_diatur' || $schedule['has_unknown'])
        <div class="notice notice--warn">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <div>Ada dealer yang kode areanya belum punya wilayah, jadi jenis jadwalnya belum bisa ditentukan. Atur wilayahnya di <a href="{{ route('areas.index') }}">Pengaturan, Area dan Jalur</a>.</div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="table-wrap table-wrap--free">
                <table class="table-clean">
                    <thead><tr><th>Dealer</th><th>Wilayah</th><th>Jadwal</th><th class="num">Volume (m³)</th></tr></thead>
                    <tbody>
                        @foreach ($schedule['dealers'] as $d)
                            <tr>
                                <td><strong>{{ $d['code'] }}</strong> <small>{{ $d['name'] }}</small></td>
                                <td>{{ $d['region_label'] ?? 'Belum diatur' }}</td>
                                <td>{{ $d['mode'] === 'daily' ? 'Harian' : ($d['mode'] === 'kapal' ? 'Kapal' : '-') }}</td>
                                <td class="num">{{ $fmtS($d['volume'], 3) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-5">
            @if ($schedule['send_date'])
                <dl class="kv mb-3">
                    <dt>Rencana kirim harian</dt>
                    <dd><strong>{{ $schedule['send_date']->translatedFormat('l, d F Y') }}</strong><br><small class="text-muted">{{ $schedule['send_label'] }}</small></dd>
                </dl>
            @endif

            @if (in_array($schedule['mode'], ['kapal', 'campuran'], true) || $upload->shipment_id)
                @if ($upload->shipment)
                    <dl class="kv mb-2">
                        <dt>Jadwal kapal</dt>
                        <dd>
                            <a href="{{ route('schedule.show', $upload->shipment) }}"><strong>{{ $upload->shipment->code }}</strong></a>,
                            {{ $upload->shipment->destination }}<br>
                            <small class="text-muted">Berangkat {{ $upload->shipment->departs_on->translatedFormat('d F Y') }}{{ $upload->shipment->is_tentative ? ' (tentatif)' : '' }}</small>
                        </dd>
                    </dl>
                @elseif ($schedule['needs_shipment'])
                    <div class="notice notice--warn mb-2">
                        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                        <div>Kloter ini berisi dealer luar pulau atau Aceh dan <strong>belum ditautkan ke jadwal kapal</strong>.</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('batches.schedule', $upload) }}" class="d-flex gap-2 flex-wrap align-items-end">
                    @csrf
                    <div class="flex-grow-1">
                        <label class="field-label" for="shipment_id">Tautkan ke jadwal kapal</label>
                        <select class="form-select" id="shipment_id" name="shipment_id">
                            <option value="">Belum dijadwalkan</option>
                            @foreach ($shipments as $s)
                                <option value="{{ $s->id }}" @selected($upload->shipment_id === $s->id)>
                                    {{ $s->code }}, {{ $s->destination }}, {{ $s->departs_on->translatedFormat('d M Y') }}{{ $s->is_tentative ? ' (tentatif)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-soft"><i class="bi bi-link-45deg" aria-hidden="true"></i> Simpan</button>
                </form>
                @if ($shipments->isEmpty())
                    <p class="panel__hint mt-2">Belum ada jadwal kapal. <a href="{{ route('schedule.create') }}">Buat jadwal kapal</a> dulu.</p>
                @endif
            @elseif ($schedule['mode'] === 'daily')
                <p class="panel__hint">Semua dealer di kloter ini dikirim harian, jadi tidak perlu jadwal kapal.</p>
            @endif
        </div>
    </div>
</section>
