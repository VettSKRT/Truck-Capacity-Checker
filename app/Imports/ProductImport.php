<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Parser master barang (hanya membaca; penyimpanan dilakukan oleh command products:import).
 *
 * Kolom: A Part Number | B Name | C Weight (kg/pcs) | D No Karton | E Qty per Box
 *        F Category (small|heavy) | G Location (opsional, mis. G16 04 1 01)
 */
class ProductImport implements ToCollection, WithStartRow
{
    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows)
    {
        // Dibaca lewat Excel::toCollection().
    }
}
