<?php

namespace App\Support;

use App\Models\Invoicing\SalesInvoice;

/**
 * A FACTURA QUE VEM NO ENDEREÇO.
 *
 * Da lista de facturas carrega-se «Receber» ou «Nota de crédito» e chega-se
 * ao ecrã com `?invoice=123`. O ecrã em Livewire lia isso no `mount()` e abria
 * já com o cliente e a factura escolhidos; quem recebe ao balcão não tem de a
 * procurar outra vez numa lista de duzentas.
 *
 * Quem resolve é o servidor, e de propósito: o React não sabe de que CLIENTE é
 * a factura, e a lista de facturas do ecrã pede-se por cliente. Aqui a factura
 * já vem com o escopo da empresa aplicado (BelongsToTenant) — uma factura de
 * outra empresa no endereço não abre nada.
 */
final class FacturaNaMorada
{
    /**
     * @return array{facturaId?: int, clienteId?: int}
     */
    public static function props(): array
    {
        $id = request()->query('invoice');

        if (! is_string($id) && ! is_int($id)) {
            return [];
        }

        if (! ctype_digit((string) $id)) {
            return [];
        }

        $factura = SalesInvoice::query()
            ->whereKey((int) $id)
            ->first(['id', 'client_id']);

        if (! $factura) {
            return [];
        }

        return [
            'facturaId' => (int) $factura->id,
            'clienteId' => $factura->client_id ? (int) $factura->client_id : null,
        ];
    }
}
