@extends('layouts.app')

@php $isNew = ! $shipment->exists; @endphp

@section('title', $isNew ? 'Jadwal Kapal Baru' : 'Ubah Jadwal ' . $shipment->code)
@section('heading', $isNew ? 'Jadwal Kapal Baru' : 'Ubah Jadwal ' . $shipment->code)
@section('subheading', 'Pengiriman mingguan atau keluar pulau. Tanggal tentatif bisa maju atau mundur.')

@section('content')
<form method="POST" action="{{ $isNew ? route('schedule.store') : route('schedule.update', $shipment) }}" class="panel">
    @csrf
    @unless ($isNew) @method('PUT') @endunless

    <div class="form-grid">
        <div>
            <label class="field-label" for="departs_on">Tanggal berangkat</label>
            <input type="date" class="form-control @error('departs_on') is-invalid @enderror" id="departs_on" name="departs_on"
                   value="{{ old('departs_on', $shipment->departs_on?->format('Y-m-d')) }}" required>
            @error('departs_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="field-label" for="destination">Tujuan</label>
            <input type="text" class="form-control @error('destination') is-invalid @enderror" id="destination" name="destination"
                   value="{{ old('destination', $shipment->destination) }}" placeholder="Contoh: Pelabuhan Banjarmasin" maxlength="120" required>
            @error('destination')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="field-label" for="vehicle_id">Kapasitas (kontainer atau kendaraan)</label>
            <select class="form-select" id="vehicle_id" name="vehicle_id">
                <option value="">Belum ditentukan</option>
                @foreach ($vehicles as $v)
                    <option value="{{ $v->id }}" @selected((string) old('vehicle_id', $shipment->vehicle_id) === (string) $v->id) data-label="{{ $v->name }}" data-hint="{{ number_format($v->volume_m3, 2, ',', '.') }} m³ · {{ number_format($v->max_payload_kg, 0, ',', '.') }} kg">
                        {{ $v->name }}: {{ number_format($v->volume_m3, 2, ',', '.') }} m³ / {{ number_format($v->max_payload_kg, 0, ',', '.') }} kg
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="field-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                @foreach (\App\Models\Shipment::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $shipment->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="span-all">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="is_tentative" name="is_tentative" value="1" @checked(old('is_tentative', $shipment->is_tentative))>
                <label class="form-check-label" for="is_tentative">Tentatif: tanggal berangkat masih bisa maju atau mundur</label>
            </div>
        </div>
        <div class="span-all">
            <label class="field-label" for="notes">Catatan (boleh kosong)</label>
            <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="1000">{{ old('notes', $shipment->notes) }}</textarea>
        </div>
    </div>

    <div class="action-row">
        <button class="btn-cta" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Simpan</button>
        <a class="btn-soft" href="{{ $isNew ? route('schedule.index') : route('schedule.show', $shipment) }}">Batal</a>
    </div>
</form>
@endsection
