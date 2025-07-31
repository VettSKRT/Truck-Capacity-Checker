<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $fillable = ['filename', 'filepath', 'total_volume', 'ratio', 'status'];

    public function boxes()
    {
        return $this->hasMany(Box::class);
    }
}
