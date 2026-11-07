<?php

namespace App\Services;

/**
 * Menentukan jalur pengiriman (1-8) dari kode dealer & tipe layanan.
 *
 * Kode dealer berformat <KODE AREA><NOMOR DEALER>, mis. "SA38":
 * "SA" = kode area, "38" = kode dealer.
 *  - Jalur 8 : Same Day untuk dealer terpilih.
 *  - Jalur 1-7: berdasarkan kode area (tabel areas, diatur di menu Pengaturan).
 */
class RouteResolver
{
    /**
     * @param array<string,int>  $areaRoutes       kode area => jalur
     * @param array<int,string>  $sameDayDealers   daftar kode dealer lengkap
     */
    public function __construct(
        private array $areaRoutes = [],
        private array $sameDayDealers = [],
        private int $sameDayRoute = 8,
    ) {
        $this->areaRoutes = array_change_key_case($areaRoutes, CASE_UPPER);
        $this->sameDayDealers = array_map(fn ($c) => strtoupper(trim($c)), $sameDayDealers);
    }

    /** @return array{area:?string, dealer:?string} */
    public function parse(?string $dealerCode): array
    {
        $code = strtoupper(trim((string) $dealerCode));

        if (preg_match('/^([A-Z]+)[\s\-]*(\d+)$/', $code, $m)) {
            return ['area' => $m[1], 'dealer' => $m[2]];
        }

        return ['area' => null, 'dealer' => null];
    }

    /** Jalur 1-8, atau null jika belum ada mapping. */
    public function resolve(?string $dealerCode, string $serviceType = 'one_day'): ?int
    {
        $code = strtoupper(trim((string) $dealerCode));

        if ($serviceType === 'same_day' && in_array($code, $this->sameDayDealers, true)) {
            return $this->sameDayRoute;
        }

        $area = $this->parse($code)['area'];

        return $area !== null ? ($this->areaRoutes[$area] ?? null) : null;
    }
}
