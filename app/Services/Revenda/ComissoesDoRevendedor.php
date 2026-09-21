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
    /**
     * O ESTADO DE UMA COMISSÃO QUE O REVENDEDOR JÁ GANHOU À CABEÇA.
     *
     * Quando é ELE a pagar pelo cliente, paga o PREÇO DE REVENDEDOR — o de
     * tabela menos a comissão que ganharia. A comissão fica registada (as
     * contas da plataforma têm de bater: factura de 25.000 = 20.000 recebidos
     * + 5.000 de comissão), mas COMPENSADA: não entra no «por pagar», e o
     * revendedor não a recebe segunda vez.
     */
    public const COMPENSADA = 'compensada';

    public function doPedido(Order $pedido, bool $confirmadoPelaPlataforma): ?ResellerCommission
    {
        if ($pedido->status !== 'approved' || ! $confirmadoPelaPlataforma || (float) $pedido->amount <= 0) {
            return null;
        }

        /*
         * PEDIDO FEITO PELO REVENDEDOR: já pagou o preço de revendedor.
         *
         * O `amount` do pedido é o que ele transferiu, já sem a comissão. O
         * que se regista é o desconto que ele efectivamente teve — o preço de
         * tabela menos o que pagou — e fica compensado. Sem isto nascia uma
         * comissão por pagar SOBRE o valor já descontado: ganhava duas vezes.
         */
        if ($pedido->reseller_id) {
            return $this->seguro(fn () => $this->compensarPedido($pedido));
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

        /*
         * RENOVAÇÃO PAGA PELO REVENDEDOR: a factura é documento fiscal e fica
         * ao preço de tabela, mas ele transferiu o preço de revendedor. A
         * comissão é a mesma de sempre — só que já foi recebida à cabeça.
         */
        $estado = $factura->payment_submitted_by_reseller_id ? self::COMPENSADA : 'por_pagar';

        return $this->seguro(fn () => $this->gerar(
            $factura->tenant_id, 'invoice', $factura->id, $factura->subscription?->plan_id,
            (float) $factura->subtotal, (float) $factura->total, $factura->paid_at ?? now(), $estado,
        ));
    }

    /**
     * O PREÇO DE REVENDEDOR — o de tabela menos a comissão que ganharia.
     *
     * A MESMA regra que gera a comissão, e é essa a garantia: o revendedor ganha
     * exactamente o mesmo, pague ele pelo cliente (desconto à cabeça) ou pague
     * o cliente (comissão depois). Percentagem ou valor fixo, com ou sem IVA,
     * excepções por plano, «só no primeiro pagamento» — tudo o que o super
     * admin configurou vale aqui sem nada escrito duas vezes.
     *
     * Sem empresa (o catálogo), assume-se o primeiro pagamento de uma empresa
     * nova — é o que o revendedor está a pensar vender.
     *
     * @return array{preco: float, desconto: float, tabela: float}
     */
    public function precoDoRevendedor(Reseller $revendedor, float $preco, ?int $planId, ?Tenant $empresa = null): array
    {
        $tabela = round(max(0.0, $preco), 2);
        $conta = $this->contaDaRegra($revendedor, $empresa, $planId, $tabela, $tabela, now());
        $desconto = min($tabela, (float) ($conta['valor'] ?? 0));

        return ['preco' => round($tabela - $desconto, 2), 'desconto' => round($desconto, 2), 'tabela' => $tabela];
    }

    /**
     * O que o revendedor transfere por uma FACTURA DE RENOVAÇÃO.
     *
     * A factura é documento fiscal e fica ao preço de tabela; ele transfere o
     * total menos a comissão que ela daria — calculada com as DUAS bases da
     * factura (sem e com IVA), como no `daFactura`. Passar o total como as duas
     * bases dava-lhe, numa regra «sobre o valor sem IVA», um desconto maior do
     * que a comissão que ganharia.
     *
     * @return array{preco: float, desconto: float, tabela: float}
     */
    public function aPagarPeloRevendedor(Reseller $revendedor, Invoice $factura): array
    {
        $tabela = round((float) $factura->total, 2);
        $empresa = $factura->tenant_id ? Tenant::find($factura->tenant_id) : null;

        $conta = ($empresa && (int) $empresa->reseller_id === (int) $revendedor->id)
            ? $this->contaDaRegra($revendedor, $empresa, $factura->subscription?->plan_id, (float) $factura->subtotal, $tabela, now())
            : null;

        $desconto = min($tabela, (float) ($conta['valor'] ?? 0));

        return ['preco' => round($tabela - $desconto, 2), 'desconto' => round($desconto, 2), 'tabela' => $tabela];
    }

    /** Pedido do revendedor aprovado: o desconto que ele teve, já compensado. */
    private function compensarPedido(Order $pedido): ?ResellerCommission
    {
        if (ResellerCommission::where('origin_type', 'order')->where('origin_id', $pedido->id)->exists()) {
            return null;
        }

        $tabela = (float) ($pedido->plan?->getPrice($pedido->billing_cycle) ?? 0);
        $desconto = round($tabela - (float) $pedido->amount, 2);

        if ($desconto <= 0) {
            return null;
        }

        $empresa = $pedido->tenant_id ? Tenant::find($pedido->tenant_id) : null;
        $revendedor = Reseller::find($pedido->reseller_id);

        if (! $empresa || ! $revendedor || (int) $empresa->reseller_id !== (int) $revendedor->id) {
            return null;
        }

        return ResellerCommission::create([
            'reseller_id' => $revendedor->id,
            'tenant_id' => $empresa->id,
            'origin_type' => 'order',
            'origin_id' => $pedido->id,
            'plan_id' => $pedido->plan_id,
            'base_amount' => round($tabela, 2),
            'amount' => $desconto,
            'rule' => $revendedor->regra()->paraGuardar() + [
                'aplicado' => ['tipo' => 'desconto', 'taxa' => $tabela > 0 ? round($desconto / $tabela * 100, 2) : 0],
                'compensada' => true,
            ],
            'status' => self::COMPENSADA,
        ]);
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

    private function gerar(?int $tenantId, string $origem, int $origemId, ?int $planId, float $semIva, float $comIva, Carbon|string $quando, string $estado = 'por_pagar'): ?ResellerCommission
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

        $conta = $this->contaDaRegra($revendedor, $empresa, $planId, $semIva, $comIva, $quando);

        if (! $conta || $conta['valor'] <= 0) {
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
            'rule' => $revendedor->regra()->paraGuardar() + [
                'aplicado' => ['tipo' => $conta['tipo'], 'taxa' => $conta['taxa']],
            ] + ($estado === self::COMPENSADA ? ['compensada' => true] : []),
            'status' => $estado,
        ]);
    }

    /**
     * A CONTA DA REGRA para um pagamento — o que o revendedor ganharia com ele.
     * Null quando a regra não se aplica (só no primeiro pagamento e já houve
     * um, ou passou o prazo em meses).
     *
     * É a peça que a comissão e o preço de revendedor partilham: se um dia a
     * regra mudar, mudam os dois ao mesmo tempo.
     *
     * @return array{base: float, valor: float, tipo: string, taxa: float}|null
     */
    private function contaDaRegra(Reseller $revendedor, ?Tenant $empresa, ?int $planId, float $semIva, float $comIva, Carbon|string $quando): ?array
    {
        $regra = $revendedor->regra();
        $quando = Carbon::parse($quando);

        if ($empresa) {
            $ligadaEm = $empresa->reseller_linked_at ?? $empresa->created_at;
            $jaHouve = ResellerCommission::where('reseller_id', $revendedor->id)->where('tenant_id', $empresa->id)
                ->where('status', '<>', 'anulada')->exists();
            $meses = $ligadaEm ? (int) $ligadaEm->diffInMonths($quando) : 0;
        } else {
            // Sem empresa ainda: o primeiro pagamento de uma empresa acabada de ligar.
            $jaHouve = false;
            $meses = 0;
        }

        if (! $regra->aplicaSe($jaHouve, $meses)) {
            return null;
        }

        return $regra->calcular($semIva, $comIva, $planId);
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
            // O que ganhou à cabeça, pagando pelo cliente ao preço de revendedor.
            'descontado' => round((float) ($por[self::COMPENSADA]->valor ?? 0), 2),
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
