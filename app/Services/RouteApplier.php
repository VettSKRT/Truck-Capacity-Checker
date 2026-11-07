<?php

namespace App\Services;

use App\Models\Box;

/**
 * Jalur pada setiap karton (boxes.route) adalah data TURUNAN dari kode dealer, tipe layanan, dan
 * pengaturan area / dealer Same Day. Setelah pengaturan berubah, jalur semua kloter dihitung ulang
 * supaya riwayat selalu mengikuti pengaturan terbaru.
 */
class RouteApplier
{
    /** @return int jumlah baris karton yang jalurnya berubah */
    public function reapplyAll(): int
    {
        // Resolver baru agar memakai pengaturan terbaru di database
        $resolver = app(RouteResolver::class);

        $pairs = Box::query()->select('customer_code', 'service_type')->distinct()->get();
        $changed = 0;

        foreach ($pairs as $pair) {
            $route = $resolver->resolve($pair->customer_code, (string) $pair->service_type);

            $query = Box::where('customer_code', $pair->customer_code)->where('service_type', $pair->service_type);
            $query = $route === null
                ? $query->whereNotNull('route')
                : $query->where(fn ($q) => $q->whereNull('route')->orWhere('route', '!=', $route));

            $changed += $query->update(['route' => $route]);
        }

        return $changed;
    }
}
