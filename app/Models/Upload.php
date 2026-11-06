<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Satu Upload = satu kloter (satu kali unggah file order).
 */
class Upload extends Model
{
    public const STATUS_READY = 'Siap Dikirim';
    public const STATUS_WAIT = 'Belum Siap';
    public const STATUS_OVER = 'Melebihi Kapasitas';

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

    /** Tambah jumlah karton (carton_total) dan jumlah dealer (dealer_total). */
    public function scopeWithSummary(Builder $query): Builder
    {
        return $query
            ->withSum('boxes as carton_total', 'box_count')
            ->withCount(['boxes as dealer_total' => function ($q) {
                $q->select(DB::raw('count(distinct customer_code)'));
            }]);
    }

    /** Siap dikirim: cukup penuh (>= batas) dan tidak melebihi kendaraan. */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && ! $this->isOver();
    }

    public function isOver(): bool
    {
        return $this->ratio > 1;
    }

    // Berdasarkan rasio, agar data lama (yang statusnya belum membedakan "melebihi") tetap benar
    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY)->where('ratio', '<=', 1);
    }

    public function scopeOver(Builder $query): Builder
    {
        return $query->where('ratio', '>', 1);
    }

    public function scopeWait(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_READY)->where('ratio', '<=', 1);
    }

    /** Nomor kloter untuk tampilan, mis. "K-0012". */
    public function getCodeAttribute(): string
    {
        return 'K-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
