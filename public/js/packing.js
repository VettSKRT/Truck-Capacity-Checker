/* Cek Kapasitas Truk - halaman utama
 * Alur: pilih kendaraan -> unggah file -> POST /upload -> POST /packing/optimize -> tampilkan hasil.
 * Mengganti kendaraan atau cara hitung setelah unggah akan menghitung ulang otomatis. */
(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const token = $('meta[name="csrf-token"]').content;

    const READY_RATIO = 0.8;
    const ROUTE_COLORS = {
        1: '#0057B8', 2: '#E4002B', 3: '#2E9E5B', 4: '#F2A900',
        5: '#7A3FB0', 6: '#00A3AD', 7: '#8C5A2B', 8: '#C2255C',
    };
    const NO_ROUTE_COLOR = '#8A94A6';
    const REASONS = {
        weight: 'Melebihi batas berat kendaraan',
        volume: 'Melebihi batas volume kendaraan',
        space: 'Tidak muat pada susunan',
        too_big: 'Ukuran karton lebih besar dari kendaraan',
    };
    const CAMERAS = {
        iso: { eye: { x: 1.15, y: -1.55, z: 0.95 }, up: { x: 0, y: 0, z: 1 } },
        side: { eye: { x: 0, y: -2.4, z: 0.25 }, up: { x: 0, y: 0, z: 1 } },
        top: { eye: { x: 0, y: -0.001, z: 2.8 }, up: { x: 0, y: 1, z: 0 } },
        end: { eye: { x: -2.4, y: 0, z: 0.35 }, up: { x: 0, y: 0, z: 1 } },
    };
    const MAX_EDGE_BOXES = 2500; // di atas ini garis tepi karton dilewati agar tetap ringan
    // Indeks segitiga kubus (urutan titik: lihat buildMeshes)
    const CUBE_I = [7, 0, 0, 0, 4, 4, 6, 6, 4, 0, 3, 2];
    const CUBE_J = [3, 4, 1, 2, 5, 6, 5, 2, 0, 1, 6, 3];
    const CUBE_K = [0, 7, 2, 3, 6, 7, 1, 1, 5, 5, 7, 6];

    const clone = (o) => JSON.parse(JSON.stringify(o));
    const state = { uploadId: null, mode: 'tetris', seq: 0, data: null, view: 'iso', file: null, cam: null, plotBound: false };
    state.cam = clone(CAMERAS.iso);

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
    };

    /* ----------------------------- Utilitas ----------------------------- */

    const nfCache = {};
    function num(n, d = 2) {
        const nf = nfCache[d] || (nfCache[d] = new Intl.NumberFormat('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d }));
        return nf.format(n);
    }
    const pct = (ratio) => num(ratio * 100, 1) + '%';
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const routeColor = (r) => ROUTE_COLORS[r] || NO_ROUTE_COLOR;
    const routeLabel = (r) => (r ? 'Jalur ' + r : 'Jalur belum diatur');
    const routeChip = (r) => `<span class="route-chip"><span class="dot" style="--c:${routeColor(r)}"></span>${routeLabel(r)}</span>`;
    const vehicleCode = () => ($('input[name="vehicle"]:checked') || {}).value;

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

    function setFile(file) {
        if (file && !/\.(xlsx|xls|csv)$/i.test(file.name)) {
            state.file = null;
            showError('Format file tidak didukung. Gunakan file .xlsx, .xls, atau .csv.');
            file = null;
        }
        state.file = file || null;
        el.dropzone.classList.toggle('has-file', !!state.file);
        el.submit.disabled = !state.file;

        if (state.file) {
            el.fileName.textContent = state.file.name;
            el.fileHint.textContent = `Ukuran ${num(state.file.size / 1024, 0)} KB. Klik untuk mengganti file.`;
            el.submitHint.textContent = 'Siap diproses.';
        } else {
            el.fileName.textContent = 'Klik di sini untuk memilih file';
            el.fileHint.textContent = 'atau tarik file ke kotak ini (format .xlsx, .xls, atau .csv)';
            el.submitHint.textContent = 'Pilih file dulu, lalu tombol ini aktif.';
        }
    }

    function setBusy(busy) {
        el.submit.disabled = busy || !state.file;
        el.submitLabel.textContent = busy ? 'Sedang memeriksa' : 'Cek Kapasitas';
        const icon = $('i, .spin', el.submit);
        if (icon) {
            icon.outerHTML = busy ? '<span class="spin" aria-hidden="true"></span>' : '<i class="bi bi-calculator" aria-hidden="true"></i>';
        }
        el.submitHint.textContent = busy ? 'Mohon tunggu sebentar.' : (state.file ? 'Siap diproses.' : 'Pilih file dulu, lalu tombol ini aktif.');
    }

    el.input.addEventListener('change', () => setFile(el.input.files[0]));

    ['dragenter', 'dragover'].forEach((ev) => el.dropzone.addEventListener(ev, (e) => {
        e.preventDefault();
        el.dropzone.classList.add('is-over');
    }));
    ['dragleave', 'drop'].forEach((ev) => el.dropzone.addEventListener(ev, (e) => {
        e.preventDefault();
        el.dropzone.classList.remove('is-over');
    }));
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
        state.uploadId = null;
        state.data = null;
        state.seq++;
        el.results.hidden = true;
        el.empty.hidden = false;
        setBusy(true);
        try {
            const fd = new FormData();
            fd.append('excel_file', state.file);
            fd.append('vehicle', vehicleCode());
            const up = await request('/upload', { method: 'POST', body: fd });

            state.uploadId = up.upload_id;
            showWarnings(up.warnings);
            await optimize(true);
        } catch (err) {
            showError(err.message);
        } finally {
            setBusy(false);
        }
    });

    // Ganti kendaraan / cara hitung setelah ada hasil -> hitung ulang otomatis
    $$('input[name="vehicle"]').forEach((r) => r.addEventListener('change', () => state.uploadId && optimize()));
    $$('input[name="mode"]').forEach((r) => r.addEventListener('change', () => {
        state.mode = r.value;
        if (state.uploadId) optimize();
    }));

    /* ----------------------------- Hitung & tampil ----------------------- */

    async function optimize(scroll = false) {
        const seq = ++state.seq;
        el.results.classList.add('is-loading');
        try {
            const data = await request('/packing/optimize', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ upload_id: state.uploadId, mode: state.mode, vehicle: vehicleCode() }),
            });
            if (seq !== state.seq) return; // ada permintaan yang lebih baru
            state.data = data;
            render(data);
            if (scroll) {
                const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                el.results.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
            }
        } catch (err) {
            if (seq === state.seq) showError(err.message);
        } finally {
            if (seq === state.seq) el.results.classList.remove('is-loading');
        }
    }

    function render(data) {
        el.empty.hidden = true;
        el.results.hidden = false;

        renderBanner(data);
        renderMeters(data);
        renderStats(data);
        renderDealers(data.items);
        renderUnloaded(data.unloaded);
        render3d(data);
    }

    function renderBanner(data) {
        const d = data.demand;
        const v = data.vehicle;
        const unloaded = data.summary.unloaded_count;
        const banner = $('#statusBanner');
        let cls, icon, title, text;

        if (d.carton_count === 0) {
            cls = 'is-wait'; icon = 'bi-inbox';
            title = 'Belum ada barang siap';
            text = 'File ini belum memuat barang berstatus 4 (Cargo Ready).';
        } else if (d.over_capacity) {
            cls = 'is-over'; icon = 'bi-x-octagon-fill';
            title = 'Melebihi kapasitas';
            text = `Muatan ${pct(d.ratio)} dari ${v.name}. Perlu kendaraan tambahan atau kurangi muatan.`;
        } else if (d.ready) {
            cls = 'is-ready'; icon = 'bi-check-circle-fill';
            title = 'Siap dikirim';
            text = `Muatan ${pct(d.ratio)} dari ${v.name}. Batas minimal 80% sudah terpenuhi.`;
        } else {
            cls = 'is-wait'; icon = 'bi-hourglass-split';
            title = 'Belum siap dikirim';
            text = `Muatan baru ${pct(d.ratio)} dari ${v.name}. Minimal 80%, kurang ${pct(READY_RATIO - d.ratio)} lagi.`;
        }
        if (unloaded > 0 && !d.over_capacity && d.carton_count > 0) {
            text += ` Ada ${unloaded} karton yang tidak muat pada susunan.`;
        }

        banner.className = 'status-banner ' + cls;
        $('#statusIcon').className = 'bi status-banner__icon ' + icon;
        $('#statusTitle').textContent = title;
        $('#statusText').textContent = text;
        $('#reportLink').href = '/result/' + data.upload.id;
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
        $('#statLoaded').textContent = num(data.summary.loaded_count, 0);
        $('#statUnloaded').textContent = num(data.summary.unloaded_count, 0);
        $('#statUnloadedBox').classList.toggle('is-alert', data.summary.unloaded_count > 0);
        $('#statDealers').textContent = new Set(all.map((i) => i.customer_code).filter(Boolean)).size;
        $('#statRoutes').textContent = new Set(all.map((i) => i.route).filter(Boolean)).size;
    }

    function renderDealers(items) {
        const groups = new Map();
        items.forEach((i) => {
            const key = (i.route || 99) + '|' + (i.customer_code || '');
            const g = groups.get(key) || { route: i.route, code: i.customer_code, name: i.customer_name, dest: i.cargo_destination, n: 0, vol: 0, kg: 0 };
            g.n += 1; g.vol += i.volume; g.kg += i.weight_kg;
            groups.set(key, g);
        });
        const rows = Array.from(groups.values()).sort((a, b) => (a.route || 99) - (b.route || 99) || String(a.code).localeCompare(String(b.code)));

        $('#dealerRows').innerHTML = rows.length ? rows.map((g) => `
            <tr>
                <td>${routeChip(g.route)}</td>
                <td><strong>${esc(g.code || '-')}</strong><br><small class="text-muted">${esc(g.name || '')}</small></td>
                <td>${esc(g.dest || '-')}</td>
                <td class="num">${num(g.n, 0)}</td>
                <td class="num">${num(g.vol, 3)}</td>
                <td class="num">${num(g.kg, 1)}</td>
            </tr>`).join('') : '<tr><td colspan="6" class="empty-state">Belum ada karton yang termuat.</td></tr>';

        const tot = rows.reduce((t, g) => ({ n: t.n + g.n, vol: t.vol + g.vol, kg: t.kg + g.kg }), { n: 0, vol: 0, kg: 0 });
        $('#dealerFoot').innerHTML = rows.length
            ? `<tr><td colspan="3">Total</td><td class="num">${num(tot.n, 0)}</td><td class="num">${num(tot.vol, 3)}</td><td class="num">${num(tot.kg, 1)}</td></tr>` : '';
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

        // Rangka kendaraan
        const cx = [], cy = [], cz = [];
        boxEdges(0, 0, 0, v.length, v.width, v.height, cx, cy, cz);
        traces.push({ type: 'scatter3d', mode: 'lines', x: cx, y: cy, z: cz, line: { color: '#00439B', width: 5 }, hoverinfo: 'skip', showlegend: false });

        // Satu mesh per jalur
        const groups = new Map();
        data.items.forEach((it) => {
            const key = it.route || 0;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(it);
        });

        groups.forEach((list, route) => {
            const x = [], y = [], z = [], i = [], j = [], k = [];
            list.forEach((it, n) => {
                const x0 = it.x, x1 = it.x + it.length, y0 = it.y, y1 = it.y + it.width, z0 = it.z, z1 = it.z + it.height;
                const base = n * 8;
                x.push(x0, x0, x1, x1, x0, x0, x1, x1);
                y.push(y0, y1, y1, y0, y0, y1, y1, y0);
                z.push(z0, z0, z0, z0, z1, z1, z1, z1);
                for (let t = 0; t < 12; t++) { i.push(CUBE_I[t] + base); j.push(CUBE_J[t] + base); k.push(CUBE_K[t] + base); }
            });
            traces.push({
                type: 'mesh3d', x, y, z, i, j, k,
                color: routeColor(route), opacity: 1, flatshading: true,
                lighting: { ambient: 0.72, diffuse: 0.65, specular: 0.05, roughness: 0.9 },
                name: `${routeLabel(route)}: ${list.length} karton`, hoverinfo: 'name', showlegend: false,
            });
        });

        // Garis tepi karton (satu trace) supaya batas antar karton terlihat
        if (data.items.length <= MAX_EDGE_BOXES) {
            const ex = [], ey = [], ez = [];
            data.items.forEach((it) => boxEdges(it.x, it.y, it.z, it.x + it.length, it.y + it.width, it.z + it.height, ex, ey, ez));
            traces.push({ type: 'scatter3d', mode: 'lines', x: ex, y: ey, z: ez, line: { color: 'rgba(22,32,51,0.55)', width: 1 }, hoverinfo: 'skip', showlegend: false });
        }
        return traces;
    }

    function renderLegend(items) {
        const counts = new Map();
        items.forEach((i) => counts.set(i.route || 0, (counts.get(i.route || 0) || 0) + 1));
        const keys = Array.from(counts.keys()).sort((a, b) => (a || 99) - (b || 99));
        $('#legend').innerHTML = keys.map((r) =>
            `<span class="legend__item"><span class="dot" style="--c:${routeColor(r)}"></span>${routeLabel(r)} (${num(counts.get(r), 0)})</span>`).join('');
    }

    function plotLayout() {
        return {
            margin: { l: 0, r: 0, t: 0, b: 0 },
            paper_bgcolor: '#ffffff',
            showlegend: false,
            scene: {
                aspectmode: 'data',
                camera: state.cam,
                xaxis: { title: { text: 'Panjang (m)' }, backgroundcolor: '#f6f8fc', gridcolor: '#d3dae6' },
                yaxis: { title: { text: 'Lebar (m)' }, backgroundcolor: '#f6f8fc', gridcolor: '#d3dae6' },
                zaxis: { title: { text: 'Tinggi (m)' }, backgroundcolor: '#f6f8fc', gridcolor: '#d3dae6' },
            },
        };
    }

    function render3d(data) {
        const isRatio = data.mode === 'ratio';
        $('#view3d').hidden = isRatio;
        $('#ratioNote').hidden = !isRatio;
        if (isRatio) return;

        renderLegend(data.items);
        Plotly.react('plot', buildTraces(data), plotLayout(), { responsive: true, displaylogo: false, displayModeBar: false });
        bindPlotGuards();
    }

    function applyCamera() {
        if (state.data && state.data.mode === 'tetris') {
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
        state.plotBound = true;
    }

    // Grafik di tab tersembunyi tidak punya lebar; sesuaikan saat tab dibuka
    document.addEventListener('shown.bs.tab', (e) => {
        if (e.target.id === 'tab3d' && state.data && state.data.mode === 'tetris') {
            Plotly.Plots.resize('plot');
        }
    });
})();
