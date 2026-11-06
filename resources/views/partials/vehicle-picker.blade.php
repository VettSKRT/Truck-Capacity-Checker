@php
    $fmtV = fn ($n, $d = 2) => number_format($n, $d, ',', '.');
    $selectedCode = $selected ?? config('logistics.default_vehicle');
@endphp
<div class="vehicle-grid" role="radiogroup" aria-label="Kendaraan">
    @foreach ($vehicles as $v)
        <label class="vehicle-card">
            <input type="radio" name="vehicle" value="{{ $v->code }}" @checked($v->code === $selectedCode)>
            <span class="vehicle-card__body">
                <span class="vehicle-card__name">{{ $v->name }}</span>
                <span class="vehicle-card__dims">{{ $fmtV($v->length_mm / 1000) }} × {{ $fmtV($v->width_mm / 1000) }} × {{ $fmtV($v->height_mm / 1000) }} m</span>
                <span class="vehicle-card__stats">{{ $fmtV($v->volume_m3) }} m³ / {{ $fmtV($v->max_payload_kg, 0) }} kg</span>
            </span>
            <i class="bi bi-check-circle-fill vehicle-card__check" aria-hidden="true"></i>
        </label>
    @endforeach
</div>
