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
                <option value="ready" @selected($filters['status'] === 'ready')>Semua jalur siap</option>
                <option value="partial" @selected($filters['status'] === 'partial')>Sebagian jalur siap</option>
                <option value="wait" @selected($filters['status'] === 'wait')>Belum ada jalur siap</option>
                <option value="over" @selected($filters['status'] === 'over')>Ada jalur melebihi</option>
            </select>
        </div>
        <div>
            <label class="field-label" for="route">Jalur</label>
            <select class="form-select" id="route" name="route">
                <option value="">Semua</option>
                @foreach (range(1, 8) as $n)
                    <option value="{{ $n }}" @selected((string) $filters['route'] === (string) $n)>Jalur {{ $n }}</option>
                @endforeach
                <option value="none" @selected($filters['route'] === 'none')>Tanpa jalur</option>
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
            <i class="bi bi-inbox" aria-hidden="true"></i>
            @if (array_filter($filters))
                Tidak ada kloter yang cocok dengan pencarian ini.
            @else
                Belum ada kloter. Mulai dengan <a href="{{ route('packing.index') }}">Cek Kapasitas</a>.
            @endif
        </div>
    @else
        @include('batches._table', ['batches' => $batches, 'summaries' => $summaries])
        @if ($batches->hasPages())
            <div class="d-flex justify-content-between align-items-center p-3 border-top flex-wrap gap-2">
                <small class="text-muted">Menampilkan {{ $batches->firstItem() }} sampai {{ $batches->lastItem() }} dari {{ $batches->total() }} kloter</small>
                {{ $batches->links() }}
            </div>
        @endif
    @endif
</section>
@endsection
