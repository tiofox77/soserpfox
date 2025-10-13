<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n═══════════════════════════════════════════════════════\n";
echo "  VERIFICAR ENVIO DE EMAIL E LIMPAR DADOS\n";
echo "═══════════════════════════════════════════════════════\n\n";

$email = 'tiofox2019@gmail.com';

// Verificar logs
echo "🔍 VERIFICANDO LOGS DE EMAIL...\n\n";
$logFile = storage_path('logs/laravel.log');
$logs = file_get_contents($logFile);

if (strpos($logs, 'EMAIL DE BOAS-VINDAS ENVIADO') !== false) {
    echo "✅ LOG ENCONTRADO: Email foi enviado!\n\n";
    preg_match_all('/EMAIL DE BOAS-VINDAS.*?tiofox2019/s', $logs, $matches);
    foreach ($matches[0] as $match) {
        echo "   " . substr($match, 0, 200) . "\n";
    }
} elseif (strpos($logs, 'ERRO AO ENVIAR EMAIL') !== false) {
    echo "❌ LOG ENCONTRADO: Erro ao enviar email!\n\n";
    preg_match_all('/ERRO AO ENVIAR EMAIL.*?}/s', $logs, $matches);
    foreach ($matches[0] as $match) {
        echo "   " . substr($match, 0, 300) . "\n\n";
    }
} else {
    echo "⚠️  NENHUM LOG DE EMAIL ENCONTRADO!\n";
    echo "   Isso significa que o código de envio não foi executado.\n\n";
}

// Limpar dados
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🗑️  LIMPANDO DADOS DO USUÁRIO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$user = \App\Models\User::withTrashed()->where('email', $email)->first();

if (!$user) {
    echo "✅ Usuário já está limpo!\n\n";
    exit(0);
}

echo "👤 Usuário: {$user->name} (ID: {$user->id})\n";
echo "🏢 Tenant ID: {$user->tenant_id}\n\n";

DB::beginTransaction();

try {
    if ($user->tenant_id) {
        $tenant = \App\Models\Tenant::withTrashed()->find($user->tenant_id);
        if ($tenant) {
            \DB::table('subscriptions')->where('tenant_id', $tenant->id)->delete();
            \DB::table('orders')->where('tenant_id', $tenant->id)->delete();
            \DB::table('users')->where('tenant_id', $tenant->id)->delete();
            $tenant->forceDelete();
            echo "✅ Tenant deletado\n";
        }
    }
    
    \DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', 'App\\Models\\User')->delete();
    $user->forceDelete();
    echo "✅ Usuário deletado\n\n";
    
    DB::commit();
    
    echo "✅ LIMPEZA CONCLUÍDA!\n\n";
    echo "Pronto para novo registro: http://soserp.test/register\n\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ ERRO: {$e->getMessage()}\n\n";
}

echo "═══════════════════════════════════════════════════════\n\n";
