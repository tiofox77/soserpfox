<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\PurchaseProforma;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\SalesQuote;
use InvalidArgumentException;

/**
 * OS DOCUMENTOS QUE SE LISTAM DA MESMA MANEIRA.
 *
 * Proformas de venda, orçamentos, facturas de compra, proformas de compra e
 * recibos são cinco ecrãs com a mesma forma: uma tabela com número, a outra
 * parte (cliente ou fornecedor), data, estado e valor, mais procura e
 * paginação. Em Blade são cinco ficheiros e 4.700 linhas que se repetem —
 * corrigir a paginação num deles deixava os outros quatro por corrigir.
 *
 * Aqui há UM controlador, UM ecrã React e este registo a dizer o que muda
 * entre eles. Acrescentar um sexto documento é acrescentar uma entrada.
 *
 * O QUE NÃO ENTRA AQUI: as facturas de venda. Têm coluna do portal AGT, dois
 * números (interno e da AGT), saldo por receber e o travão da nota de crédito
 * — é bastante para justificar um ecrã próprio, e tem-no.
 */
class TiposDeDocumento
{
    /**
     * @return array<string, array{
     *   modelo: class-string, titulo: string, numero: string, data: string,
     *   parte: string, relacao: string, permissao: string, valor: string,
     *   rota: string, tem_saldo: bool
     * }>
     */
    public static function todos(): array
    {
        return [
            'proformas-venda' => [
                'modelo' => SalesProforma::class,
                'titulo' => __('Proformas de Venda'),
                'numero' => 'proforma_number',
                'data' => 'proforma_date',
                'parte' => 'cliente',
                'relacao' => 'client',
                'permissao' => 'invoicing.sales.proformas.view',
                'valor' => 'total',
                'rota' => '/invoicing/sales/proformas',
                'tem_saldo' => false,
            ],

            'orcamentos' => [
                'modelo' => SalesQuote::class,
                'titulo' => __('Orçamentos'),
                'numero' => 'quote_number',
                'data' => 'quote_date',
                'parte' => 'cliente',
                'relacao' => 'client',
                'permissao' => 'invoicing.sales.quotes.view',
                'valor' => 'total',
                'rota' => '/invoicing/sales/quotes',
                'tem_saldo' => false,
            ],

            'facturas-compra' => [
                'modelo' => PurchaseInvoice::class,
                'titulo' => __('Facturas de Compra'),
                'numero' => 'invoice_number',
                'data' => 'invoice_date',
                'parte' => 'fornecedor',
                'relacao' => 'supplier',
                'permissao' => 'invoicing.purchases.invoices.view',
                'valor' => 'total',
                'rota' => '/invoicing/purchases/invoices',
                // A única com pagamentos: mostra quanto falta pagar.
                'tem_saldo' => true,
            ],

            'proformas-compra' => [
                'modelo' => PurchaseProforma::class,
                'titulo' => __('Proformas de Compra'),
                'numero' => 'proforma_number',
                'data' => 'proforma_date',
                'parte' => 'fornecedor',
                'relacao' => 'supplier',
                'permissao' => 'invoicing.purchases.proformas.view',
                'valor' => 'total',
                'rota' => '/invoicing/purchases/proformas',
                'tem_saldo' => false,
            ],

            'recibos' => [
                'modelo' => Receipt::class,
                'titulo' => __('Recibos'),
                'numero' => 'receipt_number',
                'data' => 'payment_date',
                'parte' => 'cliente',
                'relacao' => 'client',
                'permissao' => 'invoicing.receipts.view',
                // O recibo não tem `total`: tem o que foi recebido.
                'valor' => 'amount_paid',
                'rota' => '/invoicing/receipts',
                'tem_saldo' => false,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function um(string $slug): array
    {
        $tipos = self::todos();

        if (! isset($tipos[$slug])) {
            throw new InvalidArgumentException("Tipo de documento desconhecido: {$slug}");
        }

        return $tipos[$slug];
    }

    public static function existe(string $slug): bool
    {
        return isset(self::todos()[$slug]);
    }
}
