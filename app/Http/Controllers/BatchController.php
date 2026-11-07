<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\Shipment;
use App\Models\Upload;
use App\Services\RouteLoadService;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

/**
 * Riwayat kloter. Satu kloter = satu kali unggah file order.
 * Satu kloter berisi beberapa jalur; satu jalur = satu truk khusus ke satu tujuan.
 */
class BatchController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request, RouteLoadService $loads)
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => $request->query('status'),
            'route' => $request->query('route'),
            'date' => $request->query('date'),
        ];

        $query = Upload::withSummary()->latest('id');

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
        if ($filters['date'] && strtotime($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }

        $uploads = $query->get();

        // Status kloter dihitung per jalur, jadi penyaringan status dan jalur dilakukan setelah ringkasan dihitung
        $summaries = $loads->summarizeMany($uploads->pluck('id'));

        $uploads = $uploads->filter(function ($u) use ($summaries, $filters) {
            $s = $summaries[$u->id];
            if (in_array($filters['status'], ['ready', 'partial', 'wait', 'over'], true) && $s['overall']['state'] !== $filters['status']) {
                return false;
            }
            if ($filters['route'] && ! collect($s['routes'])->contains('key', (string) $filters['route'])) {
                return false;
            }

            return true;
        })->values();

        $page = max(1, (int) $request->query('page', 1));
        $batches = new LengthAwarePaginator(
            $uploads->forPage($page, self::PER_PAGE)->values(),
            $uploads->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('batches.index', [
            'batches' => $batches,
            'summaries' => $summaries,
            'filters' => $filters,
        ]);
    }

    public function show(Upload $upload, ScheduleService $schedule)
    {
        $upload->load('shipment');
        $upload->loadSum('boxes as carton_total', 'box_count');

        return view('batches.show', [
            'upload' => $upload,
            'schedule' => $schedule->analyzeUpload($upload),
            'shipments' => Shipment::with('vehicle')->where('status', Shipment::PLANNED)->orderBy('departs_on')->get(),
        ]);
    }

    /** Ringkasan per jalur (JSON) untuk kartu jalur di halaman hasil. */
    public function routes(Upload $upload, RouteLoadService $loads)
    {
        $summary = $loads->summarizeUpload($upload->id);

        return response()->json([
            'success' => true,
            'upload' => ['id' => $upload->id, 'code' => $upload->code, 'filename' => $upload->filename],
            'routes' => $summary['routes'],
            'overall' => $summary['overall'],
        ]);
    }

    /** Halaman cetak rencana muat untuk satu jalur (satu truk). */
    public function loadPlan(Upload $upload, string $route, RouteLoadService $loads)
    {
        abort_unless(preg_match('/^[1-8]$/', $route) === 1, 404);

        $boxes = Box::with('carton')
            ->where('upload_id', $upload->id)->where('route', (int) $route)
            ->orderBy('customer_code')->orderBy('part_number')->orderBy('id')
            ->get();
        abort_if($boxes->isEmpty(), 404);

        $summary = collect($loads->summarizeUpload($upload->id)['routes'])->firstWhere('key', $route);
        $ready = (int) config('logistics.cargo_ready_status', 4);

        $dealers = $boxes->groupBy('customer_code')->map(fn ($rows, $code) => [
            'code' => $code,
            'name' => $rows->first()->customer_name,
            'destination' => $rows->first()->cargo_destination,
            'ready' => $rows->where('status', $ready)->values(),
            'pending' => $rows->where('status', '!=', $ready)->values(),
        ])->values();

        return view('batches.load-plan', compact('upload', 'summary', 'dealers'));
    }

    /** Tautkan kloter ke jadwal kapal, atau lepas (shipment_id kosong). */
    public function schedule(Request $request, Upload $upload)
    {
        $data = $request->validate(['shipment_id' => 'nullable|integer|exists:shipments,id']);

        $upload->update(['shipment_id' => $data['shipment_id'] ?? null]);

        return back()->with('status', $upload->shipment_id
            ? "Kloter {$upload->code} ditautkan ke jadwal J-" . str_pad((string) $upload->shipment_id, 3, '0', STR_PAD_LEFT) . '.'
            : "Kloter {$upload->code} dilepas dari jadwal kapal.");
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
