<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_upload_returns_items_in_meters_and_status(): void
    {
        Storage::fake('local');
        $csv = "Dest,Code,Name,P,L,T,Status\nJakarta,C1,PT ABC,1000,500,300,4\n,,,,,,\nBandung,C2,PT X,0,500,300,4\nMedan,C3,PT Y,900,450,350,9\n";
        $file = UploadedFile::fake()->createWithContent('t.csv', $csv);

        $res = $this->postJson('/upload', ['excel_file' => $file]);

        $res->assertOk()->assertJson(['success' => true, 'ready' => false]);
        $this->assertCount(1, $res->json('items'));
        $this->assertEquals(1.0, $res->json('items.0.panjang'));
        $this->assertCount(2, $res->json('warnings'));
        $this->assertDatabaseCount('boxes', 1);
    }

    public function test_optimize_uses_meters(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('t.csv', "h\nJkt,C1,A,1000,500,300,4\n");
        $id = $this->postJson('/upload', ['excel_file' => $file])->json('upload_id');

        $res = $this->postJson('/packing/optimize', ['upload_id' => $id]);
        $res->assertOk();
        $this->assertCount(1, $res->json('items'));
        $this->assertEquals(1.0, $res->json('items.0.length'));
    }
}
