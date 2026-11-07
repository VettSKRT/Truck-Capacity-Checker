<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Box;
use App\Models\Dealer;
use Database\Seeders\CartonSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Pengaturan area (jalur dan wilayah) dan dealer Same Day lewat tampilan. Butuh database. */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([VehicleSeeder::class, CartonSeeder::class, ProductSeeder::class]);
    }

    private function uploadCsv(string $body): int
    {
        $header = "Cargo Destination,Customer Code,Customer Name,Part Number,Qty,Status,Service Type\n";
        $file = UploadedFile::fake()->createWithContent('o.csv', $header . $body);

        return $this->postJson('/upload', ['excel_file' => $file, 'vehicle' => 'CDD'])->assertOk()->json('upload_id');
    }

    private function routeOf(int $uploadId, string $dealer): ?int
    {
        return Box::where('upload_id', $uploadId)->where('customer_code', $dealer)->value('route');
    }

    public function test_migration_copies_config_defaults_into_the_database(): void
    {
        $this->assertSame(1, Area::where('code', 'SA')->value('route'));
        $this->assertSame('luar_pulau', Area::where('code', 'SJ')->value('region'));
        $this->assertTrue(Dealer::where('code', 'SH09')->value('same_day'));
    }

    public function test_area_settings_page_lists_areas_and_unregistered_area_codes(): void
    {
        $this->uploadCsv("Jakarta,ZQ01,Dealer Baru,SMP-0001,45,4,one_day\n");

        $this->get('/pengaturan/area')->assertOk()
            ->assertSee('Daftar area')
            ->assertSee('kode area dari data dealer belum terdaftar')
            ->assertSee('ZQ');
    }

    public function test_detect_adds_unregistered_areas_with_empty_route_and_region(): void
    {
        $this->uploadCsv("Jakarta,ZQ01,Dealer Baru,SMP-0001,45,4,one_day\n");

        $this->post('/pengaturan/area/deteksi')->assertRedirect();

        $area = Area::where('code', 'ZQ')->first();
        $this->assertNotNull($area);
        $this->assertNull($area->route);
        $this->assertNull($area->region);
    }

    public function test_saving_area_settings_recolors_existing_batches_immediately(): void
    {
        $id = $this->uploadCsv("Jakarta,ZQ01,Dealer Baru,SMP-0001,45,4,one_day\n");
        $this->assertNull($this->routeOf($id, 'ZQ01'));            // area belum diatur -> abu-abu

        $this->post('/pengaturan/area', ['code' => 'zq', 'name' => 'Area Uji', 'route' => 3, 'region' => 'jawa'])->assertRedirect();
        $this->assertSame(3, $this->routeOf($id, 'ZQ01'));         // kloter lama ikut berwarna

        $area = Area::firstWhere('code', 'ZQ');                    // kode disimpan huruf besar
        $this->put('/pengaturan/area', ['areas' => [$area->id => ['name' => 'Area Uji', 'route' => 6, 'region' => 'sumatra']]])->assertRedirect();
        $this->assertSame(6, $this->routeOf($id, 'ZQ01'));
        $this->assertSame('sumatra', $area->fresh()->region);

        $this->delete("/pengaturan/area/{$area->id}")->assertRedirect('/pengaturan/area');
        $this->assertNull($this->routeOf($id, 'ZQ01'));            // area dihapus -> kembali abu-abu
    }

    public function test_area_settings_change_the_schedule_type_of_a_batch(): void
    {
        $id = $this->uploadCsv("Pontianak,ZQ01,Dealer Baru,SMP-0001,45,4,one_day\n");
        $this->get("/riwayat/{$id}")->assertOk()->assertSee('Wilayah belum diatur');

        $this->post('/pengaturan/area', ['code' => 'ZQ', 'route' => 2, 'region' => 'luar_pulau'])->assertRedirect();

        $this->get("/riwayat/{$id}")->assertOk()->assertSee('Jadwal kapal')->assertDontSee('Wilayah belum diatur');
    }

    public function test_area_validation_rejects_bad_input(): void
    {
        $this->post('/pengaturan/area', ['code' => 'SA', 'route' => 1])->assertSessionHasErrors('code');          // sudah ada
        $this->post('/pengaturan/area', ['code' => 'S1', 'route' => 1])->assertSessionHasErrors('code');          // bukan huruf
        $this->post('/pengaturan/area', ['code' => 'ZX', 'route' => 8])->assertSessionHasErrors('route');         // jalur 8 khusus same day
        $this->post('/pengaturan/area', ['code' => 'ZX', 'region' => 'bulan'])->assertSessionHasErrors('region');
        $this->assertNull(Area::firstWhere('code', 'ZX'));
    }

    public function test_marking_a_dealer_same_day_moves_its_same_day_rows_to_route_8_and_back(): void
    {
        $id = $this->uploadCsv(
            "Jakarta,SA38,Dealer A,SMP-0001,45,4,same_day\nJakarta,SA38,Dealer A,SMP-0002,10,4,one_day\n"
        );
        $this->assertSame(1, $this->routeOf($id, 'SA38'));

        $this->post('/dealer/moq', ['moq' => ['SA38' => ''], 'same_day' => ['SA38' => '1']])->assertRedirect();
        $this->assertTrue(Dealer::firstWhere('code', 'SA38')->same_day);

        $routes = Box::where('upload_id', $id)->orderBy('id')->pluck('route', 'service_type')->all();
        $this->assertSame(8, $routes['same_day']);   // Same Day -> Jalur 8
        $this->assertSame(1, $routes['one_day']);    // One Day tetap jalur area

        $this->post('/dealer/moq', ['moq' => ['SA38' => '']])->assertRedirect();   // centang dilepas
        $this->assertFalse(Dealer::firstWhere('code', 'SA38')->same_day);
        $this->assertSame(1, Box::where('upload_id', $id)->where('service_type', 'same_day')->value('route'));
    }

    public function test_dealers_can_be_added_manually_with_validation(): void
    {
        $this->post('/dealer', ['code' => 'sa77', 'name' => 'Dealer Manual', 'same_day' => '1'])->assertRedirect('/dealer');
        $this->assertTrue(Dealer::firstWhere('code', 'SA77')->same_day);

        $this->post('/dealer', ['code' => 'SA77'])->assertSessionHasErrors('code');        // duplikat
        $this->post('/dealer', ['code' => '38SA'])->assertSessionHasErrors('code');        // format salah
        $this->get('/dealer?moq=sameday')->assertOk()->assertSee('SA77');
    }

    public function test_settings_pages_render(): void
    {
        $this->get('/pengaturan/area')->assertOk()->assertSee('Area dan Jalur');
        $this->get('/dealer')->assertOk()->assertSee('Tambah dealer')->assertSee('Same Day');
    }
}
