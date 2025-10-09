<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\SystemSetting;
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
            
            // Puxar configurações do sistema
            $settings = [
                // General
                'app_name' => SystemSetting::get('app_name', 'SOSERP'),
                'app_description' => SystemSetting::get('app_description', 'Sistema ERP Multi-tenant'),
                'app_url' => SystemSetting::get('app_url', config('app.url')),
                'contact_email' => SystemSetting::get('contact_email', 'contato@soserp.vip'),
                'contact_phone' => SystemSetting::get('contact_phone', '+244 939 779 902'),
                
                // Appearance
                'app_logo' => SystemSetting::get('app_logo'),
                'app_favicon' => SystemSetting::get('app_favicon'),
                
                // SEO
                'seo_title' => SystemSetting::get('seo_title', 'SOSERP - Sistema de Gestão Empresarial'),
                'seo_description' => SystemSetting::get('seo_description'),
                'seo_keywords' => SystemSetting::get('seo_keywords'),
                
                // Schema.org
                'schema_app_name' => SystemSetting::get('schema_app_name', 'SOSERP'),
                'schema_app_description' => SystemSetting::get('schema_app_description'),
                'schema_app_url' => SystemSetting::get('schema_app_url', 'https://soserp.vip'),
                'schema_app_category' => SystemSetting::get('schema_app_category', 'BusinessApplication'),
                'schema_price' => SystemSetting::get('schema_price', '0'),
                'schema_currency' => SystemSetting::get('schema_currency', 'AOA'),
                'schema_region' => SystemSetting::get('schema_region', 'Angola'),
                'schema_rating_value' => SystemSetting::get('schema_rating_value', '4.8'),
                'schema_review_count' => SystemSetting::get('schema_review_count', '150'),
                'schema_creator_name' => SystemSetting::get('schema_creator_name', 'SOSERP'),
                'schema_creator_url' => SystemSetting::get('schema_creator_url', 'https://soserp.vip'),
            ];
            
            return view('landing.home', compact('plans', 'settings'));
        } catch (\Exception $e) {
            \Log::error('Erro na landing page: ' . $e->getMessage());
            return view('landing.home', [
                'plans' => collect([]),
                'settings' => [
                    'app_name' => 'SOSERP',
                    'app_logo' => null,
                ]
            ]);
        }
    }
}
