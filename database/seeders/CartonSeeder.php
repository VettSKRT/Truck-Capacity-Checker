<?php

namespace Database\Seeders;

use App\Models\Carton;
use Illuminate\Database\Seeder;

class CartonSeeder extends Seeder
{
    public function run(): void
    {
        // No Karton => [panjang, lebar, tinggi] dalam mm (ukuran paten, tidak boleh diputar)
        $cartons = [
            1 => [380, 280, 200],
            2 => [450, 180, 150],
            3 => [400, 200, 100],
            4 => [250, 180, 150],
            5 => [400, 350, 230],
            6 => [500, 350, 330],
            7 => [495, 285, 150],
            8 => [120, 120, 100],
            9 => [170, 160, 110],
            10 => [200, 140, 150],
            11 => [240, 100, 240],
            12 => [215, 80, 175],
            13 => [750, 350, 250],
            14 => [200, 100, 890],
            15 => [950, 70, 1150],
            16 => [80, 80, 680],
            17 => [70, 50, 830],
            18 => [780, 400, 570],
            21 => [740, 460, 570],
            26 => [200, 100, 1340],
            27 => [220, 110, 790],
            28 => [240, 110, 1050],
            29 => [1100, 210, 410],
            30 => [260, 150, 950],
            31 => [1100, 50, 500],
        ];

        foreach ($cartons as $number => [$p, $l, $t]) {
            Carton::updateOrCreate(
                ['number' => $number],
                ['length_mm' => $p, 'width_mm' => $l, 'height_mm' => $t]
            );
        }
    }
}
