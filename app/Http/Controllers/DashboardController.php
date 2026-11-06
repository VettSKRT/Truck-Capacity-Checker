<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\Upload;

class DashboardController extends Controller
{
    public function index()
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

        return view('dashboard', compact('stats', 'recent'));
    }
}
