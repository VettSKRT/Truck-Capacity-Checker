<?php

namespace App\Http\Controllers;

use App\Imports\CargoImport;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\Upload;
use App\Services\LoadCalculator;
use App\Services\RouteLoadService;
use App\Services\RouteResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Upload file order (Excel/CSV). Satu kali unggah = satu kloter.
 *
 * Kolom file order (baris 1 = header):
 *   A Cargo Destination | B Customer Code (kode dealer, mis. SA38) | C Customer Name
 *   D Part Number | E Qty (pcs) | F Status (1-4) | G Service Type (opsional)
 *
 * Dimensi karton & berat TIDAK ada di file; diambil dari master barang (products -> cartons).
 * Kesiapan kirim dinilai PER JALUR (satu jalur = satu truk), lihat RouteLoadService.
 */
class UploadController extends Controller
{
    public function __construct(
        private LoadCalculator $calculator,
        private RouteResolver $routes,
        private RouteLoadService $loads,
    ) {
    }

    public function upload(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        if (! $this->loads->defaultVehicle()) {
            return $this->failure($request, 'Data kendaraan belum ada. Jalankan: php artisan db:seed');
        }

        $file = $request->file('excel_file');
        $rows = Excel::toCollection(new CargoImport, $file)->first() ?? collect();

        // Preload master barang sekaligus (hindari N+1)
        $partNumbers = $rows->map(fn ($r) => $this->str($r[3] ?? null))->filter()->unique()->values();
        $products = Product::with('carton')->whereIn('part_number', $partNumbers)->get()->keyBy('part_number');

        [$boxRows, $warnings] = $this->buildBoxRows($rows, $products);

        if (empty($boxRows)) {
            $message = 'Tidak ada data valid di file. ' . implode(' ', array_slice($warnings, 0, 3));

            return $this->failure($request, trim($message));
        }

        $path = $file->store('uploads');
        $cargoReady = (int) config('logistics.cargo_ready_status', 4);

        $upload = DB::transaction(function () use ($file, $path, $boxRows, $cargoReady) {
            $upload = Upload::create([
                'filename' => $file->getClientOriginalName(),
                'filepath' => $path,
                'total_volume' => 0,
                'total_weight' => 0,
                'ratio' => 0,
                'status' => 'Tersimpan',
            ]);

            $volume = 0.0;
            $weight = 0.0;
            foreach ($boxRows as $data) {
                $upload->boxes()->create($data);
                if ($data['status'] === $cargoReady) {
                    $volume += $data['volume'];
                    $weight += $data['weight_kg'];
                }
            }

            // Total Cargo Ready seluruh kloter (informasi). Kesiapan kirim dihitung per jalur, bukan dari angka ini.
            $upload->update(['total_volume' => $volume, 'total_weight' => $weight]);

            Dealer::syncFromBoxes(); // dealer baru masuk daftar, MOQ diisi manual

            return $upload;
        });

        if ($request->expectsJson()) {
            $summary = $this->loads->summarizeUpload($upload->id);

            return response()->json([
                'success' => true,
                'upload_id' => $upload->id,
                'code' => $upload->code,
                'routes' => $summary['routes'],
                'overall' => $summary['overall'],
                'warnings' => $warnings,
                'message' => 'Excel data loaded successfully',
            ]);
        }

        return redirect()->route('batches.show', $upload)->with('status', "Kloter {$upload->code} tersimpan.");
    }

    /* ------------------------------------------------------------------ */

    /**
     * Validasi baris order lalu pecah menjadi baris karton (unit & receh).
     *
     * @return array{0: array<int,array>, 1: array<int,string>}
     */
    private function buildBoxRows($rows, $products): array
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

            // Karton harus muat di truk jalurnya (tanpa diputar)
            $vehicle = $this->loads->vehicleForRoute($route);
            if ($vehicle && ! $this->calculator->fitsInVehicle(
                $carton->length_mm, $carton->width_mm, $carton->height_mm,
                $vehicle->length_mm, $vehicle->width_mm, $vehicle->height_mm
            )) {
                $tooBig["{$carton->number}|{$vehicle->code}"] = true;
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
            $warnings[] = "{$unmappedRoutes} baris belum punya jalur (atur di menu Pengaturan, Area dan Jalur).";
        }
        foreach (array_keys($tooBig) as $key) {
            [$number, $code] = explode('|', $key);
            $warnings[] = "Karton No. {$number} tidak muat di truk {$code} (karton tidak boleh diputar).";
        }

        return [$boxRows, array_slice($warnings, 0, 50)];
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
