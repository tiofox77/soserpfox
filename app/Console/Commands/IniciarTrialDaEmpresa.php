<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Arranca o período de teste de uma empresa que ficou à espera de aprovação.
 *
 * Serve para as empresas que se registaram ANTES de o teste passar a ser
 * automático: ficaram com o pedido pendente, sem teste e sem nada para
 * aprovar (não havia pagamento nenhum a verificar). Isto põe-nas
 * exactamente no estado que o registo produziria hoje.
 *
 * NÃO se aprova o pedido pela via normal de propósito: aprovar dá um
 * PERÍODO PAGO completo (um mês, no ciclo mensal), não um teste de 14 dias.
 * Por isso o pedido é marcado como aprovado em silêncio (saveQuietly), sem
 * acordar o OrderObserver — o trabalho dele é feito aqui, à medida do teste.
 */
class IniciarTrialDaEmpresa extends Command
{
    protected $signature = 'tenant:iniciar-trial
        {tenant : id da empresa}
        {--dias= : dias de teste (por omissão, os do plano)}
        {--so-ver : mostra o que faria, sem gravar}';

    protected $description = 'Arranca o período de teste de uma empresa com pedido pendente';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));

        if (!$tenant) {
            $this->error('Empresa não encontrada.');
            return self::FAILURE;
        }

        $order = Order::with('plan')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (!$order) {
            $this->error("A empresa {$tenant->name} não tem pedido pendente.");
            return self::FAILURE;
        }

        $plano = $order->plan;
        if (!$plano) {
            $this->error('O pedido não tem plano associado.');
            return self::FAILURE;
        }

        $dias = (int) ($this->option('dias') ?: $plano->trial_days);

        if ($dias <= 0) {
            $this->error("O plano {$plano->name} não tem período de teste. Use --dias para forçar.");
            return self::FAILURE;
        }

        // Se tiver comprovativo, houve pagamento: isso decide-se no painel,
        // não aqui — dar um teste apagaria a intenção de pagar.
        if (!empty($order->payment_proof) || !empty($order->payment_reference)) {
            $this->error('Este pedido tem comprovativo/referência de pagamento. Decida-o no painel, não por aqui.');
            return self::FAILURE;
        }

        // Quem já tem subscrição a correr não precisa de teste nenhum — e dar
        // um segundo registo criaria duas subscrições vivas na mesma empresa.
        $viva = $tenant->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->latest('id')
            ->first();

        if ($viva) {
            $this->error(
                "A empresa {$tenant->name} já tem subscrição {$viva->status}"
                . ($viva->ends_at ? ' até ' . $viva->ends_at->toDateString() : '')
                . '. Decida o pedido no painel.'
            );
            return self::FAILURE;
        }

        $fim = now()->addDays($dias);

        $this->table(['campo', 'valor'], [
            ['empresa', $tenant->name . ' (#' . $tenant->id . ')'],
            ['plano', $plano->name],
            ['teste', $dias . ' dias'],
            ['termina', $fim->toDateTimeString()],
            ['pedido', '#' . $order->id . ' (pending → approved, sem período pago)'],
        ]);

        if ($this->option('so-ver')) {
            $this->info('(só ver) nada foi gravado.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($tenant, $order, $plano, $dias, $fim) {
            // A subscrição pendente do plano passa a teste. Se não houver,
            // cria-se — é o mesmo que o registo teria feito.
            $sub = $tenant->subscriptions()
                ->where('plan_id', $plano->id)
                ->whereIn('status', ['pending', 'trial'])
                ->latest('id')
                ->first();

            $dados = [
                'plan_id'              => $plano->id,
                'status'               => 'trial',
                'trial_ends_at'        => $fim,
                'current_period_start' => now(),
                'current_period_end'   => $fim,
                'ends_at'              => $fim,
            ];

            if ($sub) {
                $sub->update($dados);
            } else {
                // Sem subscrição prévia (registo antigo que não a criou): o
                // valor e o ciclo vêm do pedido, senão a coluna não aceita.
                $tenant->subscriptions()->create($dados + [
                    'amount'        => $order->amount ?? $plano->getPrice($order->billing_cycle ?: 'monthly'),
                    'billing_cycle' => $order->billing_cycle ?: 'monthly',
                ]);
            }

            // O pedido sai da fila SEM acordar o observer: ele criaria um
            // período pago completo, que não é o que se quer aqui.
            $order->status = 'approved';
            $order->approved_at = now();
            $order->notes = trim(($order->notes ? $order->notes . ' | ' : '')
                . "Teste de {$dias} dias iniciado (política de teste automático).");
            $order->saveQuietly();

            // Os módulos do plano têm de ficar activos, senão a empresa entra
            // e não tem nada para usar.
            try {
                (new \App\Services\Tenant\TenantModuleSyncService())->syncToPlan($tenant, $plano);
            } catch (\Throwable $e) {
                $this->warn('Módulos não sincronizados: ' . $e->getMessage());
            }
        });

        $this->info("Teste iniciado. {$tenant->name} pode entrar e trabalhar até {$fim->toDateString()}.");

        return self::SUCCESS;
    }
}
