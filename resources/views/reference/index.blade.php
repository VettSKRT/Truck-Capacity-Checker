@extends('layouts.app')

@section('title', 'Kendaraan dan Karton')
@section('heading', 'Kendaraan dan Karton')
@section('subheading', 'Data acuan perhitungan muatan. Ukuran karton bersifat tetap dan tidak diputar saat disusun.')

@section('content')
@php $fmt = fn ($n, $d = 2) => number_format($n, $d, ',', '.'); @endphp

<section class="panel panel--flush">
    <div class="panel__head"><h2 class="panel__title">Kendaraan</h2></div>
    <div class="table-wrap table-wrap--free table-wrap--flush">
        <table class="table-clean">
            <thead>
                <tr>
                    <th>Kendaraan</th>
                    <th>Dimensi dalam (P × L × T)</th>
                    <th class="num">Volume fisik (m³)</th>
                    <th class="num">Faktor pakai</th>
                    <th class="num">Volume efektif (m³)</th>
                    <th class="num">Muatan maksimum (kg)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicles as $v)
                    <tr>
                        <td><strong>{{ $v->name }}</strong></td>
                        <td>{{ $fmt($v->length_mm / 1000) }} × {{ $fmt($v->width_mm / 1000) }} × {{ $fmt($v->height_mm / 1000) }} m</td>
                        <td class="num">{{ $fmt($v->physical_volume_m3) }}</td>
                        <td class="num">{{ $fmt($v->usable_factor * 100, 0) }}%</td>
                        <td class="num">{{ $fmt($v->volume_m3) }}</td>
                        <td class="num">{{ $fmt($v->max_payload_kg, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="panel panel--flush">
    <div class="panel__head"><h2 class="panel__title">Ukuran karton ({{ $cartons->count() }} jenis)</h2></div>
    <div class="table-wrap table-wrap--free table-wrap--flush">
        <table class="table-clean">
            <thead>
                <tr>
                    <th>No karton</th>
                    <th class="num">Panjang (mm)</th>
                    <th class="num">Lebar (mm)</th>
                    <th class="num">Tinggi (mm)</th>
                    <th class="num">Volume (liter)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cartons as $c)
                    <tr>
                        <td><strong>{{ $c->number }}</strong></td>
                        <td class="num">{{ $c->length_mm }}</td>
                        <td class="num">{{ $c->width_mm }}</td>
                        <td class="num">{{ $c->height_mm }}</td>
                        <td class="num">{{ $fmt($c->volume_m3 * 1000, 1) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
