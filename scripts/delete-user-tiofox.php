<?php

/**
 * DELETAR COMPLETAMENTE USUÁRIO E TENANT - tiofox2019@gmail.com
 * VERSÃO MELHORADA - LIMPA TODAS AS RELAÇÕES
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  DELETAR COMPLETO - USUÁRIO E TODAS AS RELAÇÕES\n";
echo "═══════════════════════════════════════════════════════\n\n";

$email = 'tiofox2019@gmail.com';

// Buscar usuário
$user = \App\Models\User::withTrashed()->where('email', $email)->first();

if (!$user) {
    echo "✅ Usuário não encontrado. Já está limpo para novo registro!\n\n";
    exit(0);
}

echo "👤 Usuário encontrado:\n";
echo "   ID: {$user->id}\n";
echo "   Nome: {$user->name}\n";
echo "   Email: {$user->email}\n";
echo "   Tenant ID: {$user->tenant_id}\n";
echo "   Status: " . ($user->trashed() ? "🗑️ Soft Deleted" : "✅ Ativo") . "\n\n";

// Buscar tenant
$tenant = null;
if ($user->tenant_id) {
    $tenant = \App\Models\Tenant::withTrashed()->find($user->tenant_id);
    
    if ($tenant) {
        echo "🏢 Tenant encontrado:\n";
        echo "   ID: {$tenant->id}\n";
        echo "   Nome: {$tenant->name}\n";
        echo "   Email: {$tenant->email}\n";
        echo "   Status: " . ($tenant->trashed() ? "🗑️ Soft Deleted" : "✅ Ativo") . "\n\n";
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 CONTANDO REGISTROS RELACIONADOS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$counts = [];
$totalDeleted = 0;

// Tabelas relacionadas ao TENANT
if ($tenant) {
    echo "Dados do Tenant (ID: {$tenant->id}):\n";
    
    $tenantTables = [
        'users' => "Usuários",
        'subscriptions' => "Assinaturas",
        'orders' => "Pedidos",
        'events' => "Eventos",
        'clients' => "Clientes",
        'suppliers' => "Fornecedores",
        'products' => "Produtos",
        'invoices' => "Faturas",
        'payments' => "Pagamentos",
        'expenses' => "Despesas",
        'contracts' => "Contratos",
        'tasks' => "Tarefas",
        'notes' => "Notas",
        'files' => "Arquivos",
        'notifications' => "Notificações",
    ];
    
    foreach ($tenantTables as $table => $label) {
        if (\Schema::hasTable($table) && \Schema::hasColumn($table, 'tenant_id')) {
            $count = \DB::table($table)->where('tenant_id', $tenant->id)->count();
            if ($count > 0) {
                $counts["tenant_{$table}"] = ['label' => $label, 'count' => $count];
                $totalDeleted += $count;
                echo "   {$label}: {$count}\n";
            }
        }
    }
    echo "\n";
}

// Tabelas relacionadas ao USER
echo "Dados do Usuário (ID: {$user->id}):\n";

$userTables = [
    'model_has_roles' => "Papéis (Roles)",
    'model_has_permissions' => "Permissões",
    'sessions' => "Sessões",
    'personal_access_tokens' => "Tokens API",
    'password_reset_tokens' => "Tokens de Reset",
    'activity_log' => "Logs de Atividade",
    'notifications' => "Notificações",
];

foreach ($userTables as $table => $label) {
    if (\Schema::hasTable($table)) {
        // Verificar coluna user_id ou model_id
        $column = \Schema::hasColumn($table, 'user_id') ? 'user_id' : 
                 (\Schema::hasColumn($table, 'model_id') ? 'model_id' : null);
        
        if ($column) {
            $query = \DB::table($table)->where($column, $user->id);
            
            // Se for model_has_roles ou model_has_permissions, filtrar por model_type
            if (in_array($table, ['model_has_roles', 'model_has_permissions'])) {
                $query->where('model_type', 'App\\Models\\User');
            }
            
            $count = $query->count();
            if ($count > 0) {
                $counts["user_{$table}"] = ['label' => $label, 'count' => $count];
                $totalDeleted += $count;
                echo "   {$label}: {$count}\n";
            }
        }
    }
}

echo "\n📈 Total de registros a deletar: {$totalDeleted}\n\n";

if ($totalDeleted === 0 && !$tenant) {
    echo "⚠️  Nenhum dado relacionado encontrado. Deletando apenas o usuário...\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🗑️  INICIANDO REMOÇÃO COMPLETA\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

DB::beginTransaction();

try {
    $deletedCount = 0;
    
    // 1. DELETAR DADOS DO TENANT
    if ($tenant) {
        echo "1️⃣  Deletando dados do Tenant (ID: {$tenant->id})...\n\n";
        
        foreach ($tenantTables as $table => $label) {
            if (isset($counts["tenant_{$table}"])) {
                $deleted = \DB::table($table)->where('tenant_id', $tenant->id)->delete();
                $deletedCount += $deleted;
                echo "   ✅ {$label}: {$deleted} registro(s)\n";
            }
        }
        
        // Deletar o próprio tenant
        if ($tenant->trashed()) {
            $tenant->forceDelete();
        } else {
            $tenant->delete();
        }
        echo "   ✅ Tenant deletado\n\n";
    }
    
    // 2. DELETAR DADOS DO USUÁRIO
    echo "2️⃣  Deletando dados do Usuário (ID: {$user->id})...\n\n";
    
    foreach ($userTables as $table => $label) {
        if (isset($counts["user_{$table}"])) {
            $column = \Schema::hasColumn($table, 'user_id') ? 'user_id' : 'model_id';
            
            $query = \DB::table($table)->where($column, $user->id);
            
            if (in_array($table, ['model_has_roles', 'model_has_permissions'])) {
                $query->where('model_type', 'App\\Models\\User');
            }
            
            $deleted = $query->delete();
            $deletedCount += $deleted;
            echo "   ✅ {$label}: {$deleted} registro(s)\n";
        }
    }
    
    // 3. DELETAR O USUÁRIO (FORCE DELETE para remover completamente)
    echo "\n3️⃣  Deletando usuário...\n";
    if ($user->trashed()) {
        $user->forceDelete();
    } else {
        $user->forceDelete(); // Force delete para não ficar em soft deletes
    }
    echo "   ✅ Usuário deletado permanentemente\n\n";
    
    DB::commit();
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ REMOÇÃO COMPLETA COM SUCESSO!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "📋 RESUMO:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    if ($tenant) {
        echo "✅ Tenant: {$tenant->name} (ID: {$tenant->id})\n";
    }
    echo "✅ Usuário: {$user->name} ({$email})\n";
    echo "✅ Total de registros deletados: {$deletedCount}\n\n";
    
    echo "🎯 BANCO DE DADOS TOTALMENTE LIMPO!\n\n";
    echo "Pronto para novo registro em:\n";
    echo "http://soserp.test/register\n\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    
    echo "❌ ERRO ao deletar:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Mensagem: {$e->getMessage()}\n";
    echo "Arquivo: {$e->getFile()}\n";
    echo "Linha: {$e->getLine()}\n\n";
    
    echo "Stack Trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    
    exit(1);
}

echo "═══════════════════════════════════════════════════════\n\n";
