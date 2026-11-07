<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Satu Upload = satu kloter (satu kali unggah file order).
 *
 * Kesiapan kirim TIDAK disimpan di sini: satu kloter berisi beberapa jalur dan tiap jalur adalah satu truk,
 * jadi statusnya dihitung per jalur oleh RouteLoadService. Kolom ratio, volume_ratio, weight_ratio,
 * binding_constraint, status, dan vehicle_id adalah peninggalan model lama (satu kendaraan per kloter)
 * dan tidak dipakai lagi.
 */
class Upload extends Model
{
    protected $fillable = [
        'filename', 'filepath', 'vehicle_id', 'shipment_id',
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

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    /** Tambah jumlah karton (carton_total) dan jumlah dealer (dealer_total). */
    public function scopeWithSummary(Builder $query): Builder
    {
        return $query
            ->withSum('boxes as carton_total', 'box_count')
            ->withCount(['boxes as dealer_total' => function ($q) {
                $q->select(DB::raw('count(distinct customer_code)'));
            }]);
    }

    /** Nomor kloter untuk tampilan, mis. "K-0012". */
    public function getCodeAttribute(): string
    {
        return 'K-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
