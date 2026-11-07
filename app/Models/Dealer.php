<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Dealer extends Model
{
    protected $fillable = ['code', 'name', 'moq_m3'];

    protected $casts = ['moq_m3' => 'float'];

    /**
     * Daftarkan dealer yang muncul di data kloter tetapi belum ada di tabel dealers.
     * Nama diambil dari data order; MOQ dibiarkan kosong untuk diisi manual.
     */
    public static function syncFromBoxes(): int
    {
        $known = static::pluck('code')->all();

        $missing = Box::query()
            ->whereNotNull('customer_code')
            ->when($known, fn ($q) => $q->whereNotIn('customer_code', $known))
            ->select('customer_code', DB::raw('max(customer_name) as customer_name'))
            ->groupBy('customer_code')
            ->get();

        foreach ($missing as $row) {
            static::create(['code' => $row->customer_code, 'name' => $row->customer_name]);
        }

        return $missing->count();
    }
}
