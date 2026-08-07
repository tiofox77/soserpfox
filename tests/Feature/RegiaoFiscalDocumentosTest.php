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

    public function test_a_regiao_escolhida_chega_as_linhas_gravadas(): void
    {
        // O selector sozinho não chega. A proforma gravava a região FIXA nas
        // linhas: escolher Cabinda no ecrã não mudava nada, e a factura que
        // nascesse dessa proforma herdava o erro. Foi assim que o selector
        // esteve, por um commit, a não fazer nada.
        $this->comPermissoes('invoicing.sales.proformas.create');

        \Livewire\Livewire::actingAs($this->user)
            ->test(\App\Livewire\Invoicing\Sales\ProformaCreate::class)
            ->set('tax_country_region', 'AO-CAB')
            ->assertSet('tax_country_region', 'AO-CAB');

        $fonte = file_get_contents(base_path('app/Livewire/Invoicing/Sales/ProformaCreate.php'));

        $this->assertStringNotContainsString(
            "'tax_country_region'   => 'AO',",
            $fonte,
            'a região das linhas não pode voltar a ser um valor fixo'
        );
    }

    public function test_as_compras_passam_a_ter_o_selector(): void
    {
        // As linhas de compra nem coluna tinham para a regiao: sem migracao,
        // levar o selector a estes ecras seria decorativo.
        foreach ([
            "/invoicing/purchases/invoices/create"  => "invoicing.purchases.invoices.create",
            "/invoicing/purchases/proformas/create" => "invoicing.purchases.proformas.create",
        ] as $url => $permissao) {
            $this->comPermissoes($permissao);

            $this->actingAs($this->user)->get($url)
                ->assertOk()
                ->assertSee("Região fiscal")
                ->assertSee("AO-CAB");
        }
    }

    public function test_as_linhas_de_compra_tem_onde_guardar_a_regiao(): void
    {
        foreach (["invoicing_purchase_invoice_items", "invoicing_purchase_proforma_items"] as $tabela) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasColumn($tabela, "tax_country_region"),
                $tabela . " tem de ter a coluna, senao o selector nao grava nada"
            );
        }
    }
}
