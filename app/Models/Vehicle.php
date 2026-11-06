<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'code', 'name', 'length_mm', 'width_mm', 'height_mm', 'max_payload_kg', 'usable_factor',
    ];

    protected $casts = [
        'length_mm' => 'integer',
        'width_mm' => 'integer',
        'height_mm' => 'integer',
        'max_payload_kg' => 'integer',
        'usable_factor' => 'float',
    ];

    /** Volume fisik dalam m³. */
    public function getPhysicalVolumeM3Attribute(): float
    {
        return ($this->length_mm / 1000) * ($this->width_mm / 1000) * ($this->height_mm / 1000);
    }

    /** Volume efektif (m³) = volume fisik × faktor pemakaian. Dipakai untuk rasio. */
    public function getVolumeM3Attribute(): float
    {
        return $this->physical_volume_m3 * $this->usable_factor;
    }
}
