<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pemetaan area contoh dari Fase 7 (kode => [jalur, wilayah]). Dipakai untuk mendeteksi data yang belum diubah pengguna. */
    private const OLD_EXAMPLE = [
        'SA' => [1, 'jawa'], 'SB' => [2, 'jawa'], 'SC' => [3, 'jawa'], 'SD' => [4, 'jawa'], 'SE' => [5, 'jawa'],
        'SH' => [null, 'jawa'], 'SF' => [6, 'sumatra'], 'SG' => [7, 'sumatra'],
        'SJ' => [5, 'luar_pulau'], 'SK' => [6, 'luar_pulau'], 'SL' => [7, 'aceh'],
    ];

    /** Pemetaan contoh baru: satu jalur = satu truk ke satu tujuan. */
    private const NEW_EXAMPLE = [
        'SA' => [1, 'jawa'],        // Jabodetabek
        'SC' => [2, 'jawa'],        // Jawa Tengah
        'SB' => [3, 'jawa'],        // Jawa Barat
        'SH' => [3, 'jawa'],        // Cirebon (Jawa Barat)
        'SD' => [4, 'jawa'],        // Jawa Timur
        'SE' => [4, 'jawa'],
        'SF' => [5, 'sumatra'],     // Sumatra
        'SG' => [5, 'sumatra'],
        'SJ' => [6, 'luar_pulau'],  // Luar pulau (pelabuhan)
        'SK' => [6, 'luar_pulau'],
        'SL' => [6, 'aceh'],
    ];

    public function up(): void
    {
        // Satu jalur = satu truk khusus ke satu tujuan. Tabel ini menyimpan nama tujuan dan truk default tiap jalur.
        Schema::create('route_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('route')->unique();            // 1-8
            $table->string('name')->nullable();                        // tujuan, mis. "Jabodetabek"
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete(); // kosong = truk default
            $table->timestamps();
        });

        $names = [1 => 'Jabodetabek', 2 => 'Jawa Tengah', 3 => 'Jawa Barat', 4 => 'Jawa Timur', 5 => 'Sumatra',
                  6 => 'Luar pulau (pelabuhan)', 7 => 'Cadangan', 8 => 'Same Day'];
        $now = now();
        foreach ($names as $route => $name) {
            DB::table('route_settings')->insert(['route' => $route, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Perbarui pemetaan area CONTOH hanya jika belum pernah diubah pengguna (persis sama dengan contoh lama)
        $current = DB::table('areas')->get()->mapWithKeys(fn ($a) => [$a->code => [$a->route === null ? null : (int) $a->route, $a->region]])->all();
        ksort($current);
        $old = self::OLD_EXAMPLE;
        ksort($old);

        if ($current === $old) {
            foreach (self::NEW_EXAMPLE as $code => [$route, $region]) {
                DB::table('areas')->where('code', $code)->update(['route' => $route, 'region' => $region, 'updated_at' => $now]);
            }

            // Jalur pada karton adalah data turunan dari pemetaan area; hitung ulang untuk kloter yang sudah ada
            app(\App\Services\RouteApplier::class)->reapplyAll();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('route_settings');
    }
};
