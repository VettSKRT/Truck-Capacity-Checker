<?php

namespace App\Http\Controllers;

use App\Models\Box;
use Illuminate\Http\Request;

class PackingController extends Controller
{
    // Dimensi container dalam meter
    private const LENGTH = 16;
    private const WIDTH = 1;
    private const HEIGHT = 1;

    public function index()
    {
        return view('packing.index');
    }

    /**
     * Susun box milik sebuah upload ke dalam container.
     * Semua dimensi dikonversi dari mm (database) ke meter.
     * Algoritma shelf: isi sepanjang X, lalu pindah baris (Y), lalu naik lapisan (Z).
     */
    public function optimize(Request $request)
    {
        $request->validate(['upload_id' => 'required|integer|exists:uploads,id']);

        $boxes = Box::where('upload_id', $request->upload_id)
            ->orderBy('status', 'desc')
            ->orderBy('volume', 'desc')
            ->get();

        $x = $y = $z = 0.0;
        $rowDepth = 0.0;   // lebar terbesar pada baris saat ini
        $layerHeight = 0.0; // tinggi terbesar pada lapisan saat ini
        $packed = [];
        $skipped = [];
        $eps = 1e-9;

        foreach ($boxes as $box) {
            $l = $box->panjang / 1000;
            $w = $box->lebar / 1000;
            $h = $box->tinggi / 1000;

            // Ukuran melebihi container -> pasti tidak muat
            if ($l > self::LENGTH + $eps || $w > self::WIDTH + $eps || $h > self::HEIGHT + $eps) {
                $skipped[] = $box->id;
                continue;
            }

            // Pindah baris jika melewati panjang container
            if ($x + $l > self::LENGTH + $eps) {
                $x = 0.0;
                $y += $rowDepth;
                $rowDepth = 0.0;
            }
            // Pindah lapisan jika melewati lebar container
            if ($y + $w > self::WIDTH + $eps) {
                $x = 0.0;
                $y = 0.0;
                $z += $layerHeight;
                $rowDepth = 0.0;
                $layerHeight = 0.0;
            }
            // Melewati tinggi container -> tidak muat
            if ($z + $h > self::HEIGHT + $eps) {
                $skipped[] = $box->id;
                continue;
            }

            $packed[] = [
                'id' => $box->id,
                'x' => $x,
                'y' => $y,
                'z' => $z,
                'length' => $l,
                'width' => $w,
                'height' => $h,
                'status' => $box->status,
                'volume' => $box->volume,
            ];

            $x += $l;
            $rowDepth = max($rowDepth, $w);
            $layerHeight = max($layerHeight, $h);
        }

        return response()->json([
            'success' => true,
            'items' => $packed,
            'skipped' => $skipped,
        ]);
    }
}
