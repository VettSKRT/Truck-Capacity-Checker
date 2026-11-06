<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Parser untuk file cargo (Excel/CSV).
 *
 * Class ini sengaja TIDAK menyimpan apa pun ke database. Ia hanya dipakai
 * lewat Excel::toCollection() agar UploadController bisa memvalidasi baris
 * dan menyimpan Box lengkap dengan upload_id dan volume.
 */
class CargoImport implements ToCollection, WithStartRow
{
    public function startRow(): int
    {
        return 2; // Lewati baris header
    }

    public function collection(Collection $rows)
    {
        // Tidak ada proses di sini; hasil dibaca lewat Excel::toCollection().
    }
}
