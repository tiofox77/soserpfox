<?php

namespace Tests\Feature;

use App\Models\Product;
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

    // ══════════════ o efeito colateral, e o antídoto ══════════════

    /**
     * Ligar a bandeira a um catálogo inteiro esconde do POS tudo o que estiver
     * a zero — e num catálogo que nunca contou stock, isso é muito. Do lado do
     * balcão lê-se como "o sistema deixou de ter metade dos produtos".
     */
    public function test_avisa_quantos_artigos_vao_sumir_do_pos(): void
    {
        $this->artigo(['stock_quantity' => 0]);
        $this->artigo(['name' => 'Com stock', 'stock_quantity' => 10]);

        $this->artisan('produtos:gerir-stock', ['--tenant' => $this->tenant->id])
            ->expectsOutputToContain('1 destes artigos estão a ZERO')
            ->assertSuccessful();
    }

    public function test_pode_desligar_o_esconder_sem_stock_da_empresa(): void
    {
        $this->artisan('produtos:gerir-stock', [
            '--tenant' => $this->tenant->id,
            '--esconder-sem-stock' => '0',
        ])->assertSuccessful();

        $this->assertFalse(
            (bool) \App\Models\Invoicing\InvoicingSettings::forTenant($this->tenant->id)->pos_hide_out_of_stock
        );
    }

    public function test_e_voltar_a_ligar(): void
    {
        $this->artisan('produtos:gerir-stock', ['--tenant' => $this->tenant->id, '--esconder-sem-stock' => '0'])
            ->assertSuccessful();
        $this->artisan('produtos:gerir-stock', ['--tenant' => $this->tenant->id, '--esconder-sem-stock' => '1'])
            ->assertSuccessful();

        $this->assertTrue(
            (bool) \App\Models\Invoicing\InvoicingSettings::forTenant($this->tenant->id)->pos_hide_out_of_stock
        );
    }

    public function test_os_zerados_sao_listados_com_o_estado_da_definicao(): void
    {
        $this->artigo(['manage_stock' => true, 'stock_quantity' => 0]);

        $this->artisan('produtos:gerir-stock', ['--tenant' => $this->tenant->id, '--zerados' => true])
            ->expectsOutputToContain('NÃO aparecem no POS')
            ->assertSuccessful();
    }

    // ══════════════ a causa ══════════════

    /**
     * O ecrã dos artigos é React e grava pela API. O que era o valor inicial
     * de uma propriedade do componente é hoje a omissão do
     * `ProductApiController` — e é lá que se prova.
     *
     * Sem as permissões o controlador responde 403 e não corre código nenhum,
     * pelo que um ensaio sem elas passaria pela razão errada.
     */
    private const ARTIGOS = '/api/v1/invoicing/react/products';

    private function comAcessoAProdutos(): void
    {
        $this->comModulo('invoicing')
             ->comPermissoes('invoicing.products.create', 'invoicing.products.edit');
    }

    private function corpo(array $por = []): array
    {
        return array_merge([
            'name' => 'Paracetamol 500mg',
            'type' => 'produto',
            'price' => 500,
            'unit' => 'un',
            'category_id' => \App\Models\Category::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'name' => 'Geral'],
                ['is_active' => true]
            )->id,
            'tax_type' => 'isento',
            'exemption_reason' => 'M99',
        ], $por);
    }

    /** UM ARTIGO NOVO NASCE A GERIR STOCK — é a correcção da causa. */
    public function test_um_artigo_novo_nasce_com_o_stock_ligado(): void
    {
        $this->comAcessoAProdutos();

        // Sem falar de `manage_stock`: é a omissão que interessa.
        $id = $this->postJson(self::ARTIGOS, $this->corpo())->assertCreated()->json('data.id');

        $this->assertTrue((bool) Product::find($id)->manage_stock);
    }

    /**
     * A EDITAR, QUEM MANDA É O QUE ESTÁ GRAVADO.
     *
     * Um artigo a que alguém desligou o stock de propósito não pode voltar a
     * ligá-lo só porque o pedido de edição não trouxe o campo — isso apagava
     * uma decisão do utilizador sem ninguém dar por nada. (O simétrico do ecrã
     * — trocar o tipo a editar não mexe na caixa — está no `Produtos.tsx`, e é
     * o ensaio a seguir que o segura.)
     */
    public function test_a_edicao_nao_atropela_a_decisao_gravada(): void
    {
        $this->comAcessoAProdutos();

        $a = $this->artigo(['manage_stock' => false]);

        $this->putJson(self::ARTIGOS . '/' . $a->id, $this->corpo(['name' => 'Nome Novo']))->assertOk();

        $this->assertFalse((bool) $a->refresh()->manage_stock, 'a decisão gravada tem de ficar de pé');

        // E quem a quiser mudar, muda-a de propósito.
        $this->putJson(self::ARTIGOS . '/' . $a->id, $this->corpo(['manage_stock' => true]))->assertOk();

        $this->assertTrue((bool) $a->refresh()->manage_stock);
    }

    /**
     * Guarda de fonte: no ecrã, trocar o tipo A CRIAR acerta a caixa (um
     * serviço não gere stock, um produto gere); A EDITAR não lhe toca.
     *
     * @test
     */
    public function o_ecra_so_acerta_a_caixa_do_stock_em_artigos_novos(): void
    {
        $ecra = file_get_contents(base_path('resources/js/ecras/facturacao/Produtos.tsx'));

        // Sem prender o nome da variável: ela já mudou uma vez, quando o `t`
        // do tipo passou a tapar o `t()` das traduções. O que se guarda é a
        // REGRA — a editar muda só o tipo; num artigo novo é que a caixa do
        // stock acompanha.
        $this->assertMatchesRegularExpression(
            '/aoMudar\(aEditar \? \{ \.\.\.dados, type: (\w+) \} : \{ \.\.\.dados, type: \1, manage_stock: \1 === ' . "'produto'" . ' \}\)/u',
            $ecra,
            'trocar o tipo a editar não pode mexer no «gerir stock» gravado'
        );
    }
}
