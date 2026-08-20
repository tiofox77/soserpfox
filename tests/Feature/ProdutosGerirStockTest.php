<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Products;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O "Gerenciar Stock" que devia estar ligado e não estava.
 *
 * O formulário nascia com a caixa DESMARCADA. Quem criasse um artigo sem
 * reparar nela ficava com um artigo que se vendia e nunca descia — e nada no
 * ecrã o avisava. Numa farmácia estava errado para praticamente todos os
 * artigos, e o sintoma só aparecia semanas depois, com as contagens já fora.
 *
 * Aqui guarda-se a correcção dos dois lados: a causa (o formulário) e o
 * estrago já feito (o comando).
 */
class ProdutosGerirStockTest extends TenantTestCase
{
    private function artigo(array $extra = []): Product
    {
        return Product::create(array_merge([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Paracetamol 500mg',
            'code'         => 'P-' . uniqid(),
            'type'         => 'produto',
            'price'        => 500,
            'manage_stock' => false,
            'is_active'    => true,
        ], $extra));
    }

    // ══════════════ o comando ══════════════

    public function test_liga_o_stock_nos_artigos_fisicos_que_o_nao_tinham(): void
    {
        $a = $this->artigo();
        $b = $this->artigo(['name' => 'Ibuprofeno']);

        $this->artisan('produtos:gerir-stock', ['--tenant' => $this->tenant->id, '--aplicar' => true])
            ->assertSuccessful();

        $this->assertTrue((bool) $a->refresh()->manage_stock);
        $this->assertTrue((bool) $b->refresh()->manage_stock);
    }

    /**
     * Um serviço com esta bandeira ligada é recusado no POS por "esgotado".
     * Já aconteceu nesta casa com os serviços do salão.
     */
    public function test_nunca_toca_num_servico(): void
    {
        $servico = $this->artigo(['type' => 'servico', 'name' => 'Corte de cabelo']);

        $this->artisan('produtos:gerir-stock', ['--tenant' => $this->tenant->id, '--aplicar' => true])
            ->assertSuccessful();

        $this->assertFalse((bool) $servico->refresh()->manage_stock);
    }

    public function test_sem_aplicar_nao_grava_nada(): void
    {
        $a = $this->artigo();

        $this->artisan('produtos:gerir-stock', ['--tenant' => $this->tenant->id])
            ->expectsOutputToContain('SIMULAÇÃO')
            ->assertSuccessful();

        $this->assertFalse((bool) $a->refresh()->manage_stock);
    }

    /**
     * Aplicar a todas as empresas de uma vez seria mexer no catálogo de
     * clientes diferentes com uma decisão só.
     */
    public function test_aplicar_a_todas_as_empresas_de_uma_vez_e_recusado(): void
    {
        $a = $this->artigo();

        $this->artisan('produtos:gerir-stock', ['--todas' => true, '--aplicar' => true])
            ->assertFailed();

        $this->assertFalse((bool) $a->refresh()->manage_stock);
    }

    public function test_sem_empresa_nem_todas_recusa(): void
    {
        $this->artisan('produtos:gerir-stock')->assertFailed();
    }

