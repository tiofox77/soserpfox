<?php

namespace Tests\Feature\Restaurant;

use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant\RestaurantSettings;
use Tests\TenantTestCase;

/**
 * A CARTA — os pratos e as categorias, no mesmo ecrã.
 *
 * PORQUE EXISTE. Montar o menu obrigava a saltar entre o formulário genérico
 * dos produtos e o ecrã das categorias. Aqui é tudo em linha — e estes ensaios
 * prendem os MECANISMOS: o prato nasce vendável, o preço não se estraga com um
 * dedo em falso, e daqui nada se apaga.
 *
 * O QUE MUDOU AO PASSAR A REACT: a porta passou a exigir a permissão que
 * corresponde ao que se faz. Ver a carta é `restaurant.menu.view`; mexer-lhe é
 * `restaurant.menu.manage`. O ecrã em Livewire não verificava nada por dentro —
 * quem entrasse fazia tudo.
 */
class CartaDoRestauranteTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/restaurant/carta';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');
        $this->comPermissoes('restaurant.menu.view', 'restaurant.menu.manage');
    }

    /* ─── Os pratos ────────────────────────────────────────────────────── */

    public function test_escrever_nome_e_preco_poe_o_prato_no_menu(): void
    {
        $this->postJson(self::RAIZ . '/pratos', ['name' => 'Mufete de cacusso', 'price' => 7500])
            ->assertCreated();

        $prato = Product::where('tenant_id', $this->tenant->id)
            ->where('name', 'Mufete de cacusso')->firstOrFail();

        // NASCE VENDÁVEL: activo, com imposto, sem stock próprio (um prato
        // consome ingredientes pela ficha, não a si mesmo).
        $this->assertTrue((bool) $prato->is_active);
        $this->assertNotNull($prato->tax_rate_id, 'sem imposto o TaxResolver não tem por onde pegar');
        $this->assertFalse((bool) $prato->manage_stock);
        $this->assertSame('7500.00', (string) $prato->price);
    }

    public function test_o_prato_nasce_na_categoria_escolhida(): void
    {
        $categoria = Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Grelhados', 'slug' => 'grelhados',
            'is_active' => true, 'order' => 1,
        ]);

        $this->postJson(self::RAIZ . '/pratos', [
            'name' => 'Picanha', 'price' => 12000, 'category_id' => $categoria->id,
        ])->assertCreated();

        $this->assertDatabaseHas('invoicing_products', [
            'tenant_id' => $this->tenant->id, 'name' => 'Picanha', 'category_id' => $categoria->id,
        ]);
    }

    public function test_o_preco_muda_se_a_tocar_lhe(): void
    {
        $prato = $this->prato('Muamba', 5000);

        $this->putJson(self::RAIZ . "/pratos/{$prato->id}/preco", ['price' => '6.500,00'])->assertOk();

        $this->assertSame('6500.00', (string) $prato->fresh()->price);
    }

    /**
     * UM DEDO EM FALSO NÃO PÕE O PRATO A ZERO.
     *
     * O parse devolve 0.0 para lixo — aceitar isso era um prato grátis por um
     * toque no sítio errado, descoberto só ao fechar a caixa.
     */
    public function test_lixo_no_preco_nao_estraga_o_que_la_estava(): void
    {
        $prato = $this->prato('Muamba', 5000);

        foreach (['abc', '', '   ', '-100'] as $lixo) {
            $this->putJson(self::RAIZ . "/pratos/{$prato->id}/preco", ['price' => $lixo])
                ->assertStatus(422);

            $this->assertSame('5000.00', (string) $prato->fresh()->price, "'{$lixo}' não podia passar");
        }

        // Mas zero ESCRITO é uma decisão — uma oferta da casa, por exemplo.
        $this->putJson(self::RAIZ . "/pratos/{$prato->id}/preco", ['price' => '0'])->assertOk();
        $this->assertSame('0.00', (string) $prato->fresh()->price);
    }

    public function test_esconder_tira_do_menu_sem_apagar(): void
    {
        $prato = $this->prato('Muamba', 5000);

        $this->postJson(self::RAIZ . "/pratos/{$prato->id}/disponibilidade")->assertOk();

        $this->assertFalse((bool) $prato->fresh()->is_active);
        $this->assertDatabaseHas('invoicing_products', ['id' => $prato->id]);

        $this->postJson(self::RAIZ . "/pratos/{$prato->id}/disponibilidade")->assertOk();
        $this->assertTrue((bool) $prato->fresh()->is_active);
    }

    public function test_o_nome_vazio_nao_passa(): void
    {
        $prato = $this->prato('Muamba', 5000);

        $this->putJson(self::RAIZ . "/pratos/{$prato->id}/nome", ['name' => '  '])->assertStatus(422);

        $this->assertSame('Muamba', $prato->fresh()->name);
    }

    /** O prato de outra empresa não se mexe daqui. */
    public function test_o_prato_de_outra_empresa_nao_se_toca(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        $alheio = Product::create([
            'tenant_id' => $outra->id, 'type' => 'produto', 'name' => 'Alheio',
            'price' => 1000, 'cost' => 0, 'unit' => 'UN', 'manage_stock' => false, 'is_active' => true,
        ]);

        $this->putJson(self::RAIZ . "/pratos/{$alheio->id}/preco", ['price' => '1'])->assertNotFound();

        $this->assertSame('1000.00', (string) $alheio->fresh()->price);
    }

    /* ─── As categorias ────────────────────────────────────────────────── */

    public function test_novo_tenant_recebe_categorias_exemplo_e_geral(): void
    {
        $this->getJson(self::RAIZ . '/opcoes')->assertOk();

        $nomes = Category::where('tenant_id', $this->tenant->id)->pluck('name');

        $this->assertContains('Geral', $nomes);
        $this->assertContains('Pratos Principais', $nomes);
        $this->assertContains('Bebidas', $nomes);
    }

    public function test_a_categoria_nasce_no_catalogo_partilhado(): void
    {
        $this->postJson(self::RAIZ . '/categorias', [
            'name' => 'Sumos Naturais', 'icon' => 'fa-martini-glass-citrus',
            'color' => '#0891B2', 'is_active' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('invoicing_categories', [
            'tenant_id' => $this->tenant->id, 'name' => 'Sumos Naturais', 'is_active' => 1,
        ]);
    }

    public function test_categoria_repetida_e_recusada(): void
    {
        $dados = ['name' => 'Petiscos da Casa', 'icon' => 'fa-utensils', 'color' => '#EA580C'];

        $this->postJson(self::RAIZ . '/categorias', $dados)->assertCreated();
        $this->postJson(self::RAIZ . '/categorias', $dados)->assertStatus(422);

        $this->assertSame(1, Category::where('tenant_id', $this->tenant->id)
            ->where('name', 'Petiscos da Casa')->count());
    }

    /** A «Geral» é a rede de segurança do catálogo: não se apaga nem se desliga. */
    public function test_categoria_geral_nao_pode_ser_eliminada_nem_desligada(): void
    {
        $this->getJson(self::RAIZ . '/opcoes')->assertOk();

        $geral = Category::where('tenant_id', $this->tenant->id)->where('slug', 'geral')->firstOrFail();

        $this->deleteJson(self::RAIZ . "/categorias/{$geral->id}")->assertStatus(422);
        $this->postJson(self::RAIZ . "/categorias/{$geral->id}/alternar")->assertStatus(422);

        $this->assertDatabaseHas('invoicing_categories', ['id' => $geral->id, 'deleted_at' => null, 'is_active' => 1]);
    }

    public function test_categoria_com_pratos_nao_se_apaga(): void
    {
        $categoria = Category::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Com pratos', 'slug' => 'com-pratos',
            'is_active' => true, 'order' => 5,
        ]);

        $this->prato('Um prato', 100)->update(['category_id' => $categoria->id]);

        $this->deleteJson(self::RAIZ . "/categorias/{$categoria->id}")->assertStatus(422);

        $this->assertDatabaseHas('invoicing_categories', ['id' => $categoria->id, 'deleted_at' => null]);
    }

    /** A ordem das categorias troca-se com as setas — e É a ordem da carta. */
    public function test_as_categorias_reordenam_se(): void
    {
        Category::where('tenant_id', $this->tenant->id)->delete();

        $entradas = Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Entradas', 'slug' => 'entradas', 'is_active' => true, 'order' => 1]);
        $pratos = Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Pratos', 'slug' => 'pratos', 'is_active' => true, 'order' => 2]);

        $this->postJson(self::RAIZ . "/categorias/{$pratos->id}/mover", ['direccao' => 'cima'])->assertOk();

        $this->assertLessThan(
            (int) $entradas->fresh()->order,
            (int) $pratos->fresh()->order,
            'os Pratos tinham de passar para cima das Entradas',
        );
    }

    /* ─── As fichas técnicas obrigatórias ──────────────────────────────── */

    /**
     * Com fichas obrigatórias, o prato sem ficha vem MARCADO.
     *
     * Sem esta marca, «criei o prato e ele não aparece no balcão» era um
     * mistério sem pista nenhuma.
     */
    public function test_com_fichas_obrigatorias_o_prato_sem_ficha_e_marcado(): void
    {
        RestaurantSettings::forTenant($this->tenant->id)->update(['require_recipe_for_products' => true]);

        $prato = $this->prato('Muamba', 5000);

        $linha = collect($this->getJson(self::RAIZ)->assertOk()->json('data'))
            ->firstWhere('id', $prato->id);

        $this->assertTrue($linha['falta_ficha']);
    }

    /* ─── As guardas ───────────────────────────────────────────────────── */

    public function test_sem_permissao_de_ver_nao_se_le_a_carta(): void
    {
        $outro = $this->semPermissoes();

        $this->actingAs($outro)->getJson(self::RAIZ)->assertForbidden();
        $this->actingAs($outro)->get('/restaurant/carta')->assertForbidden();
    }

    /** VER NÃO É MEXER: quem só lê a carta não lhe muda o preço. */
    public function test_quem_so_ve_nao_mexe(): void
    {
        $prato = $this->prato('Muamba', 5000);

        $outro = $this->semPermissoes();
        setPermissionsTeamId($this->tenant->id);
        $outro->givePermissionTo('restaurant.menu.view');
        $outro->forgetCachedPermissions();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($outro)->getJson(self::RAIZ)->assertOk();

        $this->actingAs($outro)
            ->putJson(self::RAIZ . "/pratos/{$prato->id}/preco", ['price' => '1'])
            ->assertForbidden();

        $this->assertSame('5000.00', (string) $prato->fresh()->price);
    }

    /* ─── Ferramentas ──────────────────────────────────────────────────── */

    private function semPermissoes(): \App\Models\User
    {
        $outro = \App\Models\User::create([
            'name' => 'Sem nada', 'email' => 'sn' . uniqid() . '@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);

        $outro->tenants()->syncWithoutDetaching([$this->tenant->id]);

        return $outro;
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
