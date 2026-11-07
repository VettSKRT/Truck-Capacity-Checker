<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Dealer extends Model
{
    protected $fillable = ['code', 'name', 'moq_m3', 'same_day'];

    protected $casts = ['moq_m3' => 'float', 'same_day' => 'boolean'];

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    /**
     * Daftarkan dealer yang muncul di data kloter tetapi belum ada di tabel dealers,
     * dan lengkapi nama dealer yang masih kosong. MOQ dibiarkan kosong untuk diisi manual.
     */
    public static function syncFromBoxes(): int
    {
        $names = Box::query()
            ->whereNotNull('customer_code')
            ->select('customer_code', DB::raw('max(customer_name) as customer_name'))
            ->groupBy('customer_code')
            ->get()
            ->keyBy('customer_code');

        $existing = static::pluck('name', 'code');
        $created = 0;

        foreach ($names as $code => $row) {
            $key = strtoupper(trim((string) $code));
            if (! $existing->has($key)) {
                static::create(['code' => $code, 'name' => $row->customer_name]);
                $created++;
            } elseif (($existing[$key] === null || $existing[$key] === '') && $row->customer_name) {
                static::where('code', $key)->update(['name' => $row->customer_name]);
            }
        }

        return $created;
    }
}
