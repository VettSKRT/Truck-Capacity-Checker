<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        // Dimensi dalam (mm) dan kapasitas muatan (kg)
        $vehicles = [
            ['code' => 'CDE',      'name' => 'CDE (ban 4)',           'length_mm' => 3200,  'width_mm' => 1700, 'height_mm' => 1700, 'max_payload_kg' => 3000],
            ['code' => 'CDD',      'name' => 'CDD (ban belakang double)', 'length_mm' => 4400,  'width_mm' => 2000, 'height_mm' => 1900, 'max_payload_kg' => 6000],
            ['code' => 'WING_BOX', 'name' => 'Wing Box',              'length_mm' => 9500,  'width_mm' => 2450, 'height_mm' => 2500, 'max_payload_kg' => 17000],
            // Kontainer 40' High Cube (dimensi dalam standar). Estimasi, sesuaikan jika perlu.
            ['code' => 'IMPOR',    'name' => 'Impor (Kontainer 40\' HC)', 'length_mm' => 12030, 'width_mm' => 2350, 'height_mm' => 2690, 'max_payload_kg' => 26000],
        ];

        foreach ($vehicles as $v) {
            Vehicle::updateOrCreate(['code' => $v['code']], $v + ['usable_factor' => 1.0]);
        }
    }
}
