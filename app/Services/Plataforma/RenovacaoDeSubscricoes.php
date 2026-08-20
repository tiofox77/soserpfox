<?php

namespace App\Services\Plataforma;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Support\CicloDeFacturacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A peça que faltava no meio do ciclo de facturação.
 *
 * O que havia: emitia-se factura ao contratar, e no fim do período o
 * `subscriptions:expire` CORTAVA o acesso. Entre uma coisa e outra, nada —
 * nenhuma segunda factura, nenhum aviso. O cliente era bloqueado sem nunca
 * ter recebido a conta do período seguinte.
 *
 * Isto fecha o ciclo em dois passos, deliberadamente separados:
 *
 *   1. emitirFacturasAVencer — dias antes do fim, sai uma factura PENDENTE
 *      do período seguinte. Cobrar não dá acesso.
 *   2. aplicarPagamento — quando essa factura é paga, a subscrição estende-se.
 *      Pagar é que dá acesso.
 *
 * Manter os dois separados é o que evita o pior erro possível aqui: estender
 * a subscrição só por ter emitido a factura, e dar meses de graça a quem
 * nunca pagou.
 */
class RenovacaoDeSubscricoes
{
    /** Com quantos dias de antecedência sai a factura do período seguinte. */
    public const DIAS_DE_ANTECEDENCIA = 8;

    /**
     * Emite as facturas dos períodos que estão a acabar.
     *
     * @param  int   $dias   janela de antecedência
     * @param  bool  $soVer  não grava nada, apenas devolve o que faria
     * @return array{emitidas: int, ignoradas: int, detalhe: array}
     */
    public function emitirFacturasAVencer(int $dias = self::DIAS_DE_ANTECEDENCIA, bool $soVer = false): array
    {
        $subscricoes = Subscription::with(['tenant', 'plan'])
            ->where('status', 'active')
            ->whereNull('cancelled_at')      // quem cancelou não é cobrado outra vez
            ->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [
                // Já expirada há mais de um dia não se renova por aqui: quem a
                // reactiva é o cliente ao subscrever de novo, com a decisão
                // dele pelo meio.
                now()->subDay(),
                now()->addDays($dias),
            ])
            ->get();

        $emitidas = 0;
        $ignoradas = 0;
        $detalhe = [];

        foreach ($subscricoes as $sub) {
            if (!$sub->tenant || !$sub->plan) {
                $ignoradas++;
                continue;
            }

            // Idempotência. Sem isto, cada passagem emitia outra factura e o
            // cliente acordava com dez contas do mesmo mês. É a razão de este
            // processo poder correr à boleia do tráfego sem fazer estragos.
            if ($this->jaTemFacturaDoProximoPeriodo($sub)) {
                $ignoradas++;
                continue;
            }

            $valor = $this->valorDoProximoPeriodo($sub);

            // Um plano a zero não se cobra. É o caso dos promocionais (FOX
            // Friendly) e das cortesias: emitir uma factura de 0,00 Kz só
            // enchia a conta do cliente de papel que não lhe pede nada.
            if ($valor <= 0) {
                $ignoradas++;
                continue;
            }

            $linha = [
                'tenant_id' => $sub->tenant_id,
                'empresa'   => $sub->tenant->name,
                'plano'     => $sub->plan->name,
                'termina'   => $sub->current_period_end->toDateString(),
                'valor'     => $valor,
            ];

            if ($soVer) {
                $emitidas++;
                $detalhe[] = $linha;
                continue;
            }

            try {
                $factura = $this->emitir($sub);
                $emitidas++;
                $detalhe[] = $linha + ['factura' => $factura->invoice_number];

                // O aviso ao cliente tem try/catch PRÓPRIO: uma falha do
                // servidor de email não pode fazer a factura — que já está
                // gravada — cair no catch de baixo e ser contada como
                // ignorada. E é aqui, no sítio exacto onde a factura de
                // renovação nasce: uma varredura não a distinguiria da
                // PRIMEIRA factura de uma subscrição nova, que não é renovação.
                try {
                    app(\App\Services\Billing\AvisosDeSubscricao::class)
                        ->facturaEmitida($factura, $sub);
                } catch (\Throwable $e) {
                    Log::error('Renovação: aviso da factura emitida falhou', [
                        'factura' => $factura->id,
                        'erro'    => $e->getMessage(),
                    ]);
                }
            } catch (\Throwable $e) {
                $ignoradas++;
                Log::error('Renovação: falha ao emitir factura', [
                    'subscription_id' => $sub->id,
                    'tenant_id'       => $sub->tenant_id,
                    'erro'            => $e->getMessage(),
                ]);
            }
        }

