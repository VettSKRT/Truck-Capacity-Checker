<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jadwal kapal: pengiriman mingguan atau keluar pulau. Satu jadwal bisa berisi beberapa kloter.
 */
class Shipment extends Model
{
    public const PLANNED = 'planned';
    public const DEPARTED = 'departed';
    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::PLANNED => 'Direncanakan',
        self::DEPARTED => 'Sudah berangkat',
        self::CANCELLED => 'Dibatalkan',
    ];

    protected $fillable = ['departs_on', 'destination', 'vehicle_id', 'status', 'is_tentative', 'notes'];

    protected $casts = [
        'departs_on' => 'date',
        'is_tentative' => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function uploads()
    {
        return $this->hasMany(Upload::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getCodeAttribute(): string
    {
        return 'J-' . str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    public function isActive(): bool
    {
        return $this->status === self::PLANNED;
    }
}
