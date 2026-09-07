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
     * A CHAVE `agt` DIZ O QUE A COLUNA DO PORTAL AGT MOSTRA, e é por isso que
     * vive aqui e não no ecrã:
     *
     *   · `propria`     — a empresa comunica-o (recibo, notas de crédito e de
     *                     débito): o selo lê o `agt_status` do documento;
     *   · `fornecedor`  — factura de compra: comunica-a quem a emitiu;
     *   · `nao-fiscal`  — proformas, orçamentos e adiantamentos, que NUNCA são
     *                     enviados. Sem esta distinção o selo caía no ramo por
     *                     omissão e dizia «pendente de envio» numa proforma —
     *                     um alarme para uma coisa que nunca vai acontecer.
     *
     * @return array<string, array{
     *   modelo: class-string, titulo: string, numero: string, data: string,
     *   parte: string, relacao: string, permissao: string, valor: string,
     *   rota: string, tem_saldo: bool, agt: string
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
                'agt' => 'nao-fiscal',
                // A VALIDADE, que a lista em Blade mostrava: uma proforma
                // caducada não se converte, e a coluna é o que o diz.
                'prazo' => ['coluna' => 'valid_until', 'rotulo' => 'Validade'],
                // Converter em factura e ver as que já saíram desta.
                'converte' => 'factura',
                'historico' => 'invoices',
                'apaga' => 'invoicing.sales.proformas.delete',
                'descricao' => 'Orçamentos e propostas comerciais',
                'novo' => 'Nova Proforma',
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
                'agt' => 'nao-fiscal',
                'prazo' => ['coluna' => 'valid_until', 'rotulo' => 'Validade'],
                'converte' => 'factura',
                'historico' => 'invoices',
                'apaga' => 'invoicing.sales.quotes.delete',
                'descricao' => 'Propostas comerciais detalhadas',
                'novo' => 'Novo Orçamento',
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
                'agt' => 'fornecedor',
                'prazo' => ['coluna' => 'due_date', 'rotulo' => 'Vencimento'],
                'descricao' => 'Faturas e compras de fornecedores',
                'novo' => 'Nova Fatura',
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
                'agt' => 'nao-fiscal',
                'prazo' => ['coluna' => 'valid_until', 'rotulo' => 'Validade'],
                'converte' => 'factura',
                // `hasOne` e não `hasMany`: a proforma de compra dá UMA factura,
                // e o `convertToInvoice` dela recusa converter duas vezes.
                'historico' => 'purchaseInvoice',
                'apaga' => 'invoicing.purchases.proformas.delete',
                'descricao' => 'Orçamentos e propostas de fornecedores',
                'novo' => 'Nova Proforma',
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
                'agt' => 'propria',
                'descricao' => 'Recebimentos dos clientes',
                'novo' => 'Novo Recibo',

                /*
                 * O RECIBO TEM DOIS LADOS, e é o único documento que os tem.
                 *
                 * Um recibo de VENDA recebe de um cliente; um de COMPRA paga a
                 * um fornecedor — e nesse o `client_id` fica vazio, porque a
                 * parte está em `supplier_id`. Ler sempre `client` deixava
                 * metade dos recibos SEM NOME NENHUM na lista, e sem maneira
                 * de os separar: o ecrã de sempre tinha a coluna e o filtro.
                 *
                 * Fica no esquema e não no controlador para que o dia em que
                 * outro documento tenha dois lados não precise de um `if` com
                 * o nome de um tipo lá dentro.
                 */
                'lados' => [
                    'coluna' => 'type',
                    'rotulo' => 'Tipo',
                    // O cabeçalho da coluna da parte, que aqui não é só o
                    // cliente. Era o que a lista em Blade tinha por cima dela.
                    'parte' => 'Cliente/Fornecedor',
                    'opcoes' => [
                        'sale' => ['rotulo' => 'Venda', 'cor' => 'bom', 'icone' => 'fa-cart-shopping', 'relacao' => 'client'],
                        'purchase' => ['rotulo' => 'Compra', 'cor' => 'aviso', 'icone' => 'fa-box', 'relacao' => 'supplier'],
                    ],
                ],
            ],

            // As notas e os adiantamentos listam-se da mesma maneira; o que
            // têm de próprio (o E43, as linhas, o saldo por usar) vive nos
            // seus emissores, não na lista.
            'notas-credito' => [
                'modelo' => \App\Models\Invoicing\CreditNote::class,
                'titulo' => __('Notas de Crédito'),
                'numero' => 'credit_note_number',
                'data' => 'issue_date',
                'parte' => 'cliente',
                'relacao' => 'client',
                'permissao' => 'invoicing.credit-notes.view',
                'valor' => 'total',
                'rota' => '/invoicing/credit-notes',
                'tem_saldo' => false,
                'agt' => 'propria',
                // A FACTURA DE ORIGEM e o MOTIVO, que a lista em Blade tinha
                // em coluna própria: uma nota de crédito sem saber de que
                // factura é não se lê.
                'origem' => ['relacao' => 'invoice', 'rotulo' => 'Fatura Origem'],
                'motivo' => [
                    'return' => 'Devolução', 'discount' => 'Desconto',
                    'correction' => 'Correção', 'other' => 'Outro',
                ],
                'apaga' => 'invoicing.credit-notes.delete',
                'descricao' => 'Devoluções, descontos e correções',
                'novo' => 'Nova Nota de Crédito',
            ],

            'notas-debito' => [
                'modelo' => \App\Models\Invoicing\DebitNote::class,
                'titulo' => __('Notas de Débito'),
                'numero' => 'debit_note_number',
                'data' => 'issue_date',
                'parte' => 'cliente',
                'relacao' => 'client',
                'permissao' => 'invoicing.debit-notes.view',
                'valor' => 'total',
                'rota' => '/invoicing/debit-notes',
                'tem_saldo' => false,
                'agt' => 'propria',
                'origem' => ['relacao' => 'invoice', 'rotulo' => 'Fatura Ref.'],
                'motivo' => [
                    'interest' => 'Juros', 'penalty' => 'Multa',
                    'additional_charge' => 'Cobrança Adicional',
                    'correction' => 'Correção', 'other' => 'Outro',
                ],
                'apaga' => 'invoicing.debit-notes.delete',
                'descricao' => 'Juros, multas e cobranças adicionais',
                'novo' => 'Nova Nota de Débito',
            ],

            'adiantamentos' => [
                'modelo' => \App\Models\Invoicing\Advance::class,
                'titulo' => __('Adiantamentos'),
                'numero' => 'advance_number',
                'data' => 'payment_date',
                'parte' => 'cliente',
                'relacao' => 'client',
                'permissao' => 'invoicing.advances.view',
                'valor' => 'amount',
                'rota' => '/invoicing/advances',
                'tem_saldo' => false,
                'agt' => 'nao-fiscal',
                'descricao' => 'Sinais recebidos por conta de facturas futuras',
                'novo' => 'Novo Adiantamento',

                /*
                 * O USADO E O DISPONÍVEL — as duas colunas que faltavam.
                 *
                 * Um adiantamento não é um documento de valor fixo: é um saldo
                 * que se vai gastando à medida que as facturas o consomem. Ver
                 * só o valor recebido não diz o que interessa saber — quanto
                 * ainda lá está para usar.
                 */
                'montantes' => [
                    ['coluna' => 'used_amount', 'rotulo' => 'Usado', 'icone' => 'fa-arrow-trend-down'],
                    ['coluna' => 'remaining_amount', 'rotulo' => 'Disponível', 'icone' => 'fa-wallet'],
                ],
            ],
        ];
    }

    /**
     * OS DOCUMENTOS QUE O EDITOR EM REACT JÁ SABE EMITIR.
     *
     * Só PROPOSTAS: proforma de venda, orçamento e proforma de compra. São os
     * três que não têm número fiscal, não são assinados, não vão à AGT e não
     * mexem em stock. Se um deles sair errado, corrige-se e grava-se outra vez.
     *
     * O QUE FICA DE FORA, E PORQUÊ, um a um:
     *   · factura de venda — número de série, hash encadeado e assinatura AGT;
     *   · factura de compra — faz o stock entrar (`stock_ja_entrou`);
     *   · recibo — mexe no `paid_amount` por lançamento diferencial;
     *   · notas de crédito e débito — o travão do E43 e as quantidades por linha.
     *
     * Cada um desses entra por si, com o seu travão. Não se apressa o que a
     * AGT recusa dias depois.
     *
     * @return array<string, array{itens: class-string, chave: string, parte_id: string, numero_de: string}>
     */
    public static function editaveis(): array
    {
        return [
            'proformas-venda' => [
                'itens' => \App\Models\Invoicing\SalesProformaItem::class,
                'chave' => 'sales_proforma_id',
                'parte_id' => 'client_id',
                'numero_de' => 'proforma',
            ],
            'orcamentos' => [
                'itens' => \App\Models\Invoicing\SalesQuoteItem::class,
                'chave' => 'sales_quote_id',
                'parte_id' => 'client_id',
                'numero_de' => 'quote',
            ],
            'proformas-compra' => [
                'itens' => \App\Models\Invoicing\PurchaseProformaItem::class,
                'chave' => 'purchase_proforma_id',
                'parte_id' => 'supplier_id',
                'numero_de' => 'proforma',
            ],
        ];
    }

    public static function eEditavel(string $slug): bool
    {
        return isset(self::editaveis()[$slug]);
    }

    /**
     * OS DOCUMENTOS QUE A LISTA DEIXA DUPLICAR.
     *
     * São os mesmos quatro que o ecrã em Blade deixava, e a escolha não é
     * arbitrária: duplica-se o que se volta a fazer parecido — a proforma que
     * se repete, a compra ao mesmo fornecedor. Um RECIBO, uma nota de crédito
     * ou um adiantamento nascem sempre de outro documento e de um valor
     * concreto; duplicá-los seria oferecer um atalho para dar por recebido
     * dinheiro que não entrou.
     *
     * A factura de VENDA também se duplica, mas tem lista e editor próprios
     * (`SalesInvoiceApiController` / `FacturaApiController`) e por isso não
     * está aqui: esta lista serve só o ecrã genérico.
     *
     * O que viaja e o que fica está no `DuplicaDocumento` — aqui só se diz
     * ONDE se oferece o botão.
     *
     * @return array<int, string>
     */
    public static function duplicaveis(): array
    {
        return ['proformas-venda', 'proformas-compra', 'facturas-compra'];
    }

    public static function eDuplicavel(string $slug): bool
    {
        return in_array($slug, self::duplicaveis(), true);
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
