{{-- Hasil per jalur. Satu jalur = satu truk khusus ke satu tujuan. Diisi oleh public/js/packing.js --}}
@php $__vehicles = \App\Models\Vehicle::orderBy('id')->get(); @endphp
<section class="results" id="results" @isset($uploadId) data-upload-id="{{ $uploadId }}" @else hidden @endisset>

    {{-- Ringkasan kloter: berapa jalur yang siap --}}
    <div class="status-banner" id="batchBanner" role="status">
        <i class="bi status-banner__icon" id="batchIcon" aria-hidden="true"></i>
        <div class="status-banner__body">
            <div class="status-banner__title" id="batchTitle">Memuat hasil...</div>
            <div class="status-banner__text" id="batchText"></div>
        </div>
    </div>

    {{-- Kartu jalur: pilih satu jalur untuk melihat truknya --}}
    <div>
        <h2 class="panel__title mb-2">Jalur dalam kloter ini</h2>
        <div class="route-grid" id="routeGrid" aria-label="Daftar jalur"></div>
    </div>

    {{-- Detail satu jalur --}}
    <div id="routeDetail" hidden>
        <div class="route-head">
            <h2 class="panel__title" id="routeTitle"></h2>
            <div class="route-head__tools">
                <label class="route-head__truck">
                    <span class="mode-switch__label">Truk:</span>
                    <select class="form-select" id="routeVehicle" aria-label="Truk untuk jalur ini">
                        @foreach ($__vehicles as $v)
                            <option value="{{ $v->code }}">{{ $v->name }} ({{ number_format($v->volume_m3, 2, ',', '.') }} m³, {{ number_format($v->max_payload_kg, 0, ',', '.') }} kg)</option>
                        @endforeach
                    </select>
                </label>
                <a class="btn-soft" id="planLink" href="#" target="_blank" rel="noopener" hidden><i class="bi bi-printer" aria-hidden="true"></i> Cetak rencana muat</a>
            </div>
        </div>

        <div class="status-banner" id="statusBanner" role="status">
            <i class="bi status-banner__icon" id="statusIcon" aria-hidden="true"></i>
            <div class="status-banner__body">
                <div class="status-banner__title" id="statusTitle"></div>
                <div class="status-banner__text" id="statusText"></div>
            </div>
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

        <div class="stat-grid mt-3">
            <div class="stat"><div class="stat__value" id="statLoaded">0</div><div class="stat__label">Karton termuat</div></div>
            <div class="stat" id="statUnloadedBox"><div class="stat__value" id="statUnloaded">0</div><div class="stat__label">Karton tidak termuat</div></div>
            <div class="stat"><div class="stat__value" id="statDealers">0</div><div class="stat__label">Dealer</div></div>
            <div class="stat"><div class="stat__value" id="statPending">0</div><div class="stat__label">Karton belum berstatus 4</div></div>
        </div>

        <div class="panel panel--flush mt-3">
            <div class="toolbar">
                <ul class="tabs" role="tablist">
                    <li role="presentation"><button type="button" class="active" id="tab3d" data-bs-toggle="tab" data-bs-target="#pane3d" role="tab" aria-controls="pane3d" aria-selected="true"><i class="bi bi-box" aria-hidden="true"></i> Tampilan 3D</button></li>
                    <li role="presentation"><button type="button" id="tabDealer" data-bs-toggle="tab" data-bs-target="#paneDealer" role="tab" aria-controls="paneDealer" aria-selected="false"><i class="bi bi-list-ul" aria-hidden="true"></i> Rincian per dealer</button></li>
                    <li role="presentation"><button type="button" id="tabUnloaded" data-bs-toggle="tab" data-bs-target="#paneUnloaded" role="tab" aria-controls="paneUnloaded" aria-selected="false"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Tidak termuat <span class="count" id="unloadedCount" hidden>0</span></button></li>
                </ul>
                <div class="mode-switch">
                    <span class="mode-switch__label" id="modeLabel">Cara hitung:</span>
                    <div class="segmented" role="radiogroup" aria-labelledby="modeLabel">
                        <label><input type="radio" name="mode" value="tetris" checked><span>Susun 3D</span></label>
                        <label><input type="radio" name="mode" value="ratio"><span>Hitung rasio saja</span></label>
                    </div>
                </div>
            </div>

            <div class="tab-content">
                <div class="tab-pane show active pane" id="pane3d" role="tabpanel" aria-labelledby="tab3d" tabindex="0">
                    <div id="view3d">
                        <div class="view-bar" id="viewBar">
                            <span class="view-bar__label">Sudut pandang:</span>
                            <button type="button" class="chip-btn is-on" data-view="iso">3D</button>
                            <button type="button" class="chip-btn" data-view="side">Samping</button>
                            <button type="button" class="chip-btn" data-view="top">Atas</button>
                            <button type="button" class="chip-btn" data-view="end">Ujung</button>
                            <span class="view-bar__label ms-2">Ukuran:</span>
                            <button type="button" class="chip-btn" data-zoom="in" aria-label="Perbesar tampilan"><i class="bi bi-zoom-in" aria-hidden="true"></i> Perbesar</button>
                            <button type="button" class="chip-btn" data-zoom="out" aria-label="Perkecil tampilan"><i class="bi bi-zoom-out" aria-hidden="true"></i> Perkecil</button>
                        </div>
                        <div class="legend" id="legend" aria-label="Keterangan warna dealer"></div>
                        <div class="plot-wrap" id="plotWrap">
                            <div id="plot" role="img" aria-label="Susunan karton di dalam truk"></div>
                            <div class="item-tip" id="itemTip" hidden></div>
                        </div>
                        <p class="pane-note">Arahkan mouse ke karton untuk ringkasan, lalu <strong>klik</strong> untuk detail lengkap. Geser dengan mouse untuk memutar. Untuk memperbesar, pakai tombol Perbesar atau tahan Ctrl sambil menggulir. Setiap warna adalah satu dealer.</p>
                        <div class="item-panel" id="itemPanel" hidden>
                            <div class="item-panel__head">
                                <strong><i class="bi bi-box-seam" aria-hidden="true"></i> Karton terpilih</strong>
                                <button type="button" class="btn-soft btn-soft--sm" id="itemClear">Lepas pilihan</button>
                            </div>
                            <dl class="kv" id="itemBody"></dl>
                        </div>
                    </div>
                    <div class="plot-empty" id="lazyNote" hidden>
                        <i class="bi bi-phone" aria-hidden="true"></i>
                        Tampilan 3D memerlukan banyak daya untuk <span id="lazyCount">0</span> karton, jadi ditunda dulu di layar kecil agar perangkat tidak berat.<br>
                        <button type="button" class="btn-soft mt-2" id="lazyBtn"><i class="bi bi-box" aria-hidden="true"></i> Tampilkan 3D</button>
                    </div>
                    <div class="plot-empty" id="ratioNote" hidden>
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        Mode "Hitung rasio saja" hanya menghitung volume dan berat, jadi tidak ada gambar susunan.<br>
                        Pilih "Susun 3D" untuk melihat penataan karton.
                    </div>
                </div>

                <div class="tab-pane pane" id="paneDealer" role="tabpanel" aria-labelledby="tabDealer" tabindex="0">
                    <div class="table-wrap">
                        <table class="table-clean">
                            <thead>
                                <tr>
                                    <th>Dealer</th><th>Tujuan</th>
                                    <th class="num">Karton</th><th class="num">Volume (m³)</th><th class="num">Berat (kg)</th>
                                </tr>
                            </thead>
                            <tbody id="dealerRows"></tbody>
                            <tfoot id="dealerFoot"></tfoot>
                        </table>
                    </div>
                </div>

                <div class="tab-pane pane" id="paneUnloaded" role="tabpanel" aria-labelledby="tabUnloaded" tabindex="0">
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
    </div>
</section>
