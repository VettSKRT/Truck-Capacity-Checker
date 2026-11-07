<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dealer: kode dari file order, MOQ diisi manual
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                     // mis. SA38
            $table->string('name')->nullable();
            $table->decimal('moq_m3', 10, 3)->nullable();         // Minimal Order Quantity (volume, m³)
            $table->timestamps();
        });

        // Jadwal kapal (pengiriman mingguan / keluar pulau), diinput manual
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->date('departs_on');                            // tanggal berangkat
            $table->string('destination');                         // tujuan
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete(); // kapasitas (kontainer / kendaraan)
            $table->string('status')->default('planned');          // planned | departed | cancelled
            $table->boolean('is_tentative')->default(true);        // tanggal bisa maju atau mundur
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Satu kloter ditautkan ke satu jadwal (kosong = belum dijadwalkan / pengiriman harian)
        Schema::table('uploads', function (Blueprint $table) {
            $table->foreignId('shipment_id')->nullable()->after('vehicle_id')->constrained('shipments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipment_id');
        });
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('dealers');
    }
};
