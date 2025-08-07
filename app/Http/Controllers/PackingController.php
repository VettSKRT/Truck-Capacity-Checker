<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\Upload;
use Illuminate\Http\Request;

class PackingController extends Controller
{
    public function index()
    {
        return view('packing.index');
    }

    public function optimize(Request $request)
    {
        $boxes = Box::where('upload_id', $request->upload_id)
                   ->orderBy('status', 'desc')
                   ->orderBy('volume', 'desc')
                   ->get();

        $containerVolume = 16; // 16m³
        $packedItems = [];
        $currentPosition = ['x' => 0, 'y' => 0, 'z' => 0];

        foreach ($boxes as $box) {
            // Cek apakah masih muat di container
            if ($this->canFitInContainer($box, $currentPosition, $containerVolume, $packedItems)) {
                $packedItems[] = [
                    'id' => $box->id,
                    'x' => $currentPosition['x'],
                    'y' => $currentPosition['y'],
                    'z' => $currentPosition['z'],
                    'length' => $box->panjang,
                    'width' => $box->lebar,
                    'height' => $box->tinggi,
                    'status' => $box->status,
                    'volume' => $box->volume
                ];

                // Update posisi untuk item berikutnya
                $currentPosition = $this->getNextPosition($currentPosition, $box);
            }
        }

        return response()->json([
            'success' => true,
            'items' => $packedItems
        ]);
    }

    private function canFitInContainer($box, $position, $containerVolume, $packedItems = [])
    {
        // Cek apakah volume total tidak melebihi kapasitas
        $totalVolume = collect($packedItems)->sum('volume') + $box->volume;
        if ($totalVolume > $containerVolume) {
            return false;
        }

        // Cek dimensi
        if (($position['x'] + $box->panjang) > 16 || 
            ($position['y'] + $box->lebar) > 1 || 
            ($position['z'] + $box->tinggi) > 1) {
            return false;
        }

        return true;
    }

    private function getNextPosition($currentPosition, $box)
    {
        // Logika sederhana: geser ke kanan
        $nextX = $currentPosition['x'] + $box->panjang;
        
        // Jika mencapai batas container, pindah ke baris berikutnya
        if ($nextX > 16) {
            return [
                'x' => 0,
                'y' => $currentPosition['y'] + $box->lebar,
                'z' => $currentPosition['z']
            ];
        }

        return [
            'x' => $nextX,
            'y' => $currentPosition['y'],
            'z' => $currentPosition['z']
        ];
    }
}