<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan satu kode area: jalur pengiriman (1-7) dan wilayah.
 * Kode area adalah huruf di depan kode dealer, mis. "SA" pada "SA38".
 */
class Area extends Model
{
    protected $fillable = ['code', 'name', 'route', 'region'];

    protected $casts = ['route' => 'integer'];

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    public function getRegionLabelAttribute(): ?string
    {
        return $this->region ? (config('logistics.regions')[$this->region] ?? $this->region) : null;
    }
}
