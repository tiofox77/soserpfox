<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Services\Invoicing\EmissorDeNotas;
use Illuminate\Support\Collection;
use Tests\TenantTestCase;

/**
 * A DEVOLUÇÃO NO TURNO DO POS.
 *
 * O turno é a conta da GAVETA, e nunca soube o que era uma nota de crédito: a
 * tabela dos movimentos tinha um ENUM de cinco tipos e a devolução não era
 * nenhum deles. Medido na base antes de corrigir: 1177 movimentos gravados,
 * TODOS `invoice`, e nem um a dizer que saiu dinheiro — enquanto sete notas de
 * crédito no valor de 52.826 Kz tinham sido emitidas dentro de três turnos.
 *
 * E não era só o relatório a ficar incompleto. O «Dinheiro Esperado» é
 * `saldo inicial + vendas em dinheiro`: uma devolução paga da gaveta fazia
 * FALTAR dinheiro ao fecho, e o operador tinha de explicar uma diferença que
 * não era dele.
 */
class DevolucaoNoTurnoDoPosTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        InvoicingSeries::create([
            'tenant_id' => $this->tenant->id, 'series_code' => 'NC', 'name' => 'NC (ensaio)',
            'document_type' => 'credit_note', 'agt_environment' => 'sandbox',
            'is_default' => true, 'is_active' => true,
        ]);
    }

    /**
     * A DEVOLUÇÃO TIRA DINHEIRO DA GAVETA.
     *
     * O movimento entra negativo, o balde do dinheiro desce e o esperado passa
     * a bater certo com o que lá está.
     */
    public function test_a_devolucao_em_dinheiro_baixa_o_esperado_em_caixa(): void
    {
        $turno = $this->turnoAberto(5000);
        $factura = $this->venda($turno, 'cash');

        $turno->refresh();

        $this->assertEqualsWithDelta(2280, $turno->cash_sales, 0.01);
        $this->assertEqualsWithDelta(7280, $turno->opening_balance + $turno->cash_sales, 0.01);

        $this->creditar($factura);

        $turno->refresh();

        // A gaveta voltou ao saldo inicial: entraram 2280 e saíram 2280.
        $this->assertEqualsWithDelta(0, $turno->cash_sales, 0.01);
        $this->assertEqualsWithDelta(5000, $turno->opening_balance + $turno->cash_sales, 0.01);

        // E o que se vendeu continua a ser o que se vendeu.
        $this->assertEqualsWithDelta(2280, $turno->total_sales, 0.01, 'o bruto não muda');
        $this->assertEqualsWithDelta(2280, $turno->credit_notes_amount, 0.01);
        $this->assertEqualsWithDelta(0, $turno->net_sales, 0.01);
        $this->assertSame(1, $turno->total_credit_notes);
        $this->assertSame(1, $turno->total_invoices, 'uma devolução não é uma factura');
    }

    /**
     * O DINHEIRO VOLTA PELO CAMINHO POR ONDE VEIO.
     *
     * Uma venda paga no TPA devolve-se ao TPA: a gaveta não é tocada, e o
     * esperado em caixa fica como estava.
     */
    public function test_a_devolucao_de_uma_venda_no_tpa_nao_toca_na_gaveta(): void
    {
        $turno = $this->turnoAberto(5000);
        $factura = $this->venda($turno, 'tpa');

        $this->creditar($factura);

        $turno->refresh();

        $this->assertEqualsWithDelta(0, $turno->cash_sales, 0.01, 'nunca entrou dinheiro na gaveta');
        $this->assertEqualsWithDelta(0, $turno->card_sales, 0.01, 'entrou e saiu pelo TPA');
        $this->assertEqualsWithDelta(2280, $turno->credit_notes_amount, 0.01);
    }

    /**
     * SEM SABER POR ONDE VEIO, NÃO SE MEXE NA CONTAGEM.
     *
     * Uma factura a crédito não tem meio de pagamento gravado — 346 das 1555
     * facturas desta base estão assim. A devolução fica registada e visível,
     * mas cai em «outros», que é o balde que NÃO entra no dinheiro esperado.
     * Não saber é razão para não tocar na contagem, não para adivinhar.
     */
    public function test_sem_meio_de_pagamento_a_devolucao_nao_mexe_no_dinheiro(): void
    {
        $turno = $this->turnoAberto(5000);

        // Uma factura que não passou por esta gaveta e não diz como foi paga.
        $factura = $this->factura();

        $this->creditar($factura);

        $turno->refresh();

        $this->assertEqualsWithDelta(0, $turno->cash_sales, 0.01);
        $this->assertEqualsWithDelta(5000, $turno->opening_balance + $turno->cash_sales, 0.01,
            'o esperado em caixa fica intacto');

        // Mas o movimento existe e vê-se.
        $this->assertEqualsWithDelta(-2280, $turno->other_sales, 0.01);
        $this->assertSame(1, $turno->total_credit_notes);
    }

    /**
     * SEM TURNO ABERTO NÃO HÁ GAVETA.
     *
     * Uma nota de crédito emitida do escritório não é uma devolução ao balcão:
     * fica só nos documentos, que é onde ela sempre esteve.
     */
    public function test_sem_turno_aberto_a_nota_nao_cria_movimento_nenhum(): void
    {
        $factura = $this->factura();

        $this->creditar($factura);

        $this->assertSame(0, \App\Models\Invoicing\PosShiftTransaction::count());
    }

    /** E o histórico mostra-a, com o nome que ela tem. */
    public function test_o_historico_mostra_a_devolucao(): void
    {
        $turno = $this->turnoAberto(5000);
        $factura = $this->venda($turno, 'cash');

        $this->creditar($factura);

        $linha = $this->getJson(self::RAIZ . '/turnos/historico')->assertOk()->json('data.0');

        $this->assertEqualsWithDelta(2280, $linha['credit_notes_amount'], 0.01);
        $this->assertEqualsWithDelta(0, $linha['net_sales'], 0.01);
        $this->assertSame(1, $linha['total_credit_notes']);

        $ficha = $this->getJson(self::RAIZ . '/turnos/' . $turno->id)->assertOk()->json('turno');

        $tipos = array_column($ficha['movimentos'], 'tipo');

        $this->assertContains('credit_note', $tipos, 'a devolução tem de aparecer nos movimentos');

        $devolucao = collect($ficha['movimentos'])->firstWhere('tipo', 'credit_note');

        $this->assertEqualsWithDelta(-2280, $devolucao['amount'], 0.01, 'negativo: é dinheiro a sair');
        $this->assertSame(
            \App\Models\Invoicing\CreditNote::latest('id')->value('credit_note_number'),
            $devolucao['reference_number'],
            'o movimento aponta para a nota que o gerou',
        );
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function turnoAberto(float $saldo): PosShift
    {
        return PosShift::createSafely([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'status' => 'open', 'opened_at' => now(), 'opening_balance' => $saldo,
        ], $this->tenant->id);
    }

    /** Uma factura de 2 × 1000 a 14% — 2280 no total. */
    private function factura(?string $meio = null): SalesInvoice
    {
        $categoria = Category::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Geral'], ['is_active' => true]);
        $taxa = Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'],
            ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']);

        $artigo = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => 'produto',
            'price' => 1000, 'unit' => 'un', 'category_id' => $categoria->id,
            'tax_type' => 'iva', 'tax_rate_id' => $taxa->id, 'is_active' => true,
        ]);

        $f = SalesInvoice::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->cliente->id,
            'invoice_number' => 'FR/' . random_int(1000, 9999), 'invoice_date' => now()->toDateString(),
            'status' => 'paid', 'subtotal' => 2000, 'tax_amount' => 280, 'total' => 2280,
            'paid_amount' => 2280, 'payment_method' => $meio, 'created_by' => $this->user->id,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id, 'product_id' => $artigo->id, 'product_name' => $artigo->name,
            'description' => $artigo->name, 'quantity' => 2, 'unit_price' => 1000, 'subtotal' => 2000,
            'tax_rate' => 14, 'tax_amount' => 280, 'total' => 2280, 'tax_code' => 'NOR',
            'tax_country_region' => 'AO', 'order' => 1,
        ]);

        return $f->fresh(['items']);
    }

    /** Uma venda que passou por esta gaveta, como o POS a regista. */
    private function venda(PosShift $turno, string $meio): SalesInvoice
    {
        $f = $this->factura($meio);

        $turno->addTransaction([
            'type' => 'invoice',
            'reference_type' => SalesInvoice::class,
            'reference_id' => $f->id,
            'reference_number' => $f->invoice_number,
            'payment_method' => $meio,
            'amount' => 2280,
            'description' => 'Venda POS',
        ]);

        return $f;
    }

    /** A nota de crédito total, pela porta única. */
    private function creditar(SalesInvoice $factura): void
    {
        $emissor = app(EmissorDeNotas::class);

        $emissor->emitirCredito([
            'client_id' => $factura->client_id,
            'invoice_id' => $factura->id,
            'issue_date' => now()->toDateString(),
            'reason' => 'return',
            'type' => 'total',
        ], Collection::make($emissor->linhasDaFactura($factura)));
    }
}
