<?php

namespace App\Support;

use App\Models\Treasury\PaymentMethod;

/**
 * A FORMA DE PAGAMENTO QUE SAI NO PAPEL — pelo nome, não pelo código.
 *
 * Os documentos guardam o CÓDIGO (`TRANSFER`, `CASH`, `tpa`…) e o papel
 * imprimia-o assim, em maiúsculas: «Forma de pagamento: TRANSFER». O nome está
 * no catálogo da empresa (Tesouraria › Formas de pagamento), que é o que a
 * pessoa escolheu e reconhece.
 *
 * Pedido de 15/09/2026: a forma de pagamento ao lado da hora de emissão e da
 * data de vencimento, em todos os documentos de venda. Um documento que ainda
 * não foi pago (factura a prazo, proforma, orçamento) não tem forma de
 * pagamento própria — mostra a CONDIÇÃO DE PAGAMENTO do cliente («30 dias»),
 * que é o que foi combinado com ele.
 *
 * UM CÓDIGO DESCONHECIDO SAI EM MAIÚSCULAS, como sempre saiu. Não é descuido:
 * o molde do PWA (`MoldeDoDocumento`) desenha o modelo com o código
 * `pagamento_token` e procura `PAGAMENTO_TOKEN` para pôr a marca no lugar.
 */
class FormaDePagamento
{
    /** Os códigos de sempre, para quando a empresa não tem o catálogo (ou o código não está nele). */
    private const NOMES = [
        'CASH' => 'Dinheiro',
        'NU' => 'Dinheiro',
        'DINHEIRO' => 'Dinheiro',
        'TRANSFER' => 'Transferência Bancária',
        'TB' => 'Transferência Bancária',
        'TPA' => 'TPA (Multicaixa)',
        'CC' => 'Cartão de Crédito',
        'CD' => 'Cartão de Débito',
        'MCX' => 'Multicaixa Express',
        'MULTICAIXA' => 'Multicaixa',
        'MB' => 'Multicaixa',
        'CHECK' => 'Cheque',
        'CH' => 'Cheque',
        'DEBIT' => 'Débito Direto',
        'MBWAY' => 'MB Way',
        'OTHER' => 'Outro',
        'OU' => 'Outro',
        'MIXED' => 'Pagamento Misto',
        'MISTO' => 'Pagamento Misto',
    ];

    /** @var array<int, array<string, string>> o catálogo de cada empresa, uma consulta por pedido */
    private static array $catalogos = [];

    /** O nome de um código de forma de pagamento. */
    public static function nome(?string $codigo, ?int $tenantId = null): ?string
    {
        $codigo = trim((string) $codigo);

        if ($codigo === '') {
            return null;
        }

        $chave = strtoupper($codigo);

        if ($tenantId) {
            self::$catalogos[$tenantId] ??= PaymentMethod::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->get(['code', 'name'])
                ->mapWithKeys(fn ($m) => [strtoupper((string) $m->code) => (string) $m->name])
                ->all();

            if (! empty(self::$catalogos[$tenantId][$chave])) {
                return self::$catalogos[$tenantId][$chave];
            }
        }

        return self::NOMES[$chave] ?? $chave;
    }

    /**
     * O que sai na coluna do documento: a forma de pagamento dele, ou, sem ela,
     * a condição de pagamento do cliente.
     */
    public static function doDocumento(?string $codigo, $cliente = null, ?int $tenantId = null): string
    {
        if ($nome = self::nome($codigo, $tenantId)) {
            return $nome;
        }

        if ($cliente) {
            $condicao = $cliente->payment_term_id ? $cliente->paymentTerm?->name : null;

            if ($condicao) {
                return $condicao;
            }

            if ((int) $cliente->payment_term_days > 0) {
                return __(':n dias', ['n' => (int) $cliente->payment_term_days]);
            }
        }

        return 'N/A';
    }

    /** Para os ensaios: esquecer os catálogos lidos. */
    public static function esquecer(): void
    {
        self::$catalogos = [];
    }
}
