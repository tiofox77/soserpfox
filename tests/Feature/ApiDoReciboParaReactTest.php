<?php

namespace Tests\Feature;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Supplier;
use Tests\TenantTestCase;

/**
 * REGISTAR UM RECIBO pela API do ecrã em React.
 *
 * O recibo parece o mais simples dos emissores e é o que tem mais armadilhas
 * juntas. Estes ensaios guardam-nas, uma a uma:
 *
 *   · o `paid_amount` da factura sobe SOZINHO, pelos ganchos do modelo;
 *   · não se recebe mais do que falta;
 *   · uma compra vai em `purchase_invoice_id`, nunca em `invoice_id`;
 *   · a lista de facturas conta pelo SALDO e não pelo nome do estado;
 *   · uma factura-recibo do balcão não aparece para receber outra vez.
 */
class ApiDoReciboParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/recibos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        // Sem série de RECIBOS não se emite recibo nenhum — e o TenantTestCase
        // só semeia as de factura e de POS.
        \App\Models\Invoicing\InvoicingSeries::create([
            'tenant_id' => $this->tenant->id,
            'series_code' => 'RC',
            'name' => 'RC (ensaio)',
            'document_type' => 'receipt',
            'agt_environment' => 'sandbox',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function venda(array $por = []): SalesInvoice
    {
        return SalesInvoice::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'invoice_type' => 'FT',
            'status' => 'sent',
            'total' => 1000,
            'paid_amount' => 0,
            'created_by' => $this->user->id,
        ], $por));
    }

    private function fornecedor(): Supplier
    {
        return Supplier::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Fornecedor de Ensaio'],
            ['nif' => (string) random_int(500000000, 599999999), 'is_active' => true]
        );
    }

    private function corpo(array $por = []): array
    {
        return array_merge([
            'type' => 'sale',
            'client_id' => $this->clienteEmpresa()->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount_paid' => 100,
        ], $por);
    }

    /* ─── Permissões ──────────────────────────────────────────────────── */

    /** @test */
    public function ver_nao_da_direito_a_receber(): void
    {
        $this->getJson(self::RAIZ . '/opcoes')->assertForbidden();

        $this->comPermissoes('invoicing.receipts.view');

        $this->getJson(self::RAIZ . '/opcoes')->assertOk();
        $this->postJson(self::RAIZ, $this->corpo())->assertForbidden();
    }

    /* ─── O lançamento ────────────────────────────────────────────────── */

    /**
     * O `paid_amount` SOBE SOZINHO.
     *
     * Este controlador não lhe toca: quem o actualiza são os ganchos do
     * modelo `Receipt`, por lançamento diferencial.
     *
     * @test
     */
    public function o_recibo_lanca_o_pagamento_na_factura(): void
    {
        $this->comPermissoes('invoicing.receipts.create');

        $f = $this->venda(['total' => 1000]);

        $this->postJson(self::RAIZ, $this->corpo([
            'invoice_id' => $f->id,
            'amount_paid' => 400,
        ]))->assertCreated();

        $this->assertEqualsWithDelta(400, $f->fresh()->paid_amount, 0.01);

        // Um segundo recibo SOMA-SE ao primeiro, não o substitui.
        $this->postJson(self::RAIZ, $this->corpo([
            'invoice_id' => $f->id,
            'amount_paid' => 600,
        ]))->assertCreated();

        $this->assertEqualsWithDelta(1000, $f->fresh()->paid_amount, 0.01);
    }

    /**
     * NÃO SE RECEBE MAIS DO QUE FALTA.
     *
     * Sem o travão, um recibo de 100.000 numa factura de 570 punha o
     * `paid_amount` acima do total e todos os mapas passavam a mostrar um
     * saldo negativo.
     *
     * @test
     */
    public function nao_se_recebe_mais_do_que_falta(): void
    {
        $this->comPermissoes('invoicing.receipts.create');

        $f = $this->venda(['total' => 570]);

        $this->postJson(self::RAIZ, $this->corpo([
            'invoice_id' => $f->id,
            'amount_paid' => 100000,
        ]))->assertStatus(422)->assertJsonValidationErrors('amount_paid');

        $this->assertEqualsWithDelta(0, $f->fresh()->paid_amount, 0.01,
            'e nada foi lançado');
    }

    /** O que falta exactamente entra, ao cêntimo. @test */
    public function recebe_se_exactamente_o_que_falta(): void
    {
        $this->comPermissoes('invoicing.receipts.create');

        $f = $this->venda(['total' => 570.55, 'paid_amount' => 70.55]);

        $this->postJson(self::RAIZ, $this->corpo([
            'invoice_id' => $f->id,
            'amount_paid' => 500,
        ]))->assertCreated();

        $this->assertEqualsWithDelta(570.55, $f->fresh()->paid_amount, 0.01);
    }

    /**
     * CADA TIPO NA SUA COLUNA.
     *
     * `invoice_id` tem chave estrangeira para as facturas de VENDA; o id de
     * uma compra escrito ali faz a base recusar a linha.
     *
     * @test
     */
    public function uma_compra_vai_na_coluna_das_compras(): void
    {
        $this->comPermissoes('invoicing.receipts.create');

        $compra = PurchaseInvoice::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->fornecedor()->id,
            'invoice_number' => 'FC/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'status' => 'pending',
            'total' => 800,
            'paid_amount' => 0,
            'created_by' => $this->user->id,
        ]);

        $id = $this->postJson(self::RAIZ, [
            'type' => 'purchase',
            'supplier_id' => $this->fornecedor()->id,
            'invoice_id' => $compra->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'amount_paid' => 300,
        ])->assertCreated()->json('id');

        $r = Receipt::find($id);

        $this->assertNull($r->invoice_id, 'a coluna das vendas fica vazia');
        $this->assertSame($compra->id, (int) $r->purchase_invoice_id);
        $this->assertEqualsWithDelta(300, $compra->fresh()->paid_amount, 0.01);
    }

    /* ─── A lista de facturas ─────────────────────────────────────────── */

    /**
     * PELO SALDO E NÃO PELO NOME DO ESTADO.
     *
     * Uma `sent` e uma `overdue` são justamente as que se querem receber, e
     * eram as que uma lista escrita ao contrário escondia.
     *
     * @test
     */
    public function a_lista_conta_pelo_saldo(): void
    {
        $this->comPermissoes('invoicing.receipts.view');

        $porReceber = $this->venda(['status' => 'sent', 'total' => 1000, 'paid_amount' => 200]);
        $paga = $this->venda(['status' => 'sent', 'total' => 1000, 'paid_amount' => 1000]);
        $rascunho = $this->venda(['status' => 'draft']);

        $ids = collect($this->getJson(self::RAIZ . '/facturas?tipo=sale')->assertOk()->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($porReceber->id), 'uma `sent` com saldo tem de aparecer');
        $this->assertFalse($ids->contains($paga->id), 'uma sem saldo não');
        $this->assertFalse($ids->contains($rascunho->id), 'e um rascunho ainda não existe');
    }

    /**
     * UMA FACTURA-RECIBO DO BALCÃO NÃO APARECE PARA RECEBER.
     *
     * É paga no acto da venda e nunca tem recibo — oferecê-la aqui é um
     * convite a receber duas vezes.
     *
     * @test
     */
    public function uma_fr_do_balcao_nao_aparece_para_receber(): void
    {
        $this->comPermissoes('invoicing.receipts.view');

        $fr = $this->venda(['invoice_type' => 'FR', 'status' => 'paid', 'total' => 570, 'paid_amount' => 0]);

        $ids = collect($this->getJson(self::RAIZ . '/facturas?tipo=sale')->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($fr->id));
    }

    /** A lista traz quanto falta em cada uma. @test */
    public function a_lista_diz_quanto_falta(): void
    {
        $this->comPermissoes('invoicing.receipts.view');

        $f = $this->venda(['total' => 1000, 'paid_amount' => 250]);

        $linha = collect($this->getJson(self::RAIZ . '/facturas?tipo=sale')->json('data'))
            ->firstWhere('id', $f->id);

        $this->assertEqualsWithDelta(750, $linha['falta'], 0.01);
    }

    /** O recibo numera-se sozinho, com a série da empresa. @test */
    public function o_recibo_numera_se_sozinho(): void
    {
        $this->comPermissoes('invoicing.receipts.create');

        $numero = $this->postJson(self::RAIZ, $this->corpo())->assertCreated()->json('numero');

        $this->assertNotEmpty($numero);
    }

    /** Um recibo de venda sem cliente não se grava. @test */
    public function um_recibo_de_venda_precisa_de_cliente(): void
    {
        $this->comPermissoes('invoicing.receipts.create');

        $this->postJson(self::RAIZ, ['type' => 'sale', 'payment_date' => now()->toDateString(),
            'payment_method' => 'cash', 'amount_paid' => 100])
            ->assertJsonValidationErrors('client_id');
    }
}
