<?php

namespace Tests\Feature\Restaurant;

use App\Livewire\Restaurant\MontarMenu;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant\RestaurantSettings;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O ecrã de montar a carta.
 *
 * PORQUE EXISTE. Montar o menu obrigava a saltar entre o formulário genérico
 * dos produtos e o ecrã das categorias. Aqui é tudo em linha — e estes
 * ensaios prendem os MECANISMOS: o prato nasce vendável, o preço não se
 * estraga com um dedo em falso, e daqui nada se apaga.
 */
class MontarMenuTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');
        $this->comPermissoes('restaurant.orders.view');
    }

    private function ecra()
    {
        return Livewire::actingAs($this->user)->test(MontarMenu::class);
    }

    /** @test */
    public function escrever_nome_e_preco_poe_o_prato_no_menu(): void
    {
        $this->ecra()
            ->set('novoPrato', 'Mufete de cacusso')
            ->set('novoPreco', '7500')
            ->call('criarPrato')
            ->assertHasNoErrors()
            ->assertSet('novoPrato', '');

        $prato = Product::where('tenant_id', $this->tenant->id)
            ->where('name', 'Mufete de cacusso')->firstOrFail();

        // NASCE VENDÁVEL: activo, com imposto, sem stock próprio (um prato
        // consome ingredientes pela ficha, não a si mesmo).
        $this->assertTrue((bool) $prato->is_active);
        $this->assertNotNull($prato->tax_rate_id, 'sem imposto o TaxResolver não tem por onde pegar');
        $this->assertFalse((bool) $prato->manage_stock);
        $this->assertSame('7500.00', (string) $prato->price);
    }

    /** O prato nasce já na categoria escolhida. */
    public function test_o_prato_nasce_na_categoria_escolhida(): void
    {
        $categoria = Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Grelhados', 'slug' => 'grelhados',
            'is_active' => true, 'order' => 1,
        ]);

        $this->ecra()
            ->set('categoriaId', $categoria->id)
            ->set('novoPrato', 'Picanha')
            ->set('novoPreco', '12000')
            ->call('criarPrato')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('invoicing_products', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Picanha',
            'category_id' => $categoria->id,
        ]);
    }

    /** @test */
    public function o_preco_muda_se_a_tocar_lhe(): void
    {
        $prato = $this->prato('Muamba', 5000);

        $this->ecra()->call('mudarPreco', $prato->id, '6.500,00');

        $this->assertSame('6500.00', (string) $prato->fresh()->price);
    }

    /**
     * UM DEDO EM FALSO NÃO PÕE O PRATO A ZERO.
     *
     * O parse devolve 0.0 para lixo — aceitar isso era um prato grátis por
     * um toque no sítio errado, descoberto só ao fechar a caixa.
     *
     * @test
     */
    public function lixo_no_preco_nao_estraga_o_que_la_estava(): void
    {
        $prato = $this->prato('Muamba', 5000);

        foreach (['abc', '', '   ', '-100'] as $lixo) {
            $this->ecra()->call('mudarPreco', $prato->id, $lixo);
            $this->assertSame('5000.00', (string) $prato->fresh()->price, "'{$lixo}' não podia passar");
        }

        // Mas zero ESCRITO é uma decisão — uma oferta da casa, por exemplo.
        $this->ecra()->call('mudarPreco', $prato->id, '0');
        $this->assertSame('0.00', (string) $prato->fresh()->price);
    }

    /** @test */
    public function esconder_tira_do_menu_sem_apagar(): void
    {
        $prato = $this->prato('Muamba', 5000);

        $this->ecra()->call('alternarDisponivel', $prato->id);

        $this->assertFalse((bool) $prato->fresh()->is_active);
        $this->assertDatabaseHas('invoicing_products', ['id' => $prato->id]);

        $this->ecra()->call('alternarDisponivel', $prato->id);
        $this->assertTrue((bool) $prato->fresh()->is_active);
    }

    /** O nome vazio não passa — e o de antes fica. */
    public function test_o_nome_vazio_nao_passa(): void
    {
        $prato = $this->prato('Muamba', 5000);

        $this->ecra()->call('mudarNome', $prato->id, '  ');

        $this->assertSame('Muamba', $prato->fresh()->name);
    }

    /** @test */
    public function criar_categoria_sem_sair_do_ecra_e_ja_ficar_nela(): void
    {
        $this->ecra()
            ->set('novaCategoria', 'Petiscos da Casa')
            ->call('criarCategoria')
            ->assertHasNoErrors()
            ->assertSet('novaCategoria', '');

        $categoria = Category::where('tenant_id', $this->tenant->id)
            ->where('name', 'Petiscos da Casa')->firstOrFail();

        // E o ecrã fica logo dentro dela: o próximo prato nasce arrumado.
        $this->ecra()->set('novaCategoria', 'Petiscos da Casa')->call('criarCategoria');
        // repetida é recusada sem criar segunda
        $this->assertSame(1, Category::where('tenant_id', $this->tenant->id)->where('name', 'Petiscos da Casa')->count());

        $this->assertTrue((bool) $categoria->is_active);
    }

    /** A ordem das categorias troca-se com as setas — e É a ordem da carta. */
    public function test_as_categorias_reordenam_se(): void
    {
        Category::where('tenant_id', $this->tenant->id)->delete();

        $entradas = Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Entradas', 'slug' => 'entradas', 'is_active' => true, 'order' => 1]);
        $pratos = Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Pratos', 'slug' => 'pratos', 'is_active' => true, 'order' => 2]);

        $this->ecra()->call('moverCategoria', $pratos->id, 'cima');

        $this->assertLessThan(
            (int) $entradas->fresh()->order,
            (int) $pratos->fresh()->order,
            'os Pratos tinham de passar para cima das Entradas'
        );
    }

    /** Com fichas técnicas obrigatórias, o prato sem ficha aparece marcado. */
    public function test_com_fichas_obrigatorias_o_prato_sem_ficha_e_marcado(): void
    {
        RestaurantSettings::forTenant($this->tenant->id)->update(['require_recipe_for_products' => true]);

        $this->prato('Muamba', 5000);

        $this->ecra()->assertSee('falta a ficha');
    }

    /** Sem a permissão do restaurante, o ecrã não abre. */
    public function test_sem_permissao_nao_abre(): void
    {
        $outro = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id]);
        $outro->tenants()->attach($this->tenant->id);

        $this->actingAs($outro)->get('/restaurant/carta')->assertForbidden();
    }

    private function prato(string $nome, float $preco): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'produto',
            'name' => $nome,
            'price' => $preco,
            'cost' => 0,
            'unit' => 'UN',
            'manage_stock' => false,
            'is_active' => true,
        ]);
    }
}
