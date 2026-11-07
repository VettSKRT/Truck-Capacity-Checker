<?php

namespace App\Services;

use App\Models\Box;
use App\Models\RouteSetting;
use App\Models\Vehicle;

/**
 * Ringkasan muatan PER JALUR.
 *
 * Satu jalur = satu truk khusus ke satu tujuan (mis. Jalur 1 ke Jabodetabek). Satu kloter (satu file order)
 * berisi beberapa jalur, jadi kesiapan kirim dinilai per jalur terhadap truk jalur itu, bukan satu kendaraan
 * untuk seluruh kloter. Hanya karton Cargo Ready (status 4) yang dihitung ke muatan.
 */
class RouteLoadService
{
    /** Kunci jalur untuk karton yang kode areanya belum punya jalur. */
    public const NO_ROUTE = 'none';

    public function __construct(private LoadCalculator $calculator)
    {
    }

    /**
     * @param iterable<int> $uploadIds
     * @return array<int,array{routes:array<int,array>, overall:array}> diindeks per upload_id
     */
    public function summarizeMany(iterable $uploadIds): array
    {
        $ids = collect($uploadIds)->map(fn ($i) => (int) $i)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $ready = (int) config('logistics.cargo_ready_status', 4);
        $settings = RouteSetting::with('vehicle')->get()->keyBy('route');
        $byStatus = Box::whereIn('upload_id', $ids)
            ->selectRaw('upload_id, route, status, sum(volume) as vol, sum(weight_kg) as wt, sum(box_count) as cartons')
            ->groupBy('upload_id', 'route', 'status')
            ->get();

        $dealers = Box::whereIn('upload_id', $ids)
            ->selectRaw('upload_id, route, count(distinct customer_code) as dealers')
            ->groupBy('upload_id', 'route')
            ->get();

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = ['routes' => [], 'overall' => []];
        }

        // Kumpulkan per upload dan jalur
        $acc = [];
        foreach ($byStatus as $row) {
            $key = $row->route === null ? self::NO_ROUTE : (string) (int) $row->route;
            $cell = &$acc[$row->upload_id][$key];
            $cell ??= ['volume' => 0.0, 'weight' => 0.0, 'cartons' => 0, 'pending' => 0, 'dealers' => 0];
            if ((int) $row->status === $ready) {
                $cell['volume'] += (float) $row->vol;
                $cell['weight'] += (float) $row->wt;
                $cell['cartons'] += (int) $row->cartons;
            } else {
                $cell['pending'] += (int) $row->cartons;
            }
            unset($cell);
        }
        foreach ($dealers as $row) {
            $key = $row->route === null ? self::NO_ROUTE : (string) (int) $row->route;
            $acc[$row->upload_id][$key]['dealers'] = (int) $row->dealers;
        }

        foreach ($acc as $uploadId => $routes) {
            $list = [];
            foreach ($routes as $key => $cell) {
                $number = $key === self::NO_ROUTE ? null : (int) $key;
                $setting = $number !== null ? ($settings[$number] ?? null) : null;
                $vehicle = $setting?->vehicle ?? $this->defaultVehicleFor($number);
                $list[] = $this->present($number, $setting, $vehicle, $cell);
            }
            usort($list, fn ($a, $b) => ($a['route'] ?? 99) <=> ($b['route'] ?? 99));

            $result[$uploadId] = ['routes' => $list, 'overall' => $this->overall($list)];
        }

        foreach ($result as $id => $r) {
            if (empty($r['overall'])) {
                $result[$id]['overall'] = $this->overall([]);
            }
        }

