<?php

namespace Tests\Feature;

use App\Models\Box;
use Database\Seeders\CartonSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Butuh database (lihat phpunit.xml). */
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

    private function route(array $json, int $number): array
    {
        return collect($json['routes'])->firstWhere('route', $number);
    }

    public function test_qty_is_split_into_unit_and_receh_boxes(): void
    {
        // SMP-0001: 20 pcs/box, qty 45 => 2 box unit + 1 receh (5 pcs). SA38 -> Jalur 1
        $res = $this->postJson('/upload', ['excel_file' => $this->csv("Jakarta,SA38,Dealer A,SMP-0001,45,4,one_day\n")])->assertOk();

        $this->assertDatabaseHas('boxes', ['part_number' => 'SMP-0001', 'packing_type' => 'unit', 'box_count' => 2, 'qty' => 40]);
        $this->assertDatabaseHas('boxes', ['part_number' => 'SMP-0001', 'packing_type' => 'receh', 'box_count' => 1, 'qty' => 5]);

        $j1 = $this->route($res->json(), 1);
        $this->assertSame(3, $j1['cartons']);
        $this->assertEqualsWithDelta(11.25, $j1['weight'], 1e-6);   // 45 pcs * 0,25 kg
    }

    public function test_only_cargo_ready_counts_toward_the_load_and_invalid_rows_are_reported(): void
    {
        $res = $this->postJson('/upload', ['excel_file' => $this->csv(
            "Jakarta,SA38,A,SMP-0005,2,4,one_day\n" .   // bemper x2, cargo ready
            "Jakarta,SA38,A,SMP-0005,2,3,one_day\n" .   // belum ready -> tidak dihitung ke muatan
            "Jakarta,SA38,A,NOPE-1,2,4,one_day\n"       // tidak ada di master
        )])->assertOk();

        $j1 = $this->route($res->json(), 1);
        $this->assertEqualsWithDelta(9.0, $j1['weight'], 1e-6);    // 2 bemper * 4,5 kg
        $this->assertSame(2, $j1['cartons']);
        $this->assertSame(2, $j1['pending']);                      // status 3 tercatat tetapi belum masuk muatan
        $this->assertNotEmpty(array_filter($res->json('warnings'), fn ($w) => str_contains($w, 'NOPE-1')));
    }

    public function test_upload_without_valid_rows_is_rejected(): void
    {
        $this->postJson('/upload', ['excel_file' => $this->csv("Jakarta,SA38,A,NOPE-1,2,4,one_day\n")])
            ->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_one_upload_holds_several_routes_each_judged_on_its_own_truck(): void
    {
        $res = $this->postJson('/upload', ['excel_file' => $this->csv(
            "Jakarta,SA38,A,SMP-0001,45,4,one_day\n" .      // Jalur 1
            "Semarang,SC07,B,SMP-0001,45,4,one_day\n" .     // Jalur 2
            "Bandung,SB21,C,SMP-0001,45,4,one_day\n"        // Jalur 3
        )])->assertOk();

        $this->assertSame([1, 2, 3], array_column($res->json('routes'), 'route'));
        $this->assertSame(3, $res->json('overall.active'));
        $this->assertSame('wait', $res->json('overall.state'));
    }

    public function test_optimize_supports_ratio_and_tetris_modes_for_one_route(): void
    {
        $id = $this->postJson('/upload', ['excel_file' => $this->csv("Jakarta,SA38,A,SMP-0001,45,4,one_day\n")])->json('upload_id');

        $tetris = $this->postJson('/packing/optimize', ['upload_id' => $id, 'route' => '1', 'mode' => 'tetris'])->assertOk();
        $this->assertSame('tetris', $tetris->json('mode'));
        $this->assertCount(3, $tetris->json('items'));
        $this->assertNotNull($tetris->json('items.0.z'));
        $this->assertSame('CDD', $tetris->json('vehicle.code'));   // truk default jalur
        $this->assertSame('Jalur 1', $tetris->json('route.label'));

        $ratio = $this->postJson('/packing/optimize', ['upload_id' => $id, 'route' => '1', 'mode' => 'ratio'])->assertOk();
        $this->assertSame('ratio', $ratio->json('mode'));
        $this->assertNull($ratio->json('items.0.x'));

        $this->postJson('/packing/optimize', ['upload_id' => $id, 'route' => '1', 'mode' => 'bogus'])->assertStatus(422);
        $this->postJson('/packing/optimize', ['upload_id' => $id, 'route' => '9'])->assertStatus(422);
    }

    public function test_optimize_expands_box_count_and_uses_meters(): void
    {
        $id = $this->postJson('/upload', ['excel_file' => $this->csv("Jakarta,SA38,A,SMP-0003,50,4,one_day\n")])->json('upload_id');

        $res = $this->postJson('/packing/optimize', ['upload_id' => $id, 'route' => '1'])->assertOk();

        $this->assertCount(1, $res->json('items'));
        $this->assertEqualsWithDelta(0.12, $res->json('items.0.length'), 1e-9);
        $this->assertSame(1, Box::where('upload_id', $id)->count());
    }
}
