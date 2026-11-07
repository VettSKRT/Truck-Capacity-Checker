<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan satu jalur. Satu jalur = satu truk khusus ke satu tujuan
 * (mis. Jalur 1 ke Jabodetabek). Menyimpan nama tujuan dan truk default.
 */
class RouteSetting extends Model
{
    protected $fillable = ['route', 'name', 'vehicle_id'];

    protected $casts = ['route' => 'integer'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Semua jalur 1-8, sudah diurutkan dan dibuat bila belum ada. */
    public static function ensureAll()
    {
        foreach (range(1, 8) as $n) {
            static::firstOrCreate(['route' => $n]);
        }

        return static::with('vehicle')->orderBy('route')->get();
    }

    public function getLabelAttribute(): string
    {
        return 'Jalur ' . $this->route . ($this->name ? ' (' . $this->name . ')' : '');
    }
}