    public function test_nao_mexe_em_artigos_de_outra_empresa(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra Empresa', 'slug' => 'outra-' . uniqid(), 'is_active' => true,
        ]);

        $alheio = Product::create([
            'tenant_id' => $outra->id, 'name' => 'Coisa', 'code' => 'X-' . uniqid(),
            'type' => 'produto', 'price' => 100, 'manage_stock' => false, 'is_active' => true,
        ]);

        $this->artisan('produtos:gerir-stock', ['--tenant' => $this->tenant->id, '--aplicar' => true])
            ->assertSuccessful();

        $this->assertFalse((bool) $alheio->refresh()->manage_stock);
    }

    public function test_quem_ja_conta_stock_fica_como_esta(): void
    {
        $ja = $this->artigo(['manage_stock' => true]);
        $antes = $ja->updated_at;

        $this->artisan('produtos:gerir-stock', ['--tenant' => $this->tenant->id, '--aplicar' => true])
            ->assertSuccessful();

        $this->assertTrue((bool) $ja->refresh()->manage_stock);
        $this->assertEquals($antes, $ja->updated_at, 'não se toca em quem já está bem');
    }

    /**
     * A bandeira e SÓ a bandeira.
     *
     * As linhas de stock por armazém são a fonte de verdade e o agregado é
     * mantido pelo StockObserver. Escrever o agregado aqui punha-o a discordar
     * das linhas.
     */
    public function test_nao_mexe_na_quantidade(): void
    {
        $a = $this->artigo(['stock_quantity' => 7]);

        $this->artisan('produtos:gerir-stock', ['--tenant' => $this->tenant->id, '--aplicar' => true])
            ->assertSuccessful();

        $this->assertEquals(7, (float) $a->refresh()->stock_quantity);
    }

    public function test_o_modo_conservador_so_apanha_quem_da_sinais(): void
    {
        $semSinais = $this->artigo(['name' => 'Sem sinais', 'cost' => 0, 'stock_quantity' => 0]);
        $comSinais = $this->artigo(['name' => 'Com custo', 'cost' => 300]);

        $this->artisan('produtos:gerir-stock', [
            '--tenant' => $this->tenant->id,
            '--so-com-indicios' => true,
            '--aplicar' => true,
        ])->assertSuccessful();

        $this->assertTrue((bool) $comSinais->refresh()->manage_stock);
        $this->assertFalse((bool) $semSinais->refresh()->manage_stock);
    }

    public function test_avisa_que_nao_inventa_o_historico_que_faltou(): void
    {
        $this->artigo();

        // Um artigo vendido durante meses sem contar stock não tem movimentos
        // desse período, e ligar a bandeira não os cria. Quem corre isto tem
        // de saber que ainda falta uma contagem física.
        $this->artisan('produtos:gerir-stock', ['--tenant' => $this->tenant->id, '--aplicar' => true])
            ->expectsOutputToContain('contagem física')
            ->assertSuccessful();
    }

    // ══════════════ a causa ══════════════

    /**
     * Sem as permissões, o create() e o edit() devolvem cedo com um erro no
     * ecrã e NÃO tocam nas propriedades — e um teste que só olhe para o valor
     * inicial passaria pela razão errada, sem nunca ter corrido o código.
     */
    private function comAcessoAProdutos(): void
    {
        $this->comPermissoes('invoicing.products.create', 'invoicing.products.edit');
    }

    public function test_o_formulario_nasce_com_o_stock_ligado(): void
    {
        $this->comAcessoAProdutos();

        Livewire::actingAs($this->user)
            ->test(Products::class)
            ->call('create')
            ->assertSet('manage_stock', true);
    }

    public function test_escolher_servico_desliga_o_stock(): void
    {
        $this->comAcessoAProdutos();
        Livewire::actingAs($this->user)
            ->test(Products::class)
            ->call('create')
            ->set('type', 'servico')
            ->assertSet('manage_stock', false);
    }

    public function test_voltar_a_produto_volta_a_ligar(): void
    {
        $this->comAcessoAProdutos();
        Livewire::actingAs($this->user)
            ->test(Products::class)
            ->call('create')
            ->set('type', 'servico')
            ->set('type', 'produto')
            ->assertSet('manage_stock', true);
    }

    /**
     * A editar, quem manda é o que está gravado.
     *
     * Mexer nisso por se trocar o tipo apagaria uma decisão do utilizador.
     */
    public function test_a_editar_o_tipo_nao_atropela_a_decisao_gravada(): void
    {
        $this->comAcessoAProdutos();
        $a = $this->artigo(['manage_stock' => false]);

        Livewire::actingAs($this->user)
            ->test(Products::class)
            ->call('edit', $a->id)
            ->assertSet('manage_stock', false)
            ->set('type', 'produto')
            ->assertSet('manage_stock', false);
    }
}
