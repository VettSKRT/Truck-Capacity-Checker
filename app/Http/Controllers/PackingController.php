<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\Upload;
use App\Models\Vehicle;
use App\Services\PackingService;
use Illuminate\Http\Request;

class PackingController extends Controller
{
    /** Batas jumlah karton per permintaan agar proses tetap cepat. */
    private const MAX_UNITS = 10000;

    public function index()
    {
        return view('packing.index');
    }

    /**
     * Susun muatan sebuah upload ke dalam kendaraan.
     *
     * Parameter:
     *  - upload_id  (wajib)
     *  - mode       ratio | tetris (default tetris)
     *  - vehicle    kode kendaraan (default: kendaraan pada upload)
     *  - only_ready true (default) = hanya Status 4 / Cargo Ready; false = semua status
     *
     * Semua dimensi pada respons dalam METER.
     */
    public function optimize(Request $request, PackingService $packing)
    {
        $data = $request->validate([
            'upload_id' => 'required|integer|exists:uploads,id',
            'mode' => 'nullable|in:' . implode(',', PackingService::MODES),
            'vehicle' => 'nullable|string|exists:vehicles,code',
            'only_ready' => 'nullable|boolean',
        ]);

        $upload = Upload::with('vehicle')->findOrFail($data['upload_id']);
        $vehicle = isset($data['vehicle'])
            ? Vehicle::where('code', $data['vehicle'])->first()
            : ($upload->vehicle ?? Vehicle::where('code', config('logistics.default_vehicle'))->first());

        if (! $vehicle) {
            return response()->json(['success' => false, 'message' => 'Data kendaraan belum ada. Jalankan: php artisan db:seed'], 422);
        }

        $query = Box::where('upload_id', $upload->id)->orderBy('id');
        if ($request->boolean('only_ready', true)) {
            $query->where('status', config('logistics.cargo_ready_status', 4));
        }

        $units = $packing->unitsFromBoxes($query->get());

        if (count($units) > self::MAX_UNITS) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah karton (' . count($units) . ') melebihi batas ' . self::MAX_UNITS . ' per permintaan.',
            ], 422);
        }

        $result = $packing->pack($units, [
            'length_mm' => $vehicle->length_mm,
            'width_mm' => $vehicle->width_mm,
            'height_mm' => $vehicle->height_mm,
            'max_payload_kg' => $vehicle->max_payload_kg,
            'volume_m3' => $vehicle->volume_m3,
        ], $data['mode'] ?? 'tetris');

        $positioned = $result['mode'] === 'tetris';

        return response()->json([
            'success' => true,
            'mode' => $result['mode'],
            'vehicle' => [
                'code' => $vehicle->code,
                'name' => $vehicle->name,
                'length' => $vehicle->length_mm / 1000,
                'width' => $vehicle->width_mm / 1000,
                'height' => $vehicle->height_mm / 1000,
                'volume_m3' => round($vehicle->volume_m3, 3),
                'max_payload_kg' => $vehicle->max_payload_kg,
            ],
            'summary' => $result['summary'],
            // Mode rasio tidak punya posisi 3D (x,y,z = null)
            'items' => array_map(fn ($u) => $this->toItem($u, $positioned), $result['loaded']),
            'unloaded' => array_map(fn ($u) => $this->toItem($u, false) + ['reason' => $u['reason']], $result['unloaded']),
        ]);
    }

    private function toItem(array $u, bool $positioned): array
    {
        return [
            'id' => $u['id'],
            'part_number' => $u['part_number'] ?? null,
            'packing_type' => $u['packing_type'] ?? null,
            'x' => $positioned ? $u['x'] / 1000 : null,
            'y' => $positioned ? $u['y'] / 1000 : null,
            'z' => $positioned ? $u['z'] / 1000 : null,
            'length' => $u['l'] / 1000,
            'width' => $u['w'] / 1000,
            'height' => $u['h'] / 1000,
            'status' => $u['status'],
            'volume' => $u['volume'],
            'weight_kg' => $u['weight'],
            'route' => $u['route'] ?? null,
            'category' => $u['category'] ?? null,
        ];
    }
}
