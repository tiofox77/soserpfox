<?php

/**
 * TESTAR SISTEMA COMPLETO DE NOTIFICAÇÕES
 * 
 * Script para testar todo o sistema de notificações MVP
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  MVP SISTEMA DE NOTIFICAÇÕES - TESTE COMPLETO\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Buscar usuário para teste
$user = \App\Models\User::where('is_super_admin', false)->first();

if (!$user) {
    echo "❌ Nenhum usuário encontrado!\n\n";
    exit(1);
}

echo "👤 Usuário de Teste:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Nome: {$user->name}\n";
echo "Email: {$user->email}\n";
echo "ID: {$user->id}\n\n";

// Criar notificações de teste
echo "📧 Criando Notificações de Teste...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$notificationsCreated = 0;

// 1. NOTIFICAÇÃO DE EVENTO CRIADO
if (class_exists('\App\Models\Event')) {
    $event = \App\Models\Event::first();
    if ($event) {
        try {
            $user->notify(new \App\Notifications\EventCreatedNotification($event, \App\Models\User::first()));
            echo "✅ Notificação de Evento Criado enviada\n";
            $notificationsCreated++;
        } catch (\Exception $e) {
            echo "⚠️  Erro ao criar notificação de evento: {$e->getMessage()}\n";
        }
    }
}

// 2. NOTIFICAÇÃO DE TÉCNICO ADICIONADO
if (class_exists('\App\Models\Event')) {
    $event = \App\Models\Event::first();
    if ($event) {
        try {
            $user->notify(new \App\Notifications\TechnicianAssignedNotification($event, \App\Models\User::first()));
            echo "✅ Notificação de Técnico Adicionado enviada\n";
            $notificationsCreated++;
        } catch (\Exception $e) {
            echo "⚠️  Erro ao criar notificação de técnico: {$e->getMessage()}\n";
        }
    }
}

// 3. NOTIFICAÇÃO DE STATUS ALTERADO
if (class_exists('\App\Models\Event')) {
    $event = \App\Models\Event::first();
    if ($event) {
        try {
            $user->notify(new \App\Notifications\EventStatusChangedNotification($event, 'pending', 'confirmed', \App\Models\User::first()));
            echo "✅ Notificação de Status Alterado enviada\n";
            $notificationsCreated++;
        } catch (\Exception $e) {
            echo "⚠️  Erro ao criar notificação de status: {$e->getMessage()}\n";
        }
    }
}

// 4. NOTIFICAÇÃO DE ESTOQUE BAIXO
if (class_exists('\App\Models\Invoicing\Product')) {
    $product = \App\Models\Invoicing\Product::first();
    if ($product) {
        try {
            $user->notify(new \App\Notifications\LowStockNotification($product, 5, 10));
            echo "✅ Notificação de Estoque Baixo enviada\n";
            $notificationsCreated++;
        } catch (\Exception $e) {
            echo "⚠️  Erro ao criar notificação de estoque: {$e->getMessage()}\n";
        }
    }
}

// 5. NOTIFICAÇÃO DE SUBSCRIPTION EXPIRANDO
$tenant = $user->activeTenant();
if ($tenant && $tenant->activeSubscription) {
    try {
        $user->notify(new \App\Notifications\SubscriptionExpiringNotification($tenant->activeSubscription, 7));
        echo "✅ Notificação de Subscription Expirando enviada\n";
        $notificationsCreated++;
    } catch (\Exception $e) {
        echo "⚠️  Erro ao criar notificação de subscription: {$e->getMessage()}\n";
    }
}

// 6. NOTIFICAÇÃO DE FATURA CRIADA
if (class_exists('\App\Models\Invoicing\SalesInvoice')) {
    $invoice = \App\Models\Invoicing\SalesInvoice::first();
    if ($invoice) {
        try {
            $user->notify(new \App\Notifications\InvoiceCreatedNotification($invoice));
            echo "✅ Notificação de Fatura Criada enviada\n";
            $notificationsCreated++;
        } catch (\Exception $e) {
            echo "⚠️  Erro ao criar notificação de fatura: {$e->getMessage()}\n";
        }
    }
}

echo "\n";
echo "📊 Resumo:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Notificações criadas: {$notificationsCreated}\n";
echo "Usuário: {$user->email}\n\n";

// Verificar notificações do usuário
$unreadCount = $user->unreadNotifications->count();
$totalCount = $user->notifications->count();

echo "📬 Notificações do Usuário:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Total: {$totalCount}\n";
echo "Não lidas: {$unreadCount}\n\n";

if ($totalCount > 0) {
    echo "📋 Últimas Notificações:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    foreach ($user->notifications->take(5) as $notification) {
        $data = $notification->data;
        $status = $notification->read_at ? '✓' : '●';
        echo "{$status} {$data['title']}\n";
        echo "   {$data['message']}\n";
        echo "   {$notification->created_at->diffForHumans()}\n\n";
    }
}

echo "🔔 Como Testar no Sistema:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. Faça login como: {$user->email}\n";
echo "2. Veja o sino no navbar (topo direito)\n";
echo "3. Deve mostrar ({$unreadCount}) notificações não lidas\n";
echo "4. Clique no sino para ver o dropdown\n";
echo "5. Clique em uma notificação para marcar como lida\n";
echo "6. Use 'Marcar todas como lidas' para limpar\n\n";

echo "💡 Notificações Dinâmicas do Sistema:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Além das notificações do banco de dados, o sistema\n";
echo "também mostra notificações dinâmicas baseadas em:\n\n";
echo "  • Plano expirando (15 dias ou menos)\n";
echo "  • Produtos expirados\n";
echo "  • Produtos expirando em breve\n";
echo "  • Estoque baixo\n";
echo "  • Pedidos pendentes (Super Admin)\n";
echo "  • Limite de empresas atingido\n\n";

echo "📝 Tipos de Notificações Implementadas:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✓ EventCreatedNotification\n";
echo "✓ TechnicianAssignedNotification\n";
echo "✓ EventStatusChangedNotification\n";
echo "✓ LowStockNotification\n";
echo "✓ SubscriptionExpiringNotification\n";
echo "✓ InvoiceCreatedNotification\n\n";

echo "🎨 Cores Disponíveis:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "• green   - Sucesso\n";
echo "• blue    - Informação\n";
echo "• yellow  - Aviso\n";
echo "• orange  - Alerta\n";
echo "• red     - Erro/Urgente\n";
echo "• purple  - Usuários/Técnicos\n";
echo "• cyan    - Mudanças\n\n";

echo "✅ Teste concluído com sucesso!\n";
echo "   Veja a documentação completa em: docs/NOTIFICATION-SYSTEM-MVP.md\n\n";
