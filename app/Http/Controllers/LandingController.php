<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function home()
    {
        try {
            $plans = Plan::where('is_active', true)
                         ->orderBy('order')
                         ->get();
            
            // Garantir que plans é uma collection válida
            if (!$plans) {
                $plans = collect([]);
            }
            
            return view('landing.home', compact('plans'));
        } catch (\Exception $e) {
            \Log::error('Erro na landing page: ' . $e->getMessage());
            return view('landing.home', ['plans' => collect([])]);
        }
    }
}
