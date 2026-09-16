<?php

namespace App\Services\Revenda;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\ResellerPayout;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * AS COMISSÕES DOS REVENDEDORES (16/09/2026, RV-11 e RV-12).
 *
 * UMA COMISSÃO POR PAGAMENTO CONFIRMADO de uma empresa ligada a um revendedor
 * aprovado, pela regra dele NAQUELE MOMENTO:
 *
 *  · um PEDIDO com valor aprovado POR QUEM GERE A PLATAFORMA — o registo, a
 *    mudança de plano, o pedido feito pelo revendedor. O teste gratuito
 *    aprova-se sozinho, do lado da empresa, e não é dinheiro nenhum (e o
 *    `approved_by` não serve para os distinguir: o OrderObserver preenche-o
 *    com quem estiver na sessão, ou com o 1);
 *  · uma FACTURA DA SUBSCRIÇÃO que passa a paga — as renovações.
 *
 * A chave única da origem garante que o mesmo pagamento não dá duas. Um
 * pagamento de antes da ligação não conta. E nada disto pode deitar abaixo a
 * aprovação ou o pagamento: falhar aqui fica no registo e mais nada.
 */
class ComissoesDoRevendedor
{
    public function doPedido(Order $pedido, bool $confirmadoPelaPlataforma): ?ResellerCommission
    {
        if ($pedido->status !== 'approved' || ! $confirmadoPelaPlataforma || (float) $pedido->amount <= 0) {
            return null;
        }

        // O pedido não separa o IVA: o valor é o mesmo nas duas bases.
        return $this->seguro(fn () => $this->gerar(
            $pedido->tenant_id, 'order', $pedido->id, $pedido->plan_id,
            (float) $pedido->amount, (float) $pedido->amount, $pedido->approved_at ?? now(),
        ));
    }

    public function daFactura(Invoice $factura): ?ResellerCommission
    {
        if ($factura->status !== 'paid' || ! $factura->subscription_id || (float) $factura->total <= 0) {
            return null;
        }

        return $this->seguro(fn () => $this->gerar(
            $factura->tenant_id, 'invoice', $factura->id, $factura->subscription?->plan_id,
            (float) $factura->subtotal, (float) $factura->total, $factura->paid_at ?? now(),
        ));
    }

    private function seguro(\Closure $trabalho): ?ResellerCommission
    {
        try {
            return $trabalho();
        } catch (\Throwable $e) {
            Log::error('Comissão do revendedor: falhou', ['erro' => $e->getMessage()]);

            return null;
        }
    }

    private function gerar(?int $tenantId, string $origem, int $origemId, ?int $planId, float $semIva, float $comIva, Carbon|string $quando): ?ResellerCommission
    {
        $empresa = $tenantId ? Tenant::find($tenantId) : null;
        $revendedor = $empresa?->reseller_id ? Reseller::find($empresa->reseller_id) : null;

        if (! $revendedor || ! $revendedor->aprovado()) {
            return null;
        }

        $quando = Carbon::parse($quando);
        $ligadaEm = $empresa->reseller_linked_at ?? $empresa->created_at;

        // Um pagamento de antes de a empresa ser do revendedor não é dele.
        if ($ligadaEm && $quando->lt($ligadaEm->copy()->subMinute())) {
            return null;
        }

        if (ResellerCommission::where('origin_type', $origem)->where('origin_id', $origemId)->exists()) {
            return null;
        }

        $regra = $revendedor->regra();
        $jaHouve = ResellerCommission::where('reseller_id', $revendedor->id)->where('tenant_id', $empresa->id)
            ->where('status', '<>', 'anulada')->exists();
        $meses = $ligadaEm ? (int) $ligadaEm->diffInMonths($quando) : 0;

        if (! $regra->aplicaSe($jaHouve, $meses)) {
            return null;
        }

        $conta = $regra->calcular($semIva, $comIva, $planId);

        if ($conta['valor'] <= 0) {
            return null;
        }

        return ResellerCommission::create([
            'reseller_id' => $revendedor->id,
            'tenant_id' => $empresa->id,
            'origin_type' => $origem,
            'origin_id' => $origemId,
            'plan_id' => $planId,
            'base_amount' => $conta['base'],
            'amount' => $conta['valor'],
            'rule' => $regra->paraGuardar() + ['aplicado' => ['tipo' => $conta['tipo'], 'taxa' => $conta['taxa']]],
            'status' => 'por_pagar',
        ]);
    }

