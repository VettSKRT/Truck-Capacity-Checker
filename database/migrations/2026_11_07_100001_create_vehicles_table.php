<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();      // CDE, CDD, WING_BOX, IMPOR
            $table->string('name');
            // Dimensi dalam (mm)
            $table->unsignedInteger('length_mm');
            $table->unsignedInteger('width_mm');
            $table->unsignedInteger('height_mm');
            // Kapasitas muatan (kg)
            $table->unsignedInteger('max_payload_kg');
            // Faktor volume efektif (1.0 = seluruh volume fisik dipakai)
            $table->decimal('usable_factor', 4, 3)->default(1.000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
