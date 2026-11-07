<?php

namespace App\Services;

/**
 * Menentukan wilayah dan jenis jadwal dealer dari kode area pada kode dealer (mis. "SA" pada "SA38").
 *
 * Jenis jadwal:
 *  - "daily": pengiriman harian (Jawa, Sumatra selain Aceh)
 *  - "kapal": mengikuti jadwal kapal (Aceh, luar pulau)
 *  - null   : wilayah belum diatur
 *
 * Kelas ini murni (tanpa database) agar mudah dites.
 */
class RegionResolver
{
    /**
     * @param array<string,string> $areaRegions    kode area => kode wilayah
     * @param array<string,string> $regionSchedule kode wilayah => daily|kapal
     */
    public function __construct(
        private RouteResolver $routes,
        private array $areaRegions = [],
        private array $regionSchedule = [],
    ) {
        $this->areaRegions = array_change_key_case($areaRegions, CASE_UPPER);
    }

    public function regionOf(?string $dealerCode): ?string
    {
        $area = $this->routes->parse($dealerCode)['area'];

        return $area !== null ? ($this->areaRegions[$area] ?? null) : null;
    }

    public function modeOfRegion(?string $region): ?string
    {
        return $region !== null ? ($this->regionSchedule[$region] ?? null) : null;
    }

    public function modeOf(?string $dealerCode): ?string
    {
        return $this->modeOfRegion($this->regionOf($dealerCode));
    }
}
