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
 * O QUE MUDOU COM O REACT. O Blade que se engoliu a si próprio já não existe:
 * o miolo da lista é desenhado no browser. Mas o defeito que isto guarda —
 * «a página responde 200 e ainda assim falta-lhe metade» — não desapareceu,
 * mudou de sítio. Passa a olhar-se para as duas metades:
 *
 *   · o SERVIDOR entrega a página com o título e o ecrã certo montado;
 *   · o ECRÃ desenha o cabeçalho, o botão de criar e a tabela — e isso vê-se
 *     no browser, em `tests/browser/react.lista-de-facturas.spec.js`, que
 *     exige a tabela, os cartões de totais e as acções de cada linha.
 *
 * Um 200 vazio continua a ser apanhado: sem o ponto de montagem, o ecrã nunca
 * chega a existir e este ensaio falha.
 */
class FaturasVendaCabecalhoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.sales.invoices.view');
        $this->comModulo('invoicing');
    }

    public function test_a_pagina_entrega_o_ecra_da_lista(): void
    {
        $this->actingAs($this->user)
            ->get('/invoicing/sales/invoices')
            ->assertOk()
            ->assertSee('data-ecra="facturacao/lista-de-facturas"', false);
    }

    public function test_a_pagina_mostra_o_cabecalho(): void
    {
        $this->actingAs($this->user)
            ->get('/invoicing/sales/invoices')
            ->assertOk()
            ->assertSee('Faturas de Venda');
    }

    /**
     * O BOTÃO DE CRIAR LEVA A ALGUM LADO.
     *
     * O ecrã desenha-o, mas a morada para onde aponta é do servidor: se a rota
     * de criar deixasse de existir, o botão ficava a apontar para um 404 e só
     * se saberia ao carregar nele.
     */
    public function test_a_morada_de_criar_existe(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $this->actingAs($this->user)
            ->get('/invoicing/sales/invoices/create')
            ->assertOk()
            ->assertSee('data-ecra="facturacao/emitir-factura"', false);
    }
}
