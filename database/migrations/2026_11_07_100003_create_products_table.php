<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master barang
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('part_number')->unique();
            $table->string('name')->nullable();
            $table->decimal('weight_kg', 10, 3);                 // berat per pcs
            $table->foreignId('carton_id')->nullable()->constrained('cartons')->nullOnDelete();
            $table->unsignedInteger('qty_per_box')->default(1);  // isi pcs per karton (packing unit)
            $table->string('category')->default('small');        // small | heavy
            $table->string('location')->nullable();              // contoh: G16 04 1 01
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
