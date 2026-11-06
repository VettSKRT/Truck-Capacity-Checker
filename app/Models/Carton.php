<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carton extends Model
{
    protected $fillable = ['number', 'length_mm', 'width_mm', 'height_mm'];

    protected $casts = [
        'number' => 'integer',
        'length_mm' => 'integer',
        'width_mm' => 'integer',
        'height_mm' => 'integer',
    ];

    /** Volume satu karton dalam m³. */
    public function getVolumeM3Attribute(): float
    {
        return ($this->length_mm / 1000) * ($this->width_mm / 1000) * ($this->height_mm / 1000);
    }
}
