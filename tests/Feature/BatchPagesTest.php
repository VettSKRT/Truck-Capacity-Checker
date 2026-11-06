<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Product;
use App\Models\Upload;
use Database\Seeders\CartonSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Halaman dashboard, riwayat kloter, master barang, dan referensi. Butuh database (lihat phpunit.xml). */
class BatchPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([VehicleSeeder::class, CartonSeeder::class, ProductSeeder::class]);
    }

    private function upload(string $name, string $body, string $vehicle = 'CDD'): int
    {
        $header = "Cargo Destination,Customer Code,Customer Name,Part Number,Qty,Status,Service Type\n";
        $file = UploadedFile::fake()->createWithContent($name, $header . $body);

        return $this->postJson('/upload', ['excel_file' => $file, 'vehicle' => $vehicle])->assertOk()->json('upload_id');
    }

    public function test_every_upload_becomes_one_batch_listed_in_history_and_dashboard(): void
    {
        $a = $this->upload('pagi.csv', "Jakarta,SA38,Dealer A,SMP-0001,45,4,one_day\n");
        $b = $this->upload('siang.csv', "Bandung,SB12,Dealer B,SMP-0002,10,4,one_day\n");

        $this->assertNotSame($a, $b);
        $this->assertSame(2, Upload::count());

        $this->get('/')->assertOk()->assertSee('Kloter hari ini')->assertSee('pagi.csv')->assertSee('siang.csv');
        $this->get('/riwayat')->assertOk()->assertSee('pagi.csv')->assertSee('siang.csv');
    }

    public function test_history_can_be_filtered_by_name_number_and_status(): void
    {
        $a = $this->upload('pagi.csv', "Jakarta,SA38,Dealer A,SMP-0001,45,4,one_day\n");
        $b = $this->upload('siang.csv', "Bandung,SB12,Dealer B,SMP-0002,10,4,one_day\n");

        $this->get('/riwayat?q=pagi')->assertOk()->assertSee('pagi.csv')->assertDontSee('siang.csv');
        $this->get('/riwayat?q=K-' . str_pad((string) $b, 4, '0', STR_PAD_LEFT))->assertOk()->assertSee('siang.csv')->assertDontSee('pagi.csv');
        // Muatan kecil: keduanya "Belum siap"
        $this->get('/riwayat?status=ready')->assertOk()->assertDontSee('pagi.csv')->assertSee('Tidak ada kloter yang cocok');
        $this->get('/riwayat?status=wait')->assertOk()->assertSee('pagi.csv')->assertSee('siang.csv');
    }

    public function test_over_capacity_batch_is_stored_and_filtered_as_over_not_ready(): void
    {
        // 6300 pcs Kampas Rem (630 karton, ~13 m³ / 5 ton) jauh melebihi CDE (9,25 m³ / 3 ton)
        $over = $this->upload('besar.csv', "Jakarta,SA38,Dealer A,SMP-0002,6300,4,emergency\n", 'CDE');
        $small = $this->upload('kecil.csv', "Jakarta,SA38,Dealer A,SMP-0001,45,4,one_day\n", 'CDD');

        $this->assertSame('Melebihi Kapasitas', Upload::find($over)->status);
        $this->assertTrue(Upload::find($over)->isOver());
        $this->assertFalse(Upload::find($over)->isReady());

        $this->get('/riwayat?status=over')->assertOk()->assertSee('besar.csv')->assertDontSee('kecil.csv');
        $this->get('/riwayat?status=ready')->assertOk()->assertDontSee('besar.csv');
        $this->get('/riwayat?status=wait')->assertOk()->assertSee('kecil.csv')->assertDontSee('besar.csv');
    }

    public function test_batch_detail_page_loads_stored_batch_and_optimize_still_works_on_it(): void
    {
        $id = $this->upload('pagi.csv', "Jakarta,SA38,Dealer A,SMP-0001,45,4,one_day\n");

        $this->get('/riwayat/' . $id)->assertOk()->assertSee('data-upload-id="' . $id . '"', false)->assertSee('pagi.csv');
        $this->postJson('/packing/optimize', ['upload_id' => $id, 'mode' => 'tetris', 'vehicle' => 'CDE'])
            ->assertOk()->assertJsonPath('vehicle.code', 'CDE');
        $this->get('/result/' . $id)->assertOk()->assertSee('Laporan Kloter');
        $this->get('/riwayat/99999')->assertNotFound();
    }

    public function test_deleting_a_batch_removes_its_boxes_and_file(): void
    {
        $id = $this->upload('pagi.csv', "Jakarta,SA38,Dealer A,SMP-0001,45,4,one_day\n");
        $path = Upload::find($id)->filepath;
        $this->assertTrue(Box::where('upload_id', $id)->exists());
        Storage::disk('local')->assertExists($path);

        $this->delete('/riwayat/' . $id)->assertRedirect('/riwayat');

        $this->assertNull(Upload::find($id));
        $this->assertFalse(Box::where('upload_id', $id)->exists());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_product_master_page_search_and_import(): void
    {
        $this->get('/master-barang')->assertOk()->assertSee('SMP-0001');
        $this->get('/master-barang?q=Bemper')->assertOk()->assertSee('SMP-0005')->assertDontSee('SMP-0001');

        $csv = "Part Number,Name,Weight,No Karton,Qty per Box,Category,Location\nNEW-0001,Barang Baru,1.5,2,10,small,A01\nBAD-0001,Salah,0,2,10,small,\nSMP-0001,Filter Oli,0.30,2,20,small,G16\n";
        $file = UploadedFile::fake()->createWithContent('master.csv', $csv);

        $this->post('/master-barang/impor', ['product_file' => $file])->assertRedirect('/master-barang');

        $this->assertDatabaseHas('products', ['part_number' => 'NEW-0001', 'qty_per_box' => 10]);
        $this->assertDatabaseMissing('products', ['part_number' => 'BAD-0001']);
        $this->assertEqualsWithDelta(0.30, Product::where('part_number', 'SMP-0001')->value('weight_kg'), 1e-6);
    }

    public function test_reference_page_lists_vehicles_and_cartons(): void
    {
        $this->get('/referensi')->assertOk()->assertSee('Wing Box')->assertSee('Ukuran karton (25 jenis)');
    }
}
