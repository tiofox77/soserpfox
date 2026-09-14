<?php

namespace App\Services\Invoicing;

/**
 * O SELO DO PORTAL AGT — o que a coluna «AGT» de uma lista diz de um documento.
 *
 * PORQUE VIVE AQUI E NÃO NO CONTROLADOR. Nasceu privado no
 * `DocumentosApiController`, para as notas, os recibos e as proformas; a lista
 * das facturas de venda, que tem ecrã e Resource próprios, ficou com uma regra
 * sua — e errada: lia o `jws_signature`, que é a assinatura LOCAL, posta ao
 * emitir, antes de qualquer envio. Uma factura recusada pela AGT, ou que nunca
 * lá chegou, aparecia a verde a dizer «Emitida no Portal AGT». Duas regras
 * para a mesma coluna divergem; esta é a única.
 *
 * O selo distingue quatro coisas, e a quarta é a que evita o alarme falso:
 * aceite, à espera, recusada — e NÃO COMUNICÁVEL, para a proforma, o orçamento
 * e o adiantamento, que não são documentos fiscais e nunca são enviados. Sem
 * essa distinção a coluna dizia «pendente de envio» numa proforma e mandava
 * alguém procurar um envio que nunca vai existir.
 *
 * Lê o `agt_status` — o que a AGT respondeu — e nada mais.
 */
final class SeloDaAgt
{
    /**
     * @param  object  $d         o documento (precisa de `status` e `agt_status`)
     * @param  string  $natureza  `propria`, `fornecedor` ou `nao-fiscal` (ver `TiposDeDocumento`)
     * @return array{natureza: string, estado: ?string, rotulo: string, cor: string}
     */
    public static function de(object $d, string $natureza): array
    {
        $estado = strtolower(trim((string) ($d->agt_status ?? '')));

        if ($natureza === 'nao-fiscal') {
            return ['natureza' => $natureza, 'estado' => null,
                'rotulo' => __('Não comunicável à AGT'), 'cor' => 'neutra'];
        }

        // Quem comunica uma factura de compra é o fornecedor que a emitiu.
        if ($natureza === 'fornecedor') {
            return ['natureza' => $natureza, 'estado' => null,
                'rotulo' => __('Responsabilidade do fornecedor'), 'cor' => 'neutra'];
        }

        // Um rascunho ainda não foi emitido: não há envio nenhum por fazer.
        if (($d->status ?? null) === 'draft') {
            return ['natureza' => $natureza, 'estado' => null,
                'rotulo' => __('Ainda não emitida'), 'cor' => 'neutra'];
        }

        [$rotulo, $cor] = match ($estado) {
            'validated', 'accepted', 'approved', 'success' => [__('Emitida no Portal AGT'), 'bom'],
            'submitted', 'processing', 'sent' => [__('Enviada — aguarda AGT'), 'primaria'],
            'rejected', 'failed', 'error' => [__('Falhou — reenviar à AGT'), 'perigo'],
            default => [__('Pendente de envio à AGT'), 'aviso'],
        };

        return ['natureza' => $natureza, 'estado' => $estado ?: null, 'rotulo' => $rotulo, 'cor' => $cor];
    }
}
