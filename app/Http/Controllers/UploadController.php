<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Models\Box;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use App\Imports\CargoImport;

class UploadController extends Controller
{
    public function index()
    {
        return view('upload'); 
    }

    public function upload(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);
    
        // Simpan file fisik
        $path = $request->file('excel_file')->store('uploads');
        $filename = $request->file('excel_file')->getClientOriginalName();
    
        // Baca isi file
        $data = Excel::toCollection(new CargoImport, $request->file('excel_file'))->first();
    
        $volumeTruk = 16;
        $totalVolume = 0;
        $items = [];
    
        $upload = Upload::create([
            'filename' => $filename,
            'filepath' => $path,
            'total_volume' => 0,
            'ratio' => 0,
            'status' => 'Processing'
        ]);
    
        foreach ($data as $row) {
            // Ambil data dari kolom Excel berdasarkan posisi kolom
            $cargoDestination = $row[0];
            $customerCode = $row[1];
            $customerName = $row[2];
            $p = (int) $row[3];
            $l = (int) $row[4];
            $t = (int) $row[5];
            $status = (int) $row[6];
        
            $volume = ($p / 1000) * ($l / 1000) * ($t / 1000);
        
            if ($status === 4) {
                $totalVolume += $volume;
            }
        
            $box = Box::create([
                'upload_id' => $upload->id,
                'cargo_destination' => $cargoDestination,
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'panjang' => $p,
                'lebar' => $l,
                'tinggi' => $t,
                'status' => $status,
                'volume' => $volume,
            ]);

            // Convert to meters for frontend
            $items[] = [
                'id' => $box->id,
                'cargo_destination' => $cargoDestination,
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'panjang' => $p / 1000, // Convert to meters
                'lebar' => $l / 1000,   // Convert to meters
                'tinggi' => $t / 1000,  // Convert to meters
                'status' => $status,
                'volume' => $volume,
            ];
        }
        
        $ratio = $totalVolume / $volumeTruk;
        $upload->update([
            'total_volume' => $totalVolume,
            'ratio' => $ratio,
            'status' => $ratio >= 0.8 ? 'Siap Dikirim' : 'Belum Siap'
        ]);

        // Check if request is AJAX (from packing page)
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'items' => $items,
                'message' => 'Excel data loaded successfully'
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
