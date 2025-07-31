<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Box extends Model
{
    protected $fillable = [
        'upload_id',
        'cargo_destination',
        'customer_code',
        'customer_name',
        'panjang',
        'lebar',
        'tinggi',
        'status',
        'volume'
    ];

    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }
}
