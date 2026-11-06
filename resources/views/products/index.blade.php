@extends('layouts.app')

@section('title', 'Master Barang')
@section('heading', 'Master Barang')
@section('subheading', number_format($total, 0, ',', '.') . ' barang terdaftar. Berat dan ukuran karton diambil dari sini saat file order diunggah.')

@section('content')
@php $fmt = fn ($n, $d = 2) => number_format($n, $d, ',', '.'); @endphp

<div class="row g-3 mb-1">
    <div class="col-lg-7">
        <form method="GET" action="{{ route('products.index') }}" class="panel h-100">
            <div class="row g-2 align-items-end">
                <div class="col-sm-6">
                    <label class="field-label" for="q">Cari</label>
                    <input type="search" class="form-control" id="q" name="q" value="{{ $q }}" placeholder="Part number atau nama">
                </div>
                <div class="col-sm-3">
                    <label class="field-label" for="category">Kategori</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">Semua</option>
                        <option value="small" @selected($category === 'small')>Kecil</option>
                        <option value="heavy" @selected($category === 'heavy')>Berat / besar</option>
                    </select>
                </div>
                <div class="col-sm-3 d-flex gap-2">
                    <button class="btn-soft" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Cari</button>
                    @if ($q !== '' || $category)
                        <a href="{{ route('products.index') }}" class="btn-soft">Reset</a>
                    @endif
                </div>
            </div>
        </form>
    </div>
    <div class="col-lg-5">
        <form method="POST" action="{{ route('products.import') }}" enctype="multipart/form-data" class="panel h-100">
            @csrf
            <label class="field-label" for="product_file">Impor atau perbarui master barang</label>
            <div class="d-flex gap-2">
                <input type="file" class="form-control" id="product_file" name="product_file" accept=".xlsx,.xls,.csv" required>
                <button class="btn-cta" type="submit"><i class="bi bi-upload" aria-hidden="true"></i> Impor</button>
            </div>
            <details class="format-help">
                <summary>Urutan kolom file</summary>
                <ol>
                    <li>Part Number, 2. Name, 3. Weight (kg per pcs)</li>
                    <li>No Karton, 5. Qty per Box</li>
                    <li>Category (small atau heavy), 7. Location (boleh kosong)</li>
                </ol>
                <span class="text-muted">Part yang sudah ada diperbarui, yang baru ditambahkan.</span>
            </details>
        </form>
    </div>
</div>

<section class="panel panel--flush">
    @if ($products->isEmpty())
        <div class="empty-state">
            <i class="bi bi-inbox" style="color: var(--blue-600)" aria-hidden="true"></i>
            {{ $total === 0 ? 'Master barang masih kosong. Impor file master barang di atas.' : 'Tidak ada barang yang cocok dengan pencarian.' }}
        </div>
    @else
        <div class="table-wrap table-wrap--free" style="border:0;border-radius:0">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Part number</th>
                        <th>Nama</th>
                        <th class="num">Berat (kg/pcs)</th>
                        <th>Karton</th>
                        <th class="num">Isi per box</th>
                        <th>Kategori</th>
                        <th>Lokasi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $p)
                        <tr>
                            <td><strong>{{ $p->part_number }}</strong></td>
                            <td>{{ $p->name ?: '-' }}</td>
                            <td class="num">{{ $fmt($p->weight_kg, 3) }}</td>
                            <td>
                                @if ($p->carton)
                                    No. {{ $p->carton->number }}
                                    <small>({{ $p->carton->length_mm }} × {{ $p->carton->width_mm }} × {{ $p->carton->height_mm }} mm)</small>
                                @else
                                    <span class="text-muted">belum diatur</span>
                                @endif
                            </td>
                            <td class="num">{{ $p->qty_per_box }}</td>
                            <td>{{ $p->category === 'heavy' ? 'Berat / besar' : 'Kecil' }}</td>
                            <td>{{ $p->location ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="d-flex justify-content-between align-items-center p-3 border-top flex-wrap gap-2">
                <small class="text-muted">Menampilkan {{ $products->firstItem() }} sampai {{ $products->lastItem() }} dari {{ $products->total() }} barang</small>
                {{ $products->links() }}
            </div>
        @endif
    @endif
</section>
@endsection
