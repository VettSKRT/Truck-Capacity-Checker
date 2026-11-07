/* Kontrol form kustom (progressive enhancement): elemen asli tetap ada dan tetap dikirim bersama formulir,
 * hanya tampilannya diganti. Bila skrip ini gagal dimuat, <select> dan <input type="date"> bawaan tetap bisa dipakai.
 *
 *  - Dropdown: semua select.form-select (kecuali [multiple] atau [data-native]).
 *      <option data-label="..." data-hint="..."> menampilkan judul + keterangan kecil di menu.
 *      Lebih dari 8 pilihan: ada kotak cari. Keyboard: panah, Home/End, Enter, Esc, ketik huruf untuk melompat.
 *  - Tanggal: semua input[type=date].form-control. Kalender bulanan (Senin di depan), tombol Hari ini / Hapus.
 *      Keyboard: panah (hari/minggu), PageUp/PageDown (bulan), Home/End (awal/akhir minggu), Enter, Esc.
 *  Perubahan memicu peristiwa 'input' dan 'change' pada elemen asli, jadi skrip lain (packing.js) tetap bekerja. */
(function () {
    'use strict';

    let uid = 0;
    const nextId = (p) => `${p}-${++uid}`;
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fire = (el) => {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    };

    // Label <label for="id"> diarahkan ke tombol baru supaya klik label membuka kontrol kustom
    function relabel(native, trigger) {
        const label = native.id ? document.querySelector(`label[for="${CSS.escape(native.id)}"]`) : null;
        if (label) {
            if (!label.id) label.id = nextId('fx-label');
            label.htmlFor = trigger.id;
            return label.id;
        }
        return null;
    }

    // Nilai yang diubah lewat skrip (select.value = 'X') ikut memperbarui tampilan
    function watchValue(el, props, onSet) {
        props.forEach((prop) => {
            const proto = Object.getPrototypeOf(el);
            const desc = Object.getOwnPropertyDescriptor(proto, prop);
            if (!desc || !desc.set) return;
            Object.defineProperty(el, prop, {
                configurable: true,
                get() { return desc.get.call(this); },
                set(v) { desc.set.call(this, v); onSet(); },
            });
        });
    }

    // Popover melayang (position: fixed di <body>) supaya tidak terpotong tabel yang bisa digulir
    function placePopover(pop, anchor, minWidth) {
        const r = anchor.getBoundingClientRect();
        const vw = document.documentElement.clientWidth, vh = window.innerHeight;
        const gap = 6, margin = 8;
        pop.style.minWidth = Math.max(r.width, minWidth || 0) + 'px';
        pop.style.maxHeight = '';
        const ph = pop.offsetHeight, pw = pop.offsetWidth;
        const below = vh - r.bottom - gap - margin, above = r.top - gap - margin;
        const up = ph > below && above > below;
        const room = up ? above : below;
        if (ph > room) pop.style.maxHeight = Math.max(160, room) + 'px';
        const h = Math.min(ph, Math.max(160, room));
        let left = Math.min(r.left, vw - pw - margin);
        left = Math.max(margin, left);
        pop.style.left = left + 'px';
        pop.style.top = (up ? r.top - gap - h : r.bottom + gap) + 'px';
        pop.dataset.side = up ? 'top' : 'bottom';
    }

    // Satu popover terbuka pada satu waktu
    let openCtl = null;
    function closeOpen(focusBack) { if (openCtl) openCtl.close(focusBack); }
    document.addEventListener('pointerdown', (e) => {
        if (openCtl && !openCtl.contains(e.target)) openCtl.close(false);
    }, true);
    const reposition = () => { if (openCtl) openCtl.place(); };
    window.addEventListener('resize', reposition);
    document.addEventListener('scroll', (e) => {
        if (openCtl && !openCtl.pop.contains(e.target)) reposition();
    }, true);

    /* ================================ Dropdown ================================ */

    function enhanceSelect(native) {
        if (native.multiple || native.dataset.native !== undefined || native.closest('.fx-select')) return;

        const wrap = document.createElement('div');
        wrap.className = 'fx-select';
        native.parentNode.insertBefore(wrap, native);
        wrap.appendChild(native);
        native.classList.add('fx-native');
        native.tabIndex = -1;
        native.setAttribute('aria-hidden', 'true');

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = nextId('fx-sel');
        trigger.className = 'fx-select__trigger';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        const valueId = nextId('fx-val');
        trigger.innerHTML = `<span class="fx-select__value" id="${valueId}"></span><span class="fx-select__hint"></span><i class="bi bi-chevron-down fx-select__chev" aria-hidden="true"></i>`;
        wrap.appendChild(trigger);

        const labelId = relabel(native, trigger);
        if (labelId) trigger.setAttribute('aria-labelledby', `${labelId} ${valueId}`);
        const ariaLabel = native.getAttribute('aria-label');

        const pop = document.createElement('div');
        pop.className = 'fx-pop fx-menu';
        pop.id = nextId('fx-list');
        pop.hidden = true;
        document.body.appendChild(pop);
        trigger.setAttribute('aria-controls', `${pop.id}-list`);

        let items = [], active = -1, typed = '', typedTimer = 0, search = null;

        const opts = () => Array.from(native.options);
        const labelOf = (o) => o.dataset.label || o.textContent.trim();

        function sync() {
            const o = native.options[native.selectedIndex];
            trigger.querySelector('.fx-select__value').textContent = o ? labelOf(o) : '';
            trigger.querySelector('.fx-select__hint').textContent = o && o.dataset.hint ? o.dataset.hint : '';
            trigger.classList.toggle('is-placeholder', !o || o.value === '');
            trigger.disabled = native.disabled;
            wrap.classList.toggle('is-invalid', native.classList.contains('is-invalid'));
            if (!labelId && ariaLabel) trigger.setAttribute('aria-label', `${ariaLabel}: ${o ? labelOf(o) : ''}`);
        }

        function build(filter) {
            const q = (filter || '').trim().toLowerCase();
            const list = pop.querySelector('.fx-menu__list');
            items = [];
            list.innerHTML = opts().map((o, i) => {
                const text = labelOf(o), hint = o.dataset.hint || '';
                if (q && !(text + ' ' + hint).toLowerCase().includes(q)) return '';
                items.push(i);
                const sel = i === native.selectedIndex;
                return `<div class="fx-option${sel ? ' is-selected' : ''}${o.disabled ? ' is-disabled' : ''}${o.value === '' ? ' is-empty' : ''}" role="option" id="${pop.id}-o${i}" data-i="${i}" aria-selected="${sel}"${o.disabled ? ' aria-disabled="true"' : ''}>`
                    + `<span class="fx-option__text"><span class="fx-option__label">${esc(text)}</span>${hint ? `<span class="fx-option__hint">${esc(hint)}</span>` : ''}</span>`
                    + `<i class="bi bi-check2 fx-option__check" aria-hidden="true"></i></div>`;
            }).join('') || '<div class="fx-menu__empty">Tidak ada yang cocok</div>';
        }

        function setActive(pos, scroll) {
            const els = pop.querySelectorAll('.fx-option');
            els.forEach((e) => e.classList.remove('is-active'));
            if (pos < 0 || pos >= items.length) { active = -1; (search || trigger).removeAttribute('aria-activedescendant'); return; }
            active = pos;
            const el = pop.querySelector(`[data-i="${items[pos]}"]`);
            el.classList.add('is-active');
            (search || trigger).setAttribute('aria-activedescendant', el.id);
            if (scroll) el.scrollIntoView({ block: 'nearest' });
        }

        // Geser sorotan |delta| pilihan ke atas/bawah, melewati pilihan yang nonaktif; berhenti di ujung daftar
        function move(delta) {
            if (!items.length) return;
            const step = delta > 0 ? 1 : -1;
            let p = active < 0 ? (step > 0 ? -1 : items.length) : active;
            let target = active, left = Math.abs(delta);
            while (left > 0) {
                p += step;
                if (p < 0 || p >= items.length) break;
                if (!native.options[items[p]].disabled) { target = p; left--; }
            }
            if (target !== active && target >= 0) setActive(target, true);
        }

        function choose(i) {
            const o = native.options[i];
            if (!o || o.disabled) return;
            const changed = native.selectedIndex !== i;
            native.selectedIndex = i;
            ctl.close(true);
            if (changed) fire(native);
        }

        const ctl = {
            pop,
            contains: (t) => wrap.contains(t) || pop.contains(t),
            place: () => placePopover(pop, trigger, 200),
            open() {
                if (native.disabled) return;
                if (openCtl && openCtl !== ctl) openCtl.close(false);
                const many = native.options.length > 8;
                pop.innerHTML = (many ? `<div class="fx-menu__search"><i class="bi bi-search" aria-hidden="true"></i><input type="text" placeholder="Cari..." aria-label="Cari pilihan" role="combobox" aria-autocomplete="list" aria-expanded="true" aria-controls="${pop.id}-list" autocomplete="off"></div>` : '')
                    + `<div class="fx-menu__list" id="${pop.id}-list" role="listbox" tabindex="-1"${labelId ? ` aria-labelledby="${labelId}"` : (ariaLabel ? ` aria-label="${esc(ariaLabel)}"` : '')}></div>`;
                search = many ? pop.querySelector('input') : null;
                build('');
                pop.hidden = false;
                wrap.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                openCtl = ctl;
                ctl.place();
                requestAnimationFrame(() => pop.classList.add('is-visible'));
                setActive(items.indexOf(native.selectedIndex), true);
                if (search) {
                    search.addEventListener('input', () => { build(search.value); setActive(items.length ? 0 : -1, true); });
                    search.addEventListener('keydown', onKey);
                    search.focus();
                }
            },
            close(focusBack) {
                if (openCtl !== ctl) return;
                openCtl = null;
                pop.classList.remove('is-visible');
                pop.hidden = true;
                pop.innerHTML = '';
                search = null;
                wrap.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.removeAttribute('aria-activedescendant');
                if (focusBack) trigger.focus();
            },
        };

        function onKey(e) {
            const isOpen = openCtl === ctl;
            switch (e.key) {
                case 'ArrowDown': case 'ArrowUp':
                    e.preventDefault();
                    if (!isOpen || e.altKey) { if (!isOpen) ctl.open(); else if (e.altKey) ctl.close(true); return; }
                    move(e.key === 'ArrowDown' ? 1 : -1);
                    return;
                case 'Home': case 'End':
                    if (!isOpen || e.target === search) return;
                    e.preventDefault();
                    setActive(e.key === 'Home' ? 0 : items.length - 1, true);
                    return;
                case 'PageDown': case 'PageUp':
                    if (!isOpen) return;
                    e.preventDefault();
                    move(e.key === 'PageDown' ? 6 : -6);
                    return;
                case 'Enter': case ' ':
                    if (e.key === ' ' && e.target === search) return;   // spasi di kotak cari = huruf biasa
                    e.preventDefault();
                    if (!isOpen) ctl.open(); else if (active >= 0) choose(items[active]); else ctl.close(true);
                    return;
                case 'Escape':
                    if (isOpen) { e.preventDefault(); e.stopPropagation(); ctl.close(true); }
                    return;
                case 'Tab':
                    if (isOpen) ctl.close(false);
                    return;
                default:
                    // Ketik huruf untuk melompat ke pilihan (tanpa kotak cari)
                    if (e.target !== search && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                        typed += e.key.toLowerCase();
                        clearTimeout(typedTimer);
                        typedTimer = setTimeout(() => { typed = ''; }, 600);
                        const start = isOpen ? items[active] ?? -1 : native.selectedIndex;
                        const all = opts();
                        for (let k = 1; k <= all.length; k++) {
                            const i = (start + k + all.length) % all.length;
                            if (!all[i].disabled && labelOf(all[i]).toLowerCase().startsWith(typed.length > 1 ? typed : e.key.toLowerCase())) {
                                if (isOpen) setActive(items.indexOf(i), true);
                                else if (native.selectedIndex !== i) { native.selectedIndex = i; fire(native); }
                                break;
                            }
                        }
                    }
            }
        }

        trigger.addEventListener('click', () => (openCtl === ctl ? ctl.close(true) : ctl.open()));
        trigger.addEventListener('keydown', onKey);
        pop.addEventListener('mousemove', (e) => {
            const o = e.target.closest('.fx-option');
            if (o) { const pos = items.indexOf(Number(o.dataset.i)); if (pos !== active) setActive(pos, false); }
        });
        pop.addEventListener('click', (e) => {
            const o = e.target.closest('.fx-option');
            if (o) choose(Number(o.dataset.i));
        });
        // Jangan sampai klik di menu memindahkan fokus dari kotak cari / tombol
        pop.addEventListener('mousedown', (e) => { if (e.target !== search) e.preventDefault(); });

        native.addEventListener('change', sync);
        native.addEventListener('invalid', (e) => { e.preventDefault(); wrap.classList.add('is-invalid'); trigger.focus(); });
        if (native.form) native.form.addEventListener('reset', () => setTimeout(sync, 0));
        new MutationObserver(sync).observe(native, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'class'] });
        watchValue(native, ['value', 'selectedIndex'], sync);
        sync();
    }

    /* ============================== Pemilih tanggal ============================== */

    const DAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    const fmtLong = new Intl.DateTimeFormat('id-ID', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
    const fmtMonth = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' });
    const fmtAria = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const pad = (n) => String(n).padStart(2, '0');
    const toIso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const parseIso = (s) => {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
        return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
    };
    const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
    const addMonths = (d, n) => {
        const t = new Date(d.getFullYear(), d.getMonth() + n, 1);
        const last = new Date(t.getFullYear(), t.getMonth() + 1, 0).getDate();
        return new Date(t.getFullYear(), t.getMonth(), Math.min(d.getDate(), last));
    };
    const sameDay = (a, b) => a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

    function enhanceDate(native) {
        if (native.dataset.native !== undefined || native.closest('.fx-date')) return;

        const wrap = document.createElement('div');
        wrap.className = 'fx-date';
        native.parentNode.insertBefore(wrap, native);
        wrap.appendChild(native);
        native.classList.add('fx-native');
        native.tabIndex = -1;
        native.setAttribute('aria-hidden', 'true');

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = nextId('fx-date');
        trigger.className = 'fx-date__trigger';
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');
        const valueId = nextId('fx-dval');
        trigger.innerHTML = `<i class="bi bi-calendar3 fx-date__icon" aria-hidden="true"></i><span class="fx-date__value" id="${valueId}"></span>`;
        wrap.appendChild(trigger);

        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'fx-date__clear';
        clear.setAttribute('aria-label', 'Hapus tanggal');
        clear.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
        if (!native.required) wrap.appendChild(clear);

        const labelId = relabel(native, trigger);
        if (labelId) trigger.setAttribute('aria-labelledby', `${labelId} ${valueId}`);

        const err = document.createElement('div');
        err.className = 'fx-error';
        err.textContent = 'Pilih tanggal dulu.';
        err.hidden = true;
        wrap.appendChild(err);

        const pop = document.createElement('div');
        pop.className = 'fx-pop fx-cal';
        pop.id = nextId('fx-cal');
        pop.hidden = true;
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-label', 'Pilih tanggal');
        document.body.appendChild(pop);
        trigger.setAttribute('aria-controls', pop.id);

        const min = () => parseIso(native.min), max = () => parseIso(native.max);
        const allowed = (d) => !((min() && d < min()) || (max() && d > max()));
        let view = null, focusDay = null;

        function sync() {
            const d = parseIso(native.value);
            trigger.querySelector('.fx-date__value').textContent = d ? fmtLong.format(d) : (native.placeholder || 'Pilih tanggal');
            trigger.classList.toggle('is-placeholder', !d);
            wrap.classList.toggle('has-value', !!d);
            wrap.classList.toggle('is-invalid', native.classList.contains('is-invalid'));
            if (d) { err.hidden = true; wrap.classList.remove('is-missing'); }
        }

        function setValue(d) {
            const v = d ? toIso(d) : '';
            if (native.value === v) { ctl.close(true); return; }
            native.value = v;
            fire(native);
            ctl.close(true);
        }

        function render() {
            const today = new Date();
            const sel = parseIso(native.value);
            const first = new Date(view.getFullYear(), view.getMonth(), 1);
            const start = addDays(first, -((first.getDay() + 6) % 7));   // Senin sebelum/tanggal 1
            let cells = '';
            for (let i = 0; i < 42; i++) {
                const d = addDays(start, i);
                const out = d.getMonth() !== view.getMonth();
                const cls = ['fx-day', out ? 'is-out' : '', sameDay(d, today) ? 'is-today' : '', sameDay(d, sel) ? 'is-selected' : '', (i % 7) >= 5 ? 'is-weekend' : ''].filter(Boolean).join(' ');
                const focus = sameDay(d, focusDay);
                cells += `${i % 7 === 0 ? '<div class="fx-cal__row" role="row">' : ''}<button type="button" role="gridcell" class="${cls}" data-iso="${toIso(d)}" tabindex="${focus ? 0 : -1}" aria-label="${fmtAria.format(d)}"${sameDay(d, sel) ? ' aria-selected="true"' : ''}${allowed(d) ? '' : ' disabled'}>${d.getDate()}</button>${i % 7 === 6 ? '</div>' : ''}`;
            }
            const todayOk = allowed(new Date(today.getFullYear(), today.getMonth(), today.getDate()));
            pop.innerHTML = `
                <div class="fx-cal__head">
                    <button type="button" class="fx-cal__nav" data-nav="-1" aria-label="Bulan sebelumnya"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                    <div class="fx-cal__title" aria-live="polite">${esc(fmtMonth.format(view))}</div>
                    <button type="button" class="fx-cal__nav" data-nav="1" aria-label="Bulan berikutnya"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                </div>
                <div class="fx-cal__grid" role="grid" aria-label="${esc(fmtMonth.format(view))}">
                    <div class="fx-cal__row fx-cal__dow" role="row">${DAYS.map((d, i) => `<span role="columnheader" class="${i >= 5 ? 'is-weekend' : ''}">${d}</span>`).join('')}</div>
                    ${cells}
                </div>
                <div class="fx-cal__foot">
                    <button type="button" class="fx-cal__link" data-act="today"${todayOk ? '' : ' disabled'}>Hari ini</button>
                    ${native.required ? '' : '<button type="button" class="fx-cal__link is-muted" data-act="clear">Hapus</button>'}
                </div>`;
        }

        function focusOn(d) {
            if (!allowed(d)) return;
            focusDay = d;
            if (d.getMonth() !== view.getMonth() || d.getFullYear() !== view.getFullYear()) {
                view = new Date(d.getFullYear(), d.getMonth(), 1);
            }
            render();
            const b = pop.querySelector(`[data-iso="${toIso(d)}"]`);
            if (b) b.focus();
        }

        const ctl = {
            pop,
            contains: (t) => wrap.contains(t) || pop.contains(t),
            place: () => placePopover(pop, trigger, 296),
            open() {
                if (openCtl && openCtl !== ctl) openCtl.close(false);
                const sel = parseIso(native.value);
                const now = new Date();
                focusDay = sel || new Date(now.getFullYear(), now.getMonth(), now.getDate());
                view = new Date(focusDay.getFullYear(), focusDay.getMonth(), 1);
                render();
                pop.hidden = false;
                wrap.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                openCtl = ctl;
                ctl.place();
                requestAnimationFrame(() => pop.classList.add('is-visible'));
                const b = pop.querySelector('.fx-day[tabindex="0"]') || pop.querySelector('.fx-day:not([disabled])');
                if (b) b.focus();
            },
            close(focusBack) {
                if (openCtl !== ctl) return;
                openCtl = null;
                pop.classList.remove('is-visible');
                pop.hidden = true;
                wrap.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
                if (focusBack) trigger.focus();
            },
        };

        trigger.addEventListener('click', () => (openCtl === ctl ? ctl.close(true) : ctl.open()));
        trigger.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' && openCtl !== ctl) { e.preventDefault(); ctl.open(); }
            if ((e.key === 'Backspace' || e.key === 'Delete') && !native.required && native.value) { e.preventDefault(); native.value = ''; fire(native); }
        });
        clear.addEventListener('click', () => { native.value = ''; fire(native); trigger.focus(); });

        pop.addEventListener('click', (e) => {
            const day = e.target.closest('.fx-day');
            if (day && !day.disabled) { setValue(parseIso(day.dataset.iso)); return; }
            const nav = e.target.closest('[data-nav]');
            if (nav) {
                view = addMonths(view, Number(nav.dataset.nav));
                focusDay = new Date(view.getFullYear(), view.getMonth(), Math.min(focusDay.getDate(), new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate()));
                render();
                pop.querySelector(`[data-nav="${nav.dataset.nav}"]`).focus();
                ctl.place();
                return;
            }
            const act = e.target.closest('[data-act]');
            if (act && act.dataset.act === 'today') { const n = new Date(); setValue(new Date(n.getFullYear(), n.getMonth(), n.getDate())); }
            if (act && act.dataset.act === 'clear') setValue(null);
        });
        pop.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); ctl.close(true); return; }
            if (e.key === 'Tab') {
                // Fokus berputar di dalam kalender selama terbuka
                const f = Array.from(pop.querySelectorAll('button:not([disabled])')).filter((b) => !b.classList.contains('fx-day') || b.tabIndex === 0);
                const i = f.indexOf(document.activeElement);
                if (e.shiftKey && i <= 0) { e.preventDefault(); f[f.length - 1].focus(); } else if (!e.shiftKey && i === f.length - 1) { e.preventDefault(); f[0].focus(); }
                return;
            }
            const day = e.target.closest('.fx-day');
            if (!day) return;
            const d = parseIso(day.dataset.iso);
            const map = {
                ArrowLeft: () => addDays(d, -1), ArrowRight: () => addDays(d, 1),
                ArrowUp: () => addDays(d, -7), ArrowDown: () => addDays(d, 7),
                PageUp: () => addMonths(d, e.shiftKey ? -12 : -1), PageDown: () => addMonths(d, e.shiftKey ? 12 : 1),
                Home: () => addDays(d, -((d.getDay() + 6) % 7)), End: () => addDays(d, 6 - ((d.getDay() + 6) % 7)),
            };
            if (map[e.key]) { e.preventDefault(); focusOn(map[e.key]()); }
        });

        native.addEventListener('change', sync);
        native.addEventListener('invalid', (e) => {
            e.preventDefault();
            wrap.classList.add('is-missing');
            err.hidden = false;
            trigger.focus();
        });
        if (native.form) native.form.addEventListener('reset', () => setTimeout(sync, 0));
        watchValue(native, ['value'], sync);
        sync();
    }

    /* ================================ Pilih file ================================ */
    // Input asli tetap di atas kotak (transparan), jadi klik, seret-lepas, keyboard, dan validasi bawaan tetap berfungsi.
    function enhanceFile(native) {
        if (native.closest('.fx-file')) return;
        const types = (native.accept || '').split(',').map((t) => t.trim()).filter(Boolean).join(', ');
        const empty = 'Belum ada file' + (types ? ` (${types})` : '');
        const wrap = document.createElement('div');
        wrap.className = 'fx-file';
        native.parentNode.insertBefore(wrap, native);
        wrap.innerHTML = `<i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i><span class="fx-file__name">${esc(empty)}</span><span class="fx-file__btn" aria-hidden="true">Pilih file</span>`;
        wrap.appendChild(native);
        native.classList.remove('form-control');
        const name = wrap.querySelector('.fx-file__name');
        const icon = wrap.querySelector('.bi');
        const sync = () => {
            const f = native.files && native.files[0];
            name.textContent = f ? `${f.name} (${Math.max(1, Math.round(f.size / 1024))} KB)` : empty;
            wrap.classList.toggle('has-file', !!f);
            icon.className = 'bi ' + (f ? 'bi-file-earmark-check' : 'bi-file-earmark-arrow-up');
        };
        native.addEventListener('change', sync);
        ['dragenter', 'dragover'].forEach((ev) => native.addEventListener(ev, () => wrap.classList.add('is-over')));
        ['dragleave', 'drop'].forEach((ev) => native.addEventListener(ev, () => wrap.classList.remove('is-over')));
        if (native.form) native.form.addEventListener('reset', () => setTimeout(sync, 0));
        sync();
    }

    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && openCtl) closeOpen(true); });
    document.querySelectorAll('select.form-select').forEach(enhanceSelect);
    document.querySelectorAll('input[type="date"].form-control').forEach(enhanceDate);
    document.querySelectorAll('input[type="file"].form-control').forEach(enhanceFile);
})();
