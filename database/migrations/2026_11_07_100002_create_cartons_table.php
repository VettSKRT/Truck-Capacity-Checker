<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartons', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number')->unique(); // No Karton (1-18, 21, 26-31)
            // Ukuran paten (mm). Karton TIDAK boleh diputar: P=panjang, L=lebar, T=tinggi.
            $table->unsignedInteger('length_mm');
            $table->unsignedInteger('width_mm');
            $table->unsignedInteger('height_mm');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartons');
    }
};
