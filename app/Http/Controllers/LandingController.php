<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function home()
    {
        $plans = Plan::where('is_active', true)
                     ->orderBy('order')
                     ->get();
                     
        return view('landing.home', compact('plans'));
    }
}
