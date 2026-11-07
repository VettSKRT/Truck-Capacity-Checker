<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\Shipment;
use App\Models\Upload;
use App\Services\ScheduleService;

class DashboardController extends Controller
{
    public function index(ScheduleService $schedule)
    {
        $today = now()->startOfDay();

        $todayBatches = Upload::where('created_at', '>=', $today);
        $readyToday = (clone $todayBatches)->ready()->count();
        $totalToday = (clone $todayBatches)->count();

        $stats = [
            'batches_today' => $totalToday,
            'ready_today' => $readyToday,
            'wait_today' => $totalToday - $readyToday,
            'cartons_today' => (int) Box::where('created_at', '>=', $today)->sum('box_count'),
            'batches_total' => Upload::count(),
        ];

        $recent = Upload::with('vehicle')->withSummary()->latest()->limit(8)->get();

        // Jadwal kapal terdekat dan kloter yang masih menunggu jadwal kapal
        $nextShipments = Shipment::with('vehicle')->withCount('uploads')
            ->where('status', Shipment::PLANNED)->where('departs_on', '>=', $today)
            ->orderBy('departs_on')->limit(3)->get();

        $waitingForShipment = Upload::whereNull('shipment_id')->latest('id')->limit(60)->get()
            ->filter(fn ($u) => $schedule->analyzeUpload($u)['needs_shipment'])->count();

        return view('dashboard', compact('stats', 'recent', 'nextShipments', 'waitingForShipment'));
    }
}
