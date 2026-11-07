<?php

namespace Database\Seeders;

use App\Models\Dealer;
use Illuminate\Database\Seeder;

/**
 * DATA CONTOH MOQ untuk sample_data_kapal.csv. Ganti dengan MOQ asli lewat menu Dealer dan MOQ.
 * SJ21 sengaja tidak diberi MOQ supaya status "MOQ belum diatur" bisa dilihat.
 */
class DealerSeeder extends Seeder
{
    public function run(): void
    {
        $dealers = [
            ['SJ10', 'Dealer Banjarmasin', 0.5],   // sample: 0,76 m³ -> memenuhi
            ['SK05', 'Dealer Makassar', 1.0],      // sample: 0,19 m³ -> kurang
            ['SL02', 'Dealer Banda Aceh', 0.5],    // sample: 0,57 m³ -> memenuhi
        ];

        foreach ($dealers as [$code, $name, $moq]) {
            Dealer::updateOrCreate(['code' => $code], ['name' => $name, 'moq_m3' => $moq]);
        }
    }
}
