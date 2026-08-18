<?php

namespace App\Services\Agent;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Aprovar ou recusar um pedido de plano, pela via do agente.
 *
 * A escrita é exactamente a mesma que o painel faz: mudar o status do
 * pedido e deixar o OrderObserver tratar de tudo o resto (cancelar a
 * subscrição anterior, criar a nova, sincronizar módulos, notificar).
 * Reimplementar aqui essa cadeia daria dois caminhos de aprovação a
 * divergir com o tempo.
 */
class DecisaoDePedido
{
    /**
     * O que aconteceria se este pedido fosse aprovado agora.
     *
     * Serve o dry_run e serve os tectos. Como aprovar não é tecnicamente
     * reversível — o observer cancela a subscrição em vigor — poder ver
     * os efeitos antes é a única salvaguarda honesta.
     */
    public function prever(Order $order): array
    {
        $tenant = $order->tenant;
        $actual = $tenant?->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->latest('id')
            ->first();

        $diasPorGastar = 0;
        if ($actual && $actual->ends_at && $actual->ends_at->isFuture()) {
            $diasPorGastar = (int) now()->diffInDays($actual->ends_at);
        }

        return [
            'pedido_id'        => $order->id,
            'empresa'          => $tenant?->name,
            'plano_pedido'     => $order->plan?->name,
            'valor'            => (float) $order->amount,
            'ciclo'            => $order->billing_cycle,
            'plano_actual'     => $actual?->plan?->name,
            'estado_actual'    => $actual?->status,
            'dias_por_gastar'  => $diasPorGastar,
            'tem_comprovativo' => !empty($order->payment_proof),
        ];
    }

    /**
     * Motivo por que o agente NÃO pode decidir este pedido sozinho.
     * Devolve null quando pode.
     */
    public function porqueNaoPode(Order $order): ?string
    {
        if ($order->status !== 'pending') {
            return "O pedido já não está pendente (está \"{$order->status}\").";
        }

        $tecto = (float) config('agent.aprovacao.valor_maximo', 50000);
        if ((float) $order->amount > $tecto) {
            return "O valor ({$order->amount}) excede o tecto do agente ({$tecto}). "
                 . 'Deixe uma recomendação e passe a um humano.';
        }

        $previsao = $this->prever($order);
        $limite   = (int) config('agent.aprovacao.dias_pagos_intocaveis', 7);

        if ($previsao['dias_por_gastar'] > $limite) {
            return "A empresa tem {$previsao['dias_por_gastar']} dias já pagos por gastar, "
                 . "e aprovar queima-os. Acima de {$limite} dias a decisão é de um humano.";
        }

        return null;
    }

    /**
     * Aprovação atómica.
     *
     * Tem de ser o update do MODELO, não do query builder: o OrderObserver
     * reage a wasChanged('status') e é ele que cancela a subscrição anterior,
     * cria a nova, sincroniza os módulos e notifica. Um update pelo query
     * builder não dispara evento nenhum e deixaria o pedido aprovado com o
     * cliente sem plano.
     *
     * A atomicidade vem do lockForUpdate: duas chamadas em paralelo esperam
     * uma pela outra e a segunda encontra o pedido já fora de 'pending'.
     */
    public function aprovar(Order $order, int $responsavelId): bool
    {
        return DB::transaction(function () use ($order, $responsavelId) {
            $fresco = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$fresco || $fresco->status !== 'pending') {
                return false;
            }

            $fresco->update([
                'status'      => 'approved',
                'approved_at' => now(),
                'approved_by' => $responsavelId,
            ]);

            return true;
        });
    }

    public function recusar(Order $order, int $responsavelId, string $motivo): bool
    {
        return DB::transaction(function () use ($order, $responsavelId, $motivo) {
            $fresco = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$fresco || $fresco->status !== 'pending') {
                return false;
            }

            $fresco->update([
                'status'           => 'rejected',
                'rejection_reason' => $motivo,
                'rejected_at'      => now(),
                'rejected_by'      => $responsavelId,
            ]);

            return true;
        });
    }
}
