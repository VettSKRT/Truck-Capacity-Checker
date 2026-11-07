@extends('layouts.app')

@section('title', 'Area dan Jalur')
@section('heading', 'Area dan Jalur')
@section('subheading', 'Kode area adalah huruf di depan kode dealer (SA pada SA38). Atur jalur dan wilayahnya di sini.')

@section('content')
@php $routeNums = range(1, 7); @endphp

<ul class="how-to mb-3">
    <li><strong>Jalur 1 sampai 7</strong>Jalur pengiriman berdasarkan kode area. Warna di tampilan 3D mengikuti jalur ini.</li>
    <li><strong>Wilayah</strong>Jawa dan Sumatra (selain Aceh) dikirim harian. Aceh dan luar pulau mengikuti jadwal kapal.</li>
    <li><strong>Jalur 8 (Same Day)</strong>Khusus dealer terpilih. Atur di menu <a href="{{ route('dealers.index', ['moq' => 'sameday']) }}">Dealer dan MOQ</a>.</li>
</ul>

@if ($unknown->isNotEmpty())
    <div class="notice notice--warn">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div class="flex-grow-1">
            <strong>{{ $unknown->count() }} kode area dari data dealer belum terdaftar:</strong>
            <span class="chip-list d-inline-flex align-middle ms-1">@foreach ($unknown as $code)<span class="chip">{{ $code }}</span>@endforeach</span>
            <form method="POST" action="{{ route('areas.detect') }}" class="mt-2">
                @csrf
                <button type="submit" class="btn-soft btn-soft--sm"><i class="bi bi-plus-lg" aria-hidden="true"></i> Tambahkan ke daftar</button>
                <span class="text-muted ms-1">Setelah itu lengkapi jalur dan wilayahnya.</span>
            </form>
        </div>
    </div>
@endif

<section class="panel panel--flush">
    <div class="panel__head p-3 pb-0 mb-2">
        <h2 class="panel__title">Daftar area ({{ $areas->count() }})</h2>
        <span class="panel__hint">Perubahan langsung diterapkan ke jalur di semua kloter, termasuk riwayat.</span>
    </div>

    @if ($areas->isEmpty())
        <div class="empty-state"><i class="bi bi-inbox" style="color: var(--blue-600)" aria-hidden="true"></i> Belum ada area. Tambahkan di bawah.</div>
    @else
        <form method="POST" action="{{ route('areas.update') }}" id="areaForm">
            @csrf @method('PUT')
            <div class="table-wrap table-wrap--free" style="border:0;border-radius:0">
                <table class="table-clean">
                    <thead>
                        <tr><th>Kode</th><th>Nama (boleh kosong)</th><th>Jalur</th><th>Wilayah</th><th>Jadwal</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($areas as $a)
                            @php $mode = $regionSchedule[$a->region] ?? null; @endphp
                            <tr>
                                <td><strong>{{ $a->code }}</strong></td>
                                <td><input type="text" class="form-control" name="areas[{{ $a->id }}][name]" value="{{ $a->name }}" maxlength="80" placeholder="mis. Jabodetabek" aria-label="Nama area {{ $a->code }}"></td>
                                <td>
                                    <select class="form-select" name="areas[{{ $a->id }}][route]" aria-label="Jalur area {{ $a->code }}">
                                        <option value="">Belum diatur</option>
                                        @foreach ($routeNums as $n)
                                            <option value="{{ $n }}" @selected($a->route === $n)>Jalur {{ $n }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select" name="areas[{{ $a->id }}][region]" aria-label="Wilayah area {{ $a->code }}">
                                        <option value="">Belum diatur</option>
                                        @foreach ($regions as $key => $label)
                                            <option value="{{ $key }}" @selected($a->region === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    @if ($mode === 'kapal')<span class="badge-status is-info">Kapal</span>
                                    @elseif ($mode === 'daily')<span class="badge-status is-info">Harian</span>
                                    @else<span class="badge-status is-muted">-</span>@endif
                                </td>
                                <td class="actions">
                                    <button type="submit" class="btn-soft btn-soft--sm btn-soft--danger" form="del-{{ $a->id }}"
                                            aria-label="Hapus area {{ $a->code }}" onclick="return confirm('Hapus area {{ $a->code }}? Dealer dengan kode area ini jadi tanpa jalur dan wilayah.')">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="sticky-save">
                <button class="btn-cta" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Simpan perubahan</button>
                <span class="text-muted">Jalur 8 tidak dipilih di sini karena khusus dealer Same Day.</span>
            </div>
        </form>

        @foreach ($areas as $a)
            <form id="del-{{ $a->id }}" method="POST" action="{{ route('areas.destroy', $a) }}">@csrf @method('DELETE')</form>
        @endforeach
    @endif
</section>

<form method="POST" action="{{ route('areas.store') }}" class="panel">
    @csrf
    <h2 class="panel__title mb-2">Tambah area</h2>
    <div class="form-grid">
        <div>
            <label class="field-label" for="code">Kode area</label>
            <input type="text" class="form-control text-uppercase" id="code" name="code" value="{{ old('code') }}" maxlength="6" placeholder="mis. SA" required>
        </div>
        <div>
            <label class="field-label" for="name">Nama (boleh kosong)</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="80">
        </div>
        <div>
            <label class="field-label" for="route">Jalur</label>
            <select class="form-select" id="route" name="route">
                <option value="">Belum diatur</option>
                @foreach ($routeNums as $n)<option value="{{ $n }}" @selected((string) old('route') === (string) $n)>Jalur {{ $n }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="field-label" for="region">Wilayah</label>
            <select class="form-select" id="region" name="region">
                <option value="">Belum diatur</option>
                @foreach ($regions as $key => $label)<option value="{{ $key }}" @selected(old('region') === $key)>{{ $label }}</option>@endforeach
            </select>
        </div>
    </div>
    <div class="action-row">
        <button class="btn-cta" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah area</button>
    </div>
</form>
@endsection
