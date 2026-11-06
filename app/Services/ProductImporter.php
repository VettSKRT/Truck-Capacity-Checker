<?php

namespace App\Services;

use App\Imports\ProductImport;
use App\Models\Carton;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import / update master barang dari Excel atau CSV.
 * Dipakai oleh halaman Master Barang dan command `products:import`.
 *
 * Kolom (baris 1 = header): Part Number | Name | Weight (kg/pcs) | No Karton |
 * Qty per Box | Category (small|heavy) | Location (opsional)
 */
class ProductImporter
{
    /**
     * @param string|UploadedFile $file
     * @return array{created:int, updated:int, skipped:int, messages:array<int,string>}
     */
    public function import($file): array
    {
        $rows = Excel::toCollection(new ProductImport, $file)->first() ?? collect();
        $cartons = Carton::pluck('id', 'number');
        $categories = array_keys(config('logistics.categories'));

        $created = $updated = $skipped = 0;
        $messages = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $part = trim((string) ($row[0] ?? ''));

            if ($part === '') {
                continue;
            }

            $weight = (float) ($row[2] ?? 0);
            $cartonNo = (int) ($row[3] ?? 0);
            $qtyPerBox = (int) ($row[4] ?? 0);
            $category = strtolower(trim((string) ($row[5] ?? ''))) ?: 'small';

            $problem = match (true) {
                $weight <= 0 => 'berat harus lebih dari 0',
                $qtyPerBox <= 0 => 'qty per box harus lebih dari 0',
                ! isset($cartons[$cartonNo]) => "nomor karton {$cartonNo} tidak ada",
                ! in_array($category, $categories, true) => 'kategori harus small atau heavy',
                default => null,
            };

            if ($problem !== null) {
                $skipped++;
                if (count($messages) < 30) {
                    $messages[] = "Baris {$line} ({$part}) dilewati: {$problem}.";
                }
                continue;
            }

            $name = trim((string) ($row[1] ?? ''));
            $location = trim((string) ($row[6] ?? ''));

            $product = Product::updateOrCreate(['part_number' => $part], [
                'name' => $name !== '' ? $name : null,
                'weight_kg' => $weight,
                'carton_id' => $cartons[$cartonNo],
                'qty_per_box' => $qtyPerBox,
                'category' => $category,
                'location' => $location !== '' ? $location : null,
            ]);

            $product->wasRecentlyCreated ? $created++ : $updated++;
        }

        return compact('created', 'updated', 'skipped', 'messages');
    }
}
