<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\Shipment;
use App\Models\Upload;
use App\Services\RouteLoadService;
use App\Services\ScheduleService;

class DashboardController extends Controller
{
    public function index(ScheduleService $schedule, RouteLoadService $loads)
    {
        $today = now()->startOfDay();

        $todayIds = Upload::where('created_at', '>=', $today)->pluck('id');
        $todaySummaries = $loads->summarizeMany($todayIds);

        // Status dihitung per jalur (satu jalur = satu truk), bukan per kloter
        $routeTotals = ['ready' => 0, 'wait' => 0, 'over' => 0];
        foreach ($todaySummaries as $s) {
            $routeTotals['ready'] += $s['overall']['ready'];
            $routeTotals['wait'] += $s['overall']['wait'];
            $routeTotals['over'] += $s['overall']['over'];
        }

        $stats = [
            'batches_today' => $todayIds->count(),
            'routes_ready' => $routeTotals['ready'],
            'routes_attention' => $routeTotals['wait'] + $routeTotals['over'],
            'routes_over' => $routeTotals['over'],
            'cartons_today' => (int) Box::where('created_at', '>=', $today)->sum('box_count'),
            'batches_total' => Upload::count(),
        ];

        $recent = Upload::withSummary()->latest()->limit(8)->get();
        $summaries = $loads->summarizeMany($recent->pluck('id'));

        // Jadwal kapal terdekat dan kloter yang masih menunggu jadwal kapal
        $nextShipments = Shipment::with('vehicle')->withCount('uploads')
            ->where('status', Shipment::PLANNED)->where('departs_on', '>=', $today)
            ->orderBy('departs_on')->limit(3)->get();

        $waitingForShipment = Upload::whereNull('shipment_id')->latest('id')->limit(60)->get()
            ->filter(fn ($u) => $schedule->analyzeUpload($u)['needs_shipment'])->count();

        $trend = $this->cargoReadyTrend(14);
        $alerts = $this->alerts($todayIds, $todaySummaries, $waitingForShipment);

        return view('dashboard', compact('stats', 'recent', 'summaries', 'nextShipments', 'waitingForShipment', 'trend', 'alerts'));
    }

    /**
     * Muatan Cargo Ready (status 4) per hari untuk grafik area dashboard.
     *
     * @return array{labels:array<int,string>, dates:array<int,string>, volume:array<int,float>, weight:array<int,float>, cartons:array<int,int>}
     */
    private function cargoReadyTrend(int $days): array
    {
        $start = now()->startOfDay()->subDays($days - 1);
        $ready = (int) config('logistics.cargo_ready_status', 4);

        $rows = Box::where('created_at', '>=', $start)->where('status', $ready)
            ->selectRaw('date(created_at) as d, sum(volume) as vol, sum(weight_kg) as wt, sum(box_count) as cartons')
            ->groupBy('d')->get()
            ->keyBy(fn ($r) => substr((string) $r->d, 0, 10));

        $trend = ['labels' => [], 'dates' => [], 'volume' => [], 'weight' => [], 'cartons' => []];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $row = $rows[$day->toDateString()] ?? null;
            $trend['dates'][] = $day->toDateString();
            $trend['labels'][] = $day->translatedFormat('d M');
            $trend['volume'][] = round((float) ($row->vol ?? 0), 3);
            $trend['weight'][] = round((float) ($row->wt ?? 0), 1);
            $trend['cartons'][] = (int) ($row->cartons ?? 0);
        }

        return $trend;
    }

    /**
     * Daftar peringatan untuk panel kanan dashboard: jalur hari ini yang melebihi truk (merah),
     * karton tanpa jalur dan kloter yang menunggu jadwal kapal (kuning).
     *
     * @return array<int,array{level:string, icon:string, title:string, text:string, url:?string, link:?string}>
     */
    private function alerts($todayIds, array $todaySummaries, int $waitingForShipment): array
    {
        $codes = Upload::whereIn('id', $todayIds)->get()->mapWithKeys(fn ($u) => [$u->id => $u->code]);
        $fmt = fn ($n, $d = 0) => number_format($n, $d, ',', '.');
        $alerts = [];

        foreach ($todaySummaries as $uploadId => $s) {
            foreach ($s['routes'] as $r) {
                if ($r['state'] === 'over') {
                    $alerts[] = [
                        'level' => 'over',
                        'icon' => 'bi-exclamation-octagon-fill',
                        'title' => ($codes[$uploadId] ?? 'Kloter') . ', ' . $r['label'] . ' melebihi truk',
                        'text' => $fmt($r['ratio'] * 100, 1) . '% dari ' . ($r['vehicle']['name'] ?? 'truk')
                            . ($r['binding'] === 'weight' ? ' (berat)' : ' (volume)') . '. Kurangi muatan atau ganti truk.',
                        'url' => route('batches.show', $uploadId),
                        'link' => 'Buka kloter',
                    ];
                }
            }
            if (($s['overall']['unrouted_cartons'] ?? 0) > 0) {
                $alerts[] = [
                    'level' => 'wait',
                    'icon' => 'bi-signpost-split',
                    'title' => ($codes[$uploadId] ?? 'Kloter') . ': ' . $fmt($s['overall']['unrouted_cartons']) . ' karton tanpa jalur',
                    'text' => 'Kode area dealernya belum dipetakan ke jalur.',
                    'url' => route('areas.index'),
                    'link' => 'Atur area dan jalur',
                ];
            }
        }

        if ($waitingForShipment > 0) {
            $alerts[] = [
                'level' => 'wait',
                'icon' => 'bi-water',
                'title' => $waitingForShipment . ' kloter menunggu jadwal kapal',
                'text' => 'Berisi dealer luar pulau atau Aceh dan belum ditautkan ke jadwal kapal.',
                'url' => route('schedule.index'),
                'link' => 'Buka Penjadwalan',
            ];
        }

        // Merah lebih dulu
        usort($alerts, fn ($a, $b) => ($a['level'] === 'over' ? 0 : 1) <=> ($b['level'] === 'over' ? 0 : 1));

        return $alerts;
    }
}
