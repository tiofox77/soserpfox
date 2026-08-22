<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Sales\QuoteCreate;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesQuote;
use App\Models\Invoicing\SalesQuoteItem;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Orçamento de venda (ORC).
 *
 * Documento comercial, não fiscal: numeração própria por empresa, descrição
 * longa por linha, e conversão em factura que resolve a taxa no momento.
 * Cobre o que distingue o orçamento da proforma — não deve ter série nem hash.
 */
class OrcamentoTest extends TenantTestCase
{
    private function produto(array $over = []): Product
    {
        return Product::create(array_merge([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Instalação Eléctrica',
            'sku'          => 'SRV-' . uniqid(),
            'price'        => 1000,
            'cost'         => 0,
            'type'         => 'servico',
            'manage_stock' => false,
        ], $over));
    }

    private function orcamento(array $over = []): SalesQuote
    {
        return SalesQuote::create(array_merge([
            'tenant_id'  => $this->tenant->id,
            'client_id'  => $this->cliente->id,
            'created_by' => $this->user->id,
            'quote_date' => now()->toDateString(),
            'status'     => 'draft',
        ], $over));
    }

    // ── numeração ──────────────────────────────────────────────────────────

    public function test_numero_e_sequencial_por_empresa(): void
    {
        $ano = now()->year;

        $a = $this->orcamento();
        $b = $this->orcamento();

        $this->assertSame("ORC-{$ano}-000001", $a->quote_number);
        $this->assertSame("ORC-{$ano}-000002", $b->quote_number);
    }

    // ── não é documento fiscal ───────────────────────────────────────────────

    public function test_orcamento_nao_tem_serie_nem_hash(): void
    {
        // A distinção do documento está no esquema: sem colunas fiscais não há
        // como, por descuido, passar a comportar-se como uma proforma.
        $this->assertFalse(Schema::hasColumn('invoicing_sales_quotes', 'saft_hash'));
        $this->assertFalse(Schema::hasColumn('invoicing_sales_quotes', 'series_id'));

        $q = $this->orcamento();
        $this->assertNull($q->getAttribute('saft_hash'));
    }

    // ── descrição longa por linha ────────────────────────────────────────────

    public function test_guardar_persiste_a_descricao_por_linha(): void
    {
        $produto = $this->produto();
        $descricao = "Instalação de quadro eléctrico trifásico.\nInclui material e 8 horas de mão de obra.";

        Livewire::test(QuoteCreate::class)
            ->set('client_id', $this->cliente->id)
            ->call('addProduct', $produto->id)
            ->call('updateDescription', $produto->id, $descricao)
            ->call('save', 'sent');

        $item = SalesQuoteItem::query()
            ->whereHas('quote', fn ($q) => $q->where('tenant_id', $this->tenant->id))
            ->where('product_id', $produto->id)
            ->first();

        $this->assertNotNull($item, 'a linha do orçamento tinha de ficar gravada');
        $this->assertSame($descricao, $item->description);
    }

    // ── conversão em factura ─────────────────────────────────────────────────

    public function test_converter_cria_factura_ligada_e_copia_a_descricao(): void
    {
        $produto = $this->produto();

        $q = $this->orcamento();
        SalesQuoteItem::create([
            'sales_quote_id' => $q->id,
            'product_id'     => $produto->id,
            'product_name'   => $produto->name,
            'description'    => 'Serviço detalhado do orçamento',
            'quantity'       => 1,
            'unit'           => 'UN',
            'unit_price'     => 1000,
            'subtotal'       => 1000,
            'tax_rate'       => 14,
            'total'          => 1140,
            'order'          => 1,
        ]);

        $factura = $q->fresh()->convertToInvoice();

        $this->assertInstanceOf(SalesInvoice::class, $factura);
        $this->assertSame($q->id, $factura->quote_id, 'a factura tem de apontar ao orçamento de origem');

        $linha = $factura->items()->first();
        $this->assertNotNull($linha);
        $this->assertSame('Serviço detalhado do orçamento', $linha->description);
        // A taxa é a de hoje, resolvida pelo TaxResolver (14% no tenant de teste).
        $this->assertSame(14.0, (float) $linha->tax_rate);
    }

    public function test_a_factura_convertida_aparece_no_historico_do_orcamento(): void
    {
        $produto = $this->produto();
        $q = $this->orcamento();
        SalesQuoteItem::create([
            'sales_quote_id' => $q->id,
            'product_id'     => $produto->id,
            'product_name'   => $produto->name,
            'quantity'       => 1, 'unit' => 'UN', 'unit_price' => 1000,
            'subtotal'       => 1000, 'tax_rate' => 14, 'total' => 1140, 'order' => 1,
        ]);

        $q->fresh()->convertToInvoice();

        $this->assertSame(1, $q->fresh()->invoices()->count());
    }
}
