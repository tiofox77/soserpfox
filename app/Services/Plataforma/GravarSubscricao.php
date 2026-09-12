<?php

namespace App\Services\Plataforma;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Tenant\TenantModuleSyncService;
use App\Support\AcordoDeSubscricao;
use App\Support\CicloDeFacturacao;
use Illuminate\Support\Facades\DB;

/**
 * CRIAR OU CORRIGIR UMA SUBSCRIÇÃO À MÃO — o que o dono faz no ecrã da facturação.
 *
 * Vivia dentro do componente em Livewire, a meio de cem linhas de estado de
 * formulário. Saiu para aqui para ter UMA porta e poder ser ensaiado sem ecrã.
 * As regras são as que ali foram aprendidas, e os porquês vêm com elas:
 *
 *  · A EDIÇÃO ESCREVE NA LINHA QUE SE ABRIU. O ecrã antigo voltava a adivinhar
 *    o alvo pela empresa e escrevia na subscrição VIVA, fosse qual fosse a
 *    linha em que se tinha carregado.
 *  · UM PLANO POR PAGAR NÃO DERRUBA O QUE ESTÁ EM VIGOR. Desligar «pago» punha a
 *    subscrição activa a 'pending', e o CheckSubscription só aceita 'active' e
 *    'trial': a empresa ficava fora do ERP com o ano pago apagado do registo.
 *  · SÓ UMA SUBSCRIÇÃO A DAR ACESSO: meio sistema faz `->first()` e apanharia
 *    uma delas ao calhas.
 *  · OS MÓDULOS SAEM TAMBÉM, e não só entram. Baixar de Enterprise para Starter
 *    deixava os módulos caros a funcionar.
 *  · O PEDIDO VAI EM NOME DO DONO DA EMPRESA, não de quem carregou no botão: é a
 *    ele que o OrderObserver manda o email.
 *  · `trial_ends_at` SÓ QUANDO É MESMO TESTE: gravar sempre queimava a cortesia
 *    única do cliente numa venda paga.
 */
