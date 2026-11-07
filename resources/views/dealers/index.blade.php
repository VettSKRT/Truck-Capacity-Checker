@extends('layouts.app')

@section('title', 'Dealer dan MOQ')
@section('heading', 'Dealer dan MOQ')
@section('subheading', 'MOQ (Minimal Order Quantity) per dealer dalam m³ untuk menilai jadwal kapal, dan penanda Same Day untuk dealer terpilih (Jalur 8).')

@section('content')
@php $fmt = fn ($n, $d = 2) => number_format($n, $d, ',', '.'); @endphp

<form method="GET" action="{{ route('dealers.index') }}" class="panel">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="field-label" for="q">Cari</label>
            <input type="search" class="form-control" id="q" name="q" value="{{ $q }}" placeholder="Kode atau nama dealer">
        </div>
        <div class="col-md-3">
            <label class="field-label" for="moq">Tampilkan</label>
            <select class="form-select" id="moq" name="moq">
                <option value="">Semua dealer ({{ $total }})</option>
                <option value="unset" @selected($filter === 'unset')>MOQ belum diisi ({{ $unsetCount }})</option>
                <option value="sameday" @selected($filter === 'sameday')>Dealer Same Day ({{ $sameDayCount }})</option>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn-soft" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Cari</button>
            @if ($q !== '' || $filter)<a class="btn-soft" href="{{ route('dealers.index') }}">Reset</a>@endif
        </div>
    </div>
</form>

<form method="POST" action="{{ route('dealers.store') }}" class="panel">
    @csrf
    <h2 class="panel__title mb-2">Tambah dealer</h2>
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="field-label" for="new_code">Kode dealer</label>
            <input type="text" class="form-control text-uppercase" id="new_code" name="code" value="{{ old('code') }}" placeholder="mis. SA38" maxlength="12" required>
        </div>
        <div class="col-md-4">
            <label class="field-label" for="new_name">Nama (boleh kosong)</label>
            <input type="text" class="form-control" id="new_name" name="name" value="{{ old('name') }}" maxlength="120">
        </div>
        <div class="col-md-3">
            <div class="form-check mb-2">
                <input type="checkbox" class="form-check-input" id="new_same_day" name="same_day" value="1" @checked(old('same_day'))>
                <label class="form-check-label" for="new_same_day">Dealer Same Day (Jalur 8)</label>
            </div>
        </div>
        <div class="col-md-2"><button class="btn-soft" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah</button></div>
    </div>
    <p class="panel__hint mt-2 mb-0">Dealer juga muncul otomatis dari file order. Tambah manual untuk dealer yang belum pernah muncul, misalnya dealer Same Day.</p>
</form>

<section class="panel panel--flush">
    @if ($dealers->isEmpty())
        <div class="empty-state">
            <i class="bi bi-inbox" aria-hidden="true"></i>
            {{ $total === 0 ? 'Belum ada dealer. Dealer muncul otomatis setelah file order diunggah.' : 'Tidak ada dealer yang cocok.' }}
        </div>
    @else
        <form method="POST" action="{{ route('dealers.moq') }}">
            @csrf
            <div class="table-wrap table-wrap--free table-wrap--flush">
                <table class="table-clean">
                    <thead>
                        <tr><th>Kode</th><th>Nama</th><th>Wilayah</th><th>Jadwal</th><th>Same Day</th><th class="num">MOQ (m³)</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($dealers as $d)
                            <tr>
                                <td><strong>{{ $d->code }}</strong></td>
                                <td>{{ $d->name ?: '-' }}</td>
                                <td>{{ $d->region_label ?? 'Belum diatur' }}</td>
                                <td>
                                    @if ($d->mode === 'kapal')<span class="badge-status is-info">Kapal</span>
                                    @elseif ($d->mode === 'daily')<span class="badge-status is-info">Harian</span>
                                    @else<span class="badge-status is-muted">-</span>@endif
                                </td>
                                <td>
                                    <div class="form-check mb-0">
                                        <input type="checkbox" class="form-check-input" id="sd-{{ $d->id }}" name="same_day[{{ $d->code }}]" value="1" @checked($d->same_day)>
                                        <label class="form-check-label" for="sd-{{ $d->id }}">Jalur 8</label>
                                    </div>
                                </td>
                                <td class="num">
                                    <input type="number" step="0.001" min="0" class="form-control moq-input ms-auto"
                                           name="moq[{{ $d->code }}]" value="{{ $d->moq_m3 !== null ? rtrim(rtrim(number_format($d->moq_m3, 3, '.', ''), '0'), '.') : '' }}"
                                           placeholder="belum diisi" aria-label="MOQ dealer {{ $d->code }} dalam meter kubik">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="sticky-save">
                <button class="btn-cta" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Simpan MOQ</button>
                <span class="text-muted">Kosongkan MOQ untuk menghapusnya. Mengubah Same Day langsung memperbarui jalur di riwayat kloter.</span>
            </div>
        </form>
        @if ($dealers->hasPages())
            <div class="d-flex justify-content-between align-items-center p-3 border-top flex-wrap gap-2">
                <small class="text-muted">Dealer {{ $dealers->firstItem() }} sampai {{ $dealers->lastItem() }} dari {{ $dealers->total() }}. Simpan sebelum pindah halaman.</small>
                {{ $dealers->links() }}
            </div>
        @endif
    @endif
</section>
@endsection
