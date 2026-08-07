<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * A região fiscal do documento existe onde há imposto a calcular.
 *
 * Cabinda tem regime de IVA próprio (AO-CAB), e o que o determina é o LOCAL DA
 * OPERAÇÃO — a mesma entidade pode comprar em Luanda e em Cabinda. O selector
 * existia só nas facturas de venda: as proformas calculavam sempre à taxa
 * continental, e uma proposta feita em Cabinda saía com o imposto errado.
 *
 * Isso não é diferença de aspecto entre ecrãs, é imposto errado.
 */
class RegiaoFiscalDocumentosTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('invoicing');
    }

    public function test_a_factura_de_venda_tem_o_selector(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $this->actingAs($this->user)
            ->get('/invoicing/sales/invoices/create')
            ->assertOk()
            ->assertSee('Região fiscal')
            ->assertSee('AO-CAB');
    }

    public function test_a_proforma_de_venda_passa_a_ter_o_selector(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.create');

        $this->actingAs($this->user)
            ->get('/invoicing/sales/proformas/create')
            ->assertOk()
            ->assertSee('Região fiscal')
            ->assertSee('Cabinda (regime próprio)');
    }

    public function test_por_omissao_deriva_da_provincia(): void
    {
        // Vazio significa "automática": deriva da província do cliente. Fixar
        // AO por omissão era o que fazia Cabinda passar despercebida.
        $this->comPermissoes('invoicing.sales.proformas.create');

        \Livewire\Livewire::actingAs($this->user)
            ->test(\App\Livewire\Invoicing\Sales\ProformaCreate::class)
            ->assertSet('tax_country_region', '');
    }
}
