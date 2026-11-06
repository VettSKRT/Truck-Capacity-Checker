@extends('layouts.app')

@section('title', 'Riwayat Kloter')
@section('heading', 'Riwayat Kloter')
@section('subheading', 'Satu kali unggah file order adalah satu kloter.')

@section('actions')
    <a class="btn-cta" href="{{ route('packing.index') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Cek Kapasitas Baru</a>
@endsection

@section('content')
<form method="GET" action="{{ route('batches.index') }}" class="panel">
    <div class="filter-bar">
        <div>
            <label class="field-label" for="q">Cari</label>
            <input type="search" class="form-control" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Nama file atau nomor kloter">
        </div>
        <div>
            <label class="field-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">Semua</option>
                <option value="ready" @selected($filters['status'] === 'ready')>Siap dikirim</option>
                <option value="wait" @selected($filters['status'] === 'wait')>Belum siap</option>
                <option value="over" @selected($filters['status'] === 'over')>Melebihi kapasitas</option>
            </select>
        </div>
        <div>
            <label class="field-label" for="vehicle">Kendaraan</label>
            <select class="form-select" id="vehicle" name="vehicle">
                <option value="">Semua</option>
                @foreach ($vehicles as $v)
                    <option value="{{ $v->id }}" @selected((string) $filters['vehicle'] === (string) $v->id)>{{ $v->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="field-label" for="date">Tanggal</label>
            <input type="date" class="form-control" id="date" name="date" value="{{ $filters['date'] }}">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn-soft"><i class="bi bi-search" aria-hidden="true"></i> Cari</button>
            @if (array_filter($filters))
                <a href="{{ route('batches.index') }}" class="btn-soft">Reset</a>
            @endif
        </div>
    </div>
</form>

<section class="panel panel--flush">
    @if ($batches->isEmpty())
        <div class="empty-state">
            <i class="bi bi-inbox" style="color: var(--blue-600)" aria-hidden="true"></i>
            @if (array_filter($filters))
                Tidak ada kloter yang cocok dengan pencarian ini.
            @else
                Belum ada kloter. Mulai dengan <a href="{{ route('packing.index') }}">Cek Kapasitas</a>.
            @endif
        </div>
    @else
        @include('batches._table', ['batches' => $batches])
        @if ($batches->hasPages())
            <div class="d-flex justify-content-between align-items-center p-3 border-top flex-wrap gap-2">
                <small class="text-muted">Menampilkan {{ $batches->firstItem() }} sampai {{ $batches->lastItem() }} dari {{ $batches->total() }} kloter</small>
                {{ $batches->links() }}
            </div>
        @endif
    @endif
</section>
@endsection
