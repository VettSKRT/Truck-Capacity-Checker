<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\Shipment;
use App\Models\Upload;
use App\Models\Vehicle;
use Database\Seeders\CartonSeeder;
use Database\Seeders\DealerSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Penjadwalan: harian vs kapal, jadwal kapal manual, dan MOQ per dealer. Butuh database. */
class ScheduleFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([VehicleSeeder::class, CartonSeeder::class, ProductSeeder::class, DealerSeeder::class]);
    }

    private function uploadSample(string $file, string $vehicle = 'IMPOR'): int
    {
        $upload = new UploadedFile(base_path($file), $file, 'text/csv', null, true);

        return $this->postJson('/upload', ['excel_file' => $upload, 'vehicle' => $vehicle])->assertOk()->json('upload_id');
    }

    private function newShipment(array $overrides = []): Shipment
    {
        return Shipment::create($overrides + [
            'departs_on' => now()->addDays(5)->toDateString(),
            'destination' => 'Pelabuhan Banjarmasin',
            'vehicle_id' => Vehicle::where('code', 'IMPOR')->value('id'),
            'is_tentative' => true,
        ]);
    }

    public function test_dealers_are_registered_automatically_from_uploads_keeping_existing_moq(): void
    {
        $this->uploadSample('sample_data_kapal.csv');

        $this->assertDatabaseHas('dealers', ['code' => 'SJ21', 'name' => 'Dealer Pontianak', 'moq_m3' => null]);
        $this->assertEquals(0.5, Dealer::where('code', 'SJ10')->value('moq_m3')); // MOQ seeder tidak tertimpa
    }

    public function test_batch_with_outer_island_dealers_needs_a_ship_while_java_batch_is_daily(): void
    {
        $kapal = $this->uploadSample('sample_data_kapal.csv');
        $harian = $this->uploadSample('sample_data_lengkap.csv', 'CDD');

        $this->get("/riwayat/{$kapal}")->assertOk()->assertSee('Jadwal kapal')->assertSee('belum ditautkan ke jadwal kapal');
        $this->get("/riwayat/{$harian}")->assertOk()->assertSee('Pengiriman harian')->assertSee('Rencana kirim harian');

        $this->get('/penjadwalan')->assertOk()
            ->assertSee('Kloter menunggu jadwal kapal (1)')
            ->assertSee('Pengiriman harian (1)');
    }

    public function test_same_day_batch_is_planned_today_and_one_day_batch_tomorrow(): void
    {
        $service = app(\App\Services\ScheduleService::class);

        $lengkap = Upload::find($this->uploadSample('sample_data_lengkap.csv', 'CDD')); // ada baris same_day
        $kapal = Upload::find($this->uploadSample('sample_data_kapal.csv'));           // semua one_day

        $this->assertTrue($service->analyzeUpload($lengkap)['send_date']->isToday());
        // kloter kapal bukan harian, jadi tidak punya rencana kirim harian
        $this->assertNull($service->analyzeUpload($kapal)['send_date']);
    }

    public function test_shipment_page_evaluates_moq_per_dealer_and_flips_to_ok_when_met(): void
    {
        $upload = $this->uploadSample('sample_data_kapal.csv');
        $shipment = $this->newShipment();

        $this->post("/penjadwalan/{$shipment->id}/kloter", ['upload_id' => $upload])->assertRedirect();
        $this->assertSame($shipment->id, Upload::find($upload)->shipment_id);

        // SJ10 memenuhi, SK05 kurang, SL02 memenuhi, SJ21 belum punya MOQ
        $page = $this->get("/penjadwalan/{$shipment->id}")->assertOk();
        $page->assertSee('Memenuhi')->assertSee('Kurang 0,808 m³', false)->assertSee('MOQ belum diatur');
        $page->assertSee('1 dealer belum memenuhi MOQ'); // SJ21 tanpa MOQ tidak boleh ikut terhitung

        // Isi manual: turunkan MOQ SK05 dan beri MOQ SJ21
        $this->post('/dealer/moq', ['moq' => ['SK05' => '0.15', 'SJ21' => '0,2']])->assertRedirect();
        $this->assertEquals(0.15, Dealer::where('code', 'SK05')->value('moq_m3'));
        $this->assertEquals(0.2, Dealer::where('code', 'SJ21')->value('moq_m3'));

        $this->get("/penjadwalan/{$shipment->id}")->assertOk()->assertSee('MOQ terpenuhi untuk semua dealer');
    }

    public function test_batch_can_be_linked_and_unlinked_from_its_own_page(): void
    {
        $upload = $this->uploadSample('sample_data_kapal.csv');
        $shipment = $this->newShipment();

        $this->post("/riwayat/{$upload}/jadwal", ['shipment_id' => $shipment->id])->assertRedirect();
        $this->assertSame($shipment->id, Upload::find($upload)->shipment_id);
        $this->get("/riwayat/{$upload}")->assertOk()->assertSee($shipment->code);

        $this->post("/riwayat/{$upload}/jadwal", ['shipment_id' => null])->assertRedirect();
        $this->assertNull(Upload::find($upload)->shipment_id);
    }

    public function test_deleting_a_shipment_keeps_batches_and_releases_them(): void
    {
        $upload = $this->uploadSample('sample_data_kapal.csv');
        $shipment = $this->newShipment();
        Upload::find($upload)->update(['shipment_id' => $shipment->id]);

        $this->delete("/penjadwalan/{$shipment->id}")->assertRedirect('/penjadwalan');

        $this->assertNull(Shipment::find($shipment->id));
        $this->assertNotNull(Upload::find($upload));
        $this->assertNull(Upload::find($upload)->shipment_id);
    }

    public function test_cancelled_shipment_does_not_accept_new_batches(): void
    {
        $upload = $this->uploadSample('sample_data_kapal.csv');
        $shipment = $this->newShipment(['status' => Shipment::CANCELLED]);

        $this->post("/penjadwalan/{$shipment->id}/kloter", ['upload_id' => $upload])->assertSessionHasErrors('upload_id');
        $this->assertNull(Upload::find($upload)->shipment_id);
    }

    public function test_creating_and_editing_a_shipment_validates_input(): void
    {
        $this->post('/penjadwalan', ['departs_on' => '', 'destination' => '', 'status' => 'planned'])
            ->assertSessionHasErrors(['departs_on', 'destination']);

        $this->post('/penjadwalan', [
            'departs_on' => now()->addDays(3)->toDateString(), 'destination' => 'Makassar',
            'vehicle_id' => Vehicle::where('code', 'IMPOR')->value('id'), 'status' => 'planned', 'is_tentative' => '1',
        ])->assertRedirect();

        $shipment = Shipment::firstWhere('destination', 'Makassar');
        $this->assertTrue($shipment->is_tentative);

        $this->put("/penjadwalan/{$shipment->id}", [
            'departs_on' => now()->addDays(10)->toDateString(), 'destination' => 'Makassar Baru', 'status' => 'planned',
        ])->assertRedirect();
        $this->assertFalse($shipment->fresh()->is_tentative);   // checkbox tidak dicentang
        $this->assertSame('Makassar Baru', $shipment->fresh()->destination);
    }

    public function test_scheduling_dashboard_and_dealer_pages_render(): void
    {
        $this->uploadSample('sample_data_kapal.csv');
        $this->newShipment();

        $this->get('/penjadwalan')->assertOk()->assertSee('Jadwal kapal mendatang');
        $this->get('/penjadwalan/baru')->assertOk();
        $this->get('/')->assertOk()->assertSee('Jadwal kapal terdekat')->assertSee('belum ditautkan ke jadwal kapal');
        $this->get('/dealer')->assertOk()->assertSee('SK05');
        $this->get('/dealer?moq=unset')->assertOk()->assertSee('SJ21')->assertDontSee('SK05');
    }

    public function test_negative_moq_is_rejected(): void
    {
        $this->uploadSample('sample_data_kapal.csv');

        $this->post('/dealer/moq', ['moq' => ['SK05' => '-1']])->assertSessionHasErrors('moq.SK05');
        $this->assertEquals(1.0, Dealer::where('code', 'SK05')->value('moq_m3'));
    }
}
