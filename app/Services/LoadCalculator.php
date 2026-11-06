<?php

namespace App\Services;

/**
 * Perhitungan rasio muatan. Ada dua parameter loading: VOLUME dan BERAT.
 * Mana yang mencapai batas lebih dulu menjadi penentu (binding constraint).
 *
 * Kelas ini murni (tanpa database) agar mudah dites.
 */
class LoadCalculator
{
    public function __construct(private float $readyRatio = 0.8)
    {
    }

    /**
     * @param float $volumeM3        total volume muatan (m³)
     * @param float $weightKg        total berat muatan (kg)
     * @param float $vehicleVolumeM3 volume efektif kendaraan (m³)
     * @param float $vehicleMaxKg    kapasitas muatan kendaraan (kg)
     * @return array{volume_ratio:float, weight_ratio:float, ratio:float, binding:string, ready:bool, over_capacity:bool}
     */
    public function summarize(float $volumeM3, float $weightKg, float $vehicleVolumeM3, float $vehicleMaxKg): array
    {
        $volumeRatio = $vehicleVolumeM3 > 0 ? $volumeM3 / $vehicleVolumeM3 : 0.0;
        $weightRatio = $vehicleMaxKg > 0 ? $weightKg / $vehicleMaxKg : 0.0;

        $binding = $weightRatio > $volumeRatio ? 'weight' : 'volume';
        $ratio = max($volumeRatio, $weightRatio);

        return [
            'volume_ratio' => $volumeRatio,
            'weight_ratio' => $weightRatio,
            'ratio' => $ratio,
            'binding' => $binding,
            'ready' => $ratio >= $this->readyRatio,
            'over_capacity' => $ratio > 1.0,
        ];
    }

    /**
     * Apakah SATU karton muat di dalam kendaraan, TANPA diputar
     * (P sejajar panjang kendaraan, L sejajar lebar, T sejajar tinggi)?
     */
    public function fitsInVehicle(int $cartonL, int $cartonW, int $cartonH, int $vehicleL, int $vehicleW, int $vehicleH): bool
    {
        return $cartonL <= $vehicleL && $cartonW <= $vehicleW && $cartonH <= $vehicleH;
    }
}
