<?php

namespace App\Services;

/**
 * Membandingkan volume dealer dengan MOQ-nya (Minimal Order Quantity, dalam m³).
 * Murni (tanpa database).
 */
class MoqEvaluator
{
    /**
     * @return array{met:?bool, shortfall:float, moq:?float}
     *   met = null bila MOQ belum diatur; shortfall = kekurangan volume (m³), 0 bila terpenuhi.
     */
    public static function evaluate(float $volumeM3, ?float $moqM3): array
    {
        if ($moqM3 === null || $moqM3 <= 0) {
            return ['met' => null, 'shortfall' => 0.0, 'moq' => null];
        }

        $met = $volumeM3 + 1e-9 >= $moqM3;

        return ['met' => $met, 'shortfall' => $met ? 0.0 : $moqM3 - $volumeM3, 'moq' => $moqM3];
    }
}