        return $result;
    }

    /** @return array{routes:array<int,array>, overall:array} */
    public function summarizeUpload(int $uploadId): array
    {
        return $this->summarizeMany([$uploadId])[$uploadId];
    }

    /** Truk untuk sebuah jalur: pengaturan jalur, atau truk default jalur itu bila belum diatur. */
    public function vehicleForRoute(?int $route): ?Vehicle
    {
        if ($route !== null) {
            $setting = RouteSetting::with('vehicle')->where('route', $route)->first();
            if ($setting?->vehicle) {
                return $setting->vehicle;
            }
        }

        return $this->defaultVehicleFor($route);
    }

    /** Truk default bila jalur belum punya truk sendiri: truk besar untuk jalur akhir, selain itu truk kecil. */
    public function defaultVehicleFor(?int $route): ?Vehicle
    {
        if ($route !== null && in_array($route, (array) config('logistics.big_truck_routes', []), true)) {
            $big = Vehicle::where('code', config('logistics.big_vehicle'))->first();
            if ($big) {
                return $big;
            }
        }

        return $this->defaultVehicle();
    }

    public function routeName(?int $route): ?string
    {
        return $route !== null ? RouteSetting::where('route', $route)->value('name') : null;
    }

    public function defaultVehicle(): ?Vehicle
    {
        return Vehicle::where('code', config('logistics.default_vehicle'))->first() ?? Vehicle::orderBy('id')->first();
    }

    /* ----------------------------------------------------------------- */

    private function present(?int $route, ?RouteSetting $setting, ?Vehicle $vehicle, array $cell): array
    {
        $hasLoad = $cell['cartons'] > 0;
        $calc = $vehicle
            ? $this->calculator->summarize($cell['volume'], $cell['weight'], $vehicle->volume_m3, $vehicle->max_payload_kg)
            : ['volume_ratio' => 0.0, 'weight_ratio' => 0.0, 'ratio' => 0.0, 'binding' => 'volume', 'state' => 'wait', 'over_capacity' => false, 'ready' => false];

        return [
            'route' => $route,
            'key' => $route === null ? self::NO_ROUTE : (string) $route,
            'label' => $route === null ? 'Tanpa jalur' : 'Jalur ' . $route,
            'name' => $setting?->name,
            'vehicle' => $vehicle ? [
                'id' => $vehicle->id,
                'code' => $vehicle->code,
                'name' => $vehicle->name,
                'volume_m3' => round($vehicle->volume_m3, 3),
                'max_payload_kg' => $vehicle->max_payload_kg,
            ] : null,
            'cartons' => $cell['cartons'],          // karton Cargo Ready
            'pending' => $cell['pending'],          // karton status 1-3 (belum masuk muatan)
            'dealers' => $cell['dealers'],
            'volume' => $cell['volume'],
            'weight' => $cell['weight'],
            'volume_ratio' => $calc['volume_ratio'],
            'weight_ratio' => $calc['weight_ratio'],
            'ratio' => $calc['ratio'],
            'binding' => $calc['binding'],
            // none = belum ada karton Cargo Ready di jalur ini
            'state' => $hasLoad ? $calc['state'] : 'none',
        ];
    }

    /**
     * Status kloter dari status tiap jalur:
     *  over (ada jalur melebihi) > ready (semua jalur aktif siap) > partial (sebagian siap) > wait > none.
     */
    private function overall(array $routes): array
    {
        $active = array_filter($routes, fn ($r) => $r['route'] !== null && $r['state'] !== 'none');
        $count = fn (string $s) => count(array_filter($active, fn ($r) => $r['state'] === $s));

        $ready = $count('ready');
        $wait = $count('wait');
        $over = $count('over');
        $unrouted = array_sum(array_map(fn ($r) => $r['route'] === null ? $r['cartons'] + $r['pending'] : 0, $routes));

        $state = match (true) {
            count($active) === 0 => 'none',
            $over > 0 => 'over',
            $wait === 0 => 'ready',
            $ready > 0 => 'partial',
            default => 'wait',
        };

        return [
            'state' => $state,
            'active' => count($active),
            'ready' => $ready,
            'wait' => $wait,
            'over' => $over,
            'unrouted_cartons' => $unrouted,
        ];
    }
}
