<?php

namespace App\Services\Invoicing;

use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * PARA ONDE FOI ESTE ARTIGO: as vendas e os movimentos de stock, lado a lado.
 *
 * AS DUAS COISAS JUNTAS DE PROPÓSITO, e é essa a razão de este ecrã existir. As
 * vendas dizem para quem foi e por quanto; os movimentos dizem de que armazém
 * saiu e por que documento. É a DISCREPÂNCIA entre os dois que denuncia
 * problemas — vendeu-se sem saída de stock, saiu sem venda, ajustou-se à mão
 * sem justificação. Cada uma das listas sozinha não mostra nada disso.
 *
 * Estava dentro do componente Livewire dos artigos (`verRastreio`), e a
 * migração para React deixou-a para trás por inteiro: nem ecrã, nem API, nem
 * contas. Vive agora aqui, num serviço, para não voltar a ser propriedade de um
 * ecrã — o dia em que o relatório de artigos quiser o mesmo confronto, bebe
 * daqui em vez de o repetir.
 */
class RastreioDoArtigo
{
    /** Quantas linhas se trazem de cada lista. Acima disto ninguém lê. */
    private const TECTO = 300;

    /** Os períodos que o ecrã oferece. Zero é «tudo». */
    public const PERIODOS = [0, 30, 90, 365];

    /** O período por omissão: três meses chegam para ver um padrão. */
    public const PERIODO_OMISSAO = 90;

    /**
     * @return array{
     *     artigo: array<string,mixed>,
     *     vendas: list<array<string,mixed>>,
     *     movimentos: list<array<string,mixed>>,
     *     por_armazem: list<array<string,mixed>>,
     *     resumo: array<string,float|int>,
     *     dias: int
     * }
     */
    public function para(Product $artigo, int $dias = self::PERIODO_OMISSAO): array
    {
        $dias = in_array($dias, self::PERIODOS, true) ? $dias : self::PERIODO_OMISSAO;
        $desde = $dias > 0 ? Carbon::now()->subDays($dias) : null;
        $empresa = (int) $artigo->tenant_id;

        $vendas = SalesInvoiceItem::query()
            ->where('product_id', $artigo->id)
            /*
             * O ESCOPO DA EMPRESA VAI PELA FACTURA, e não pela linha.
             *
             * `invoicing_sales_invoice_items` não tem `tenant_id`: quem o tem é
             * o documento. Sem este `whereHas`, um `product_id` chegava para
             * ler as linhas de venda de outra empresa.
             */
            ->whereHas('invoice', function ($q) use ($empresa, $desde) {
                $q->where('tenant_id', $empresa)
                    ->when($desde, fn ($s) => $s->where('invoice_date', '>=', $desde));
            })
            ->with(['invoice:id,invoice_number,invoice_date,client_id,status,invoice_type', 'invoice.client:id,name'])
            ->latest('id')
            ->limit(self::TECTO)
            ->get();

        $movimentos = StockMovement::query()
            ->where('tenant_id', $empresa)
            ->where('product_id', $artigo->id)
            ->when($desde, fn ($q) => $q->where('created_at', '>=', $desde))
            ->with(['warehouse:id,name'])
            ->latest('id')
            ->limit(self::TECTO)
            ->get();

        $porArmazem = Stock::where('tenant_id', $empresa)
            ->where('product_id', $artigo->id)
            ->with('warehouse:id,name')
            ->get();

        $vendido = (float) $vendas->sum('quantity');
        $saidas = (float) $movimentos->where('type', 'out')->sum('quantity');

        return [
            'artigo' => [
                'id' => $artigo->id,
                'nome' => $artigo->name,
                'codigo' => $artigo->code,
                'unidade' => $artigo->unit,
            ],

            'vendas' => $vendas->map(fn ($v) => [
                'id' => $v->id,
                'data' => optional($v->invoice?->invoice_date)->toDateString(),
                'documento' => $v->invoice?->numeroInterno(),
                'documento_id' => $v->invoice?->id,
                'cliente' => $v->invoice?->client?->name,
                'quantidade' => (float) $v->quantity,
                'preco' => (float) $v->unit_price,
                'total' => (float) $v->total,
            ])->values()->all(),

            'movimentos' => $movimentos->map(fn ($m) => [
                'id' => $m->id,
                'data' => optional($m->created_at)->toDateTimeString(),
                'tipo' => $m->type,
                'armazem' => $m->warehouse?->name,
                'quantidade' => (float) $m->quantity,

                /*
                 * A ORIGEM: que documento mexeu no stock, e a nota se houver.
                 *
                 * `reference_type` é o nome da classe com o espaço de nomes
                 * todo (`App\Models\Invoicing\SalesInvoice`) — sai só o nome
                 * curto, como o ecrã de sempre mostrava. Uma nota longa corta,
                 * porque isto é uma célula de tabela e não um campo de texto.
                 */
                'origem' => trim(implode(' · ', array_filter([
                    $m->reference_type ? class_basename($m->reference_type) : null,
                    $m->notes ? \Illuminate\Support\Str::limit($m->notes, 45) : null,
                ]))),
            ])->values()->all(),

            'por_armazem' => $porArmazem->map(fn ($s) => [
                'armazem' => $s->warehouse?->name ?? __('Armazém #:id', ['id' => $s->warehouse_id]),
                'quantidade' => (float) $s->quantity,
            ])->values()->all(),

            'resumo' => [
                'qtd_vendida' => round($vendido, 3),
                'valor_vendido' => round((float) $vendas->sum('total'), 2),
                'documentos' => $vendas->pluck('sales_invoice_id')->unique()->count(),
                'entradas' => round((float) $movimentos->where('type', 'in')->sum('quantity'), 3),
                'saidas' => round($saidas, 3),
                'stock_total' => round((float) $porArmazem->sum('quantity'), 3),

                /*
                 * VENDIDO MENOS SAÍDAS — o número que justifica o ecrã.
                 *
                 * Diferente de zero quer dizer que se venderam unidades que
                 * nunca saíram do stock: é o sintoma exacto do artigo que
                 * aparece disponível no balcão mas cuja baixa falha. Aparece
                 * assinalado a vermelho, com a frase por extenso.
                 */
                'divergencia' => round($vendido - $saidas, 3),
            ],

            'dias' => $dias,
        ];
    }
}
