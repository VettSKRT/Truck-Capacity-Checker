<?php

namespace Tests\Unit;

use App\Services\MoqEvaluator;
use App\Services\RegionResolver;
use App\Services\RouteResolver;
use PHPUnit\Framework\TestCase;

class SchedulingRulesTest extends TestCase
{
    private function regions(): RegionResolver
    {
        return new RegionResolver(
            new RouteResolver(),
            ['SA' => 'jawa', 'sf' => 'sumatra', 'SL' => 'aceh', 'SJ' => 'luar_pulau'],
            ['jawa' => 'daily', 'sumatra' => 'daily', 'aceh' => 'kapal', 'luar_pulau' => 'kapal'],
        );
    }

    public function test_region_is_resolved_from_the_area_code_of_the_dealer_code(): void
    {
        $r = $this->regions();

        $this->assertSame('jawa', $r->regionOf('SA38'));
        $this->assertSame('sumatra', $r->regionOf('sf09'));   // huruf kecil dan kunci config huruf kecil
        $this->assertSame('aceh', $r->regionOf('SL02'));
        $this->assertNull($r->regionOf('ZZ01'));
        $this->assertNull($r->regionOf(null));
    }

    public function test_java_and_sumatra_are_daily_while_aceh_and_outer_islands_follow_the_ship(): void
    {
        $r = $this->regions();

        $this->assertSame('daily', $r->modeOf('SA38'));
        $this->assertSame('daily', $r->modeOf('SF09'));
        $this->assertSame('kapal', $r->modeOf('SL02'));       // Aceh dikecualikan dari harian
        $this->assertSame('kapal', $r->modeOf('SJ10'));
        $this->assertNull($r->modeOf('ZZ01'));                // wilayah belum diatur
    }

    public function test_moq_is_met_at_or_above_the_minimum(): void
    {
        $this->assertTrue(MoqEvaluator::evaluate(1.0, 1.0)['met']);
        $this->assertTrue(MoqEvaluator::evaluate(1.2, 1.0)['met']);
        $this->assertSame(0.0, MoqEvaluator::evaluate(1.2, 1.0)['shortfall']);
    }

    public function test_moq_not_met_reports_the_shortfall(): void
    {
        $r = MoqEvaluator::evaluate(0.322, 1.0);

        $this->assertFalse($r['met']);
        $this->assertEqualsWithDelta(0.678, $r['shortfall'], 1e-9);
    }

    public function test_unset_moq_is_neither_met_nor_unmet(): void
    {
        $this->assertNull(MoqEvaluator::evaluate(5.0, null)['met']);
        $this->assertNull(MoqEvaluator::evaluate(5.0, 0.0)['met']);
    }
}
