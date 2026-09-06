<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * A FACTURA QUE VEM NO ENDEREÇO ABRE O ECRÃ JÁ ESCOLHIDA.
 *
 * Da lista de facturas carrega-se «Receber» ou «Nota de crédito» e vai-se para
 * `/invoicing/receipts/create?invoice=123`. O ecrã em Livewire lia isso no
 * `mount()`; ao passar para React, o `?invoice=` ficou a ser ignorado — quem
 * recebe ao balcão tinha de procurar a factura outra vez numa lista de
 * duzentas, e uma nota de crédito emitida sem referência à factura é uma nota
 * que a AGT recusa.
 *
 * Quem resolve é o servidor: o React não sabe de que CLIENTE é a factura, e a
 * lista de facturas do ecrã pede-se por cliente.
 */
class FacturaNaMoradaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function factura(): SalesInvoice
    {
        return SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT MORADA/' . random_int(1000, 9999),
            'invoice_date'   => now()->toDateString(),
            'status'         => 'sent',
            'total'          => 1000,
            'created_by'     => $this->user->id,
        ]);
    }

    /** @test */
    public function o_recibo_abre_com_a_factura_e_o_cliente_escolhidos(): void
    {
        $this->comPermissoes('invoicing.receipts.create', 'invoicing.receipts.view');

        $factura = $this->factura();

        $this->actingAs($this->user)
            ->get('/invoicing/receipts/create?invoice=' . $factura->id)
            ->assertOk()
            ->assertSee('&quot;facturaId&quot;:' . $factura->id, false)
            ->assertSee('&quot;clienteId&quot;:' . $factura->client_id, false);
    }

    /** @test */
    public function as_duas_notas_abrem_com_a_factura_escolhida(): void
    {
        $this->comPermissoes(
            'invoicing.credit-notes.create', 'invoicing.credit-notes.view',
            'invoicing.debit-notes.create', 'invoicing.debit-notes.view',
        );

        $factura = $this->factura();

        foreach (['credit-notes', 'debit-notes'] as $morada) {
            $this->actingAs($this->user)
                ->get("/invoicing/{$morada}/create?invoice=" . $factura->id)
                ->assertOk()
                ->assertSee('&quot;facturaId&quot;:' . $factura->id, false);
        }
    }

    /**
     * UMA FACTURA DE OUTRA EMPRESA NÃO ABRE NADA.
     *
     * O escopo da empresa é o mesmo do resto do sistema: sem ele, bastava
     * escrever um número no endereço para saber de que cliente é a factura de
     * outra casa.
     *
     * @test
     */
    public function uma_factura_alheia_nao_pre_escolhe_coisa_nenhuma(): void
    {
        $this->comPermissoes('invoicing.receipts.create', 'invoicing.receipts.view');

        $outra = Tenant::create([
            'name' => 'Alheia', 'slug' => 'alheia-' . uniqid(),
            'nif' => (string) random_int(700000000, 799999999),
            'email' => 'a' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        // O cliente é o desta casa de propósito: se o escopo da empresa
        // falhasse, o ecrã abria com ele escolhido — e é isso que se recusa.
        $alheia = SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id'      => $outra->id,
            'client_id'      => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT ALHEIA/' . random_int(1000, 9999),
            'invoice_date'   => now()->toDateString(),
            'status'         => 'sent',
            'total'          => 500,
            'created_by'     => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get('/invoicing/receipts/create?invoice=' . $alheia->id)
            ->assertOk()
            ->assertDontSee('facturaId');
    }

    /** Lixo no endereço não rebenta o ecrã. @test */
    public function um_endereco_disparatado_abre_o_ecra_vazio(): void
    {
        $this->comPermissoes('invoicing.receipts.create', 'invoicing.receipts.view');

        $this->actingAs($this->user)
            ->get('/invoicing/receipts/create?invoice=nao-e-numero')
            ->assertOk()
            ->assertDontSee('facturaId');

        $this->actingAs($this->user)
            ->get('/invoicing/receipts/create?invoice=99999999')
            ->assertOk()
            ->assertDontSee('facturaId');
    }

    /**
     * A ENTRADA DO MENU PARA AS FATURAS-RECIBO ABRE FILTRADA.
     *
     * O menu liga as Faturas-Recibo por `?type=FR`. A lista em React nascia
     * sempre sem filtro nenhum: a entrada existia e mostrava tudo.
     *
     * @test
     */
    public function a_lista_abre_filtrada_pelas_faturas_recibo(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->actingAs($this->user)
            ->get('/invoicing/sales/invoices?type=FR')
            ->assertOk()
            ->assertSee('&quot;tipo&quot;:&quot;FR&quot;', false);

        // Sem nada no endereço, a lista abre inteira.
        $this->actingAs($this->user)
            ->get('/invoicing/sales/invoices')
            ->assertOk()
            ->assertDontSee('&quot;tipo&quot;', false);
    }
}
