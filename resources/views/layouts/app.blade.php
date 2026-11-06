<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') - {{ config('app.name') }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/packing.css') }}" rel="stylesheet">
</head>
<body>
    <a class="skip-link" href="#main">Langsung ke isi halaman</a>

    <header class="app-header">
        <div class="container-xl app-header__inner">
            <a class="brand" href="{{ route('packing.index') }}">
                <span class="brand__icon" aria-hidden="true"><i class="bi bi-truck"></i></span>
                <span class="brand__text">
                    <strong>Cek Kapasitas Truk</strong>
                    <small>Pengecekan muatan sebelum dikirim</small>
                </span>
            </a>
            <nav class="app-nav" aria-label="Menu utama">
                <a href="{{ route('packing.index') }}" class="{{ request()->routeIs('packing.index') ? 'is-active' : '' }}">
                    <i class="bi bi-speedometer2" aria-hidden="true"></i> Cek Kapasitas
                </a>
                <a href="{{ route('upload.form') }}" class="{{ request()->routeIs('upload.form') ? 'is-active' : '' }}">
                    <i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i> Unggah Biasa
                </a>
            </nav>
        </div>
    </header>

    <main id="main" class="container-xl app-main">
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
