<?php

namespace Tests\Feature;

use App\Models\Upload;
use Database\Seeders\CartonSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Aset tampilan tetap ringan, dan aksi yang berdampak besar meminta konfirmasi lewat modal (bukan confirm() bawaan). */
class UiAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([VehicleSeeder::class, CartonSeeder::class, ProductSeeder::class]);
    }

    private function upload(): int
    {
        $csv = "Cargo Destination,Customer Code,Customer Name,Part Number,Qty,Status,Service Type\nJakarta,SA38,Dealer A,SMP-0001,45,4,one_day\n";

        return $this->postJson('/upload', ['excel_file' => UploadedFile::fake()->createWithContent('pagi.csv', $csv)])
            ->assertOk()->json('upload_id');
    }

    public function test_heavy_scripts_are_not_loaded_up_front(): void
    {
        $id = $this->upload();

        foreach (['/', '/packing', "/riwayat/{$id}", '/riwayat', '/dealer'] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('bootstrap.bundle', false)          // tab memakai public/js/app.js
                ->assertDontSee('cdn.plot.ly/plotly-', false)        // Plotly tidak dipasang sebagai <script>
                ->assertSee('js/app.js', false)
                ->assertSee('js/controls.js', false)               // dropdown, kalender, dan pilih file kustom
                ->assertSee('id="confirmDialog"', false)
                ->assertSee('id="pageLoader"', false);
        }

        // Dashboard memakai grafik SVG sendiri
        $this->get('/')->assertSee('data-area-chart', false)->assertSee('js/area-chart.js', false);
        // Halaman 3D memuat bundel gl3d dari packing.js saat dibutuhkan
        $this->assertStringContainsString('plotly-gl3d-2.35.2.min.js', file_get_contents(public_path('js/packing.js')));
        $this->get("/riwayat/{$id}")->assertSee('id="plotLoader"', false)->assertSee('is-skeleton', false)
            ->assertSee('data-label="CDD (ban belakang double)"', false)       // opsi truk: nama + keterangan kapasitas
            ->assertSee('m³ · 6.000 kg', false);
    }

    public function test_destructive_actions_use_the_confirmation_modal(): void
    {
        $id = $this->upload();
        $code = Upload::find($id)->code;

        $this->get('/riwayat')->assertOk()
            ->assertSee('data-confirm-title="Hapus kloter ' . $code . '?"', false)
            ->assertDontSee('return confirm(', false);

        $this->get('/pengaturan/area')->assertOk()
            ->assertSee('data-confirm-title="Simpan pengaturan jalur?"', false)
            ->assertSee('data-confirm-title="Simpan perubahan area?"', false)
            ->assertDontSee('return confirm(', false);

        $this->get('/master-barang')->assertOk()->assertSee('data-confirm-title="Impor master barang?"', false);
    }
}