        return ['emitidas' => $emitidas, 'ignoradas' => $ignoradas, 'detalhe' => $detalhe];
    }

    /**
     * Já saiu a factura do período que se segue a este?
     *
     * Reconhece-se pela data de emissão: uma factura desta subscrição criada
     * DEPOIS do início do período em curso só pode ser a do seguinte — a
     * deste período foi emitida no início dele ou antes.
     */
    private function jaTemFacturaDoProximoPeriodo(Subscription $sub): bool
    {
        $inicio = $sub->current_period_start ?? $sub->created_at;

        return Invoice::where('subscription_id', $sub->id)
            ->where('invoice_date', '>=', $inicio->copy()->addDay()->startOfDay())
            ->exists();
    }

    /**
     * Quanto custa o período seguinte.
     *
     * O valor da subscrição em vigor manda: foi o que foi combinado com ESTE
     * cliente — inclui descontos e planos à medida. Só na falta dele se
     * recorre à tabela do plano, que pode ter mudado de preço entretanto.
     */
    private function valorDoProximoPeriodo(Subscription $sub): float
    {
        $valor = round((float) ($sub->amount ?? 0), 2);

        if ($valor > 0) {
            return $valor;
        }

        return round((float) $sub->plan->getPrice(
            CicloDeFacturacao::normalizar($sub->billing_cycle)
        ), 2);
    }

    private function emitir(Subscription $sub): Invoice
    {
        $ciclo = CicloDeFacturacao::normalizar($sub->billing_cycle);
        $valor = $this->valorDoProximoPeriodo($sub);

        return DB::transaction(function () use ($sub, $ciclo, $valor) {
            return Invoice::create([
                'tenant_id'       => $sub->tenant_id,
                'subscription_id' => $sub->id,
                'invoice_number'  => Invoice::generateInvoiceNumber(),
                'description'     => "Renovação {$sub->plan->name} — " . CicloDeFacturacao::nome($ciclo),
                'invoice_date'    => now(),
                // Vence no dia em que o período acaba — é até aí que há tempo
                // de pagar sem perder o acesso. Esta data é também o que
                // distingue uma factura de renovação já aplicada de uma por
                // aplicar (ver aplicarPagamento).
                'due_date'        => $sub->current_period_end,
                'subtotal'        => $valor,
                'tax'             => 0,
                'total'           => $valor,
                'status'          => 'pending',
            ]);
        });
    }

    /**
     * Pagar a factura de renovação estende a subscrição.
     *
     * O período novo começa onde o antigo acaba — não hoje. Senão, quem paga
     * com uma semana de antecedência perdia essa semana.
     *
     * Idempotente pela data: uma factura de renovação vence no fim do período
     * que ela renova, logo enquanto o período ainda terminar nessa data (ou
     * antes) o pagamento não foi aplicado. Depois de aplicado, o fim do
     * período fica para lá do vencimento e uma segunda passagem não faz nada.
     * A primeira factura de uma subscrição também cai deste lado: o período
     * dela já estava aberto quando foi emitida, e não deve ser estendido.
     */
    public function aplicarPagamento(Invoice $factura): ?Subscription
    {
        $sub = $factura->subscription;

        if (!$sub || $factura->status !== 'paid') {
            return $sub;
        }

        if (!$sub->current_period_end || !$factura->due_date) {
            return $sub;
        }

        if ($sub->current_period_end->greaterThan($factura->due_date->copy()->endOfDay())) {
            // O período já vai além desta factura: ou já foi aplicada, ou esta
            // é a factura inicial do período que está a correr.
            return $sub;
        }

        $inicio = $sub->current_period_end->isFuture()
            ? $sub->current_period_end->copy()
            : now();

        $fim = CicloDeFacturacao::fim($inicio, $sub->billing_cycle);

        $sub->update([
            'status'               => 'active',
            'current_period_start' => $inicio,
            'current_period_end'   => $fim,
            'ends_at'              => $fim,
        ]);

        Log::info('Renovação: subscrição estendida por pagamento', [
            'subscription_id' => $sub->id,
            'tenant_id'       => $sub->tenant_id,
            'factura'         => $factura->invoice_number,
            'ate'             => $fim->toDateString(),
        ]);

        return $sub->fresh();
    }
}
