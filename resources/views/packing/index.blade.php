@extends('layouts.app')

@section('title', 'Cek Kapasitas')
@section('heading', 'Cek Kapasitas')
@section('subheading', 'Setiap file yang diunggah menjadi satu kloter baru dan tersimpan di Riwayat Kloter.')

@section('content')
<form id="checkForm" class="panel" novalidate>
    <span class="field-label">File order</span>
    <label class="dropzone" id="dropzone" for="excel_file">
        <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
        <span class="dropzone__text">
            <span class="dropzone__main" id="fileName">Klik untuk memilih file</span>
            <span class="dropzone__hint" id="fileHint">atau tarik file ke sini (.xlsx, .xls, atau .csv)</span>
        </span>
        <input type="file" class="visually-hidden" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv">
    </label>

    <details class="format-help">
        <summary>Urutan kolom yang dibutuhkan</summary>
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
</form>

<div id="messages" aria-live="polite"></div>

<section class="panel" id="emptyState">
    <h2 class="mb-2">Cara membaca hasil</h2>
    <ul class="how-to">
        <li><strong>Satu jalur, satu truk</strong>Setiap jalur adalah satu truk khusus ke satu tujuan (mis. Jabodetabek). Tiap jalur dicek sendiri terhadap truknya.</li>
        <li><strong>Hijau, kuning, merah</strong>Hijau berarti siap dikirim (80% atau lebih), kuning berarti belum cukup penuh, merah berarti melebihi truk.</li>
        <li><strong>Rencana muat</strong>Pilih satu jalur untuk melihat susunan 3D dan mencetak rencana muatnya.</li>
    </ul>
</section>

@include('partials.results')
@endsection

@push('scripts')
<script src="https://cdn.plot.ly/plotly-2.35.2.min.js" charset="utf-8"></script>
<script src="{{ asset('js/packing.js') }}?v={{ filemtime(public_path('js/packing.js')) }}"></script>
@endpush
