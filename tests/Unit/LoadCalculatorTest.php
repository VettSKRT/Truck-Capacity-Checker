<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use App\Services\LoadCalculator;
use PHPUnit\Framework\TestCase;

class LoadCalculatorTest extends TestCase
{
    public function test_volume_is_the_binding_constraint_when_it_hits_first(): void
    {
        $r = (new LoadCalculator(0.8))->summarize(14.0, 1000, 16.72, 6000);

        $this->assertSame('volume', $r['binding']);
        $this->assertEqualsWithDelta(14.0 / 16.72, $r['ratio'], 1e-9);
        $this->assertTrue($r['ready']);
        $this->assertFalse($r['over_capacity']);
    }

    public function test_weight_is_the_binding_constraint_when_it_hits_first(): void
    {
        $r = (new LoadCalculator(0.8))->summarize(2.0, 5400, 16.72, 6000);

        $this->assertSame('weight', $r['binding']);
        $this->assertEqualsWithDelta(0.9, $r['ratio'], 1e-9);
        $this->assertTrue($r['ready']);
    }

    public function test_below_threshold_is_not_ready_and_over_capacity_is_flagged(): void
    {
        $calc = new LoadCalculator(0.8);

        $this->assertFalse($calc->summarize(5.0, 500, 16.72, 6000)['ready']);
        $this->assertTrue($calc->summarize(20.0, 500, 16.72, 6000)['over_capacity']);
    }

    public function test_carton_fit_does_not_rotate(): void
    {
        $calc = new LoadCalculator();

        // Karton No. 26 (200x100x1340) muat di CDE (3200x1700x1700)
        $this->assertTrue($calc->fitsInVehicle(200, 100, 1340, 3200, 1700, 1700));
        // Tinggi 1900 > 1700 -> tidak muat (tanpa rotasi)
        $this->assertFalse($calc->fitsInVehicle(200, 100, 1900, 3200, 1700, 1700));
    }

    public function test_vehicle_volumes_match_specification(): void
    {
        $cde = new Vehicle(['length_mm' => 3200, 'width_mm' => 1700, 'height_mm' => 1700, 'usable_factor' => 1.0]);
        $cdd = new Vehicle(['length_mm' => 4400, 'width_mm' => 2000, 'height_mm' => 1900, 'usable_factor' => 1.0]);
        $wing = new Vehicle(['length_mm' => 9500, 'width_mm' => 2450, 'height_mm' => 2500, 'usable_factor' => 1.0]);

        $this->assertEqualsWithDelta(9.248, $cde->volume_m3, 1e-6);
        $this->assertEqualsWithDelta(16.72, $cdd->volume_m3, 1e-6);
        $this->assertEqualsWithDelta(58.1875, $wing->volume_m3, 1e-6);
    }
}
