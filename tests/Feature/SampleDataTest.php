<?php

namespace Tests\Feature;

use Database\Seeders\CartonSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Menjaga file sample di root proyek tetap valid dan berwarna (semua karton punya jalur). */
class SampleDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([VehicleSeeder::class, CartonSeeder::class, ProductSeeder::class]);
    }

    private function uploadSample(string $file, string $vehicle = 'CDD')
    {
        $upload = new UploadedFile(base_path($file), $file, 'text/csv', null, true);

        return $this->postJson('/upload', ['excel_file' => $upload, 'vehicle' => $vehicle])->assertOk();
    }

    public function test_sample_data_lengkap_is_clean_ready_and_uses_all_eight_routes(): void
    {
        $res = $this->uploadSample('sample_data_lengkap.csv');

        $this->assertSame([], $res->json('warnings'), 'Tidak boleh ada baris yang dilewati atau jalur kosong');
        $this->assertTrue($res->json('summary.ready'));
        $this->assertSame('Siap Dikirim', $res->json('summary.status'));
        $this->assertGreaterThanOrEqual(0.80, $res->json('summary.ratio'));
        $this->assertLessThanOrEqual(0.90, $res->json('summary.ratio'));

        $routes = collect($res->json('items'))->pluck('route')->unique()->sort()->values()->all();
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $routes);

        $opt = $this->postJson('/packing/optimize', ['upload_id' => $res->json('upload_id'), 'mode' => 'tetris'])->assertOk();
        $this->assertNotContains(null, array_column($opt->json('items'), 'route'));
        $this->assertGreaterThan(550, $opt->json('summary.loaded_count'));
    }

    public function test_original_sample_data_also_gets_routes(): void
    {
        $res = $this->uploadSample('sample_data.csv');

        $this->assertSame([], $res->json('warnings'));
        $this->assertNotContains(null, array_column($res->json('items'), 'route'));
    }
}
