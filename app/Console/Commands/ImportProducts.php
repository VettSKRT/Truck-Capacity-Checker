<?php

namespace App\Console\Commands;

use App\Imports\ProductImport;
use App\Models\Carton;
use App\Models\Product;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportProducts extends Command
{
    protected $signature = 'products:import {file : Path file master barang (.xlsx/.xls/.csv)}';

    protected $description = 'Import / update master barang (part number, berat, nomor karton, qty per box)';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $rows = Excel::toCollection(new ProductImport, $path)->first() ?? collect();
        $cartons = Carton::pluck('id', 'number');

        $created = $updated = $skipped = 0;

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $part = trim((string) ($row[0] ?? ''));

            if ($part === '') {
                continue;
            }

            $weight = (float) ($row[2] ?? 0);
            $cartonNo = (int) ($row[3] ?? 0);
            $qtyPerBox = (int) ($row[4] ?? 0);
            $category = strtolower(trim((string) ($row[5] ?? 'small'))) ?: 'small';

            if ($weight <= 0 || $qtyPerBox <= 0 || ! isset($cartons[$cartonNo]) || ! array_key_exists($category, config('logistics.categories'))) {
                $this->warn("Baris {$line} ({$part}) dilewati: berat/qty per box/no karton/kategori tidak valid.");
                $skipped++;
                continue;
            }

            $product = Product::updateOrCreate(['part_number' => $part], [
                'name' => ($n = trim((string) ($row[1] ?? ''))) !== '' ? $n : null,
                'weight_kg' => $weight,
                'carton_id' => $cartons[$cartonNo],
                'qty_per_box' => $qtyPerBox,
                'category' => $category,
                'location' => ($l = trim((string) ($row[6] ?? ''))) !== '' ? $l : null,
            ]);

            $product->wasRecentlyCreated ? $created++ : $updated++;
        }

        $this->info("Selesai: {$created} baru, {$updated} diperbarui, {$skipped} dilewati.");

        return self::SUCCESS;
    }
}
