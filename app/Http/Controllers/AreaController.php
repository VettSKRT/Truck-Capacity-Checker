<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Dealer;
use App\Models\RouteSetting;
use App\Models\Vehicle;
use App\Services\RouteApplier;
use App\Services\RouteResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pengaturan area: kode area -> jalur (1-7) dan wilayah.
 * Setiap perubahan langsung dihitung ulang ke semua kloter (lihat RouteApplier).
 */
class AreaController extends Controller
{
    public function index(RouteResolver $routes)
    {
        Dealer::syncFromBoxes();

        $areas = Area::orderBy('code')->get();

        // Kode area yang muncul pada kode dealer tetapi belum ada di daftar
        $known = $areas->pluck('code')->all();
        $unknown = Dealer::pluck('code')
            ->map(fn ($code) => $routes->parse($code)['area'])
            ->filter()
            ->unique()
            ->reject(fn ($area) => in_array($area, $known, true))
            ->sort()
            ->values();

        return view('areas.index', [
            'routeSettings' => RouteSetting::ensureAll(),
            'vehicles' => Vehicle::orderBy('id')->get(),
            'areas' => $areas,
            'unknown' => $unknown,
            'regions' => config('logistics.regions'),
            'regionSchedule' => config('logistics.region_schedule'),
        ]);
    }

    /** Nama tujuan dan truk default tiap jalur (satu jalur = satu truk ke satu tujuan). */
    public function updateRoutes(Request $request)
    {
        $data = $request->validate([
            'routes' => 'required|array',
            'routes.*.name' => 'nullable|string|max:60',
            'routes.*.vehicle_id' => 'nullable|integer|exists:vehicles,id',
        ]);

        $changed = 0;
        foreach ($data['routes'] as $number => $values) {
            if (! in_array((int) $number, range(1, 8), true)) {
                continue;
            }
            $setting = RouteSetting::firstOrCreate(['route' => (int) $number]);
            $setting->fill([
                'name' => ($values['name'] ?? '') !== '' ? $values['name'] : null,
                'vehicle_id' => ($values['vehicle_id'] ?? '') !== '' ? (int) $values['vehicle_id'] : null,
            ]);
            if ($setting->isDirty()) {
                $setting->save();
                $changed++;
            }
        }

        return back()->with('status', $changed > 0 ? "Pengaturan {$changed} jalur disimpan." : 'Tidak ada perubahan.');
    }

    public function store(Request $request, RouteApplier $applier)
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'regex:/^[A-Z]{1,6}$/', 'unique:areas,code'],
            'name' => 'nullable|string|max:80',
            'route' => 'nullable|integer|between:1,7',
            'region' => ['nullable', Rule::in(array_keys(config('logistics.regions')))],
        ], $this->messages());

        Area::create($data);
        $applier->reapplyAll();

        return redirect()->route('areas.index')->with('status', "Area {$data['code']} ditambahkan.");
    }

    public function update(Request $request, RouteApplier $applier)
    {
        $data = $request->validate([
            'areas' => 'required|array',
            'areas.*.name' => 'nullable|string|max:80',
            'areas.*.route' => 'nullable|integer|between:1,7',
            'areas.*.region' => ['nullable', Rule::in(array_keys(config('logistics.regions')))],
        ], $this->messages());

        $changed = 0;
        foreach ($data['areas'] as $id => $values) {
            $area = Area::find($id);
            if (! $area) {
                continue;
            }
            $area->fill([
                'name' => ($values['name'] ?? '') !== '' ? $values['name'] : null,
                'route' => ($values['route'] ?? '') !== '' ? (int) $values['route'] : null,
                'region' => ($values['region'] ?? '') !== '' ? $values['region'] : null,
            ]);
            if ($area->isDirty()) {
                $area->save();
                $changed++;
            }
        }

        $moved = $changed > 0 ? $applier->reapplyAll() : 0;

        return back()->with('status', $changed > 0
            ? "Pengaturan {$changed} area disimpan. Jalur diperbarui pada {$moved} baris karton di riwayat."
            : 'Tidak ada perubahan.');
    }

    public function destroy(Area $area, RouteApplier $applier)
    {
        $code = $area->code;
        $area->delete();
        $applier->reapplyAll();

        return redirect()->route('areas.index')->with('status', "Area {$code} dihapus.");
    }

    /** Tambahkan semua kode area yang terdeteksi dari dealer tetapi belum terdaftar (jalur dan wilayah dikosongkan). */
    public function detect(RouteResolver $routes)
    {
        Dealer::syncFromBoxes();

        $known = Area::pluck('code')->all();
        $added = Dealer::pluck('code')
            ->map(fn ($code) => $routes->parse($code)['area'])
            ->filter()->unique()
            ->reject(fn ($area) => in_array($area, $known, true));

        foreach ($added as $code) {
            Area::create(['code' => $code]);
        }

        return back()->with('status', $added->isEmpty()
            ? 'Tidak ada kode area baru.'
            : $added->count() . ' kode area ditambahkan: ' . $added->implode(', ') . '. Lengkapi jalur dan wilayahnya.');
    }

    private function messages(): array
    {
        return [
            'code.required' => 'Kode area wajib diisi.',
            'code.regex' => 'Kode area hanya berisi huruf (maksimal 6), contoh: SA.',
            'code.unique' => 'Kode area ini sudah ada.',
            'areas.*.route.between' => 'Jalur area hanya 1 sampai 7 (Jalur 8 khusus dealer Same Day).',
            'route.between' => 'Jalur area hanya 1 sampai 7 (Jalur 8 khusus dealer Same Day).',
        ];
    }
}
