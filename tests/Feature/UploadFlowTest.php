<?php

namespace Tests\Feature;

use Database\Seeders\CartonSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Butuh driver SQLite (lihat phpunit.xml). */
class UploadFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([VehicleSeeder::class, CartonSeeder::class, ProductSeeder::class]);
    }

    private function csv(string $body): UploadedFile
    {
        $header = "Cargo Destination,Customer Code,Customer Name,Part Number,Qty,Status,Service Type\n";

        return UploadedFile::fake()->createWithContent('order.csv', $header . $body);
    }

    public function test_qty_is_split_into_unit_and_receh_boxes(): void
    {
        // SMP-0001: 20 pcs/box, qty 45 => 2 box unit + 1 receh (5 pcs)
        $res = $this->postJson('/upload', [
            'excel_file' => $this->csv("Jakarta,SA38,Dealer A,SMP-0001,45,4,one_day\n"),
            'vehicle' => 'CDD',
        ])->assertOk();

        $this->assertCount(3, $res->json('items'));
        $this->assertDatabaseHas('boxes', ['part_number' => 'SMP-0001', 'packing_type' => 'unit', 'box_count' => 2, 'qty' => 40]);
        $this->assertDatabaseHas('boxes', ['part_number' => 'SMP-0001', 'packing_type' => 'receh', 'box_count' => 1, 'qty' => 5]);
        // berat total = 45 pcs * 0.25 kg
        $this->assertEqualsWithDelta(11.25, $res->json('summary.total_weight'), 1e-6);
    }

    public function test_only_cargo_ready_counts_and_invalid_rows_are_reported(): void
    {
        $res = $this->postJson('/upload', [
            'excel_file' => $this->csv(
                "Jakarta,SA38,A,SMP-0005,2,4,one_day\n" .   // bemper x2, cargo ready
                "Jakarta,SA38,A,SMP-0005,2,3,one_day\n" .   // belum ready -> tidak dihitung
                "Jakarta,SA38,A,NOPE-1,2,4,one_day\n"       // tidak ada di master
            ),
            'vehicle' => 'CDE',
        ])->assertOk();

        // 2 bemper * 4.5 kg = 9 kg (hanya Status 4)
        $this->assertEqualsWithDelta(9.0, $res->json('summary.total_weight'), 1e-6);
        $this->assertNotEmpty(array_filter($res->json('warnings'), fn ($w) => str_contains($w, 'NOPE-1')));
    }

    public function test_upload_without_valid_rows_is_rejected(): void
    {
        $this->postJson('/upload', [
            'excel_file' => $this->csv("Jakarta,SA38,A,NOPE-1,2,4,one_day\n"),
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_optimize_supports_ratio_and_tetris_modes(): void
    {
        $id = $this->postJson('/upload', [
            'excel_file' => $this->csv("Jakarta,SA38,A,SMP-0001,45,4,one_day\n"),
            'vehicle' => 'CDD',
        ])->json('upload_id');

        $tetris = $this->postJson('/packing/optimize', ['upload_id' => $id, 'mode' => 'tetris'])->assertOk();
        $this->assertSame('tetris', $tetris->json('mode'));
        $this->assertCount(3, $tetris->json('items'));
        $this->assertNotNull($tetris->json('items.0.z'));
        $this->assertSame('CDD', $tetris->json('vehicle.code'));

        $ratio = $this->postJson('/packing/optimize', ['upload_id' => $id, 'mode' => 'ratio'])->assertOk();
        $this->assertSame('ratio', $ratio->json('mode'));
        $this->assertNull($ratio->json('items.0.x'));

        $this->postJson('/packing/optimize', ['upload_id' => $id, 'mode' => 'bogus'])->assertStatus(422);
    }

    public function test_optimize_expands_box_count_and_uses_meters(): void
    {
        $id = $this->postJson('/upload', [
            'excel_file' => $this->csv("Jakarta,SA38,A,SMP-0003,50,4,one_day\n"),
        ])->json('upload_id');

        $res = $this->postJson('/packing/optimize', ['upload_id' => $id])->assertOk();

        $this->assertCount(1, $res->json('items'));
        $this->assertEqualsWithDelta(0.12, $res->json('items.0.length'), 1e-9);
    }
}
