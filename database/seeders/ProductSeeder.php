<?php

namespace Database\Seeders;

use App\Models\Carton;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * DATA CONTOH untuk pengembangan/demo. Ganti dengan master barang asli
 * lewat: php artisan products:import <file.csv|xlsx>
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // part_number, name, weight_kg per pcs, carton no, qty_per_box, category, location
        $products = [
            ['SMP-0001', 'Filter Oli',       0.25,  2, 20, 'small', 'G16 04 1 01'],
            ['SMP-0002', 'Kampas Rem Depan', 0.80,  1, 10, 'small', 'G16 04 1 02'],
            ['SMP-0003', 'Busi',             0.05,  8, 50, 'small', 'H02 01 2 05'],
            ['SMP-0004', 'Shockbreaker',     3.50, 14,  4, 'small', 'K05 03 1 01'],
            ['SMP-0005', 'Bemper Depan',     4.50, 29,  1, 'heavy', 'P01 01 1 01'],
            ['SMP-0006', 'Kap Mobil',        9.00, 31,  1, 'heavy', 'T01 02 1 01'],
        ];

        foreach ($products as [$part, $name, $weight, $cartonNo, $qtyPerBox, $category, $location]) {
            $carton = Carton::where('number', $cartonNo)->first();

            Product::updateOrCreate(['part_number' => $part], [
                'name' => $name,
                'weight_kg' => $weight,
                'carton_id' => $carton?->id,
                'qty_per_box' => $qtyPerBox,
                'category' => $category,
                'location' => $location,
            ]);
        }
    }
}
