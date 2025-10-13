<?php

/**
 * TESTAR NOTIFICAÇÃO: USUÁRIO ADICIONADO A EMPRESA
 * 
 * Testa o envio de email quando um usuário é adicionado a uma empresa
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  TESTAR NOTIFICAÇÃO: USUÁRIO ADICIONADO A EMPRESA\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Buscar um usuário e uma empresa para teste
$user = \App\Models\User::where('is_super_admin', false)->first();
$tenant = \App\Models\Tenant::first();

if (!$user) {
    echo "❌ Nenhum usuário encontrado!\n\n";
    exit(1);
}

if (!$tenant) {
    echo "❌ Nenhum tenant encontrado!\n\n";
    exit(1);
}

echo "👤 Usuário de Teste:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Nome: {$user->name}\n";
echo "Email: {$user->email}\n";
echo "ID: {$user->id}\n\n";

echo "🏢 Empresa de Teste:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Nome: {$tenant->name}\n";
echo "ID: {$tenant->id}\n\n";

echo "📧 Enviando notificação por email...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

try {
    $addedBy = \App\Models\User::first(); // Quem adicionou
    $roleName = "Administrador"; // Role atribuído
    
    $user->notify(new \App\Notifications\UserAddedToTenantNotification(
        $tenant,
        $addedBy,
        $roleName
    ));
    
    echo "✅ Notificação enviada com sucesso!\n\n";
    
    echo "📋 Detalhes do Email:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Para: {$user->email}\n";
    echo "Assunto: 🏢 Você foi adicionado a uma nova empresa\n";
    echo "Empresa: {$tenant->name}\n";
    echo "Adicionado por: {$addedBy->name}\n";
    echo "Perfil: {$roleName}\n\n";
    
    echo "📝 Conteúdo do Email:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Olá, {$user->name}!\n\n";
    echo "Você foi adicionado à empresa {$tenant->name}.\n";
    echo "Adicionado por: {$addedBy->name}\n";
    echo "Seu perfil: {$roleName}\n\n";
    echo "Agora você tem acesso a esta empresa e pode alternar\n";
    echo "entre suas empresas no sistema.\n\n";
    echo "[Botão: Acessar Empresa]\n\n";
    echo "Para alternar entre empresas, use o seletor no topo da página.\n";
    echo "Bem-vindo à equipe!\n\n";
    
    echo "ℹ️  Verificar Log:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Arquivo: storage/logs/laravel.log\n";
    echo "Buscar por: '📧 Notificação enviada: Usuário adicionado a empresa'\n\n";
    
    echo "✅ Teste concluído com sucesso!\n\n";
    
} catch (\Exception $e) {
    echo "❌ Erro ao enviar notificação:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n\n";
}

echo "📊 Quando a Notificação é Enviada:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. Admin edita um usuário existente\n";
echo "2. Seleciona uma NOVA empresa para adicionar\n";
echo "3. Sistema detecta que é nova empresa\n";
echo "4. Vincula usuário à empresa\n";
echo "5. Envia notificação por email automaticamente\n";
echo "6. Usuário recebe email com:\n";
echo "   - Nome da empresa\n";
echo "   - Quem o adicionou\n";
echo "   - Seu perfil/role\n";
echo "   - Link para acessar\n\n";

echo "💡 Nota:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "- Notificação enviada apenas para NOVAS empresas\n";
echo "- Se empresa já estava vinculada, NÃO envia\n";
echo "- Usa fila (ShouldQueue) para não bloquear\n";
echo "- Logs completos em storage/logs/laravel.log\n\n";
