<?php

namespace Tests\Unit;

use App\Services\LoadCalculator;
use App\Services\PackingService;
use PHPUnit\Framework\TestCase;

class PackingServiceTest extends TestCase
{
    private function service(): PackingService
    {
        return new PackingService(new LoadCalculator(0.8));
    }

    private function vehicle(int $l, int $w, int $h, int $kg): array
    {
        return [
            'length_mm' => $l, 'width_mm' => $w, 'height_mm' => $h,
            'max_payload_kg' => $kg, 'volume_m3' => $l * $w * $h / 1e9,
        ];
    }

    private function unit(int $id, int $l, int $w, int $h, float $kg, string $service = 'one_day'): array
    {
        return ['id' => $id, 'l' => $l, 'w' => $w, 'h' => $h, 'weight' => $kg, 'status' => 4, 'route' => null, 'service_type' => $service];
    }

    /** Pastikan semua penempatan valid: di dalam kendaraan, tidak bertabrakan, dan tertopang penuh. */
    private function assertValidPlacement(array $loaded, array $v): void
    {
        foreach ($loaded as $i => $a) {
            $this->assertGreaterThanOrEqual(0, $a['x']);
            $this->assertGreaterThanOrEqual(0, $a['y']);
            $this->assertGreaterThanOrEqual(0, $a['z']);
            $this->assertLessThanOrEqual($v['length_mm'], $a['x'] + $a['l']);
            $this->assertLessThanOrEqual($v['width_mm'], $a['y'] + $a['w']);
            $this->assertLessThanOrEqual($v['height_mm'], $a['z'] + $a['h']);

            foreach ($loaded as $j => $b) {
                if ($j <= $i) {
                    continue;
                }
                $overlap = $a['x'] < $b['x'] + $b['l'] && $b['x'] < $a['x'] + $a['l']
                    && $a['y'] < $b['y'] + $b['w'] && $b['y'] < $a['y'] + $a['w']
                    && $a['z'] < $b['z'] + $b['h'] && $b['z'] < $a['z'] + $a['h'];
                $this->assertFalse($overlap, "Unit {$i} dan {$j} bertabrakan");
            }

            if ($a['z'] > 0) {
                // Harus ada karton tepat di bawah yang menopang seluruh alas, dan lebih berat/sama
                $supported = false;
                foreach ($loaded as $b) {
                    if ($b['z'] + $b['h'] === $a['z']
                        && $b['x'] <= $a['x'] && $b['x'] + $b['l'] >= $a['x'] + $a['l']
                        && $b['y'] <= $a['y'] && $b['y'] + $b['w'] >= $a['y'] + $a['w']) {
                        $supported = true;
                        $this->assertGreaterThanOrEqual($a['weight'] - 1e-9, $b['weight'], 'Yang ringan harus di atas');
                    }
                }
                $this->assertTrue($supported, "Unit {$i} melayang / tidak tertopang penuh");
            }
        }
    }

    public function test_identical_cartons_stack_without_gaps(): void
    {
        // Lantai 2000x1000 -> 2 stack, tinggi 1000 -> 2 karton per stack = 4 karton
        $v = $this->vehicle(2000, 1000, 1000, 1000);
        $units = [];
        for ($i = 1; $i <= 5; $i++) {
            $units[] = $this->unit($i, 1000, 1000, 500, 10);
        }

        $r = $this->service()->pack($units, $v, 'tetris');

        $this->assertSame(4, $r['summary']['loaded_count']);
        $this->assertSame(1, $r['summary']['unloaded_count']);
        // Kendaraan tepat penuh secara volume (4 x 0,5 = 2 m³), jadi karton ke-5 ditolak karena volume
        $this->assertSame(['volume' => 1], $r['summary']['unloaded_reasons']);
        $this->assertSame(2, $r['summary']['stack_count']);
        $this->assertEqualsWithDelta(1.0, $r['summary']['space_utilization'], 1e-9);
        $this->assertValidPlacement($r['loaded'], $v);
    }

    public function test_unit_is_rejected_for_space_when_floor_is_blocked_but_volume_remains(): void
    {
        // Lantai 1000x1000: dua karton 600x600 tidak bisa berdampingan, volume total masih < kapasitas
        $v = $this->vehicle(1000, 1000, 1000, 1000);
        $units = [
            $this->unit(1, 600, 600, 1000, 10),
            $this->unit(2, 600, 600, 1000, 9),
        ];

        $r = $this->service()->pack($units, $v, 'tetris');

        $this->assertSame(1, $r['summary']['loaded_count']);
        $this->assertSame(['space' => 1], $r['summary']['unloaded_reasons']);
    }

