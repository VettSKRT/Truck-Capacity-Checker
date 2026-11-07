<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\Upload;
use App\Models\Vehicle;
use App\Services\RouteLoadService;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Penjadwalan: jadwal kapal (diinput manual) dan pengiriman harian.
 */
class ScheduleController extends Controller
{
    public function __construct(private ScheduleService $schedule, private RouteLoadService $loads)
    {
    }

    public function index()
    {
        $today = now()->startOfDay();

        $shipments = Shipment::with('vehicle')->withCount('uploads')->orderBy('departs_on')->get();
        $upcoming = $shipments->filter(fn ($s) => $s->isActive() && $s->departs_on >= $today)->values();
        $others = $shipments->reject(fn ($s) => $upcoming->contains($s))->sortByDesc('departs_on')->values();

        $summaries = [];
        foreach ($upcoming as $s) {
            $summaries[$s->id] = $this->schedule->analyzeShipment($s);
        }

        [$waiting, $daily, $undecided] = $this->classifyUnscheduled();

        return view('schedule.index', compact('upcoming', 'others', 'summaries', 'waiting', 'daily', 'undecided'));
    }

    public function show(Shipment $shipment)
    {
        $shipment->load('vehicle');

        $uploads = $shipment->uploads()->withSummary()->latest('id')->get();

        return view('schedule.show', [
            'shipment' => $shipment,
            'analysis' => $this->schedule->analyzeShipment($shipment),
            'uploads' => $uploads,
            'summaries' => $this->loads->summarizeMany($uploads->pluck('id')),
            'available' => $this->classifyUnscheduled()[0],
        ]);
    }

    public function create()
    {
        return view('schedule.form', [
            'shipment' => new Shipment([
                'departs_on' => now()->addDays(7),
                'status' => Shipment::PLANNED,
                'is_tentative' => true,
                'vehicle_id' => Vehicle::where('code', 'IMPOR')->value('id'),
            ]),
            'vehicles' => Vehicle::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $shipment = Shipment::create($this->validated($request));

        return redirect()->route('schedule.show', $shipment)->with('status', "Jadwal {$shipment->code} dibuat. Tautkan kloter di bawah ini.");
    }

    public function edit(Shipment $shipment)
    {
        return view('schedule.form', ['shipment' => $shipment, 'vehicles' => Vehicle::orderBy('id')->get()]);
    }

    public function update(Request $request, Shipment $shipment)
    {
        $shipment->update($this->validated($request));

        return redirect()->route('schedule.show', $shipment)->with('status', "Jadwal {$shipment->code} diperbarui.");
    }

    public function destroy(Shipment $shipment)
    {
        $code = $shipment->code;
        $shipment->delete(); // kloter yang tertaut kembali menjadi "belum dijadwalkan"

        return redirect()->route('schedule.index')->with('status', "Jadwal {$code} dihapus. Kloter yang tertaut kembali belum dijadwalkan.");
    }

    public function attach(Request $request, Shipment $shipment)
    {
        $data = $request->validate(['upload_id' => 'required|integer|exists:uploads,id']);
        $upload = Upload::findOrFail($data['upload_id']);

        if (! $shipment->isActive()) {
            return back()->withErrors(['upload_id' => 'Jadwal ini sudah ' . strtolower($shipment->status_label) . ', tidak bisa menerima kloter baru.']);
        }

        $upload->update(['shipment_id' => $shipment->id]);

        return back()->with('status', "Kloter {$upload->code} ditautkan ke jadwal {$shipment->code}.");
    }

    public function detach(Shipment $shipment, Upload $upload)
    {
        abort_unless($upload->shipment_id === $shipment->id, 404);

        $upload->update(['shipment_id' => null]);

        return back()->with('status', "Kloter {$upload->code} dilepas dari jadwal {$shipment->code}.");
    }

    /**
     * Kloter yang belum punya jadwal, dibagi menurut jenis jadwalnya.
     *
     * @return array{0:\Illuminate\Support\Collection,1:\Illuminate\Support\Collection,2:\Illuminate\Support\Collection}
     */
    private function classifyUnscheduled(): array
    {
        $uploads = Upload::whereNull('shipment_id')->withSummary()->latest('id')->limit(60)->get();
        $summaries = $this->loads->summarizeMany($uploads->pluck('id'));

        $waiting = collect();   // perlu jadwal kapal
        $daily = collect();     // pengiriman harian
        $undecided = collect(); // wilayah dealer belum diatur

        foreach ($uploads as $u) {
            $a = $this->schedule->analyzeUpload($u);
            $row = ['upload' => $u, 'analysis' => $a, 'overall' => $summaries[$u->id]['overall']];

            match ($a['mode']) {
                'kapal', 'campuran' => $waiting->push($row),
                'daily' => $daily->push($row),
                'belum_diatur' => $undecided->push($row),
                default => null,
            };
        }

        return [$waiting, $daily, $undecided];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'departs_on' => 'required|date',
            'destination' => 'required|string|max:120',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'status' => ['required', Rule::in(array_keys(Shipment::STATUSES))],
            'is_tentative' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ], [
            'departs_on.required' => 'Tanggal berangkat wajib diisi.',
            'destination.required' => 'Tujuan wajib diisi.',
        ]);

        $data['is_tentative'] = $request->boolean('is_tentative');

        return $data;
    }
}
