<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#121212">

    <title>@yield('title') - Cek Kapasitas Truk</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/packing.css') }}?v={{ filemtime(public_path('css/packing.css')) }}" rel="stylesheet">
</head>
<body>
    <a class="skip-link" href="#main">Langsung ke isi halaman</a>

    <div class="app-shell">
        <aside class="sidebar" id="sidebar" aria-label="Menu utama">
            <a class="sidebar__brand" href="{{ route('dashboard') }}">
                <span class="sidebar__logo" aria-hidden="true"><i class="bi bi-truck"></i></span>
                <span class="sidebar__title">
                    <strong>Cek Kapasitas Truk</strong>
                    <small>Gudang dan pengiriman</small>
                </span>
            </a>

            <nav class="sidebar__nav">
                <a class="nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="bi bi-grid-1x2" aria-hidden="true"></i> Dashboard
                </a>

                <span class="sidebar__group">Kloter</span>
                <a class="nav-item {{ request()->routeIs('packing.index') ? 'is-active' : '' }}" href="{{ route('packing.index') }}">
                    <i class="bi bi-speedometer2" aria-hidden="true"></i> Cek Kapasitas
                </a>
                <a class="nav-item {{ request()->routeIs('batches.*', 'result') ? 'is-active' : '' }}" href="{{ route('batches.index') }}">
                    <i class="bi bi-clock-history" aria-hidden="true"></i> Riwayat Kloter
                </a>
                <a class="nav-item {{ request()->routeIs('schedule.*') ? 'is-active' : '' }}" href="{{ route('schedule.index') }}">
                    <i class="bi bi-calendar3" aria-hidden="true"></i> Penjadwalan
                </a>

                <span class="sidebar__group">Data</span>
                <a class="nav-item {{ request()->routeIs('dealers.*') ? 'is-active' : '' }}" href="{{ route('dealers.index') }}">
                    <i class="bi bi-people" aria-hidden="true"></i> Dealer dan MOQ
                </a>
                <a class="nav-item {{ request()->routeIs('products.*') ? 'is-active' : '' }}" href="{{ route('products.index') }}">
                    <i class="bi bi-boxes" aria-hidden="true"></i> Master Barang
                </a>
                <a class="nav-item {{ request()->routeIs('reference.*') ? 'is-active' : '' }}" href="{{ route('reference.index') }}">
                    <i class="bi bi-rulers" aria-hidden="true"></i> Kendaraan dan Karton
                </a>

                <span class="sidebar__group">Pengaturan</span>
                <a class="nav-item {{ request()->routeIs('areas.*') ? 'is-active' : '' }}" href="{{ route('areas.index') }}">
                    <i class="bi bi-signpost-split" aria-hidden="true"></i> Area dan Jalur
                </a>
            </nav>

            <div class="sidebar__foot"><span class="live-dot" aria-hidden="true"></span> Hari ini: {{ now()->translatedFormat('d F Y') }}</div>
        </aside>

        <div class="main-col">
            <header class="topbar">
                <button type="button" class="topbar__toggle" id="navToggle" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <div>
                    <h1>@yield('heading')</h1>
                    @hasSection('subheading')
                        <div class="topbar__sub">@yield('subheading')</div>
                    @endif
                </div>
                <div class="topbar__actions">@yield('actions')</div>
            </header>

            <main id="main" class="content">
                @if (session('status'))
                    <div class="notice notice--ok" role="status">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <div>
                            {{ session('status') }}
                            @if (session('import_messages'))
                                <details class="mt-1"><summary>Lihat baris yang dilewati</summary>
                                    <ul>@foreach (session('import_messages') as $m)<li>{{ $m }}</li>@endforeach</ul>
                                </details>
                            @endif
                        </div>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="notice notice--error" role="alert">
                        <i class="bi bi-x-octagon-fill" aria-hidden="true"></i>
                        <div><strong>Gagal memproses.</strong> {{ $errors->first() }}</div>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            var btn = document.getElementById('navToggle');
            btn.addEventListener('click', function () {
                var open = document.body.classList.toggle('nav-open');
                btn.setAttribute('aria-expanded', open);
            });
            document.addEventListener('click', function (e) {
                if (document.body.classList.contains('nav-open') && !e.target.closest('#sidebar') && !e.target.closest('#navToggle')) {
                    document.body.classList.remove('nav-open');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
