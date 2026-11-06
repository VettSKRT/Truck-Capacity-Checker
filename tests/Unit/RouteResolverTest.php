<?php

namespace Tests\Unit;

use App\Services\RouteResolver;
use PHPUnit\Framework\TestCase;

class RouteResolverTest extends TestCase
{
    private function resolver(): RouteResolver
    {
        return new RouteResolver(['SA' => 1, 'sb' => 2], ['SA38'], 8);
    }

    public function test_dealer_code_is_split_into_area_and_dealer(): void
    {
        $this->assertSame(['area' => 'SA', 'dealer' => '38'], $this->resolver()->parse('sa38'));
        $this->assertSame(['area' => null, 'dealer' => null], $this->resolver()->parse('???'));
    }

    public function test_area_code_maps_to_route(): void
    {
        $this->assertSame(1, $this->resolver()->resolve('SA10', 'one_day'));
        $this->assertSame(2, $this->resolver()->resolve('SB07', 'emergency'));
    }

    public function test_same_day_selected_dealer_uses_route_8(): void
    {
        $this->assertSame(8, $this->resolver()->resolve('SA38', 'same_day'));
        // Dealer lain di area yang sama tetap pakai jalur area
        $this->assertSame(1, $this->resolver()->resolve('SA39', 'same_day'));
        // Dealer terpilih tapi bukan Same Day -> jalur area
        $this->assertSame(1, $this->resolver()->resolve('SA38', 'one_day'));
    }

    public function test_unmapped_area_returns_null(): void
    {
        $this->assertNull($this->resolver()->resolve('ZZ01', 'one_day'));
        $this->assertNull($this->resolver()->resolve(null, 'one_day'));
    }
}
