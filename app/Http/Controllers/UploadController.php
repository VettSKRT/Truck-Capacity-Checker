<?php

namespace App\Http\Controllers;

use App\Imports\CargoImport;
use App\Models\Box;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class UploadController extends Controller
{
    /** Kapasitas truk dalam m³ (16 m × 1 m × 1 m). */
    public const TRUCK_CAPACITY = 16;

    /** Batas rasio status 4 agar truk dianggap siap dikirim. */
    public const READY_RATIO = 0.8;

    public function index()
    {
        return view('upload');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        $file = $request->file('excel_file');
        $rows = Excel::toCollection(new CargoImport, $file)->first() ?? collect();

        // Validasi & normalisasi baris sebelum menyentuh database
        $parsed = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2; // +1 karena index 0-based, +1 karena header

            // Lewati baris kosong
            if ($row->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }

            $p = (float) ($row[3] ?? 0);
            $l = (float) ($row[4] ?? 0);
            $t = (float) ($row[5] ?? 0);
            $status = (int) ($row[6] ?? 0);

            if ($p <= 0 || $l <= 0 || $t <= 0) {
                $errors[] = "Baris {$line}: panjang, lebar, dan tinggi harus lebih dari 0.";
                continue;
            }
            if (! in_array($status, [2, 3, 4], true)) {
                $errors[] = "Baris {$line}: status harus 2, 3, atau 4.";
                continue;
            }

            $parsed[] = [
                'cargo_destination' => $row[0] !== null ? trim((string) $row[0]) : null,
                'customer_code' => $row[1] !== null ? trim((string) $row[1]) : null,
                'customer_name' => $row[2] !== null ? trim((string) $row[2]) : null,
                'panjang' => (int) round($p),
                'lebar' => (int) round($l),
                'tinggi' => (int) round($t),
                'status' => $status,
                'volume' => ($p / 1000) * ($l / 1000) * ($t / 1000),
            ];
        }

        if (empty($parsed)) {
            $message = 'Tidak ada data valid di file. ' . implode(' ', array_slice($errors, 0, 3));

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => trim($message)], 422)
                : back()->withErrors(['excel_file' => trim($message)]);
        }

        $path = $file->store('uploads');

        $upload = DB::transaction(function () use ($file, $path, $parsed) {
            $upload = Upload::create([
                'filename' => $file->getClientOriginalName(),
                'filepath' => $path,
                'total_volume' => 0,
                'ratio' => 0,
                'status' => 'Processing',
            ]);

            $totalVolume = 0;
            foreach ($parsed as $i => $data) {
                $box = $upload->boxes()->create($data);
                $parsed[$i]['id'] = $box->id;

                // Hanya Status 4 yang dihitung untuk kesiapan kirim
                if ($data['status'] === 4) {
                    $totalVolume += $data['volume'];
                }
            }

            $ratio = $totalVolume / self::TRUCK_CAPACITY;
            $upload->update([
                'total_volume' => $totalVolume,
                'ratio' => $ratio,
                'status' => $ratio >= self::READY_RATIO ? 'Siap Dikirim' : 'Belum Siap',
            ]);

            return $upload;
        });

        if ($request->expectsJson()) {
            // Konversi mm -> meter untuk frontend
            $items = array_map(fn ($d) => [
                'id' => $d['id'],
                'cargo_destination' => $d['cargo_destination'],
                'customer_code' => $d['customer_code'],
                'customer_name' => $d['customer_name'],
                'panjang' => $d['panjang'] / 1000,
                'lebar' => $d['lebar'] / 1000,
                'tinggi' => $d['tinggi'] / 1000,
                'status' => $d['status'],
                'volume' => $d['volume'],
            ], $parsed);

            return response()->json([
                'success' => true,
                'upload_id' => $upload->id,
                'items' => $items,
                'ready' => $upload->ratio >= self::READY_RATIO,
                'ratio' => $upload->ratio,
                'status' => $upload->status,
                'warnings' => $errors,
                'message' => 'Excel data loaded successfully',
            ]);
        }

        return redirect()->route('result', $upload->id);
    }

    public function result($id)
    {
        $upload = Upload::with('boxes')->findOrFail($id);

        return view('result', compact('upload'));
    }
}