    public function test_heavy_goes_below_light_regardless_of_input_order(): void
    {
        $v = $this->vehicle(500, 500, 1000, 1000);
        $units = [
            $this->unit(1, 500, 500, 500, 5),    // ringan, datang lebih dulu
            $this->unit(2, 500, 500, 500, 20),   // berat
        ];

        $r = $this->service()->pack($units, $v, 'tetris');

        $byId = array_column($r['loaded'], null, 'id');
        $this->assertSame(0, $byId[2]['z']);
        $this->assertSame(500, $byId[1]['z']);
    }

    public function test_smaller_carton_can_sit_on_larger_one_but_not_the_reverse(): void
    {
        $v = $this->vehicle(600, 600, 1000, 1000);
        $units = [
            $this->unit(1, 600, 600, 400, 10),  // besar & berat -> dasar
            $this->unit(2, 300, 300, 400, 5),   // kecil & ringan -> di atas
        ];
        $r = $this->service()->pack($units, $v, 'tetris');
        $this->assertSame(2, $r['summary']['loaded_count']);
        $this->assertValidPlacement($r['loaded'], $v);

        // Kecil tapi lebih berat: kecil jadi dasar, besar tidak boleh menumpuk (overhang) -> lantai penuh -> tidak muat
        $v2 = $this->vehicle(600, 300, 1000, 1000);
        $units2 = [
            $this->unit(1, 300, 300, 400, 10),
            $this->unit(2, 600, 300, 400, 5),
        ];
        $r2 = $this->service()->pack($units2, $v2, 'tetris');
        $this->assertValidPlacement($r2['loaded'], $v2);
        $byId = array_column($r2['loaded'], null, 'id');
        if (isset($byId[2], $byId[1])) {
            $this->assertSame(0, $byId[2]['z'], 'Karton besar tidak boleh menumpuk di atas karton kecil');
        }
    }

    public function test_weight_limit_stops_loading_in_tetris_and_ratio(): void
    {
        $v = $this->vehicle(4000, 2000, 2000, 25);
        $units = [];
        for ($i = 1; $i <= 5; $i++) {
            $units[] = $this->unit($i, 300, 300, 300, 10);
        }

        foreach (['tetris', 'ratio'] as $mode) {
            $r = $this->service()->pack($units, $v, $mode);
            $this->assertSame(2, $r['summary']['loaded_count'], $mode);
            $this->assertSame(['weight' => 3], $r['summary']['unloaded_reasons'], $mode);
            $this->assertSame('weight', $r['summary']['binding'], $mode);
        }
    }

    public function test_oversized_carton_is_rejected_without_rotation(): void
    {
        $v = $this->vehicle(3200, 1700, 1700, 3000);
        $r = $this->service()->pack([$this->unit(1, 200, 100, 1900, 1)], $v, 'tetris');

        $this->assertSame(['too_big' => 1], $r['summary']['unloaded_reasons']);
    }

    public function test_ratio_mode_loads_by_service_priority_until_volume_limit(): void
    {
        // Volume efektif 1 m³; tiap karton 0,6 m³ -> hanya 1 yang masuk; emergency didahulukan
        $v = $this->vehicle(1000, 1000, 1000, 100000);
        $units = [
            $this->unit(1, 1000, 1000, 600, 1, 'one_day'),
            $this->unit(2, 1000, 1000, 600, 1, 'emergency'),
        ];

        $r = $this->service()->pack($units, $v, 'ratio');

        $this->assertSame([2], array_column($r['loaded'], 'id'));
        $this->assertSame(['volume' => 1], $r['summary']['unloaded_reasons']);
        $this->assertArrayNotHasKey('x', $r['loaded'][0]);
    }

    public function test_random_load_is_always_valid(): void
    {
        mt_srand(42);
        $sizes = [[380, 280, 200], [450, 180, 150], [120, 120, 100], [750, 350, 250], [200, 100, 890], [1100, 210, 410], [80, 80, 680], [500, 350, 330]];
        $v = $this->vehicle(4400, 2000, 1900, 6000);

        $units = [];
        for ($i = 1; $i <= 150; $i++) {
            [$l, $w, $h] = $sizes[mt_rand(0, count($sizes) - 1)];
            $units[] = $this->unit($i, $l, $w, $h, mt_rand(1, 400) / 10);
        }

        $r = $this->service()->pack($units, $v, 'tetris');

        $this->assertGreaterThan(0, $r['summary']['loaded_count']);
        $this->assertLessThanOrEqual($v['max_payload_kg'] + 1e-6, $r['summary']['loaded_weight_kg']);
        $this->assertLessThanOrEqual($v['volume_m3'] + 1e-6, $r['summary']['loaded_volume_m3']);
        $this->assertValidPlacement($r['loaded'], $v);
    }
}
