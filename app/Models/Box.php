<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris muatan = N karton identik (box_count) dari satu part.
 * panjang/lebar/tinggi (mm) adalah ukuran SATU karton; volume & weight_kg adalah total baris.
 */
class Box extends Model
{
    protected $fillable = [
        'upload_id',
        'product_id',
        'carton_id',
        'cargo_destination',
        'customer_code',
        'customer_name',
        'part_number',
        'qty',
        'box_count',
        'packing_type',
        'fill_ratio',
        'panjang',
        'lebar',
        'tinggi',
        'status',
        'volume',
        'weight_kg',
        'category',
        'service_type',
        'route',
    ];

    protected $casts = [
        'qty' => 'integer',
        'box_count' => 'integer',
        'fill_ratio' => 'float',
        'volume' => 'float',
        'weight_kg' => 'float',
        'route' => 'integer',
    ];

    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function carton()
    {
        return $this->belongsTo(Carton::class);
    }

    /** Volume satu karton (m³). */
    public function getUnitVolumeAttribute(): float
    {
        return ($this->panjang / 1000) * ($this->lebar / 1000) * ($this->tinggi / 1000);
    }

    /** Berat satu karton (kg). */
    public function getUnitWeightAttribute(): float
    {
        return $this->box_count > 0 ? $this->weight_kg / $this->box_count : 0.0;
    }
}