    /** Anular com motivo — nunca apagar. Uma já paga não se anula. */
    public function anular(ResellerCommission $c, string $motivo, ?int $userId): void
    {
        if ($c->status !== 'por_pagar') {
            throw ValidationException::withMessages(['motivo' => __('Só uma comissão por pagar se pode anular.')]);
        }

        $c->forceFill(['status' => 'anulada', 'cancel_reason' => $motivo, 'cancelled_at' => now(), 'cancelled_by' => $userId])->save();
    }

    /**
     * REGISTAR UM PAGAMENTO ao revendedor, sobre as comissões escolhidas.
     *
     * O valor é a soma delas — não se escreve à mão, para o extracto fechar.
     *
     * @param  list<int>  $ids
     * @param  array{method:string, reference:?string, paid_at:string, notes:?string}  $dados
     */
    public function pagar(Reseller $revendedor, array $ids, array $dados, ?int $userId): ResellerPayout
    {
        return DB::transaction(function () use ($revendedor, $ids, $dados, $userId) {
            /** @var Collection<int, ResellerCommission> $comissoes */
            $comissoes = ResellerCommission::where('reseller_id', $revendedor->id)
                ->whereIn('id', $ids)->where('status', 'por_pagar')->lockForUpdate()->get();

            if ($comissoes->isEmpty() || $comissoes->count() !== count(array_unique($ids))) {
                throw ValidationException::withMessages(['comissoes' => __('Escolha comissões por pagar deste revendedor.')]);
            }

            $pagamento = ResellerPayout::create([
                'reseller_id' => $revendedor->id,
                'amount' => round((float) $comissoes->sum('amount'), 2),
                'method' => $dados['method'],
                'reference' => $dados['reference'] ?? null,
                'paid_at' => $dados['paid_at'],
                'notes' => $dados['notes'] ?? null,
                'user_id' => $userId,
            ]);

            ResellerCommission::whereIn('id', $comissoes->pluck('id'))->update(['status' => 'paga', 'payout_id' => $pagamento->id, 'updated_at' => now()]);

            return $pagamento;
        });
    }

    /** Os números de um revendedor: por pagar, pago, anulado, e o do mês. */
    public static function totais(int $resellerId): array
    {
        $por = ResellerCommission::where('reseller_id', $resellerId)
            ->selectRaw('status, COUNT(*) AS n, COALESCE(SUM(amount), 0) AS valor')
            ->groupBy('status')->get()->keyBy('status');

        $doMes = (float) ResellerCommission::where('reseller_id', $resellerId)->where('status', '<>', 'anulada')
            ->where('created_at', '>=', now()->startOfMonth())->sum('amount');

        return [
            'por_pagar' => round((float) ($por['por_pagar']->valor ?? 0), 2),
            'por_pagar_n' => (int) ($por['por_pagar']->n ?? 0),
            'pago' => round((float) ($por['paga']->valor ?? 0), 2),
            'anulado' => round((float) ($por['anulada']->valor ?? 0), 2),
            'do_mes' => round($doMes, 2),
        ];
    }

    public static function paraEcra(ResellerCommission $c): array
    {
        return [
            'id' => $c->id,
            'empresa_id' => $c->tenant_id,
            'empresa' => $c->empresa?->name,
            'plano' => $c->plano?->name,
            'origem' => $c->origin_type,
            'origem_rotulo' => __(ResellerCommission::ORIGENS[$c->origin_type] ?? $c->origin_type),
            'base' => (float) $c->base_amount,
            'valor' => (float) $c->amount,
            'regra' => ($c->rule['aplicado']['tipo'] ?? 'percentagem') === 'fixo'
                ? __('Valor fixo')
                : rtrim(rtrim(number_format((float) ($c->rule['aplicado']['taxa'] ?? 0), 2, ',', '.'), '0'), ',') . '%',
            'estado' => $c->status,
            'estado_rotulo' => __(ResellerCommission::ESTADOS[$c->status] ?? $c->status),
            'motivo' => $c->cancel_reason,
            'pagamento' => $c->pagamento ? ['id' => $c->pagamento->id, 'data' => $c->pagamento->paid_at?->toDateString(), 'referencia' => $c->pagamento->reference] : null,
            'criada_em' => $c->created_at?->toIso8601String(),
        ];
    }
}
