<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Products;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Os campos de farmácia e vestuário no catálogo.
 *
 * O POS já tem os seus testes. Estes cobrem o outro lado: gravar, editar,
 * filtrar — que é por onde os dados entram. Se a gravação falhar, o aviso de
 * receita no balcão nunca chega a ter o que avisar.
 */
class CatalogoFarmaciaVestuarioTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes(
            'invoicing.products.view',
            'invoicing.products.create',
            'invoicing.products.edit',
        );
    }

    /**
     * O formulário com o mínimo obrigatório já preenchido.
     *
     * A categoria e a taxa são exigidas pelo formulário há muito — e é bom
     * sinal que sejam: um artigo sem imposto sai numa factura sem IVA e a AGT
     * recusa-a. O que aqui se testa são os campos NOVOS, portanto o resto vai
     * preenchido para não estorvar.
     */
    private function formulario()
    {
        $categoria = \App\Models\Category::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Geral'],
            ['is_active' => true]
        );

        return Livewire::test(Products::class)
            ->call('create')
            ->set('name', 'Artigo de Teste')
            ->set('code', 'T-' . uniqid())
            ->set('price', 1000)
            ->set('type', 'produto')
            ->set('category_id', $categoria->id)
            ->set('tax_rate_id', $this->imposto->id);
    }

    /**
     * Um artigo já gravado.
     *
     * Leva categoria e taxa porque o formulário as exige: sem elas, um
     * `save()` num teste de edição falha na validação em SILÊNCIO e o teste
     * passa a medir a validação em vez do que queria medir. Custou-me dois
     * falsos negativos.
     */
    private function artigo(array $campos = []): Product
    {
        $categoria = \App\Models\Category::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Geral'],
            ['is_active' => true]
        );

        return Product::create(array_merge([
            'tenant_id'   => $this->tenant->id,
            'name'        => 'Artigo ' . uniqid(),
            'code'        => 'A-' . uniqid(),
            'price'       => 1000,
            'type'        => 'produto',
            'is_active'   => true,
            'tax_id'      => $this->imposto->id,
            'category_id' => $categoria->id,
            'tax_rate_id' => $this->imposto->id,
        ], $campos));
    }

    // ==================== gravar ====================

    /** Um medicamento grava os seus campos todos. */
    public function test_um_medicamento_grava_os_campos_de_farmacia(): void
    {
        $this->formulario()
            ->set('name', 'Paracetamol 500mg')
            ->set('requires_prescription', true)
            ->set('is_controlled', false)
            ->set('active_ingredient', 'Paracetamol')
            ->set('dosage', '500mg')
            ->set('pharmaceutical_form', 'comprimido')
            ->set('armed_registration', 'ARMED-12345')
            ->call('save')
            ->assertHasNoErrors();

        $p = Product::where('tenant_id', $this->tenant->id)
            ->where('name', 'Paracetamol 500mg')
            ->first();

        $this->assertNotNull($p);
        $this->assertTrue($p->requires_prescription);
        $this->assertSame('Paracetamol', $p->active_ingredient);
        $this->assertSame('500mg', $p->dosage);
        $this->assertSame('comprimido', $p->pharmaceutical_form);
        $this->assertSame('ARMED-12345', $p->armed_registration);
    }

    /** Uma peça de roupa grava os seus. */
    public function test_uma_peca_de_roupa_grava_os_campos_de_vestuario(): void
    {
        $this->formulario()
            ->set('name', 'T-shirt Algodão')
            ->set('size', 'M')
            ->set('color', 'Azul-marinho')
            ->set('gender', 'unissexo')
            ->set('material', '100% algodão')
            ->call('save')
            ->assertHasNoErrors();

        $p = Product::where('name', 'T-shirt Algodão')->first();

        $this->assertSame('M', $p->size);
        $this->assertSame('Azul-marinho', $p->color);
        $this->assertSame('unissexo', $p->gender);
        $this->assertSame('100% algodão', $p->material);
    }

    /**
     * Um artigo que não é nem medicamento nem roupa grava na mesma.
     *
     * É a maioria do catálogo. Um campo tornado obrigatório para servir dois
     * sectores partia o sistema de toda a gente — este teste é a guarda.
     */
    public function test_um_artigo_comum_grava_sem_nenhum_destes_campos(): void
    {
        $this->formulario()
            ->set('name', 'Saco Plástico')
            ->call('save')
            ->assertHasNoErrors();

        $p = Product::where('name', 'Saco Plástico')->first();

        $this->assertNotNull($p);
        $this->assertNull($p->active_ingredient);
        $this->assertNull($p->size);
        $this->assertFalse((bool) $p->requires_prescription);
    }

    /** Um género inventado é recusado. */
    public function test_um_genero_invalido_nao_passa(): void
    {
        $this->formulario()
            ->set('gender', 'qualquer-coisa')
            ->call('save')
            ->assertHasErrors('gender');
    }

    // ==================== editar ====================

    /** Editar carrega os campos que já lá estavam. */
    public function test_editar_um_medicamento_carrega_os_campos(): void
    {
        $p = $this->artigo([
            'name'                  => 'Amoxicilina',
            'requires_prescription' => true,
            'active_ingredient'     => 'Amoxicilina tri-hidratada',
            'dosage'                => '250mg',
        ]);

        Livewire::test(Products::class)
            ->call('edit', $p->id)
            ->assertSet('requires_prescription', true)
            ->assertSet('active_ingredient', 'Amoxicilina tri-hidratada')
            ->assertSet('dosage', '250mg');
    }

    /**
     * Tirar a marca de receita a um artigo tem de pegar.
     *
     * Um booleano que só sabe ligar-se é pior do que não existir: o artigo
     * ficava marcado como sujeito a receita para sempre.
     */
    public function test_tirar_a_marca_de_receita_pega(): void
    {
        $p = $this->artigo(['requires_prescription' => true]);

        Livewire::test(Products::class)
            ->call('edit', $p->id)
            ->set('requires_prescription', false)
            ->call('save');

        $this->assertFalse((bool) $p->fresh()->requires_prescription);
    }

    // ==================== filtrar ====================

    /** O filtro de receita separa os que exigem dos que não. */
    public function test_o_filtro_de_receita_separa(): void
    {
        $comReceita = $this->artigo(['name' => 'Antibiótico X', 'requires_prescription' => true]);
        $livre      = $this->artigo(['name' => 'Vitamina C', 'requires_prescription' => false]);

        Livewire::test(Products::class)
            ->set('filterPrescricao', 'sim')
            ->assertSee('Antibiótico X')
            ->assertDontSee('Vitamina C');

        Livewire::test(Products::class)
            ->set('filterPrescricao', 'nao')
            ->assertSee('Vitamina C')
            ->assertDontSee('Antibiótico X');
    }

    /**
     * A coluna é NOT NULL com default 0 — e é isso que se quer.
     *
     * Escrevi este teste a supor que os artigos migrados chegariam com NULL e
     * desapareceriam do filtro "venda livre". Não chegam: a coluna não aceita
     * NULL, portanto tudo o que já lá estava ficou a `false` e conta como
     * venda livre sem precisar de tratamento nenhum.
     *
     * O teste fica, agora a provar o que é verdade: um artigo criado sem
     * tocar no campo aparece no filtro dos que não exigem receita. É o caso
     * dos dez mil artigos que uma farmácia migra de um sistema antigo.
     */
    public function test_um_artigo_migrado_conta_como_venda_livre(): void
    {
        $migrado = $this->artigo(['name' => 'Artigo Migrado']);

        $this->assertFalse(
            (bool) $migrado->requires_prescription,
            'Sem valor, um artigo é de venda livre.'
        );

        Livewire::test(Products::class)
            ->set('filterPrescricao', 'nao')
            ->assertSee('Artigo Migrado');
    }

    /** Os filtros de tamanho e cor encontram a peça certa. */
    public function test_os_filtros_de_tamanho_e_cor_encontram_a_peca(): void
    {
        $this->artigo(['name' => 'Calça Preta 42', 'size' => '42', 'color' => 'Preto']);
        $this->artigo(['name' => 'Calça Azul 38', 'size' => '38', 'color' => 'Azul']);

        Livewire::test(Products::class)
            ->set('filterTamanho', '42')
            ->assertSee('Calça Preta 42')
            ->assertDontSee('Calça Azul 38');

        Livewire::test(Products::class)
            ->set('filterCor', 'Azul')
            ->assertSee('Calça Azul 38')
            ->assertDontSee('Calça Preta 42');
    }

    // ==================== procurar ====================

    /**
     * A pergunta que uma farmácia faz todos os dias.
     *
     * "O que tenho com paracetamol?" — pela substância, não pela marca. Quem
     * está ao balcão com uma receita na mão precisa de encontrar o genérico.
     */
    public function test_procurar_pela_substancia_encontra_as_marcas_todas(): void
    {
        $this->artigo(['name' => 'Ben-u-ron', 'active_ingredient' => 'Paracetamol']);
        $this->artigo(['name' => 'Panadol', 'active_ingredient' => 'Paracetamol']);
        $this->artigo(['name' => 'Brufen', 'active_ingredient' => 'Ibuprofeno']);

        $encontrados = Product::where('tenant_id', $this->tenant->id)
            ->porSubstancia('paracetamol')
            ->pluck('name')
            ->all();

        sort($encontrados);

        $this->assertSame(['Ben-u-ron', 'Panadol'], $encontrados);
    }

    /**
     * Um % escrito na pesquisa procura um %, não o catálogo inteiro.
     *
     * O % e o _ são curingas do LIKE. Sem os escapar, procurar por "50%"
     * devolvia tudo — e quem procura por "50%" está mesmo à procura de uma
     * concentração.
     */
    public function test_a_pesquisa_por_substancia_escapa_os_curingas(): void
    {
        $this->artigo(['name' => 'Álcool 70%', 'active_ingredient' => 'Etanol 70%']);
        $this->artigo(['name' => 'Soro', 'active_ingredient' => 'Cloreto de sódio']);

        $encontrados = Product::where('tenant_id', $this->tenant->id)
            ->porSubstancia('%')
            ->pluck('name')
            ->all();

        $this->assertSame(['Álcool 70%'], $encontrados, 'O % devia procurar um %, não tudo.');
    }

    /** A descrição farmacêutica junta o que identifica o medicamento. */
    public function test_a_descricao_farmaceutica_junta_nome_dosagem_e_forma(): void
    {
        $p = $this->artigo([
            'name'                => 'Paracetamol',
            'dosage'              => '500mg',
            'pharmaceutical_form' => 'comprimido',
        ]);

        $this->assertSame('Paracetamol 500mg comprimido', $p->descricao_farmaceutica);
    }

    /** Sem dosagem nem forma, é só o nome — e não o nome com espaços a mais. */
    public function test_a_descricao_farmaceutica_de_um_artigo_comum_e_so_o_nome(): void
    {
        $p = $this->artigo(['name' => 'Saco Plástico']);

        $this->assertSame('Saco Plástico', $p->descricao_farmaceutica);
    }

    // ==================== o aparelho ====================

    /**
     * Os campos chegam ao PWA.
     *
     * O POS offline avisa da receita, e para isso precisa de os ter no
     * catálogo local. Vão SEMPRE, mesmo a null: o PWA junta com bulkPut e uma
     * chave omitida deixava o valor velho — um artigo que deixasse de exigir
     * receita continuava a avisar no aparelho.
     */
    public function test_os_campos_chegam_ao_catalogo_do_aparelho(): void
    {
        $p = $this->artigo([
            'name'                  => 'Diazepam',
            'requires_prescription' => true,
            'is_controlled'         => true,
            'dosage'                => '5mg',
        ]);

        \App\Models\Invoicing\Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $p->id,
            'quantity'     => 10,
        ]);

        $produtos = collect($this->getJson('/api/v1/invoicing/sync')->assertOk()->json('data.products'))
            ->keyBy('id');

        $this->assertArrayHasKey($p->id, $produtos->all());

        $doAparelho = $produtos[$p->id];

        $this->assertTrue((bool) $doAparelho['requires_prescription']);
        $this->assertTrue((bool) $doAparelho['is_controlled']);
        $this->assertSame('5mg', $doAparelho['dosage']);

        // As chaves existem mesmo quando não há valor.
        foreach (['size', 'color', 'active_ingredient'] as $campo) {
            $this->assertArrayHasKey($campo, $doAparelho, "O campo {$campo} tem de vir sempre.");
        }
    }
}
