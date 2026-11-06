@extends('layouts.app')

@section('title', 'Unggah Biasa')

@section('content')
<div class="page-intro">
    <h1>Unggah Biasa</h1>
    <p>Unggah file order lalu langsung buka laporan lengkapnya. Untuk tampilan 3D, gunakan halaman Cek Kapasitas.</p>
</div>

<form action="{{ route('upload') }}" method="POST" enctype="multipart/form-data" class="panel">
    @csrf

    @if ($errors->any())
        <div class="notice notice--error" role="alert">
            <i class="bi bi-x-octagon-fill" aria-hidden="true"></i>
            <div><strong>Gagal memproses.</strong><br>{{ $errors->first() }}</div>
        </div>
    @endif

    <div class="mb-3">
        <label class="form-label fw-bold" for="vehicle">Kendaraan</label>
        <select name="vehicle" id="vehicle" class="form-select form-select-lg">
            @foreach ($vehicles as $v)
                <option value="{{ $v->code }}" @selected($v->code === config('logistics.default_vehicle'))>
                    {{ $v->name }}: {{ number_format($v->volume_m3, 2, ',', '.') }} m³ dan {{ number_format($v->max_payload_kg, 0, ',', '.') }} kg
                </option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold" for="excel_file">File order (.xlsx, .xls, atau .csv)</label>
        <input type="file" id="excel_file" name="excel_file" class="form-control form-control-lg" accept=".xlsx,.xls,.csv" required>
    </div>

    <div class="action-row">
        <button type="submit" class="btn-cta"><i class="bi bi-upload" aria-hidden="true"></i> Unggah dan Cek</button>
        <a href="{{ route('packing.index') }}" class="btn-soft">Kembali</a>
    </div>
</form>
@endsection
