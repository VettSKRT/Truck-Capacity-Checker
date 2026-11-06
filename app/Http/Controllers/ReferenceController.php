<?php

namespace App\Http\Controllers;

use App\Models\Carton;
use App\Models\Vehicle;

class ReferenceController extends Controller
{
    public function index()
    {
        return view('reference.index', [
            'vehicles' => Vehicle::orderBy('id')->get(),
            'cartons' => Carton::orderBy('number')->get(),
        ]);
    }
}
