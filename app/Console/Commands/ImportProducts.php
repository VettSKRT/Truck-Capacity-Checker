<?php

namespace App\Console\Commands;

use App\Services\ProductImporter;
use Illuminate\Console\Command;

class ImportProducts extends Command
{
    protected $signature = 'products:import {file : Path file master barang (.xlsx/.xls/.csv)}';

    protected $description = 'Import / update master barang (part number, berat, nomor karton, qty per box)';

    public function handle(ProductImporter $importer): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $result = $importer->import($path);

        foreach ($result['messages'] as $message) {
            $this->warn($message);
        }
        $this->info("Selesai: {$result['created']} baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati.");

        return self::SUCCESS;
    }
}
