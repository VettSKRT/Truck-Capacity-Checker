<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('filepath')->constrained('vehicles')->nullOnDelete();
            $table->float('total_weight')->default(0)->after('total_volume');   // kg, hanya Cargo Ready
            $table->float('volume_ratio')->default(0)->after('ratio');
            $table->float('weight_ratio')->default(0)->after('volume_ratio');
            $table->string('binding_constraint')->nullable()->after('weight_ratio'); // volume | weight
        });

        Schema::table('boxes', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('upload_id')->constrained('products')->nullOnDelete();
            $table->foreignId('carton_id')->nullable()->after('product_id')->constrained('cartons')->nullOnDelete();
            $table->string('part_number')->nullable()->after('customer_name');
            $table->unsignedInteger('qty')->default(1)->after('part_number');          // total pcs pada baris ini
            $table->unsignedInteger('box_count')->default(1)->after('qty');            // jumlah karton
            $table->string('packing_type')->default('unit')->after('box_count');      // unit | receh
            $table->float('fill_ratio')->default(1)->after('packing_type');           // isi karton (0-1)
            $table->float('weight_kg')->default(0)->after('volume');                   // total berat baris
            $table->string('category')->default('small')->after('weight_kg');
            $table->string('service_type')->default('one_day')->after('category');
            $table->unsignedTinyInteger('route')->nullable()->after('service_type');  // jalur 1-8
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('carton_id');
            $table->dropColumn([
                'part_number', 'qty', 'box_count', 'packing_type', 'fill_ratio',
                'weight_kg', 'category', 'service_type', 'route',
            ]);
        });

        Schema::table('uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn(['total_weight', 'volume_ratio', 'weight_ratio', 'binding_constraint']);
        });
    }
};
