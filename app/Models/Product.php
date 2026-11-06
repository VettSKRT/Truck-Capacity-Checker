<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'part_number', 'name', 'weight_kg', 'carton_id', 'qty_per_box', 'category', 'location',
    ];

    protected $casts = [
        'weight_kg' => 'float',
        'qty_per_box' => 'integer',
    ];

    public function carton()
    {
        return $this->belongsTo(Carton::class);
    }
}
