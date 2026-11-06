<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $fillable = [
        'filename', 'filepath', 'vehicle_id',
        'total_volume', 'total_weight',
        'ratio', 'volume_ratio', 'weight_ratio', 'binding_constraint',
        'status',
    ];

    public function boxes()
    {
        return $this->hasMany(Box::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