class GravarSubscricao
{
    /**
     * @param  array{tenant_id:int, plan_id:int, billing_cycle:string, pago:bool,
     *               metodo:?string, referencia:?string, com_oferta?:bool, dias?:?int,
     *               preco_por_utilizador?:?float, utilizadores?:?int}  $dados
     * @return array{subscricao: Subscription, mensagem: string}
     */
    public function gravar(array $dados, ?Subscription $emEdicao, int $autorId): array
    {
        return DB::transaction(function () use ($dados, $emEdicao, $autorId) {
            // A editar, a empresa é a da linha: uma edição nunca muda a
            // subscrição de dono, e o que viesse no pedido é ignorado.
            $empresaId = $emEdicao ? $emEdicao->tenant_id : (int) $dados['tenant_id'];
            $empresa = Tenant::findOrFail($empresaId);
            $plano = Plan::findOrFail($dados['plan_id']);
            $ciclo = $dados['billing_cycle'];
            $pago = (bool) $dados['pago'];

            $acordo = AcordoDeSubscricao::calcular($plano, $ciclo, [
                'com_oferta' => $dados['com_oferta'] ?? true,
                'dias' => $dados['dias'] ?? null,
                'preco_por_utilizador' => $dados['preco_por_utilizador'] ?? null,
                'utilizadores' => $dados['utilizadores'] ?? null,
            ]);
            $colunas = AcordoDeSubscricao::colunas($acordo);
            $valor = $acordo['valor'];
            $fim = $acordo['fim'];

            $existente = $emEdicao ?: Subscription::where('tenant_id', $empresaId)
                ->whereIn('status', ['active', 'pending', 'trial'])
                ->latest('id')->first();

            $emVigor = $existente && in_array($existente->status, ['active', 'trial'], true);

            if ($emVigor && ! $pago) {
                $subscricao = Subscription::create([
                    'tenant_id' => $empresaId,
                    'plan_id' => $plano->id,
                    'status' => 'pending',
                    'billing_cycle' => $ciclo,
                    'current_period_start' => null,
                    'current_period_end' => null,
                    'ends_at' => null,
                ] + $colunas);
                $mensagem = __('Subscrição pendente criada. O acesso actual mantém-se até o pagamento ser confirmado.');
            } elseif ($existente) {
                $existente->update($colunas + [
                    'plan_id' => $plano->id,
                    'billing_cycle' => $ciclo,
                    'status' => $pago ? 'active' : 'pending',
                    'current_period_start' => $pago ? now() : $existente->current_period_start,
                    'current_period_end' => $pago ? $fim : $existente->current_period_end,
                    'ends_at' => $pago ? $fim : $existente->ends_at,
                ]);
                $subscricao = $existente;
                $mensagem = __('Subscrição actualizada.');
            } else {
                $subscricao = Subscription::create($colunas + [
                    'tenant_id' => $empresaId,
                    'plan_id' => $plano->id,
                    'status' => $pago ? 'active' : 'pending',
                    'billing_cycle' => $ciclo,
                    'current_period_start' => now(),
                    'current_period_end' => $fim,
                    'ends_at' => $fim,
                    'trial_ends_at' => (! $pago && $plano->trial_days > 0) ? now()->addDays($plano->trial_days) : null,
                ]);
                $mensagem = __('Subscrição criada.');
            }

            if ($subscricao->status === 'active') {
                Subscription::where('tenant_id', $empresaId)
                    ->where('id', '!=', $subscricao->id)
                    ->whereIn('status', ['active', 'trial'])
                    ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'ends_at' => now()]);

                $this->sincronizarModulos($empresa, $plano);

                $empresa->update([
                    'max_users' => $plano->max_users,
                    'max_storage_mb' => $plano->max_storage_mb,
                ]);
            }

            $dono = $empresa->users()->orderBy('tenant_user.id')->first();

            Order::create([
                'tenant_id' => $empresa->id,
                'user_id' => $dono?->id ?? $autorId,
                'plan_id' => $plano->id,
                'amount' => $valor,
                'billing_cycle' => $ciclo,
                'status' => $pago ? 'approved' : 'pending',
                'payment_method' => $dados['metodo'] ?: 'bank_transfer',
                'payment_reference' => $dados['referencia'] ?: null,
                'approved_at' => $pago ? now() : null,
                'approved_by' => $pago ? $autorId : null,
                'notes' => 'Subscrição criada/alterada manualmente pelo dono da plataforma, no ecrã da facturação.',
            ]);

            if ($pago) {
                Invoice::create([
                    'tenant_id' => $empresa->id,
                    'subscription_id' => $subscricao->id,
                    'invoice_number' => Invoice::generateInvoiceNumber(),
                    'description' => "Subscrição {$plano->name} — ".CicloDeFacturacao::nome($ciclo),
                    'invoice_date' => now(),
                    'due_date' => now(),
                    'paid_at' => now(),
                    'payment_method' => $dados['metodo'] ?: 'bank_transfer',
                    'payment_reference' => $dados['referencia'] ?: null,
                    'subtotal' => $valor,
                    'tax' => 0,
                    'total' => $valor,
                    'status' => 'paid',
                ]);

                $mensagem .= ' '.__('Factura paga emitida.');
            } else {
                $mensagem .= ' '.__('Aguarda confirmação do pagamento.');
            }

            return ['subscricao' => $subscricao, 'mensagem' => $mensagem];
        });
    }

    /**
     * Os módulos do plano, com as dependências — e os que o plano já não dá
     * saem. O serviço recusa tirar um módulo de que outro activo dependa.
     */
    private function sincronizarModulos(Tenant $empresa, Plan $plano): void
    {
        $sync = new TenantModuleSyncService();
        $doPlano = $plano->moduleSlugsWithDependencies();

        foreach ($doPlano as $slug) {
            $sync->activateModule($empresa, $slug);
        }

        $activos = $empresa->modules()->wherePivot('is_active', true)->pluck('modules.slug')->all();

        foreach (array_diff($activos, $doPlano) as $slug) {
            $sync->deactivateModule($empresa, $slug);
        }
    }
}
