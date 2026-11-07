/* Cek Kapasitas Truk
 * Satu jalur = satu truk khusus ke satu tujuan. Satu kloter (satu file) berisi beberapa jalur,
 * jadi kesiapan kirim dinilai PER JALUR.
 *
 * Alur: unggah file -> POST /upload -> GET /riwayat/{id}/jalur (kartu jalur)
 *       -> pilih jalur -> POST /packing/optimize (muatan, rasio, tampilan 3D satu truk).
 * Di halaman detail kloter, kartu jalur dimuat otomatis dari data yang tersimpan. */
(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const token = $('meta[name="csrf-token"]').content;

    const READY_RATIO = 0.8;
    // Satu tampilan 3D = satu truk, jadi karton diwarnai per dealer
    // Warna terang yang jelas di latar gelap. Tidak ada merah: merah dipakai untuk karton terpilih, cian untuk rangka truk.
    const DEALER_COLORS = ['#0A84FF', '#FF9F0A', '#30D158', '#BF5AF2', '#FFD60A', '#64D2FF', '#AC8E68', '#5E5CE6', '#66D4CF', '#D1D1D6'];
    const THEME = { frame: '#00E5FF', selected: '#FF453A', edge: 'rgba(0,0,0,0.6)', paper: '#171717', wall: '#1B1B1B', grid: '#2C2C2E', text: '#98989D' };
    const REASONS = {
        weight: 'Melebihi batas berat truk',
        volume: 'Melebihi batas volume truk',
        space: 'Tidak muat pada susunan',
        too_big: 'Ukuran karton lebih besar dari truk',
    };
    const STATE_UI = {
        ready: { badge: 'is-ready', text: 'Siap dikirim', icon: 'bi-check-circle-fill' },
        wait: { badge: 'is-wait', text: 'Belum siap', icon: 'bi-hourglass-split' },
        over: { badge: 'is-over', text: 'Melebihi kapasitas', icon: 'bi-x-octagon-fill' },
        none: { badge: 'is-muted', text: 'Belum ada barang siap', icon: 'bi-inbox' },
    };
    const CAMERAS = {
        iso: { eye: { x: 1.4, y: -1.9, z: 1.15 }, up: { x: 0, y: 0, z: 1 } },
        side: { eye: { x: 0, y: -2.4, z: 0.25 }, up: { x: 0, y: 0, z: 1 } },
        top: { eye: { x: 0, y: -0.001, z: 2.8 }, up: { x: 0, y: 1, z: 0 } },
        end: { eye: { x: -2.4, y: 0, z: 0.35 }, up: { x: 0, y: 0, z: 1 } },
    };
    const PLOT_CONFIG = { responsive: true, displaylogo: false, displayModeBar: false };
    // Garis tepi karton adalah bagian paling berat bagi GPU (puluhan ribu titik garis untuk satu jalur besar),
    // jadi hanya digambar untuk muatan kecil.
    const MAX_EDGE_BOXES = 150;
    // Layar kecil (HP) atau muatan sangat besar: grafik 3D baru digambar setelah pengguna menekan "Tampilkan 3D"
    const LAZY_3D_CARTONS = 2500;
    // Indeks segitiga kubus (urutan titik: lihat buildTraces)
    const CUBE_I = [7, 0, 0, 0, 4, 4, 6, 6, 4, 0, 3, 2];
    const CUBE_J = [3, 4, 1, 2, 5, 6, 5, 2, 0, 1, 6, 3];
    const CUBE_K = [0, 7, 2, 3, 6, 7, 1, 1, 5, 5, 7, 6];

    const clone = (o) => JSON.parse(JSON.stringify(o));
    const state = {
        uploadId: null, routes: [], overall: null, routeKey: null,
        mode: 'tetris', seq: 0, data: null, view: 'iso', file: null, cam: clone(CAMERAS.iso), plotBound: false,
        meshMap: {}, selected: null, hovered: null, clickHandled: false, hlIndex: null, plotDrawn: false, force3d: false, mouse: { x: 0, y: 0 },
    };

    const el = {
        form: $('#checkForm'),
        input: $('#excel_file'),
        dropzone: $('#dropzone'),
        fileName: $('#fileName'),
        fileHint: $('#fileHint'),
        submit: $('#submitBtn'),
        submitLabel: $('#submitLabel'),
        submitHint: $('#submitHint'),
        messages: $('#messages'),
        empty: $('#emptyState'),
        results: $('#results'),
        routeGrid: $('#routeGrid'),
        routeDetail: $('#routeDetail'),
        vehicleSelect: $('#routeVehicle'),
        planLink: $('#planLink'),
    };

    /* ------------------------- Plotly (dimuat saat perlu) ------------------------- */
    // Bundel gl3d hanya berisi jenis grafik 3D (scatter3d, mesh3d, ...): sekitar 1,7 MB, bukan 4,5 MB bundel penuh.
    // Tidak dipasang sebagai <script> di halaman, jadi halaman tampil dulu dan Plotly diunduh di latar belakang.
    const PLOTLY_SRC = 'https://cdn.plot.ly/plotly-gl3d-2.35.2.min.js';
    let plotlyPromise = null;
    function loadPlotly() {
        if (window.Plotly) return Promise.resolve(window.Plotly);
        if (!plotlyPromise) {
            plotlyPromise = new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = PLOTLY_SRC;
                s.async = true;
                s.charset = 'utf-8';
                s.onload = () => (window.Plotly ? resolve(window.Plotly) : reject(new Error('Plotly tidak tersedia.')));
                s.onerror = () => {
                    plotlyPromise = null;   // boleh dicoba lagi
                    s.remove();
                    reject(new Error('Tampilan 3D gagal dimuat. Periksa koneksi internet, lalu pilih jalurnya lagi.'));
                };
                document.head.appendChild(s);
            });
        }
        return plotlyPromise;
    }
    const prefetchPlotly = () => loadPlotly().catch(() => { /* dicoba lagi saat benar-benar dibutuhkan */ });

    // Overlay animasi di area 3D
    function plotBusy(text) {
        const box = $('#plotLoader');
        if (!box) return;
        $('#plotLoaderText').textContent = text;
        box.hidden = false;
    }
    function plotIdle() {
        const box = $('#plotLoader');
        if (box) box.hidden = true;
    }
    const pageLoader = () => (window.App && window.App.loader) || { show() {}, hide() {} };

    /* ----------------------------- Utilitas ----------------------------- */

    const nfCache = {};
    function num(n, d = 2) {
        const nf = nfCache[d] || (nfCache[d] = new Intl.NumberFormat('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d }));
        return nf.format(n);
    }
    const pct = (ratio) => num(ratio * 100, 1) + '%';
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const currentRoute = () => state.routes.find((r) => r.key === state.routeKey) || null;

    async function request(url, options = {}) {
        const res = await fetch(url, {
            ...options,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, ...(options.headers || {}) },
        });
        let body = null;
        try { body = await res.json(); } catch (e) { /* bukan JSON */ }

        if (!res.ok) {
            let message = body && body.message;
            if (body && body.errors) {
                message = Object.values(body.errors).flat().join(' ');
            }
            if (!message) {
                message = {
                    413: 'File terlalu besar untuk diunggah.',
                    419: 'Sesi sudah habis. Muat ulang halaman ini lalu coba lagi.',
                    500: 'Terjadi kesalahan di server. Coba lagi, atau hubungi admin bila berulang.',
                }[res.status] || 'Permintaan gagal (kode ' + res.status + ').';
            }
            throw new Error(message);
        }
        return body;
    }

    /* ------------------------------ Pesan ------------------------------- */

    function clearMessages() { el.messages.innerHTML = ''; }

    function showError(text) {
        el.messages.innerHTML = `<div class="notice notice--error" role="alert"><i class="bi bi-x-octagon-fill" aria-hidden="true"></i><div><strong>Gagal memproses.</strong><br>${esc(text)}</div></div>`;
        el.messages.scrollIntoView({ block: 'nearest' });
    }

    function showWarnings(list) {
        if (!list || !list.length) return;
        const items = list.map((w) => `<li>${esc(w)}</li>`).join('');
        el.messages.insertAdjacentHTML('beforeend',
            `<div class="notice notice--warn"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><details open><summary>${list.length} catatan perlu dicek pada file</summary><ul>${items}</ul></details></div>`);
    }

    /* ----------------------------- Form & file --------------------------- */
    // Hanya ada di halaman Cek Kapasitas (di detail kloter tidak ada formulir unggah)
    if (el.form) {
        const setFile = (file) => {
            if (file && !/\.(xlsx|xls|csv)$/i.test(file.name)) {
                showError('Format file tidak didukung. Gunakan file .xlsx, .xls, atau .csv.');
                file = null;
            }
            state.file = file || null;
            el.dropzone.classList.toggle('has-file', !!state.file);
            el.submit.disabled = !state.file;

            if (state.file) {
                prefetchPlotly();   // hampir pasti dibutuhkan sebentar lagi
                el.fileName.textContent = state.file.name;
                el.fileHint.textContent = `Ukuran ${num(state.file.size / 1024, 0)} KB. Klik untuk mengganti file.`;
                el.submitHint.textContent = 'Siap diproses.';
            } else {
                el.fileName.textContent = 'Klik untuk memilih file';
                el.fileHint.textContent = 'atau tarik file ke sini (.xlsx, .xls, atau .csv)';
                el.submitHint.textContent = 'Pilih file dulu, lalu tombol ini aktif.';
            }
        };

        const setBusy = (busy) => {
            el.submit.disabled = busy || !state.file;
            el.submitLabel.textContent = busy ? 'Sedang memeriksa' : 'Cek Kapasitas';
            const icon = $('i, .spin', el.submit);
            if (icon) {
                icon.outerHTML = busy ? '<span class="spin" aria-hidden="true"></span>' : '<i class="bi bi-calculator" aria-hidden="true"></i>';
            }
            el.submitHint.textContent = busy ? 'Mohon tunggu sebentar.' : (state.file ? 'Siap diproses.' : 'Pilih file dulu, lalu tombol ini aktif.');
        };

        el.input.addEventListener('change', () => setFile(el.input.files[0]));
        ['dragenter', 'dragover'].forEach((ev) => el.dropzone.addEventListener(ev, (e) => { e.preventDefault(); el.dropzone.classList.add('is-over'); }));
        ['dragleave', 'drop'].forEach((ev) => el.dropzone.addEventListener(ev, (e) => { e.preventDefault(); el.dropzone.classList.remove('is-over'); }));
        el.dropzone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files.length) {
                el.input.files = e.dataTransfer.files;
                setFile(e.dataTransfer.files[0]);
            }
        });

        el.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!state.file) return;

            clearMessages();
            // Hapus hasil lama agar tidak tertukar dengan hasil file yang baru (atau error-nya)
            state.uploadId = null; state.routes = []; state.routeKey = null; state.data = null;
            state.seq++;
            el.results.hidden = true;
            if (el.empty) el.empty.hidden = false;
            setBusy(true);
            pageLoader().show('Mengunggah dan memeriksa file', true);
            try {
                const fd = new FormData();
                fd.append('excel_file', state.file);
                const up = await request('/upload', { method: 'POST', body: fd });

                state.uploadId = up.upload_id;
                showWarnings(up.warnings);
                pageLoader().show('Menghitung muatan tiap jalur', true);
                await loadRoutes(true, () => pageLoader().hide());
            } catch (err) {
                showError(err.message);
            } finally {
                pageLoader().hide();
                setBusy(false);
            }
        });
    }

    $$('input[name="mode"]').forEach((r) => r.addEventListener('change', () => {
        state.mode = r.value;
        if (state.uploadId && state.routeKey) optimize();
    }));

    // Ganti truk khusus untuk jalur yang sedang dilihat (tidak disimpan; truk default diatur di Pengaturan)
    if (el.vehicleSelect) {
        el.vehicleSelect.addEventListener('change', () => state.uploadId && state.routeKey && optimize());
    }

    /* ------------------------------ Kartu jalur -------------------------- */

    async function loadRoutes(scroll = false, onShown = null) {
        const data = await request(`/riwayat/${state.uploadId}/jalur`);
        state.routes = data.routes;
        state.overall = data.overall;

        if (el.empty) el.empty.hidden = true;
        el.results.hidden = false;
        renderBatchBanner();
        renderRouteGrid();
        el.routeGrid.setAttribute('aria-busy', 'false');
        if (onShown) onShown();

        // Pertahankan jalur terpilih bila masih ada, jika tidak pilih jalur pertama yang punya muatan
        let key = state.routeKey;
        if (!state.routes.some((r) => r.key === key)) {
            const first = state.routes.find((r) => r.route !== null && r.state !== 'none') || state.routes[0];
            key = first ? first.key : null;
        }
        if (key === null) {
            el.routeDetail.hidden = true;
            return;
        }
        await selectRoute(key, scroll);
    }

    function renderBatchBanner() {
        const o = state.overall;
        const banner = $('#batchBanner');
        let cls, icon, title, text;

        switch (o.state) {
            case 'ready':
                cls = 'is-ready'; icon = 'bi-check-circle-fill';
                title = `Semua ${o.active} jalur siap dikirim`;
                text = 'Setiap jalur sudah mencapai batas minimal 80% dari truknya.';
                break;
            case 'partial':
                cls = 'is-wait'; icon = 'bi-hourglass-split';
                title = `${o.ready} dari ${o.active} jalur siap dikirim`;
                text = `${o.wait} jalur belum cukup penuh. Jalur yang belum siap menunggu tambahan muatan.`;
                break;
            case 'over':
                cls = 'is-over'; icon = 'bi-x-octagon-fill';
                title = `${o.over} jalur melebihi kapasitas truk`;
                text = 'Perlu truk tambahan atau pindahkan sebagian muatan jalur tersebut.';
                break;
            case 'wait':
                cls = 'is-wait'; icon = 'bi-hourglass-split';
                title = 'Belum ada jalur yang siap dikirim';
                text = `${o.wait} jalur belum mencapai 80% dari truknya.`;
                break;
            default:
                cls = 'is-wait'; icon = 'bi-inbox';
                title = 'Belum ada barang siap';
                text = 'Kloter ini belum memuat barang berstatus 4 (Cargo Ready).';
        }
        if (o.unrouted_cartons > 0) {
            text += ` ${num(o.unrouted_cartons, 0)} karton belum punya jalur (kode area belum diatur di Pengaturan, Area dan Jalur).`;
        }

        banner.className = 'status-banner ' + cls;
        $('#batchIcon').className = 'bi status-banner__icon ' + icon;
        $('#batchTitle').textContent = title;
        $('#batchText').textContent = text;
    }

    function renderRouteGrid() {
        el.routeGrid.innerHTML = state.routes.map((r) => {
            const ui = STATE_UI[r.state] || STATE_UI.none;
            const fill = r.state === 'over' ? 'is-over' : (r.state === 'ready' ? 'is-ok' : '');
            const unrouted = r.route === null;
            const limit = r.binding === 'weight' ? 'dibatasi berat' : 'dibatasi volume';
            const meta = r.state === 'none'
                ? `${num(r.pending, 0)} karton belum berstatus 4`
                : `${num(r.cartons, 0)} karton, ${num(r.dealers, 0)} dealer`;

            return `
            <button type="button" class="route-card is-${r.state}${unrouted ? ' is-unrouted' : ''}${r.key === state.routeKey ? ' is-selected' : ''}" data-key="${esc(r.key)}" aria-pressed="${r.key === state.routeKey}">
                <span class="route-card__head">
                    <strong>${esc(r.label)}</strong>
                    <span class="badge-status ${ui.badge}">${ui.text}</span>
                </span>
                <span class="route-card__name">${esc(unrouted ? 'Kode area belum diatur' : (r.name || 'Tujuan belum diisi'))}</span>
                <span class="route-card__truck"><i class="bi bi-truck" aria-hidden="true"></i> ${esc(r.vehicle ? r.vehicle.name : '-')}</span>
                <span class="minibar route-card__bar" title="Volume ${pct(r.volume_ratio)}, berat ${pct(r.weight_ratio)}">
                    <span class="minibar__track"><span class="minibar__fill ${fill}" style="width:${Math.min(100, r.ratio * 100)}%"></span></span>
                    <b>${r.state === 'none' ? '-' : pct(r.ratio)}</b>
                </span>
                <span class="route-card__meta">${meta}${r.state !== 'none' ? `, ${limit}` : ''}</span>
            </button>`;
        }).join('');
    }

    el.routeGrid && el.routeGrid.addEventListener('click', (e) => {
        const card = e.target.closest('.route-card');
        if (card) selectRoute(card.dataset.key, false);
    });

    async function selectRoute(key, scroll) {
        state.routeKey = key;
        const r = currentRoute();
        $$('.route-card', el.routeGrid).forEach((c) => {
            const on = c.dataset.key === key;
            c.classList.toggle('is-selected', on);
            c.setAttribute('aria-pressed', on);
        });

        el.routeDetail.hidden = false;
        $('#routeTitle').textContent = r.label + (r.name ? `: ${r.name}` : '');
        // Truk default jalur ini
        if (el.vehicleSelect && r.vehicle) el.vehicleSelect.value = r.vehicle.code;
        // Cetak rencana muat hanya untuk jalur bernomor yang punya karton
        el.planLink.hidden = r.route === null;
        if (r.route !== null) el.planLink.href = `/riwayat/${state.uploadId}/jalur/${r.key}/rencana-muat`;

        await optimize(scroll);
    }

    /* ----------------------------- Hitung & tampil ----------------------- */

    async function optimize(scroll = false) {
        const seq = ++state.seq;
        el.routeDetail.classList.add('is-loading');
        if (state.mode === 'tetris') plotBusy('Menyusun karton di truk');
        try {
            const data = await request('/packing/optimize', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    upload_id: state.uploadId,
                    route: state.routeKey,
                    mode: state.mode,
                    vehicle: el.vehicleSelect ? el.vehicleSelect.value : undefined,
                }),
            });
            if (seq !== state.seq) return; // ada permintaan yang lebih baru
            state.data = data;
            state.selected = null;
            render(data);
            if (scroll) {
                const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                el.results.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
            }
        } catch (err) {
            if (seq === state.seq) { showError(err.message); plotIdle(); }
        } finally {
            if (seq === state.seq) el.routeDetail.classList.remove('is-loading');
        }
    }

    function render(data) {
        renderBanner(data);
        renderMeters(data);
        renderStats(data);
        renderDealers(data.items);
        renderUnloaded(data.unloaded);
        render3d(data).catch((err) => { plotIdle(); showError(err.message); });
    }

    function renderBanner(data) {
        const d = data.demand;
        const v = data.vehicle;
        const label = data.route ? data.route.label : 'Jalur ini';
        const unloaded = data.summary.unloaded_count;
        const banner = $('#statusBanner');
        let cls, icon, title, text;

        if (d.carton_count === 0) {
            cls = 'is-wait'; icon = 'bi-inbox';
            title = `${label}: belum ada barang siap`;
            text = 'Jalur ini belum memuat barang berstatus 4 (Cargo Ready).';
        } else if (d.over_capacity) {
            cls = 'is-over'; icon = 'bi-x-octagon-fill';
            title = `${label} melebihi kapasitas`;
            text = `Muatan ${pct(d.ratio)} dari truk ${v.name}. Perlu truk tambahan atau pindahkan sebagian muatan.`;
        } else if (d.ready) {
            cls = 'is-ready'; icon = 'bi-check-circle-fill';
            title = `${label} siap dikirim`;
            text = `Muatan ${pct(d.ratio)} dari truk ${v.name}. Batas minimal 80% sudah terpenuhi.`;
        } else {
            cls = 'is-wait'; icon = 'bi-hourglass-split';
            title = `${label} belum siap dikirim`;
            text = `Muatan baru ${pct(d.ratio)} dari truk ${v.name}. Minimal 80%, kurang ${pct(READY_RATIO - d.ratio)} lagi.`;
        }
        if (unloaded > 0 && !d.over_capacity && d.carton_count > 0) {
            text += ` Ada ${unloaded} karton yang tidak muat pada susunan.`;
        }

        banner.className = 'status-banner ' + cls;
        $('#statusIcon').className = 'bi status-banner__icon ' + icon;
        $('#statusTitle').textContent = title;
        $('#statusText').textContent = text;
    }

    function setMeter(root, ratio, text, isBinding) {
        const fill = $('.meter__fill', root);
        fill.style.width = Math.min(ratio, 1) * 100 + '%';
        fill.className = 'meter__fill' + (ratio > 1 ? ' is-over' : (ratio >= READY_RATIO ? ' is-ok' : ''));
        $('[data-pct]', root).textContent = pct(ratio);
        $('[data-text]', root).textContent = text;
        $('.meter__badge', root).hidden = !isBinding;
        $('[role="progressbar"]', root).setAttribute('aria-valuenow', Math.round(Math.min(ratio, 1) * 100));
    }

    function renderMeters(data) {
        const d = data.demand;
        const v = data.vehicle;
        const hasLoad = d.carton_count > 0;
        setMeter($('#meterVolume'), d.volume_ratio, `${num(d.total_volume_m3)} dari ${num(v.volume_m3)} m³`, hasLoad && d.binding === 'volume');
        setMeter($('#meterWeight'), d.weight_ratio, `${num(d.total_weight_kg, 1)} dari ${num(v.max_payload_kg, 0)} kg`, hasLoad && d.binding === 'weight');
    }

    function renderStats(data) {
        const all = data.items.concat(data.unloaded);
        const r = currentRoute();
        $('#statLoaded').textContent = num(data.summary.loaded_count, 0);
        $('#statUnloaded').textContent = num(data.summary.unloaded_count, 0);
        $('#statUnloadedBox').classList.toggle('is-alert', data.summary.unloaded_count > 0);
        $('#statDealers').textContent = new Set(all.map((i) => i.customer_code).filter(Boolean)).size;
        $('#statPending').textContent = num(r ? r.pending : 0, 0);
    }

    function renderDealers(items) {
        const groups = new Map();
        items.forEach((i) => {
            const key = i.customer_code || '';
            const g = groups.get(key) || { code: i.customer_code, name: i.customer_name, dest: i.cargo_destination, n: 0, vol: 0, kg: 0 };
            g.n += 1; g.vol += i.volume; g.kg += i.weight_kg;
            groups.set(key, g);
        });
        const rows = Array.from(groups.values()).sort((a, b) => String(a.code).localeCompare(String(b.code)));

        $('#dealerRows').innerHTML = rows.length ? rows.map((g) => `
            <tr>
                <td><span class="dot" style="--c:${dealerColor(g.code)}"></span> <strong>${esc(g.code || '-')}</strong><br><small class="text-muted">${esc(g.name || '')}</small></td>
                <td>${esc(g.dest || '-')}</td>
                <td class="num">${num(g.n, 0)}</td>
                <td class="num">${num(g.vol, 3)}</td>
                <td class="num">${num(g.kg, 1)}</td>
            </tr>`).join('') : '<tr><td colspan="5" class="empty-state">Belum ada karton yang termuat.</td></tr>';

        const tot = rows.reduce((t, g) => ({ n: t.n + g.n, vol: t.vol + g.vol, kg: t.kg + g.kg }), { n: 0, vol: 0, kg: 0 });
        $('#dealerFoot').innerHTML = rows.length
            ? `<tr><td colspan="2">Total</td><td class="num">${num(tot.n, 0)}</td><td class="num">${num(tot.vol, 3)}</td><td class="num">${num(tot.kg, 1)}</td></tr>` : '';
    }

    function renderUnloaded(list) {
        const groups = new Map();
        list.forEach((u) => {
            const key = [u.reason, u.part_number, u.customer_code].join('|');
            const g = groups.get(key) || { reason: u.reason, part: u.part_number, code: u.customer_code, name: u.customer_name, n: 0 };
            g.n += 1;
            groups.set(key, g);
        });
        const rows = Array.from(groups.values()).sort((a, b) => b.n - a.n);

        $('#unloadedEmpty').hidden = rows.length > 0;
        $('#unloadedWrap').hidden = rows.length === 0;
        const badge = $('#unloadedCount');
        badge.hidden = list.length === 0;
        badge.textContent = num(list.length, 0);

        $('#unloadedRows').innerHTML = rows.map((g) => `
            <tr>
                <td><strong>${esc(g.part || '-')}</strong></td>
                <td>${esc(g.code || '-')}<br><small class="text-muted">${esc(g.name || '')}</small></td>
                <td>${esc(REASONS[g.reason] || g.reason)}</td>
                <td class="num">${num(g.n, 0)}</td>
            </tr>`).join('');
    }

    /* -------------------------------- 3D -------------------------------- */

    // Warna per dealer, urutan tetap berdasarkan kode dealer pada data yang tampil
    let dealerIndex = new Map();
    function dealerColor(code) {
        if (!dealerIndex.has(code)) dealerIndex.set(code, dealerIndex.size);
        return DEALER_COLORS[dealerIndex.get(code) % DEALER_COLORS.length];
    }

    function boxEdges(x0, y0, z0, x1, y1, z1, X, Y, Z) {
        const p = [[x0, y0, z0], [x1, y0, z0], [x1, y1, z0], [x0, y1, z0], [x0, y0, z1], [x1, y0, z1], [x1, y1, z1], [x0, y1, z1]];
        // 12 rusuk, tiap rusuk diakhiri null agar garis terputus
        [[0, 1], [1, 2], [2, 3], [3, 0], [4, 5], [5, 6], [6, 7], [7, 4], [0, 4], [1, 5], [2, 6], [3, 7]].forEach(([a, b]) => {
            X.push(p[a][0], p[b][0], null); Y.push(p[a][1], p[b][1], null); Z.push(p[a][2], p[b][2], null);
        });
    }

    function buildTraces(data) {
        const v = data.vehicle;
        const traces = [];

        // Rangka truk
        const cx = [], cy = [], cz = [];
        boxEdges(0, 0, 0, v.length, v.width, v.height, cx, cy, cz);
        traces.push({ type: 'scatter3d', mode: 'lines', x: cx, y: cy, z: cz, line: { color: THEME.frame, width: 5 }, hoverinfo: 'skip', showlegend: false });

        // Satu mesh per dealer. Setiap titik sudut membawa teks hover; 8 titik sudut = 1 karton.
        const meshMap = {};
        const groups = new Map();
        data.items.forEach((it) => {
            const key = it.customer_code || '';
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(it);
        });

        groups.forEach((list, code) => {
            const x = [], y = [], z = [], i = [], j = [], k = [];
            list.forEach((it, n) => {
                const x0 = it.x, x1 = it.x + it.length, y0 = it.y, y1 = it.y + it.width, z0 = it.z, z1 = it.z + it.height;
                const base = n * 8;
                x.push(x0, x0, x1, x1, x0, x0, x1, x1);
                y.push(y0, y1, y1, y0, y0, y1, y1, y0);
                z.push(z0, z0, z0, z0, z1, z1, z1, z1);
                for (let t = 0; t < 12; t++) { i.push(CUBE_I[t] + base); j.push(CUBE_J[t] + base); k.push(CUBE_K[t] + base); }
            });
            meshMap[traces.length] = list;
            traces.push({
                type: 'mesh3d', x, y, z, i, j, k,
                color: dealerColor(code), opacity: 1, flatshading: true,
                lighting: { ambient: 0.72, diffuse: 0.65, specular: 0.05, roughness: 0.9 },
                // 'none' = tanpa tooltip (ringan), tetapi karton tetap bisa diklik
                name: `${code || 'Tanpa dealer'}`, hoverinfo: 'none', showlegend: false,
            });
        });
        state.meshMap = meshMap;

        // Garis tepi karton (satu trace) supaya batas antar karton terlihat
        if (data.items.length <= MAX_EDGE_BOXES) {
            const ex = [], ey = [], ez = [];
            data.items.forEach((it) => boxEdges(it.x, it.y, it.z, it.x + it.length, it.y + it.width, it.z + it.height, ex, ey, ez));
            traces.push({ type: 'scatter3d', mode: 'lines', x: ex, y: ey, z: ez, line: { color: THEME.edge, width: 1 }, hoverinfo: 'skip', showlegend: false });
        }

        // Satu mesh kecil untuk menyorot karton terpilih. Selalu ada (disembunyikan) supaya memilih karton
        // cukup mengubah mesh kecil ini dan seluruh susunan tidak perlu digambar ulang.
        state.hlIndex = traces.length;
        traces.push(Object.assign({
            type: 'mesh3d', i: CUBE_I, j: CUBE_J, k: CUBE_K, color: THEME.selected, opacity: 1, flatshading: true,
            hoverinfo: 'skip', showlegend: false, name: 'Karton terpilih', visible: !!state.selected,
        }, highlightGeometry(state.selected)));
        return traces;
    }

    /* ----------------------- Isi karton (klik) ----------------------- */

    const SERVICE_LABEL = { same_day: 'Same Day', one_day: 'One Day', emergency: 'Emergency' };
    const mm = (m) => Math.round(m * 1000);

    function highlightGeometry(it) {
        if (!it) return { x: [0, 0, 0, 0, 0, 0, 0, 0], y: [0, 0, 0, 0, 0, 0, 0, 0], z: [0, 0, 0, 0, 0, 0, 0, 0] };
        const g = 0.004; // sedikit lebih besar dari karton agar tidak berebut permukaan
        const x0 = it.x - g, x1 = it.x + it.length + g, y0 = it.y - g, y1 = it.y + it.width + g, z0 = it.z - g, z1 = it.z + it.height + g;
        return { x: [x0, x0, x1, x1, x0, x0, x1, x1], y: [y0, y1, y1, y0, y0, y1, y1, y0], z: [z0, z0, z0, z0, z1, z1, z1, z1] };
    }

    function renderItemPanel() {
        const it = state.selected;
        const panel = $('#itemPanel');
        if (!panel) return;
        panel.hidden = !it;
        if (!it) return;

        const row = (k, v) => `<dt>${k}</dt><dd>${v}</dd>`;
        $('#itemBody').innerHTML = [
            row('Barang', `<strong>${esc(it.part_number || '-')}</strong>${it.part_name ? ' ' + esc(it.part_name) : ''}`),
            row('Dealer', `${esc(it.customer_code || '-')}${it.customer_name ? ' ' + esc(it.customer_name) : ''}${it.cargo_destination ? ', ' + esc(it.cargo_destination) : ''}`),
            row('Isi karton', `${num(it.qty ?? 0, 0)} pcs, ${it.packing_type === 'receh' ? 'packing receh (sisa kurang dari isi box)' : 'packing unit (box penuh)'}`),
            row('Karton', `No. ${esc(it.carton_no ?? '-')}, ${mm(it.length)} × ${mm(it.width)} × ${mm(it.height)} mm`),
            row('Berat', `${num(it.weight_kg, 2)} kg`),
            row('Volume', `${num(it.volume * 1000, 1)} liter`),
            row('Lokasi gudang', esc(it.location || '-')),
            row('Layanan', esc(SERVICE_LABEL[it.service_type] || it.service_type || '-')),
            row('Posisi di truk', `${Math.round(it.x * 100)} cm dari depan, ${Math.round(it.y * 100)} cm dari sisi, tinggi ${Math.round(it.z * 100)} cm dari lantai`),
        ].join('');
    }

    function selectItem(item) {
        if (item && item === state.selected) return;   // sudah terpilih: jangan sorot dua kali (klik Plotly + jalur cadangan)
        state.selected = item;
        renderItemPanel();
        if (state.hlIndex === null || !state.plotDrawn) return;   // grafik belum digambar

        // Hanya mesh sorotan yang diubah; susunan karton tidak digambar ulang.
        // Kamera yang sedang dipakai ikut dikirim dalam pembaruan yang sama agar tampilan TIDAK loncat kembali
        // ke sudut awal setelah pengguna memutar lalu mengklik karton.
        const g = highlightGeometry(item);
        const cam = currentCamera();
        state.cam = cam;
        Plotly.update('plot', item ? { x: [g.x], y: [g.y], z: [g.z], visible: [true] } : { visible: [false] },
            { 'scene.camera': cam }, [state.hlIndex]);
    }

    // Kamera yang sedang dipakai grafik (termasuk hasil memutar). Bila API internal Plotly tidak tersedia,
    // dipakai kamera terakhir yang dilaporkan lewat peristiwa plotly_relayout.
    function currentCamera() {
        try {
            const cam = $('#plot')._fullLayout.scene._scene.getCamera();
            if (cam && cam.eye) return clone(cam);
        } catch (e) { /* API internal tidak tersedia */ }
        return clone(state.cam);
    }

    function itemFromPoint(p) {
        const list = p && state.meshMap[p.curveNumber];
        return list ? list[Math.floor(p.pointNumber / 8)] || null : null;
    }

    /* ----------------------- Kotak info saat mouse di atas karton ----------------------- */
    // Sengaja bukan tooltip Plotly: teks per titik sudut membuat adegan 3D berat. Ini hanya satu kotak DOM kecil
    // yang diisi saat karton yang di-hover berganti, dan hanya di perangkat yang punya mouse.
    const canHover = () => !!(window.matchMedia && window.matchMedia('(hover: hover)').matches);
    let tipItem = null;
    let tipRaf = 0;

    function tipHtml(it) {
        const kind = it.packing_type === 'receh' ? 'receh' : 'unit penuh';
        return `<strong>${esc(it.part_number || '-')}</strong>${it.part_name ? ' ' + esc(it.part_name) : ''}<br>` +
            `Dealer ${esc(it.customer_code || '-')}<br>${num(it.qty ?? 0, 0)} pcs (${kind}), ${num(it.weight_kg, 2)} kg<br><em>Klik untuk detail</em>`;
    }

    function positionTip() {
        const tip = $('#itemTip');
        const wrap = $('#plotWrap');
        if (!tip || !wrap || tip.hidden || tipRaf) return;
        tipRaf = requestAnimationFrame(() => {
            tipRaf = 0;
            const r = wrap.getBoundingClientRect();
            let x = state.mouse.x - r.left + 14;
            let y = state.mouse.y - r.top + 14;
            if (x + tip.offsetWidth > r.width) x = Math.max(0, state.mouse.x - r.left - tip.offsetWidth - 14);
            if (y + tip.offsetHeight > r.height) y = Math.max(0, state.mouse.y - r.top - tip.offsetHeight - 14);
            tip.style.transform = `translate(${x}px, ${y}px)`;
        });
    }

    function hideTip() {
        const tip = $('#itemTip');
        if (tip) tip.hidden = true;
        tipItem = null;
    }

    function showTip(it) {
        const tip = $('#itemTip');
        if (!tip || !canHover()) return;
        if (!it) { hideTip(); return; }
        if (it !== tipItem) { tip.innerHTML = tipHtml(it); tipItem = it; }   // isi hanya diganti bila karton berganti
        tip.hidden = false;
        positionTip();
    }

    function renderLegend(items) {
        const counts = new Map();
        items.forEach((i) => counts.set(i.customer_code || '', (counts.get(i.customer_code || '') || 0) + 1));
        $('#legend').innerHTML = Array.from(counts.keys()).sort().map((code) =>
            `<span class="legend__item"><span class="dot" style="--c:${dealerColor(code)}"></span>${esc(code || 'Tanpa dealer')} (${num(counts.get(code), 0)})</span>`).join('');
    }

    function plotLayout() {
        return {
            margin: { l: 0, r: 0, t: 0, b: 0 },
            uirevision: 'kamera',   // pertahankan kamera hasil putaran pengguna saat grafik diperbarui
            paper_bgcolor: THEME.paper,
            font: { family: 'Inter, sans-serif', color: THEME.text, size: 12 },
            showlegend: false,
            scene: {
                aspectmode: 'data',
                camera: state.cam,
                xaxis: { title: { text: 'Panjang (m)' }, backgroundcolor: THEME.wall, gridcolor: THEME.grid, zerolinecolor: THEME.grid, linecolor: THEME.grid, showbackground: true },
                yaxis: { title: { text: 'Lebar (m)' }, backgroundcolor: THEME.wall, gridcolor: THEME.grid, zerolinecolor: THEME.grid, linecolor: THEME.grid, showbackground: true },
                zaxis: { title: { text: 'Tinggi (m)' }, backgroundcolor: THEME.wall, gridcolor: THEME.grid, zerolinecolor: THEME.grid, linecolor: THEME.grid, showbackground: true },
            },
        };
    }

    async function render3d(data) {
        const isRatio = data.mode === 'ratio';
        const small = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
        const lazy = !isRatio && !state.force3d && (small || data.items.length > LAZY_3D_CARTONS);

        $('#view3d').hidden = isRatio || lazy;
        $('#ratioNote').hidden = !isRatio;
        const lazyNote = $('#lazyNote');
        if (lazyNote) {
            lazyNote.hidden = !lazy;
            $('#lazyCount').textContent = num(data.items.length, 0);
        }
        if (isRatio || lazy) { plotIdle(); return; }

        dealerIndex = new Map();
        Array.from(new Set(data.items.map((i) => i.customer_code || ''))).sort().forEach((c) => dealerColor(c));

        renderLegend(data.items);
        hideTip();
        renderItemPanel();

        const seq = state.seq;
        plotBusy(window.Plotly ? 'Menggambar susunan 3D' : 'Memuat tampilan 3D');
        try {
            await loadPlotly();
        } catch (err) {
            plotIdle();
            showError(err.message);
            return;
        }
        if (seq !== state.seq || state.data !== data) return;   // sudah ada hasil yang lebih baru

        plotBusy('Menggambar susunan 3D');
        // Beri browser satu frame untuk menampilkan animasi sebelum menggambar (menggambar bisa berat)
        await new Promise((r) => requestAnimationFrame(() => setTimeout(r, 0)));
        if (seq !== state.seq || state.data !== data) return;
        await Plotly.react('plot', buildTraces(data), plotLayout(), PLOT_CONFIG);
        state.plotDrawn = true;
        bindPlotGuards();
        plotIdle();
    }

    function applyCamera() {
        if (state.plotDrawn && state.data && state.data.mode === 'tetris') {
            Plotly.relayout('plot', { 'scene.camera': state.cam });
        }
    }

    $$('#viewBar [data-view]').forEach((btn) => btn.addEventListener('click', () => {
        state.view = btn.dataset.view;
        state.cam = clone(CAMERAS[state.view]);
        $$('#viewBar [data-view]').forEach((b) => b.classList.toggle('is-on', b === btn));
        applyCamera();
    }));

    // Perbesar / perkecil lewat tombol (gulir biasa dipakai untuk menggulir halaman)
    $$('#viewBar [data-zoom]').forEach((btn) => btn.addEventListener('click', () => {
        const f = btn.dataset.zoom === 'in' ? 0.8 : 1.25;
        const e = state.cam.eye;
        const len = Math.hypot(e.x, e.y, e.z) * f;
        if (len < 0.8 || len > 6) return; // batas wajar
        state.cam.eye = { x: e.x * f, y: e.y * f, z: e.z * f };
        applyCamera();
    }));

    // Cegah gulir halaman memperbesar grafik tanpa sengaja; Ctrl + gulir tetap bisa zoom
    function bindPlotGuards() {
        if (state.plotBound) return;
        const plot = $('#plot');
        plot.addEventListener('wheel', (e) => { if (!e.ctrlKey) e.stopPropagation(); }, true);
        plot.on('plotly_relayout', (ev) => {
            if (ev && ev['scene.camera']) state.cam = clone(ev['scene.camera']);
        });

        // Klik karton -> tampilkan isinya. Dua jalur agar andal: peristiwa klik Plotly, dan klik biasa
        // pada karton yang sedang di-hover (dibedakan dari geser memutar dengan jarak gerak mouse).
        plot.on('plotly_click', (ev) => {
            const it = itemFromPoint(ev && ev.points && ev.points[0]);
            if (it) { state.clickHandled = true; selectItem(it); }
        });
        plot.on('plotly_hover', (ev) => {
            state.hovered = itemFromPoint(ev && ev.points && ev.points[0]);
            showTip(state.hovered);
        });
        plot.on('plotly_unhover', () => { state.hovered = null; hideTip(); });
        plot.addEventListener('mousemove', (e) => { state.mouse = { x: e.clientX, y: e.clientY }; positionTip(); });
        plot.addEventListener('mouseleave', hideTip);

        let down = null;
        plot.addEventListener('mousedown', (e) => { down = { x: e.clientX, y: e.clientY }; state.clickHandled = false; hideTip(); });
        plot.addEventListener('mouseup', (e) => {
            const moved = down ? Math.hypot(e.clientX - down.x, e.clientY - down.y) : 99;
            down = null;
            if (moved < 5) {
                setTimeout(() => { if (!state.clickHandled && state.hovered) selectItem(state.hovered); }, 0);
            }
        });
        state.plotBound = true;
    }

    const lazyBtn = $('#lazyBtn');
    if (lazyBtn) lazyBtn.addEventListener('click', () => {
        state.force3d = true;
        if (state.data) render3d(state.data).catch((err) => { plotIdle(); showError(err.message); });
    });

    const clearBtn = $('#itemClear');
    if (clearBtn) clearBtn.addEventListener('click', () => selectItem(null));

    // Grafik di tab tersembunyi tidak punya lebar; sesuaikan saat tab dibuka (peristiwa dari public/js/app.js)
    document.addEventListener('tab:shown', (e) => {
        if (e.detail.tab.id === 'tab3d' && state.plotDrawn && state.data && state.data.mode === 'tetris') {
            Plotly.Plots.resize('plot');
        }
    });

    // Detail kloter: kartu jalur dimuat otomatis dari data yang tersimpan
    if (el.results.dataset.uploadId) {
        state.uploadId = Number(el.results.dataset.uploadId);
        prefetchPlotly();   // paralel dengan memuat data jalur
        loadRoutes().catch((err) => showError(err.message));
    }
})();
