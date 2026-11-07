/* Grafik area ringan (SVG) untuk dashboard, pengganti Plotly (sekitar 1 MB) yang dulu hanya dipakai untuk satu grafik.
 * Pemakaian: <div class="chart" data-area-chart data-series='{"labels":[...],"volume":[...],"weight":[...],"cartons":[...]}'></div>
 * Kurva halus monoton (tidak pernah turun di bawah nol), gradien cian ke hijau, tooltip, dan animasi garis saat pertama tampil. */
(function () {
    'use strict';

    const NS = 'http://www.w3.org/2000/svg';
    const M = { l: 56, r: 16, t: 12, b: 32 };
    const reduceMotion = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const nf = (d) => new Intl.NumberFormat('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
    const fmt = { v: nf(2), kg: nf(0), n: nf(0), tick: nf(0), tickFine: nf(1) };
    let uid = 0;

    // Batas atas sumbu Y yang "rapi" (1, 2, 2,5, 5, 10 x 10^n) dan jarak antar garis bantu
    function niceScale(max) {
        if (!(max > 0)) return { max: 1, step: 0.25 };
        const rough = max / 4;
        const mag = Math.pow(10, Math.floor(Math.log10(rough)));
        const norm = rough / mag;
        const step = (norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 2.5 ? 2.5 : norm <= 5 ? 5 : 10) * mag;
        return { max: step * Math.ceil(max / step), step };
    }

    // Kurva monoton Fritsch-Carlson: halus tetapi tidak melampaui titik data (tidak ada lengkung di bawah nol)
    function monotonePath(pts) {
        const n = pts.length;
        if (n < 2) return n ? `M${pts[0][0]},${pts[0][1]}` : '';
        const dx = [], dy = [], m = [];
        for (let i = 0; i < n - 1; i++) {
            dx[i] = pts[i + 1][0] - pts[i][0];
            dy[i] = pts[i + 1][1] - pts[i][1];
            m[i] = dy[i] / dx[i];
        }
        const t = [m[0]];
        for (let i = 1; i < n - 1; i++) t[i] = m[i - 1] * m[i] <= 0 ? 0 : (m[i - 1] + m[i]) / 2;
        t[n - 1] = m[n - 2];
        for (let i = 0; i < n - 1; i++) {
            if (m[i] === 0) { t[i] = 0; t[i + 1] = 0; continue; }
            const a = t[i] / m[i], b = t[i + 1] / m[i], h = a * a + b * b;
            if (h > 9) { const k = 3 / Math.sqrt(h); t[i] = k * a * m[i]; t[i + 1] = k * b * m[i]; }
        }
        let d = `M${pts[0][0]},${pts[0][1]}`;
        for (let i = 0; i < n - 1; i++) {
            const h = dx[i] / 3;
            d += ` C${pts[i][0] + h},${pts[i][1] + t[i] * h} ${pts[i + 1][0] - h},${pts[i + 1][1] - t[i + 1] * h} ${pts[i + 1][0]},${pts[i + 1][1]}`;
        }
        return d;
    }

    function draw(el, data, animate) {
        const w = el.clientWidth, h = el.clientHeight;
        if (!w || !h) return;
        const values = data.volume;
        const n = values.length;
        const pw = w - M.l - M.r, ph = h - M.t - M.b;
        const scale = niceScale(Math.max(...values));
        const x = (i) => M.l + (n === 1 ? pw / 2 : (pw * i) / (n - 1));
        const y = (v) => M.t + ph - (v / scale.max) * ph;
        const pts = values.map((v, i) => [+x(i).toFixed(2), +y(v).toFixed(2)]);
        const id = 'ac' + (++uid);
        const tickFmt = scale.step < 1 ? fmt.tickFine : fmt.tick;

        let grid = '';
        for (let v = 0; v <= scale.max + 1e-9; v += scale.step) {
            const yy = y(v).toFixed(1);
            grid += `<line class="chart__grid" x1="${M.l}" x2="${w - M.r}" y1="${yy}" y2="${yy}"/>`
                + `<text class="chart__tick" x="${M.l - 8}" y="${yy}" dy="0.32em" text-anchor="end">${tickFmt.format(v)} m³</text>`;
        }
        const every = Math.max(1, Math.ceil((n * 52) / pw));   // label tanggal tidak boleh bertumpuk
        let xlab = '';
        data.labels.forEach((lab, i) => {
            if ((n - 1 - i) % every === 0) xlab += `<text class="chart__tick" x="${x(i).toFixed(1)}" y="${h - 8}" text-anchor="middle">${lab}</text>`;
        });

        const line = monotonePath(pts);
        const base = (M.t + ph).toFixed(1);
        const area = `${line} L${pts[n - 1][0]},${base} L${pts[0][0]},${base} Z`;
        const dots = pts.map((p, i) => `<circle class="chart__dot${i === n - 1 ? ' is-last' : ''}" cx="${p[0]}" cy="${p[1]}" r="${i === n - 1 ? 5 : 3}"/>`).join('');

        el.innerHTML = `
            <svg xmlns="${NS}" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" aria-hidden="true" focusable="false">
                <defs>
                    <linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#00E5FF" stop-opacity="0.38"/>
                        <stop offset="55%" stop-color="#30D158" stop-opacity="0.14"/>
                        <stop offset="100%" stop-color="#30D158" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                ${grid}${xlab}
                <path class="chart__area" d="${area}" fill="url(#${id})"/>
                <path class="chart__line" d="${line}"/>
                ${dots}
                <line class="chart__guide" y1="${M.t}" y2="${base}" x1="0" x2="0" visibility="hidden"/>
                <circle class="chart__focus" r="6" cx="0" cy="0" visibility="hidden"/>
                <rect class="chart__hit" x="${M.l}" y="${M.t}" width="${pw}" height="${ph}" fill="transparent"/>
            </svg>
            <div class="chart__tip" hidden></div>`;

        if (animate && !reduceMotion()) {
            const path = el.querySelector('.chart__line');
            const len = path.getTotalLength();
            path.style.strokeDasharray = `${len}`;
            path.style.strokeDashoffset = `${len}`;
            el.classList.add('is-animating');
            requestAnimationFrame(() => requestAnimationFrame(() => {
                path.style.strokeDashoffset = '0';
                el.classList.remove('is-animating');
            }));
        }

        // Tooltip: titik terdekat dari posisi mouse/jari
        const svg = el.querySelector('svg');
        const tip = el.querySelector('.chart__tip');
        const guide = el.querySelector('.chart__guide');
        const focus = el.querySelector('.chart__focus');
        const hit = el.querySelector('.chart__hit');
        const show = (ev) => {
            const r = svg.getBoundingClientRect();
            const mx = ev.clientX - r.left;
            const i = Math.max(0, Math.min(n - 1, Math.round(((mx - M.l) / pw) * (n - 1))));
            const [px, py] = pts[i];
            guide.setAttribute('x1', px); guide.setAttribute('x2', px); guide.setAttribute('visibility', 'visible');
            focus.setAttribute('cx', px); focus.setAttribute('cy', py); focus.setAttribute('visibility', 'visible');
            tip.innerHTML = `<b>${data.labels[i]}</b><span>${fmt.v.format(values[i])} m³</span>`
                + `<small>${fmt.kg.format(data.weight[i])} kg, ${fmt.n.format(data.cartons[i])} karton</small>`;
            tip.hidden = false;
            const tx = px + 12 + tip.offsetWidth > w ? px - 12 - tip.offsetWidth : px + 12;
            const ty = Math.max(0, Math.min(h - tip.offsetHeight, py - tip.offsetHeight / 2));
            tip.style.transform = `translate(${tx}px, ${ty}px)`;
        };
        const hide = () => { tip.hidden = true; guide.setAttribute('visibility', 'hidden'); focus.setAttribute('visibility', 'hidden'); };
        hit.addEventListener('pointermove', show);
        hit.addEventListener('pointerdown', show);
        hit.addEventListener('pointerleave', hide);
    }

    document.querySelectorAll('[data-area-chart]').forEach((el) => {
        let data;
        try { data = JSON.parse(el.dataset.series); } catch (e) { return; }
        if (!data || !data.volume || !data.volume.length) return;
        draw(el, data, true);
        let lastW = el.clientWidth;
        if ('ResizeObserver' in window) {
            new ResizeObserver(() => {
                if (el.clientWidth !== lastW) { lastW = el.clientWidth; draw(el, data, false); }
            }).observe(el);
        }
    });
})();
