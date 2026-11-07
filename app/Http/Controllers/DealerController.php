<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Services\RegionResolver;
use App\Services\RouteApplier;
use App\Services\RouteResolver;
use Illuminate\Http\Request;

/**
 * Dealer: MOQ (m³, diisi manual) dan penanda Same Day (dealer terpilih Jalur 8).
 */
class DealerController extends Controller
{
    public function index(Request $request, RegionResolver $regions)
    {
        Dealer::syncFromBoxes(); // dealer baru dari kloter otomatis masuk daftar

        $q = trim((string) $request->query('q', ''));
        $filter = $request->query('moq'); // 'unset' = MOQ belum diisi

        $dealers = Dealer::query()
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
            }))
            ->when($filter === 'unset', fn ($query) => $query->whereNull('moq_m3'))
            ->when($filter === 'sameday', fn ($query) => $query->where('same_day', true))
            ->orderBy('code')
            ->paginate(30)
            ->withQueryString();

        $labels = config('logistics.regions');
        $dealers->getCollection()->transform(function ($d) use ($regions, $labels) {
            $d->region = $regions->regionOf($d->code);
            $d->region_label = $d->region ? ($labels[$d->region] ?? $d->region) : null;
            $d->mode = $regions->modeOfRegion($d->region);

            return $d;
        });

        return view('dealers.index', [
            'dealers' => $dealers,
            'q' => $q,
            'filter' => $filter,
            'unsetCount' => Dealer::whereNull('moq_m3')->count(),
            'sameDayCount' => Dealer::where('same_day', true)->count(),
            'total' => Dealer::count(),
        ]);
    }

    /** Tambah dealer secara manual (mis. dealer Same Day yang belum pernah muncul di file order). */
    public function store(Request $request, RouteApplier $applier)
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'regex:/^[A-Z]{1,6}\s*-?\s*\d{1,6}$/', 'unique:dealers,code'],
            'name' => 'nullable|string|max:120',
        ], [
            'code.required' => 'Kode dealer wajib diisi.',
            'code.regex' => 'Kode dealer berformat huruf area lalu angka, contoh: SA38.',
            'code.unique' => 'Kode dealer ini sudah terdaftar.',
        ]);

        $dealer = Dealer::create($data + ['same_day' => $request->boolean('same_day')]);
        if ($dealer->same_day) {
            $applier->reapplyAll();
        }

        return redirect()->route('dealers.index')->with('status', "Dealer {$dealer->code} ditambahkan.");
    }

    /** Simpan MOQ dan penanda Same Day untuk dealer yang tampil di halaman. */
    public function updateMoq(Request $request, RouteApplier $applier)
    {
        // Terima koma sebagai pemisah desimal ("0,5" sama dengan "0.5")
        $request->merge(['moq' => array_map(
            fn ($v) => is_string($v) ? str_replace(',', '.', trim($v)) : $v,
            (array) $request->input('moq', [])
        )]);

        $data = $request->validate([
            'moq' => 'required|array',
            'moq.*' => 'nullable|numeric|min:0|max:100000',
            'same_day' => 'nullable|array',
        ], [
            'moq.*.numeric' => 'MOQ harus berupa angka.',
            'moq.*.min' => 'MOQ tidak boleh negatif.',
        ]);

        $sameDayChecked = array_keys($data['same_day'] ?? []);
        $moqChanged = 0;
        $sameDayChanged = 0;

        foreach ($data['moq'] as $code => $value) {
            $dealer = Dealer::where('code', $code)->first();
            if (! $dealer) {
                continue;
            }

            $new = ($value === null || $value === '') ? null : (float) $value;
            $dealer->moq_m3 = $new;
            // Semua dealer yang tampil dikirim bersama (kolom MOQ), jadi yang tidak dicentang berarti dimatikan
            $dealer->same_day = in_array($code, $sameDayChecked, true);

            if ($dealer->isDirty('moq_m3')) {
                $moqChanged++;
            }
            if ($dealer->isDirty('same_day')) {
                $sameDayChanged++;
            }
            $dealer->save();
        }

        if ($sameDayChanged > 0) {
            $applier->reapplyAll(); // Same Day mengubah jalur (Jalur 8) pada kloter yang sudah ada
        }

        $parts = [];
        if ($moqChanged > 0) {
            $parts[] = "MOQ {$moqChanged} dealer";
        }
        if ($sameDayChanged > 0) {
            $parts[] = "Same Day {$sameDayChanged} dealer";
        }

        return back()->with('status', $parts ? 'Disimpan: ' . implode(' dan ', $parts) . '.' : 'Tidak ada perubahan.');
    }
}
