@extends('layouts.app')

@section('title', 'Cek Kapasitas')

@section('content')
@php
    $fmt = fn ($n, $d = 2) => number_format($n, $d, ',', '.');
@endphp

<div class="page-intro">
    <h1>Cek Kapasitas Muatan</h1>
    <p>Pilih kendaraan, unggah file order, lalu lihat apakah muatan sudah siap dikirim.</p>
</div>

<form id="checkForm" novalidate>
    {{-- Langkah 1 --}}
    <section class="panel" aria-labelledby="stepVehicle">
        <h2 class="panel__title" id="stepVehicle"><span class="step" aria-hidden="true">1</span> Pilih kendaraan</h2>
        <div class="vehicle-grid" role="radiogroup" aria-labelledby="stepVehicle">
            @foreach ($vehicles as $v)
                <label class="vehicle-card">
                    <input type="radio" name="vehicle" value="{{ $v->code }}" @checked($v->code === config('logistics.default_vehicle'))>
                    <span class="vehicle-card__body">
                        <span class="vehicle-card__name">{{ $v->name }}</span>
                        <span class="vehicle-card__dims">
                            {{ $fmt($v->length_mm / 1000) }} × {{ $fmt($v->width_mm / 1000) }} × {{ $fmt($v->height_mm / 1000) }} meter
                        </span>
                        <span class="vehicle-card__stats">
                            <b>{{ $fmt($v->volume_m3) }}</b> m³ &nbsp;dan&nbsp; <b>{{ $fmt($v->max_payload_kg, 0) }}</b> kg
                        </span>
                    </span>
                    <i class="bi bi-check-circle-fill vehicle-card__check" aria-hidden="true"></i>
                </label>
            @endforeach
        </div>
    </section>

    {{-- Langkah 2 --}}
    <section class="panel" aria-labelledby="stepFile">
        <h2 class="panel__title" id="stepFile"><span class="step" aria-hidden="true">2</span> Unggah file order</h2>

        <label class="dropzone" id="dropzone" for="excel_file">
            <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
            <span class="dropzone__main" id="fileName">Klik di sini untuk memilih file</span>
            <span class="dropzone__hint" id="fileHint">atau tarik file ke kotak ini (format .xlsx, .xls, atau .csv)</span>
            <input type="file" class="visually-hidden" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv">
        </label>

        <details class="format-help">
            <summary>Lihat urutan kolom yang dibutuhkan</summary>
            <ol>
                <li>Cargo Destination (tujuan)</li>
                <li>Customer Code (kode dealer, contoh SA38)</li>
                <li>Customer Name (nama dealer)</li>
                <li>Part Number</li>
                <li>Qty (jumlah pcs)</li>
                <li>Status (1 sampai 4, hanya status 4 yang dihitung siap kirim)</li>
                <li>Service Type (boleh kosong: same_day, one_day, atau emergency)</li>
            </ol>
        </details>

        <div class="action-row">
            <button type="submit" class="btn-cta" id="submitBtn" disabled>
                <i class="bi bi-calculator" aria-hidden="true"></i>
                <span id="submitLabel">Cek Kapasitas</span>
            </button>
            <span class="action-row__hint" id="submitHint">Pilih file dulu, lalu tombol ini aktif.</span>
        </div>
    </section>
</form>

<div id="messages" aria-live="polite"></div>

{{-- Kondisi awal --}}
<section class="panel" id="emptyState">
    <h2 class="mb-3">Cara membaca hasil</h2>
    <ul class="how-to">
        <li><strong>Status besar di atas</strong>Hijau berarti siap dikirim. Kuning berarti belum cukup penuh. Merah berarti muatan melebihi kendaraan.</li>
        <li><strong>Dua batang ukur</strong>Volume dan berat dihitung terpisah. Yang lebih dulu penuh menjadi penentu.</li>
        <li><strong>Tampilan 3D</strong>Memperlihatkan susunan karton di dalam kendaraan. Berat di bawah, ringan di atas.</li>
    </ul>
</section>

