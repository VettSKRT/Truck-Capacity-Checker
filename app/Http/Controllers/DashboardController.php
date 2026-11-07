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

        return view('dashboard', compact('stats', 'recent', 'summaries', 'nextShipments', 'waitingForShipment'));
    }
}
