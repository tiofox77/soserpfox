<?php

namespace App\Services\Invoicing;

use App\Models\Client;

/**
 * O CLIENTE COMO O EMISSOR DE FACTURAS O PRECISA DE CONHECER.
 *
 * Uma forma só, usada pela lista das opções do emissor e pela procura no
 * servidor (`PartesApiController`): um cliente que chega pela procura tem de
 * propor o mesmo vencimento e a mesma região fiscal que um que já vinha na
 * lista.
 */
final class ClienteDoEmissor
{
    /** @return array{id:int,name:string,nif:?string,province:?string,payment_term_days:int,regiao:string} */
    public static function linha(Client $cliente): array
    {
        return [
            'id' => $cliente->id,
            'name' => $cliente->name,
            'nif' => $cliente->nif,
            'province' => $cliente->province,
            // É daqui que sai o vencimento quando se escolhe o cliente. O ecrã
            // em Livewire preenchia-o no `selectClient`; sem este campo a
            // factura nascia sem prazo.
            'payment_term_days' => self::diasDaCondicao($cliente),
            /*
             * A REGIÃO FISCAL QUE ESTE CLIENTE IMPLICA — decidida cá.
             *
             * Cabinda tem regime próprio (AO-CAB) e a regra é do `TaxResolver`.
             * O ecrã mostra o crachá «a aplicar: X» sem repetir a regra em
             * JavaScript — duas versões da mesma regra fiscal divergem, e esta
             * decide quanto imposto se cobra.
             */
            'regiao' => TaxResolver::regionForClient($cliente),
        ];
    }

    /** Os dias da condição de pagamento; sem condição, o prazo antigo da ficha. */
    public static function diasDaCondicao(Client $cliente): int
    {
        return $cliente->payment_term_id
            ? (int) ($cliente->paymentTerm?->days ?? 0)
            : (int) ($cliente->payment_term_days ?? 0);
    }
}
