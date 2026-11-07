<?php

namespace Tests\Feature;

use App\Models\RouteSetting;
use App\Models\Upload;
use App\Models\Vehicle;
use Database\Seeders\CartonSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Satu jalur = satu truk khusus ke satu tujuan. Satu kloter berisi beberapa jalur dan
 * kesiapan kirim dinilai PER JALUR terhadap truk jalur itu. Butuh database.
 */
class RouteLoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([VehicleSeeder::class, CartonSeeder::class, ProductSeeder::class]);
    }

    private function uploadSample(string $file = 'sample_data_lengkap.csv'): int
    {
        $upload = new UploadedFile(base_path($file), $file, 'text/csv', null, true);

        return $this->postJson('/upload', ['excel_file' => $upload])->assertOk()->json('upload_id');
    }

    private function routes(int $id): \Illuminate\Support\Collection
    {
        return collect($this->getJson("/riwayat/{$id}/jalur")->assertOk()->json('routes'))->keyBy('route');
    }

    public function test_route_settings_are_created_with_destination_names(): void
    {
        $this->assertSame(8, RouteSetting::count());
        $this->assertSame('Jabodetabek', RouteSetting::where('route', 1)->value('name'));
        $this->assertSame('Same Day', RouteSetting::where('route', 8)->value('name'));
    }

    public function test_routes_endpoint_returns_name_truck_and_ratio_per_route(): void
    {
        $id = $this->uploadSample();
        $routes = $this->routes($id);

        $this->assertSame('Jabodetabek', $routes[1]['name']);
        $this->assertSame('CDD', $routes[1]['vehicle']['code']);           // truk default
        $this->assertEqualsWithDelta(16.72, $routes[1]['vehicle']['volume_m3'], 1e-6);
        $this->assertGreaterThan(0.8, $routes[1]['ratio']);
        $this->assertLessThan(0.8, $routes[3]['ratio']);                    // Jalur 3 belum cukup penuh (sekitar 53%)
        $this->assertSame('wait', $routes[3]['state']);
    }

    public function test_changing_a_route_truck_changes_that_route_status_only(): void
    {
        $id = $this->uploadSample();
        $this->assertSame('over', $this->routes($id)[4]['state']);          // Jalur 4 melebihi CDD

        // Jalur 4 diberi truk Wing Box (58 m³): muatan 17,8 m³ jadi kurang dari 80%
        $wing = Vehicle::where('code', 'WING_BOX')->value('id');
        $this->put('/pengaturan/jalur', ['routes' => [4 => ['name' => 'Jawa Timur', 'vehicle_id' => $wing]]])->assertRedirect();

        $routes = $this->routes($id);
        $this->assertSame('WING_BOX', $routes[4]['vehicle']['code']);
        $this->assertSame('wait', $routes[4]['state']);
        $this->assertSame('CDD', $routes[1]['vehicle']['code']);            // jalur lain tidak terpengaruh
        $this->assertSame('ready', $routes[1]['state']);
    }

    public function test_route_settings_update_validates_and_stores_names(): void
    {
        $this->put('/pengaturan/jalur', ['routes' => [2 => ['name' => 'Jawa Tengah dan DIY', 'vehicle_id' => 999999]]])
            ->assertSessionHasErrors('routes.2.vehicle_id');

        $this->put('/pengaturan/jalur', ['routes' => [2 => ['name' => 'Jawa Tengah dan DIY', 'vehicle_id' => '']]])->assertRedirect();
        $this->assertSame('Jawa Tengah dan DIY', RouteSetting::where('route', 2)->value('name'));
        $this->assertNull(RouteSetting::where('route', 2)->value('vehicle_id'));

        $this->get('/pengaturan/area')->assertOk()->assertSee('Jalur dan truk')->assertSee('Jawa Tengah dan DIY');
    }

    public function test_optimize_packs_only_the_cartons_of_the_requested_route_into_that_routes_truck(): void
    {
        $id = $this->uploadSample();

        $res = $this->postJson('/packing/optimize', ['upload_id' => $id, 'route' => '3'])->assertOk();

        $this->assertSame([3], array_values(array_unique(array_column($res->json('items'), 'route'))));
        $this->assertSame('Jalur 3', $res->json('route.label'));
        $this->assertSame('Jawa Barat', $res->json('route.name'));
        $this->assertSame('CDD', $res->json('vehicle.code'));
        $this->assertEquals($this->routes($id)[3]['cartons'], $res->json('demand.carton_count'));
    }

    public function test_truck_override_in_optimize_is_not_saved(): void
    {
        $id = $this->uploadSample();

        $this->postJson('/packing/optimize', ['upload_id' => $id, 'route' => '4', 'vehicle' => 'WING_BOX'])
            ->assertOk()->assertJsonPath('vehicle.code', 'WING_BOX')->assertJsonPath('demand.state', 'wait');

        $this->assertSame('over', $this->routes($id)[4]['state']);          // pengaturan jalur tidak berubah
    }

    public function test_cartons_without_a_route_are_grouped_and_flagged(): void
    {
        $header = "Cargo Destination,Customer Code,Customer Name,Part Number,Qty,Status,Service Type\n";
        $file = UploadedFile::fake()->createWithContent('o.csv', $header . "Jakarta,SA38,A,SMP-0001,45,4,one_day\nPontianak,ZQ01,B,SMP-0001,45,4,one_day\n");
        $id = $this->postJson('/upload', ['excel_file' => $file])->assertOk()->json('upload_id');

        $res = $this->getJson("/riwayat/{$id}/jalur")->json();

        $this->assertSame(1, $res['overall']['active']);                    // hanya jalur bernomor yang dihitung aktif
        $this->assertSame(3, $res['overall']['unrouted_cartons']);
        $none = collect($res['routes'])->firstWhere('key', 'none');
        $this->assertSame('Tanpa jalur', $none['label']);

        $opt = $this->postJson('/packing/optimize', ['upload_id' => $id, 'route' => 'none'])->assertOk();
        $this->assertSame([null], array_values(array_unique(array_column($opt->json('items'), 'route'))));
    }

    public function test_load_plan_page_lists_dealers_and_totals_for_one_route(): void
    {
        $id = $this->uploadSample();

        $this->get("/riwayat/{$id}/jalur/1/rencana-muat")->assertOk()
            ->assertSee('Rencana Muat Jalur 1')
            ->assertSee('Jabodetabek')
            ->assertSee('SA38')->assertSee('SA12')
            ->assertDontSee('SC07')                                         // dealer jalur lain tidak ikut
            ->assertSee('Disiapkan oleh');

        $this->get("/riwayat/{$id}/jalur/6/rencana-muat")->assertNotFound();    // jalur tanpa karton
        $this->get("/riwayat/{$id}/jalur/9/rencana-muat")->assertNotFound();
        $this->get("/riwayat/{$id}/jalur/none/rencana-muat")->assertNotFound();
    }

    public function test_dashboard_counts_routes_not_batches(): void
    {
        $this->uploadSample();   // 3 jalur siap, 2 belum siap, 1 melebihi

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/dash-stat__value">3<\/div>\s*<div class="dash-stat__label">Jalur siap dikirim hari ini/', $html);
        $this->assertMatchesRegularExpression('/dash-stat__value">3<\/div>\s*<div class="dash-stat__label">Jalur belum siap atau melebihi/', $html);
    }

    public function test_batch_detail_page_has_route_cards_container_and_no_global_vehicle_picker(): void
    {
        $id = $this->uploadSample();

        $this->get("/riwayat/{$id}")->assertOk()
            ->assertSee('id="routeGrid"', false)
            ->assertSee('Jalur dalam kloter ini')
            ->assertDontSee('name="vehicle"', false);                        // truk diatur per jalur, bukan satu untuk kloter
        $this->get('/packing')->assertOk()->assertDontSee('vehicle-card', false);
    }
}
