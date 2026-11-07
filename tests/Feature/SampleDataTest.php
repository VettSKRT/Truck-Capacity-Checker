<?php

namespace Tests\Feature;

use Database\Seeders\CartonSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Menjaga file sample di root proyek tetap valid dan memperlihatkan semua kondisi jalur. */
class SampleDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([VehicleSeeder::class, CartonSeeder::class, ProductSeeder::class]);
    }

    private function uploadSample(string $file)
    {
        $upload = new UploadedFile(base_path($file), $file, 'text/csv', null, true);

        return $this->postJson('/upload', ['excel_file' => $upload])->assertOk();
    }

    public function test_sample_data_lengkap_shows_ready_wait_and_over_routes(): void
    {
        $res = $this->uploadSample('sample_data_lengkap.csv');

        $this->assertSame([], $res->json('warnings'), 'Tidak boleh ada baris yang dilewati atau jalur kosong');

        $byRoute = collect($res->json('routes'))->keyBy('route');
        $this->assertSame([1, 2, 3, 4, 5, 8], $byRoute->keys()->all());   // jalur 6 dan 7 tidak dipakai di sample ini

        $this->assertSame('ready', $byRoute[1]['state']);
        $this->assertSame('ready', $byRoute[2]['state']);
        $this->assertSame('weight', $byRoute[2]['binding']);              // jalur 2 dibatasi BERAT, bukan volume
        $this->assertLessThan(0.5, $byRoute[2]['volume_ratio']);
        $this->assertSame('wait', $byRoute[3]['state']);
        $this->assertSame('over', $byRoute[4]['state']);                  // jalur 4 melebihi truk
        $this->assertSame('ready', $byRoute[5]['state']);
        $this->assertSame('wait', $byRoute[8]['state']);                  // Same Day (SH09)

        $this->assertSame('over', $res->json('overall.state'));
        $this->assertSame(3, $res->json('overall.ready'));
        $this->assertSame(2, $res->json('overall.wait'));
        $this->assertSame(1, $res->json('overall.over'));
        $this->assertSame(0, $res->json('overall.unrouted_cartons'));
    }

    public function test_every_ready_route_of_the_sample_packs_into_its_truck_in_3d(): void
    {
        $id = $this->uploadSample('sample_data_lengkap.csv')->json('upload_id');

        foreach ([1, 2, 5] as $route) {
            $opt = $this->postJson('/packing/optimize', ['upload_id' => $id, 'route' => (string) $route, 'mode' => 'tetris'])->assertOk();
            $this->assertGreaterThan(300, $opt->json('summary.loaded_count'), "Jalur {$route}");
            $this->assertSame([$route], array_values(array_unique(array_column($opt->json('items'), 'route'))), 'Satu truk hanya memuat satu jalur');
        }
    }

    public function test_sample_data_kapal_goes_to_the_port_route_without_warnings(): void
    {
        $res = $this->uploadSample('sample_data_kapal.csv');

        $this->assertSame([], $res->json('warnings'));
        $this->assertSame([6], array_column($res->json('routes'), 'route'));   // luar pulau dan Aceh lewat jalur pelabuhan
    }
}
