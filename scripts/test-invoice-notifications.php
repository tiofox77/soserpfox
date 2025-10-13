<?php

/**
 * TESTAR NOTIFICAÇÕES DE FATURAS
 * 
 * Testa o sistema de lembretes de faturas vencidas e expirando
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  TESTE: NOTIFICAÇÕES DE FATURAS\n";
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
echo "Email: {$user->email}\n\n";

// Verificar se existe modelo de fatura
$invoiceModels = [
    '\App\Models\Invoicing\SalesInvoice',
    '\App\Models\Invoice',
    '\App\Models\Invoicing\Invoice',
];

$invoiceModel = null;
foreach ($invoiceModels as $model) {
    if (class_exists($model)) {
        $invoiceModel = $model;
        break;
    }
}

if (!$invoiceModel) {
    echo "⚠️  Modelo de fatura não encontrado no sistema.\n";
    echo "   Modelos verificados:\n";
    foreach ($invoiceModels as $model) {
        echo "   - {$model}\n";
    }
    echo "\n";
    echo "💡 Este teste só funciona se você tiver o módulo de faturação.\n\n";
    exit(0);
}

echo "✅ Modelo de fatura encontrado: {$invoiceModel}\n\n";

// Buscar faturas
$tenant = $user->activeTenant();

if (!$tenant) {
    echo "❌ Usuário não tem tenant ativo\n\n";
    exit(1);
}

echo "🏢 Tenant: {$tenant->name}\n\n";

// 1. FATURAS VENCIDAS
echo "🔍 Verificando Faturas Vencidas...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$overdueInvoices = $invoiceModel::where('tenant_id', $tenant->id)
    ->whereIn('status', ['pending', 'sent', 'partial'])
    ->whereDate('due_date', '<', Carbon::today())
    ->get();

if ($overdueInvoices->isEmpty()) {
    echo "✅ Nenhuma fatura vencida. Ótimo!\n\n";
} else {
    echo "⚠️  {$overdueInvoices->count()} fatura(s) vencida(s):\n\n";
    
    foreach ($overdueInvoices as $invoice) {
        $daysOverdue = Carbon::parse($invoice->due_date)->diffInDays(Carbon::today());
        $urgency = $daysOverdue > 30 ? 'CRÍTICA' : ($daysOverdue > 15 ? 'ALTA' : 'MÉDIA');
        $icon = $daysOverdue > 30 ? '🔴' : ($daysOverdue > 15 ? '🟠' : '🟡');
        
        echo "  {$icon} Fatura #{$invoice->invoice_number}\n";
        echo "     Cliente: {$invoice->client_name}\n";
        echo "     Valor: " . number_format($invoice->total, 2) . " Kz\n";
        echo "     Vencimento: {$invoice->due_date->format('d/m/Y')}\n";
        echo "     Atraso: {$daysOverdue} dia(s) ({$urgency})\n\n";
        
        // Testar envio de notificação
        if ($daysOverdue <= 3) {
            echo "     📧 Enviando notificação de teste...\n";
            try {
                $user->notify(new \App\Notifications\InvoiceOverdueNotification($invoice, $daysOverdue));
                echo "     ✅ Notificação enviada!\n\n";
            } catch (\Exception $e) {
                echo "     ❌ Erro: {$e->getMessage()}\n\n";
            }
        }
    }
    
    $totalOverdue = $overdueInvoices->sum('total');
    $criticalCount = $overdueInvoices->filter(fn($inv) => Carbon::parse($inv->due_date)->diffInDays(Carbon::today()) > 30)->count();
    
    echo "📊 Resumo de Vencidas:\n";
    echo "   Total de faturas: {$overdueInvoices->count()}\n";
    echo "   Valor total: " . number_format($totalOverdue, 2) . " Kz\n";
    echo "   Críticas (> 30 dias): {$criticalCount}\n\n";
}

// 2. FATURAS EXPIRANDO EM BREVE
echo "🔍 Verificando Faturas Expirando em Breve...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$expiringInvoices = $invoiceModel::where('tenant_id', $tenant->id)
    ->whereIn('status', ['pending', 'sent', 'partial'])
    ->whereDate('due_date', '>', Carbon::today())
    ->whereDate('due_date', '<=', Carbon::today()->addDays(7))
    ->get();

if ($expiringInvoices->isEmpty()) {
    echo "✅ Nenhuma fatura expirando nos próximos 7 dias.\n\n";
} else {
    echo "ℹ️  {$expiringInvoices->count()} fatura(s) expirando em breve:\n\n";
    
    foreach ($expiringInvoices as $invoice) {
        $daysUntilDue = Carbon::today()->diffInDays(Carbon::parse($invoice->due_date));
        $urgency = $daysUntilDue <= 3 ? 'URGENTE' : 'NORMAL';
        $icon = $daysUntilDue <= 3 ? '🟠' : '🟡';
        
        echo "  {$icon} Fatura #{$invoice->invoice_number}\n";
        echo "     Cliente: {$invoice->client_name}\n";
        echo "     Valor: " . number_format($invoice->total, 2) . " Kz\n";
        echo "     Vencimento: {$invoice->due_date->format('d/m/Y')}\n";
        echo "     Dias restantes: {$daysUntilDue} ({$urgency})\n\n";
        
        // Testar envio de notificação
        if (in_array($daysUntilDue, [7, 3, 1])) {
            echo "     📧 Enviando notificação de teste...\n";
            try {
                $user->notify(new \App\Notifications\InvoiceExpiringNotification($invoice, $daysUntilDue));
                echo "     ✅ Notificação enviada!\n\n";
            } catch (\Exception $e) {
                echo "     ❌ Erro: {$e->getMessage()}\n\n";
            }
        }
    }
    
    $totalExpiring = $expiringInvoices->sum('total');
    $urgentCount = $expiringInvoices->filter(fn($inv) => Carbon::today()->diffInDays(Carbon::parse($inv->due_date)) <= 3)->count();
    
    echo "📊 Resumo de Expirando:\n";
    echo "   Total de faturas: {$expiringInvoices->count()}\n";
    echo "   Valor total: " . number_format($totalExpiring, 2) . " Kz\n";
    echo "   Urgentes (≤ 3 dias): {$urgentCount}\n\n";
}

// 3. COMANDO AGENDADO
echo "⏰ Comando Agendado:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Para verificação automática diária, agende:\n";
echo "php artisan notifications:check-overdue-invoices\n\n";

echo "📅 Frequência de Notificações:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Faturas Vencidas:\n";
echo "  • 1, 3, 7, 15, 30, 60, 90 dias após vencimento\n\n";
echo "Faturas Expirando:\n";
echo "  • 7, 3 e 1 dia antes do vencimento\n\n";

echo "🔔 No Sino de Notificações:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "As notificações também aparecem em tempo real:\n";
echo "  • Faturas vencidas → Vermelho/Laranja\n";
echo "  • Faturas expirando → Amarelo/Laranja\n";
echo "  • Contador atualiza automaticamente\n\n";

echo "📧 Notificações por Email:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Ambas as notificações são enviadas por:\n";
echo "  ✓ Banco de dados (sino)\n";
echo "  ✓ Email (para admins e gerentes)\n\n";

echo "✅ Teste concluído!\n";
echo "   Veja as notificações no sino do navbar.\n\n";
