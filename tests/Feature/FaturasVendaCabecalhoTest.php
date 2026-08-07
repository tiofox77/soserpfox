<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

/**
 * A lista de facturas tem de mostrar o cabeçalho e o botão de criar.
 *
 * Desapareceram os dois — título, subtítulo e botão — sem erro nenhum. A causa
 * era um comentário Blade que escrevia por extenso o nome das directivas de
 * bloco PHP: o compilador foi buscar a abertura DENTRO do comentário e engoliu
 * tudo até ao bloco seguinte. A página continuava a devolver 200 e a tabela
 * aparecia, por isso nada denunciava a falta.
 *
 * O comentário existia justamente para avisar de uma armadilha parecida, e caiu
 * nela. É o tipo de defeito que nenhum teste de componente apanha: o Livewire
 * renderiza, o HTML é válido, só que falta um pedaço.
 */
class FaturasVendaCabecalhoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.sales.invoices.view');
        $this->comModulo('invoicing');
    }

    public function test_a_pagina_mostra_o_botao_de_criar(): void
    {
        $this->actingAs($this->user)
            ->get('/invoicing/sales/invoices')
            ->assertOk()
            ->assertSee('Nova Fatura')
            ->assertSee('invoices/create', false);
    }

    public function test_a_pagina_mostra_o_cabecalho(): void
    {
        $this->actingAs($this->user)
            ->get('/invoicing/sales/invoices')
            ->assertOk()
            ->assertSee('Faturas de Venda')
            ->assertSee('Faturas de vendas para clientes');
    }

    public function test_a_tabela_continua_la(): void
    {
        // O cabeçalho sumiu e a tabela ficou — foi isso que fez o defeito
        // passar despercebido. Os dois têm de estar.
        $this->actingAs($this->user)
            ->get('/invoicing/sales/invoices')
            ->assertOk()
            ->assertSee('Lista de Faturas');
    }
}
