<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class ApproveOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:approve {orderId? : ID do pedido} {--all : Aprovar todos os pedidos pendentes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aprovar pedido(s) e ativar plano com sincronização de módulos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('all')) {
            return $this->approveAllPending();
        }

        $orderId = $this->argument('orderId');

        if (!$orderId) {
            $this->error('❌ Por favor, forneça o ID do pedido ou use --all para aprovar todos os pendentes');
            return 1;
        }

        return $this->approveOrder($orderId);
    }

    protected function approveOrder($orderId)
    {
        $order = Order::with(['tenant', 'plan', 'user'])->find($orderId);

        if (!$order) {
            $this->error("❌ Pedido #{$orderId} não encontrado!");
            return 1;
        }

        if ($order->status !== 'pending') {
            $this->error("❌ Pedido #{$orderId} não está pendente (Status atual: {$order->status})");
            return 1;
        }

        $this->info("📋 Pedido: #{$order->id}");
        $this->info("👤 Usuário: {$order->user->name}");
        $this->info("🏢 Tenant: {$order->tenant->name}");
        $this->info("📦 Plano: {$order->plan->name}");
        $this->info("💰 Valor: {$order->amount} Kz");
        $this->newLine();

        if (!$this->confirm('Deseja aprovar este pedido?', true)) {
            $this->info('❌ Aprovação cancelada');
            return 0;
        }

        try {
            $order->approve();
            
            $this->newLine();
            $this->info('✅ Pedido aprovado com sucesso!');
            $this->info('✅ Subscription ativada');
            $this->info('✅ Módulos sincronizados');
            $this->newLine();

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Erro ao aprovar pedido: {$e->getMessage()}");
            $this->error("Ver logs para mais detalhes");
            return 1;
        }
    }

    protected function approveAllPending()
    {
        $orders = Order::where('status', 'pending')
            ->with(['tenant', 'plan', 'user'])
            ->get();

        if ($orders->isEmpty()) {
            $this->info('ℹ️ Nenhum pedido pendente encontrado');
            return 0;
        }

        $this->info("📋 Encontrados {$orders->count()} pedidos pendentes");
        $this->newLine();

        foreach ($orders as $index => $order) {
            $num = $index + 1;
            $this->info("#{$num} - Pedido {$order->id} | {$order->tenant->name} | {$order->plan->name} | {$order->amount} Kz");
        }

        $this->newLine();

        if (!$this->confirm('Deseja aprovar TODOS estes pedidos?', false)) {
            $this->info('❌ Aprovação cancelada');
            return 0;
        }

        $approved = 0;
        $failed = 0;

        $this->newLine();
        $this->info('🔄 Processando pedidos...');
        $this->newLine();

        foreach ($orders as $order) {
            try {
                $order->approve();
                $this->info("✅ Pedido #{$order->id} aprovado");
                $approved++;
            } catch (\Exception $e) {
                $this->error("❌ Erro no pedido #{$order->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("📊 Resultado:");
        $this->info("✅ Aprovados: {$approved}");
        if ($failed > 0) {
            $this->error("❌ Falharam: {$failed}");
        }

        return $failed > 0 ? 1 : 0;
    }
}
