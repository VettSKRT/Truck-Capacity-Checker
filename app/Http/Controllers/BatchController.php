<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Riwayat kloter. Satu kloter = satu kali unggah file order.
 */
class BatchController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => $request->query('status'),
            'vehicle' => $request->query('vehicle'),
            'date' => $request->query('date'),
        ];

        $query = Upload::with('vehicle')->withSummary()->latest('id');

        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $id = (int) preg_replace('/\D/', '', $term); // "K-0012" atau "12"
            $query->where(function ($w) use ($term, $id) {
                $w->where('filename', 'like', '%' . $term . '%');
                if ($id > 0) {
                    $w->orWhere('id', $id);
                }
            });
        }
        match ($filters['status']) {
            'ready' => $query->ready(),
            'wait' => $query->wait(),
            'over' => $query->over(),
            default => null,
        };
        if ($filters['vehicle']) {
            $query->where('vehicle_id', (int) $filters['vehicle']);
        }
        if ($filters['date'] && strtotime($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }

        return view('batches.index', [
            'batches' => $query->paginate(15)->withQueryString(),
            'vehicles' => Vehicle::orderBy('id')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Upload $upload)
    {
        $upload->load('vehicle');
        $upload->loadSum('boxes as carton_total', 'box_count');

        return view('batches.show', [
            'upload' => $upload,
            'vehicles' => Vehicle::orderBy('id')->get(),
        ]);
    }

    public function destroy(Upload $upload)
    {
        $code = $upload->code;

        if ($upload->filepath) {
            Storage::delete($upload->filepath);
        }
        $upload->delete(); // boxes ikut terhapus (cascade)

        return redirect()->route('batches.index')->with('status', "Kloter {$code} sudah dihapus.");
    }
}