{{-- Hasil --}}
<section class="results" id="results" hidden>
    <div class="status-banner" id="statusBanner" role="status">
        <i class="bi status-banner__icon" id="statusIcon" aria-hidden="true"></i>
        <div class="status-banner__body">
            <div class="status-banner__title" id="statusTitle"></div>
            <div class="status-banner__text" id="statusText"></div>
        </div>
        <a class="btn-soft" id="reportLink" href="#"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Buka laporan lengkap</a>
    </div>

    <div class="meter-grid">
        <div class="panel meter" id="meterVolume">
            <div class="meter__head">
                <span><i class="bi bi-box-seam" aria-hidden="true"></i> Volume</span>
                <span class="meter__badge" hidden>Penentu</span>
            </div>
            <div class="meter__value"><b data-pct>0%</b><span data-text></span></div>
            <div class="meter__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-label="Volume terpakai">
                <div class="meter__fill"></div>
                <div class="meter__mark"><span>minimal 80%</span></div>
            </div>
        </div>
        <div class="panel meter" id="meterWeight">
            <div class="meter__head">
                <span><i class="bi bi-speedometer" aria-hidden="true"></i> Berat</span>
                <span class="meter__badge" hidden>Penentu</span>
            </div>
            <div class="meter__value"><b data-pct>0%</b><span data-text></span></div>
            <div class="meter__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-label="Berat terpakai">
                <div class="meter__fill"></div>
                <div class="meter__mark"><span>minimal 80%</span></div>
            </div>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat"><div class="stat__value" id="statLoaded">0</div><div class="stat__label">Karton termuat</div></div>
        <div class="stat" id="statUnloadedBox"><div class="stat__value" id="statUnloaded">0</div><div class="stat__label">Karton tidak termuat</div></div>
        <div class="stat"><div class="stat__value" id="statDealers">0</div><div class="stat__label">Dealer</div></div>
        <div class="stat"><div class="stat__value" id="statRoutes">0</div><div class="stat__label">Jalur</div></div>
    </div>

    <div class="panel panel--flush">
        <div class="toolbar">
            <ul class="tabs" role="tablist">
                <li role="presentation"><button type="button" class="active" id="tab3d" data-bs-toggle="tab" data-bs-target="#pane3d" role="tab" aria-controls="pane3d" aria-selected="true"><i class="bi bi-box" aria-hidden="true"></i> Tampilan 3D</button></li>
                <li role="presentation"><button type="button" id="tabDealer" data-bs-toggle="tab" data-bs-target="#paneDealer" role="tab" aria-controls="paneDealer" aria-selected="false"><i class="bi bi-list-ul" aria-hidden="true"></i> Rincian per dealer</button></li>
                <li role="presentation"><button type="button" id="tabUnloaded" data-bs-toggle="tab" data-bs-target="#paneUnloaded" role="tab" aria-controls="paneUnloaded" aria-selected="false"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Tidak termuat <span class="count" id="unloadedCount" hidden>0</span></button></li>
            </ul>
            <div class="mode-switch">
                <span class="mode-switch__label" id="modeLabel">Cara hitung</span>
                <div class="segmented" role="radiogroup" aria-labelledby="modeLabel">
                    <label><input type="radio" name="mode" value="tetris" checked><span>Susun 3D</span></label>
                    <label><input type="radio" name="mode" value="ratio"><span>Hitung rasio saja</span></label>
                </div>
            </div>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade show active pane" id="pane3d" role="tabpanel" aria-labelledby="tab3d" tabindex="0">
                <div id="view3d">
                    <div class="view-bar" id="viewBar">
                        <span class="view-bar__label">Sudut pandang:</span>
                        <button type="button" class="chip-btn is-on" data-view="iso">3D</button>
                        <button type="button" class="chip-btn" data-view="side">Samping</button>
                        <button type="button" class="chip-btn" data-view="top">Atas</button>
                        <button type="button" class="chip-btn" data-view="end">Ujung</button>
                    </div>
                    <div class="legend" id="legend" aria-label="Keterangan warna jalur"></div>
                    <div id="plot" role="img" aria-label="Susunan karton di dalam kendaraan"></div>
                    <p class="pane-note">Geser untuk memutar, gulir untuk memperbesar. Setiap warna adalah satu jalur pengiriman.</p>
                </div>
                <div class="plot-empty" id="ratioNote" hidden>
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    Mode "Hitung rasio saja" hanya menghitung volume dan berat, jadi tidak ada gambar susunan.<br>
                    Pilih "Susun 3D" untuk melihat penataan karton.
                </div>
            </div>

            <div class="tab-pane fade pane" id="paneDealer" role="tabpanel" aria-labelledby="tabDealer" tabindex="0">
                <div class="table-wrap">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Jalur</th><th>Dealer</th><th>Tujuan</th>
                                <th class="num">Karton</th><th class="num">Volume (m³)</th><th class="num">Berat (kg)</th>
                            </tr>
                        </thead>
                        <tbody id="dealerRows"></tbody>
                        <tfoot id="dealerFoot"></tfoot>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade pane" id="paneUnloaded" role="tabpanel" aria-labelledby="tabUnloaded" tabindex="0">
                <div id="unloadedEmpty" class="empty-state" hidden>
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    Semua karton termuat. Tidak ada yang tertinggal.
                </div>
                <div class="table-wrap" id="unloadedWrap">
                    <table class="table-clean">
                        <thead>
                            <tr><th>Part</th><th>Dealer</th><th>Alasan</th><th class="num">Karton</th></tr>
                        </thead>
                        <tbody id="unloadedRows"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.plot.ly/plotly-2.35.2.min.js" charset="utf-8"></script>
<script src="{{ asset('js/packing.js') }}"></script>
@endpush
