<?php

/**
 * Script para atualizar planos existentes com max_companies
 * Execute: php UPDATE_PLANS.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Plan;

echo "🔄 Atualizando planos com campo max_companies...\n\n";

$updates = [
    'starter' => 1,
    'professional' => 3,
    'business' => 10,
    'enterprise' => 999,
];

foreach ($updates as $slug => $maxCompanies) {
    $plan = Plan::where('slug', $slug)->first();
    
    if ($plan) {
        $plan->max_companies = $maxCompanies;
        $plan->save();
        
        echo "✅ {$plan->name}: max_companies = {$maxCompanies}\n";
    } else {
        echo "⚠️  Plano '{$slug}' não encontrado\n";
    }
}

echo "\n✅ Planos atualizados com sucesso!\n";
echo "\n📊 Verificação:\n";

$plans = Plan::all();
foreach ($plans as $plan) {
    echo "   - {$plan->name}: {$plan->max_companies} empresa(s)\n";
}

echo "\n";
