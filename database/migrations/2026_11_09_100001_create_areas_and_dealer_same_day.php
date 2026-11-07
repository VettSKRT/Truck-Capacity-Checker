<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengaturan area: kode area (mis. "SA") -> jalur pengiriman (1-7) dan wilayah
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->nullable();                    // mis. "Jabodetabek" (opsional)
            $table->unsignedTinyInteger('route')->nullable();      // jalur 1-7
            $table->string('region')->nullable();                  // jawa | sumatra | aceh | luar_pulau
            $table->timestamps();
        });

        // Dealer terpilih untuk Jalur 8 (Same Day)
        Schema::table('dealers', function (Blueprint $table) {
            $table->boolean('same_day')->default(false)->after('moq_m3');
        });

        // Data awal diambil dari config/logistics.php agar aplikasi langsung berjalan seperti sebelumnya.
        // Setelah ini sumber kebenarannya adalah database (menu Pengaturan).
        $routes = array_change_key_case((array) config('logistics.area_routes', []), CASE_UPPER);
        $regions = array_change_key_case((array) config('logistics.area_regions', []), CASE_UPPER);
        $now = now();

        foreach (array_unique(array_merge(array_keys($routes), array_keys($regions))) as $code) {
            DB::table('areas')->insert([
                'code' => $code,
                'route' => $routes[$code] ?? null,
                'region' => $regions[$code] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ((array) config('logistics.same_day_dealers', []) as $dealer) {
            $code = strtoupper(trim($dealer));
            if ($code === '') {
                continue;
            }
            if (DB::table('dealers')->where('code', $code)->exists()) {
                DB::table('dealers')->where('code', $code)->update(['same_day' => true, 'updated_at' => $now]);
            } else {
                DB::table('dealers')->insert(['code' => $code, 'same_day' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropColumn('same_day');
        });
        Schema::dropIfExists('areas');
    }
};
