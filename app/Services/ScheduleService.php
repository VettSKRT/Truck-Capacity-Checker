<?php

namespace App\Services;

use App\Models\Box;
use App\Models\Dealer;
use App\Models\Shipment;
use App\Models\Upload;
use Illuminate\Support\Carbon;

/**
 * Analisis penjadwalan:
 *  - kloter: jenis jadwal (harian atau kapal) dan rencana kirim dari dealer di dalamnya
 *  - jadwal kapal: volume per dealer dibanding MOQ, serta kapasitas kontainer
 */
class ScheduleService
{
    public function __construct(
        private RegionResolver $regions,
        private LoadCalculator $calculator,
    ) {
    }

    /**
     * @return array{
     *   mode:string, dealers:array<int,array>, needs_shipment:bool, send_date:?Carbon, send_label:?string,
     *   has_unknown:bool, kapal_count:int, daily_count:int
     * }
     * mode: kosong | daily | kapal | campuran | belum_diatur
     */
    public function analyzeUpload(Upload $upload): array
    {
        $boxes = Box::where('upload_id', $upload->id)->get();
        $dealers = $this->dealerRows($boxes);

        $kapal = collect($dealers)->where('mode', 'kapal')->count();
        $daily = collect($dealers)->where('mode', 'daily')->count();
        $unknown = collect($dealers)->whereNull('mode')->count();

        $mode = match (true) {
            empty($dealers) => 'kosong',
            $unknown > 0 && $kapal === 0 && $daily === 0 => 'belum_diatur',
            $kapal > 0 && $daily > 0 => 'campuran',
            $kapal > 0 => 'kapal',
            $daily > 0 => 'daily',
            default => 'belum_diatur',
        };

        // Rencana kirim harian: Same Day hari ini, selain itu besok (lihat config daily_lead_days)
        $sendDate = null;
        $sendLabel = null;
        if (in_array($mode, ['daily', 'campuran'], true) && $boxes->isNotEmpty()) {
            $lead = config('logistics.daily_lead_days');
            $days = $boxes->map(fn ($b) => $lead[$b->service_type] ?? 1)->min();
            $sendDate = $upload->created_at->copy()->startOfDay()->addDays($days);
            $sendLabel = $days === 0 ? 'Hari yang sama (Same Day)' : 'Keesokan harinya (One Day / Emergency)';
        }

        return [
            'mode' => $mode,
            'dealers' => $dealers,
            'needs_shipment' => in_array($mode, ['kapal', 'campuran'], true) && ! $upload->shipment_id,
            'send_date' => $sendDate,
            'send_label' => $sendLabel,
            'has_unknown' => $unknown > 0,
            'kapal_count' => $kapal,
            'daily_count' => $daily,
        ];
    }

    /**
     * Ringkasan sebuah jadwal kapal: volume Cargo Ready per dealer vs MOQ, dan kapasitas.
     *
     * @return array{dealers:array<int,array>, met:int, not_met:int, unset:int, moq_ok:bool, totals:array, capacity:?array, uploads:int}
     */
    public function analyzeShipment(Shipment $shipment): array
    {
        $uploadIds = $shipment->uploads()->pluck('id');
        $boxes = Box::whereIn('upload_id', $uploadIds)->get();

        $ready = (int) config('logistics.cargo_ready_status', 4);
        $dealers = $this->dealerRows($boxes);
        $moqs = Dealer::whereIn('code', array_column($dealers, 'code'))->pluck('moq_m3', 'code');

        foreach ($dealers as &$d) {
            $moq = $moqs[$d['code']] ?? null;
            $eval = MoqEvaluator::evaluate($d['volume'], $moq !== null ? (float) $moq : null);
            $d['moq'] = $eval['moq'];
            $d['moq_met'] = $eval['met'];
            $d['shortfall'] = $eval['shortfall'];
        }
        unset($d);

        $totalVolume = array_sum(array_column($dealers, 'volume'));
        $totalWeight = array_sum(array_column($dealers, 'weight'));

        $capacity = null;
        if ($shipment->vehicle) {
            $capacity = $this->calculator->summarize($totalVolume, $totalWeight, $shipment->vehicle->volume_m3, $shipment->vehicle->max_payload_kg);
        }

        // Perbandingan ketat: null (MOQ belum diisi) tidak boleh dihitung sebagai "tidak memenuhi"
        $met = collect($dealers)->filter(fn ($d) => $d['moq_met'] === true)->count();
        $notMet = collect($dealers)->filter(fn ($d) => $d['moq_met'] === false)->count();
        $unset = collect($dealers)->filter(fn ($d) => $d['moq_met'] === null)->count();

        return [
            'dealers' => $dealers,
            'met' => $met,
            'not_met' => $notMet,
            'unset' => $unset,
            'moq_ok' => ! empty($dealers) && $notMet === 0 && $unset === 0,
            'totals' => ['volume' => $totalVolume, 'weight' => $totalWeight, 'cartons' => array_sum(array_column($dealers, 'cartons'))],
            'capacity' => $capacity,
            'uploads' => $uploadIds->count(),
        ];
    }

    /**
     * Kelompokkan box per dealer. Volume dan berat hanya menghitung Cargo Ready (status 4).
     *
     * @return array<int,array{code:string,name:?string,region:?string,region_label:?string,mode:?string,volume:float,weight:float,cartons:int}>
     */
    private function dealerRows($boxes): array
    {
        $ready = (int) config('logistics.cargo_ready_status', 4);
        $labels = config('logistics.regions');

        return $boxes->groupBy('customer_code')->map(function ($rows, $code) use ($ready, $labels) {
            $region = $this->regions->regionOf($code);
            $readyRows = $rows->where('status', $ready);

            return [
                'code' => (string) $code,
                'name' => $rows->first()->customer_name,
                'region' => $region,
                'region_label' => $region !== null ? ($labels[$region] ?? $region) : null,
                'mode' => $this->regions->modeOfRegion($region),
                'volume' => (float) $readyRows->sum('volume'),
                'weight' => (float) $readyRows->sum('weight_kg'),
                'cartons' => (int) $readyRows->sum('box_count'),
            ];
        })->sortBy('code')->values()->all();
    }
}
