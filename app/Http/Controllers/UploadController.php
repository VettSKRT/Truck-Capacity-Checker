<?php

namespace App\Http\Controllers;

use App\Imports\CargoImport;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\Upload;
use App\Models\Vehicle;
use App\Services\LoadCalculator;
use App\Services\RouteResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Upload file order (Excel/CSV).
 *
 * Kolom file order (baris 1 = header):
 *   A Cargo Destination | B Customer Code (kode dealer, mis. SA38) | C Customer Name
 *   D Part Number | E Qty (pcs) | F Status (1-4) | G Service Type (opsional)
 *
 * Dimensi karton & berat TIDAK ada di file; diambil dari master barang (products -> cartons).
 */
class UploadController extends Controller
{
    /** Batas jumlah karton yang diperluas ke respons JSON agar browser tidak kewalahan. */
    private const MAX_EXPANDED_ITEMS = 5000;

    public function __construct(
        private LoadCalculator $calculator,
        private RouteResolver $routes,
    ) {
    }

    public function upload(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv,txt',
            'vehicle' => 'nullable|string|exists:vehicles,code',
        ]);

        $vehicle = Vehicle::where('code', $request->input('vehicle', config('logistics.default_vehicle')))->first()
            ?? Vehicle::orderBy('id')->first();

        if (! $vehicle) {
            return $this->failure($request, 'Data kendaraan belum ada. Jalankan: php artisan db:seed');
        }

        $file = $request->file('excel_file');
        $rows = Excel::toCollection(new CargoImport, $file)->first() ?? collect();

        // Preload master barang sekaligus (hindari N+1)
        $partNumbers = $rows->map(fn ($r) => $this->str($r[3] ?? null))->filter()->unique()->values();
        $products = Product::with('carton')->whereIn('part_number', $partNumbers)->get()->keyBy('part_number');

        [$boxRows, $warnings] = $this->buildBoxRows($rows, $products, $vehicle);

        if (empty($boxRows)) {
            $message = 'Tidak ada data valid di file. ' . implode(' ', array_slice($warnings, 0, 3));

            return $this->failure($request, trim($message));
        }

        $path = $file->store('uploads');
        $cargoReady = (int) config('logistics.cargo_ready_status', 4);

        $upload = DB::transaction(function () use ($file, $path, &$boxRows, $vehicle, $cargoReady) {
            $upload = Upload::create([
                'filename' => $file->getClientOriginalName(),
                'filepath' => $path,
                'vehicle_id' => $vehicle->id,
                'total_volume' => 0,
                'ratio' => 0,
                'status' => 'Processing',
            ]);

            $volume = 0.0;
            $weight = 0.0;
            foreach ($boxRows as $i => $data) {
                $box = $upload->boxes()->create($data);
                $boxRows[$i]['id'] = $box->id;

                // Hanya Cargo Ready (Status 4) yang dihitung ke muatan siap kirim
                if ($data['status'] === $cargoReady) {
                    $volume += $data['volume'];
                    $weight += $data['weight_kg'];
                }
            }

            $sum = $this->calculator->summarize($volume, $weight, $vehicle->volume_m3, $vehicle->max_payload_kg);

            $upload->update([
                'total_volume' => $volume,
                'total_weight' => $weight,
                'ratio' => $sum['ratio'],
                'volume_ratio' => $sum['volume_ratio'],
                'weight_ratio' => $sum['weight_ratio'],
                'binding_constraint' => $sum['binding'],
                'status' => $sum['over_capacity'] ? Upload::STATUS_OVER : ($sum['ready'] ? Upload::STATUS_READY : Upload::STATUS_WAIT),
            ]);

            Dealer::syncFromBoxes(); // dealer baru masuk daftar, MOQ diisi manual

            return $upload;
        });

        if ($request->expectsJson()) {
            return $this->jsonSuccess($upload, $vehicle, $boxRows, $warnings);
        }

        return redirect()->route('result', $upload->id);
    }

    public function result($id)
    {
        $upload = Upload::with(['boxes', 'vehicle'])->findOrFail($id);

        return view('result', compact('upload'));
    }

    /* ------------------------------------------------------------------ */

    /**
     * Validasi baris order lalu pecah menjadi baris karton (unit & receh).
     *
     * @return array{0: array<int,array>, 1: array<int,string>}
     */
    private function buildBoxRows($rows, $products, Vehicle $vehicle): array
    {
        $boxRows = [];
        $warnings = [];
        $unmappedRoutes = 0;
        $tooBig = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // +1 index 0-based, +1 header

            if ($row->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue; // baris kosong
            }

            $dealerCode = $this->str($row[1] ?? null);
            $part = $this->str($row[3] ?? null);
            $qty = (int) ($row[4] ?? 0);
            $status = (int) ($row[5] ?? 0);
            $service = $this->normalizeService($this->str($row[6] ?? null));

            if ($part === null) {
                $warnings[] = "Baris {$line}: part number kosong.";
                continue;
            }
            if ($qty <= 0) {
                $warnings[] = "Baris {$line}: qty harus lebih dari 0.";
                continue;
            }
            if (! array_key_exists($status, config('logistics.statuses'))) {
                $warnings[] = "Baris {$line}: status harus 1, 2, 3, atau 4.";
                continue;
            }

            $product = $products->get($part);
            if (! $product) {
                $warnings[] = "Baris {$line}: part {$part} tidak ada di master barang.";
                continue;
            }
            if (! $product->carton) {
                $warnings[] = "Baris {$line}: part {$part} belum punya nomor karton.";
                continue;
            }

            $carton = $product->carton;
            $perBox = max(1, $product->qty_per_box);
            $fullBoxes = intdiv($qty, $perBox);
            $remainder = $qty % $perBox;

            $route = $this->routes->resolve($dealerCode, $service);
            if ($route === null) {
                $unmappedRoutes++;
            }

            if (! $this->calculator->fitsInVehicle(
                $carton->length_mm, $carton->width_mm, $carton->height_mm,
                $vehicle->length_mm, $vehicle->width_mm, $vehicle->height_mm
            )) {
                $tooBig[$carton->number] = true;
            }

            $base = [
                'product_id' => $product->id,
                'carton_id' => $carton->id,
                'cargo_destination' => $this->str($row[0] ?? null),
                'customer_code' => $dealerCode,
                'customer_name' => $this->str($row[2] ?? null),
                'part_number' => $part,
                'panjang' => $carton->length_mm,
                'lebar' => $carton->width_mm,
                'tinggi' => $carton->height_mm,
                'status' => $status,
                'category' => $product->category,
                'service_type' => $service,
                'route' => $route,
            ];
            $unitVolume = $carton->volume_m3;

            // Packing Unit: semua pcs memenuhi box
            if ($fullBoxes > 0) {
                $boxRows[] = $base + [
                    'qty' => $fullBoxes * $perBox,
                    'box_count' => $fullBoxes,
                    'packing_type' => 'unit',
                    'fill_ratio' => 1.0,
                    'volume' => $unitVolume * $fullBoxes,
                    'weight_kg' => $product->weight_kg * $fullBoxes * $perBox,
                ];
            }

            // Packing Receh: pesanan kurang dari qty box -> 1 karton (konservatif: ukuran karton penuh)
            if ($remainder > 0) {
                $boxRows[] = $base + [
                    'qty' => $remainder,
                    'box_count' => 1,
                    'packing_type' => 'receh',
                    'fill_ratio' => $remainder / $perBox,
                    'volume' => $unitVolume,
                    'weight_kg' => $product->weight_kg * $remainder,
                ];
            }
        }

        if ($unmappedRoutes > 0) {
            $warnings[] = "{$unmappedRoutes} baris belum punya jalur (isi mapping area di config/logistics.php).";
        }
        foreach (array_keys($tooBig) as $number) {
            $warnings[] = "Karton No. {$number} tidak muat di {$vehicle->code} (karton tidak boleh diputar).";
        }

        return [$boxRows, array_slice($warnings, 0, 50)];
    }

    private function jsonSuccess(Upload $upload, Vehicle $vehicle, array $boxRows, array $warnings)
    {
        $items = [];
        $truncated = false;

        foreach ($boxRows as $r) {
            for ($n = 0; $n < $r['box_count']; $n++) {
                if (count($items) >= self::MAX_EXPANDED_ITEMS) {
                    $truncated = true;
                    break 2;
                }
                // Satu item = satu karton, dimensi dalam meter (untuk frontend)
                $items[] = [
                    'id' => $r['id'],
                    'cargo_destination' => $r['cargo_destination'],
                    'customer_code' => $r['customer_code'],
                    'customer_name' => $r['customer_name'],
                    'part_number' => $r['part_number'],
                    'packing_type' => $r['packing_type'],
                    'panjang' => $r['panjang'] / 1000,
                    'lebar' => $r['lebar'] / 1000,
                    'tinggi' => $r['tinggi'] / 1000,
                    'status' => $r['status'],
                    'volume' => $r['volume'] / $r['box_count'],
                    'weight_kg' => $r['weight_kg'] / $r['box_count'],
                    'route' => $r['route'],
                    'service_type' => $r['service_type'],
                    'category' => $r['category'],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'upload_id' => $upload->id,
            'vehicle' => [
                'code' => $vehicle->code,
                'name' => $vehicle->name,
                'length_mm' => $vehicle->length_mm,
                'width_mm' => $vehicle->width_mm,
                'height_mm' => $vehicle->height_mm,
                'volume_m3' => round($vehicle->volume_m3, 3),
                'max_payload_kg' => $vehicle->max_payload_kg,
            ],
            'summary' => [
                'total_volume' => $upload->total_volume,
                'total_weight' => $upload->total_weight,
                'volume_ratio' => $upload->volume_ratio,
                'weight_ratio' => $upload->weight_ratio,
                'ratio' => $upload->ratio,
                'binding' => $upload->binding_constraint,
                'ready' => $upload->isReady(),
                'status' => $upload->status,
            ],
            'items' => $items,
            'items_truncated' => $truncated,
            'warnings' => $warnings,
            'message' => 'Excel data loaded successfully',
        ]);
    }

    private function failure(Request $request, string $message)
    {
        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => $message], 422)
            : back()->withErrors(['excel_file' => $message]);
    }

    private function str($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function normalizeService(?string $value): string
    {
        $key = strtolower(str_replace([' ', '-'], '_', (string) $value));
        $key = $key === 'sameday' ? 'same_day' : ($key === 'oneday' ? 'one_day' : $key);

        return array_key_exists($key, config('logistics.service_types'))
            ? $key
            : config('logistics.default_service_type', 'one_day');
    }
}
