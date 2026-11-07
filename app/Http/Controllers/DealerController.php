<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Services\RegionResolver;
use Illuminate\Http\Request;

/**
 * Dealer dan MOQ (Minimal Order Quantity per dealer, dalam m³, diisi manual).
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
            'total' => Dealer::count(),
        ]);
    }

    public function updateMoq(Request $request)
    {
        // Terima koma sebagai pemisah desimal ("0,5" sama dengan "0.5")
        $request->merge(['moq' => array_map(
            fn ($v) => is_string($v) ? str_replace(',', '.', trim($v)) : $v,
            (array) $request->input('moq', [])
        )]);

        $data = $request->validate([
            'moq' => 'required|array',
            'moq.*' => 'nullable|numeric|min:0|max:100000',
        ], [
            'moq.*.numeric' => 'MOQ harus berupa angka.',
            'moq.*.min' => 'MOQ tidak boleh negatif.',
        ]);

        $changed = 0;
        foreach ($data['moq'] as $code => $value) {
            $dealer = Dealer::where('code', $code)->first();
            if (! $dealer) {
                continue;
            }
            $new = ($value === null || $value === '') ? null : (float) str_replace(',', '.', (string) $value);
            if ($new !== $dealer->moq_m3) {
                $dealer->update(['moq_m3' => $new]);
                $changed++;
            }
        }

        return back()->with('status', $changed > 0 ? "MOQ disimpan untuk {$changed} dealer." : 'Tidak ada perubahan MOQ.');
    }
}
