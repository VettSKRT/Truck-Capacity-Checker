<?php

namespace App\Imports;

use App\Models\Box;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\Log;

class CargoImport implements ToCollection, WithStartRow
{
    public function startRow(): int
    {
        return 2; // Mulai dari baris ke-2 (setelah header)
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            try {
                if (!isset($row[0])) {
                    Log::info('Baris kosong ditemukan pada index: ' . $index);
                    continue;
                }

                Box::create([
                    'cargo_destination' => $row[0], // Kolom A
                    'customer_code' => $row[1],     // Kolom B
                    'customer_name' => $row[2],     // Kolom C
                    'panjang' => $row[3],          // Kolom D
                    'lebar' => $row[4],            // Kolom E
                    'tinggi' => $row[5],           // Kolom F
                    'status' => $row[6]            // Kolom G
                ]);
            } catch (\Exception $e) {
                Log::error('Error pada baris ' . ($index + 2) . ': ' . $e->getMessage());
                throw $e;
            }
        }
    }
}
