/* Cek Kapasitas Truk - skrip bersama semua halaman (ringan, tanpa Bootstrap JS).
 *  - Menu samping di layar sempit
 *  - Tab (pengganti bootstrap.bundle.js yang dulu hanya dipakai untuk tab)
 *  - Animasi loading saat pindah halaman / mengirim formulir
 *  - Modal konfirmasi: tambahkan data-confirm="pesan" pada <form> atau tombol submit
 *      data-confirm-title, data-confirm-ok (teks tombol), data-confirm-variant (danger | warn | info), data-confirm-icon
 * Fungsi untuk skrip lain: window.App.confirm({...}) -> Promise<boolean>, window.App.loader.show(teks) / hide(). */
(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const reduceMotion = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ------------------------------ Menu samping ------------------------------ */
    const navBtn = $('#navToggle');
    if (navBtn) {
        const setNav = (open) => {
            document.body.classList.toggle('nav-open', open);
            navBtn.setAttribute('aria-expanded', String(open));
        };
        navBtn.addEventListener('click', () => setNav(!document.body.classList.contains('nav-open')));
        document.addEventListener('click', (e) => {
            if (document.body.classList.contains('nav-open') && !e.target.closest('#sidebar') && !e.target.closest('#navToggle')) setNav(false);
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setNav(false); });
    }

    /* ---------------------------------- Tab ---------------------------------- */
    // <button role="tab" data-tab="#paneId">. Setelah tab tampil, dikirim peristiwa 'tab:shown' (detail.tab = tombol).
    function showTab(btn, focus) {
        const list = btn.closest('[role="tablist"]');
        if (!list) return;
        list.querySelectorAll('[data-tab]').forEach((b) => {
            const on = b === btn;
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', String(on));
            b.tabIndex = on ? 0 : -1;
            const pane = $(b.dataset.tab);
            if (pane) {
                pane.classList.toggle('active', on);
                pane.classList.toggle('show', on);
            }
        });
        if (focus) btn.focus();
        document.dispatchEvent(new CustomEvent('tab:shown', { detail: { tab: btn } }));
    }
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-tab]');
        if (btn) showTab(btn, false);
    });
    // Panah kiri/kanan, Home/End di daftar tab
    document.addEventListener('keydown', (e) => {
        const btn = e.target.closest && e.target.closest('[role="tab"][data-tab]');
        if (!btn || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
        const tabs = Array.from(btn.closest('[role="tablist"]').querySelectorAll('[role="tab"][data-tab]'));
        const i = tabs.indexOf(btn);
        const next = { ArrowLeft: tabs[(i - 1 + tabs.length) % tabs.length], ArrowRight: tabs[(i + 1) % tabs.length], Home: tabs[0], End: tabs[tabs.length - 1] }[e.key];
        e.preventDefault();
        showTab(next, true);
    });
    document.querySelectorAll('[role="tab"][data-tab]').forEach((b) => { b.tabIndex = b.classList.contains('active') ? 0 : -1; });

    /* --------------------------- Animasi loading ----------------------------- */
    // Garis progres tipis langsung muncul; kartu loading (truk diisi karton) baru muncul bila lebih dari 450 ms,
    // supaya halaman yang cepat tidak berkedip.
    const progress = $('#pageProgress');
    const overlay = $('#pageLoader');
    const overlayText = $('#pageLoaderText');
    let overlayTimer = 0;

    const loader = {
        show(text, immediate) {
            if (progress) { progress.classList.remove('is-done'); progress.classList.add('is-active'); }
            if (!overlay) return;
            if (overlayText) overlayText.textContent = text || 'Memuat halaman';
            clearTimeout(overlayTimer);
            const open = () => { overlay.hidden = false; requestAnimationFrame(() => overlay.classList.add('is-visible')); };
            if (immediate) open(); else overlayTimer = setTimeout(open, 450);
        },
        hide() {
            clearTimeout(overlayTimer);
            if (progress && progress.classList.contains('is-active')) {
                progress.classList.add('is-done');
                setTimeout(() => progress.classList.remove('is-active', 'is-done'), 300);
            }
            if (overlay) { overlay.classList.remove('is-visible'); overlay.hidden = true; }
        },
    };

    // Pindah halaman lewat tautan biasa
    document.addEventListener('click', (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const a = e.target.closest('a[href]');
        if (!a || a.target === '_blank' || a.hasAttribute('download') || a.dataset.noLoader !== undefined) return;
        const url = new URL(a.href, location.href);
        if (url.origin !== location.origin) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return; // lompat ke anchor
        loader.show(a.dataset.loading || 'Memuat halaman');
    });

    // Kembali dengan tombol Back (halaman dari cache): tutup loader dan aktifkan lagi tombol yang tadi dikirim
    window.addEventListener('pageshow', () => {
        loader.hide();
        document.querySelectorAll('.is-busy').forEach((b) => { b.classList.remove('is-busy'); b.disabled = false; });
    });

    /* --------------------------- Modal konfirmasi ----------------------------- */
    const dialog = $('#confirmDialog');
    const VARIANT_ICON = { danger: 'bi-trash3', warn: 'bi-exclamation-triangle', info: 'bi-question-circle' };
    let resolver = null;

    function confirmDialog(opts) {
        opts = opts || {};
        // Tanpa dukungan <dialog>: pakai konfirmasi bawaan browser
        if (!dialog || typeof dialog.showModal !== 'function') {
            return Promise.resolve(window.confirm([opts.title, opts.message].filter(Boolean).join('\n\n')));
        }
        const variant = opts.variant || 'danger';
        dialog.dataset.variant = variant;
        $('#confirmIcon').className = 'bi ' + (opts.icon || VARIANT_ICON[variant] || VARIANT_ICON.info);
        $('#confirmTitle').textContent = opts.title || 'Lanjutkan?';
        $('#confirmText').textContent = opts.message || '';
        const ok = $('#confirmOk');
        ok.textContent = opts.ok || (variant === 'danger' ? 'Hapus' : 'Lanjutkan');
        ok.className = variant === 'danger' ? 'btn-danger' : 'btn-cta';

        if (resolver) resolver(false);
        return new Promise((resolve) => {
            resolver = resolve;
            dialog.returnValue = '';
            dialog.showModal();
            // Tombol aman (Batal) yang terfokus lebih dulu, supaya Enter tidak langsung menghapus
            $('#confirmCancel').focus();
        });
    }

    if (dialog) {
        dialog.addEventListener('close', () => {
            const r = resolver;
            resolver = null;
            if (r) r(dialog.returnValue === 'ok');
        });
        // Klik di luar kotak = batal
        dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close('cancel'); });
    }

    // Formulir: konfirmasi (bila diminta), lalu tampilkan loading saat dikirim
    document.addEventListener('submit', (e) => {
        const form = e.target;
        const submitter = e.submitter || null;
        const src = (submitter && submitter.dataset.confirm !== undefined) ? submitter
            : (form.dataset.confirm !== undefined ? form : null);

        if (src && form.dataset.confirmed !== '1') {
            e.preventDefault();
            e.stopImmediatePropagation();
            confirmDialog({
                title: src.dataset.confirmTitle,
                message: src.dataset.confirm,
                ok: src.dataset.confirmOk,
                variant: src.dataset.confirmVariant,
                icon: src.dataset.confirmIcon,
            }).then((yes) => {
                if (!yes) return;
                form.dataset.confirmed = '1';
                if (form.requestSubmit) form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
                else form.submit();
            });
            return;
        }
        delete form.dataset.confirmed;
    }, true);

    // Setelah semua handler lain: bila formulir benar-benar dikirim ke server, tampilkan loading
    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;
        const form = e.target;
        if (form.target === '_blank') return;
        const btn = e.submitter;
        if (btn && btn.matches('button, input[type="submit"]')) {
            btn.classList.add('is-busy');
            // Nonaktifkan setelah formulir terkirim (bukan sebelumnya, agar nilai tombol tetap ikut terkirim)
            setTimeout(() => { btn.disabled = true; }, 0);
        }
        loader.show(form.dataset.loading || (form.method.toLowerCase() === 'get' ? 'Mencari data' : 'Menyimpan perubahan'));
    });

    window.App = { confirm: confirmDialog, loader, reduceMotion };
})();
