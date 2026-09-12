<?php

namespace Tests\Feature\Restaurant;

use App\Models\Product;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\KitchenStation;
use App\Models\Restaurant\Recipe;
use App\Models\Restaurant\Reservation;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * OS ECRÃS DO RESTAURANTE EM REACT — as portas e as guardas.
 *
 * O QUE ESTE ENSAIO PRENDE é o que uma migração de catorze ecrãs mais
 * facilmente perde: as PERMISSÕES. Os componentes Livewire não verificavam
 * nada por dentro — quem entrasse na página fazia tudo o que ela mostrava.
 * Cada porta em React exige a permissão que corresponde ao que faz, e é isso
 * que aqui se confirma, uma por uma.
 *
 * E confirma-se também que cada página MONTA O ECRÃ CERTO: um `data-ecra` que
 * não esteja no registo não monta nada, e a página fica com um buraco branco
 * sem explicação.
 */
class EcrasDoRestauranteEmReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/restaurant';

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');

        RestaurantSettings::forTenant($this->tenant->id)->update([
            'default_warehouse_id' => $this->armazem->id,
        ]);

        $this->venue = Venue::create([
            'tenant_id' => $this->tenant->id, 'code' => 'PRINCIPAL',
            'name' => 'Restaurante Principal', 'warehouse_id' => $this->armazem->id,
        ]);
    }

    /** As páginas e o ecrã que cada uma monta. */
    public static function paginas(): array
    {
        return [
            'painel' => ['/restaurant/dashboard', 'restaurant/painel', 'restaurant.dashboard.view'],
            'sala' => ['/restaurant/floor', 'restaurant/sala', 'restaurant.floor.view'],
            'comandas' => ['/restaurant/orders', 'restaurant/comandas', 'restaurant.orders.view'],
            'balcão' => ['/restaurant/pos', 'restaurant/balcao', 'restaurant.orders.view'],
            'contactos' => ['/restaurant/contacts', 'restaurant/contactos', 'restaurant.orders.view'],
            'carta' => ['/restaurant/carta', 'restaurant/carta', 'restaurant.menu.view'],
            'categorias' => ['/restaurant/categories', 'restaurant/carta', 'restaurant.menu.view'],
            'cozinha' => ['/restaurant/kitchen', 'restaurant/cozinha', 'restaurant.kitchen.view'],
            'reservas' => ['/restaurant/reservations', 'restaurant/reservas', 'restaurant.reservations.view'],
            'fichas' => ['/restaurant/recipes', 'restaurant/fichas', 'restaurant.recipes.view'],
            'stock' => ['/restaurant/stock', 'restaurant/stock', 'restaurant.stock.view'],
            'relatórios' => ['/restaurant/reports', 'restaurant/relatorios', 'restaurant.reports.view'],
            'definições' => ['/restaurant/settings', 'restaurant/definicoes', 'restaurant.settings.view'],
            'aparência' => ['/restaurant/carta/aparencia', 'restaurant/aparencia-da-carta', 'restaurant.settings.view'],
        ];
    }

    /**
     * @dataProvider paginas
     */
    public function test_cada_pagina_monta_o_seu_ecra(string $morada, string $ecra, string $permissao): void
    {
        // Sem a permissão, a porta fecha-se.
        $this->get($morada)->assertForbidden();

        $this->comPermissoes($permissao);

        $this->get($morada)->assertOk()->assertSee($ecra, false);
    }

    /** O ecrã que cada página pede tem de estar no registo do React. */
    public function test_os_ecras_pedidos_existem_no_registo(): void
    {
        $registo = file_get_contents(resource_path('js/ecras/registo.ts'));

        foreach (self::paginas() as [$morada, $ecra]) {
            $this->assertStringContainsString("'{$ecra}':", $registo,
                "A página {$morada} pede o ecrã {$ecra}, que não está no registo — a página montaria um buraco branco.");
        }
    }

    /** E o ficheiro de cada ecrã existe mesmo — o registo aponta para algo. */
    public function test_os_ficheiros_dos_ecras_existem(): void
    {
        foreach ([
            'Painel', 'Sala', 'Comandas', 'Balcao', 'Cozinha', 'Carta', 'Contactos',
            'Reservas', 'Fichas', 'Stock', 'Relatorios', 'Definicoes', 'Aparencia',
            'PecasDaComanda',
        ] as $ficheiro) {
            $this->assertFileExists(resource_path("js/ecras/restaurant/{$ficheiro}.tsx"));
        }
    }

    /** O Livewire do restaurante foi-se — só a carta pública ficou. */
    public function test_so_a_carta_publica_continua_em_livewire(): void
    {
        $restantes = array_values(array_diff(
            scandir(app_path('Livewire/Restaurant')) ?: [],
            ['.', '..'],
        ));

        $this->assertSame(['MenuOnline.php'], $restantes,
            'A carta pública é a única página do restaurante que não tem sessão — e por isso a única que fica.');
    }

    /* ─── As portas, uma a uma ─────────────────────────────────────────── */

    public function test_o_painel_exige_a_sua_permissao(): void
    {
        $this->getJson(self::RAIZ . '/painel')->assertForbidden();

        $this->comPermissoes('restaurant.dashboard.view');

        $this->getJson(self::RAIZ . '/painel')
            ->assertOk()
            ->assertJsonStructure(['resumo' => ['mesas', 'comandas_abertas', 'venda_hoje', 'ticket_medio'], 'por_dia', 'por_hora']);
    }

    /** O painel conta os dias sem venda A ZERO — senão o gráfico mente. */
    public function test_o_painel_devolve_todos_os_dias_do_periodo(): void
    {
        $this->comPermissoes('restaurant.dashboard.view');

        $dados = $this->getJson(self::RAIZ . '/painel?dias=7')->assertOk()->json();

        $this->assertCount(7, $dados['por_dia']['etiquetas']);
        $this->assertCount(7, $dados['por_dia']['valores']);
    }

    public function test_a_cozinha_exige_a_sua_permissao(): void
    {
        $this->getJson(self::RAIZ . '/cozinha')->assertForbidden();

        $this->comPermissoes('restaurant.kitchen.view');

        $this->getJson(self::RAIZ . '/cozinha')->assertOk()->assertJsonStructure(['data']);

        // VER NÃO É MEXER: avançar um bilhete pede a permissão de gerir.
        $this->postJson(self::RAIZ . '/cozinha/1/avancar')->assertForbidden();
    }

    public function test_as_reservas_gravam_se_e_mudam_de_estado(): void
    {
        $this->comPermissoes(
            'restaurant.reservations.view', 'restaurant.reservations.create',
            'restaurant.reservations.edit', 'restaurant.reservations.cancel',
        );

        $mesa = DiningTable::create([
            'tenant_id' => $this->tenant->id, 'venue_id' => $this->venue->id,
            'code' => 'M01', 'name' => 'Mesa 01', 'capacity' => 4,
        ]);

        $quando = now()->addDay()->setTime(20, 0);

        $this->postJson(self::RAIZ . '/reservas', [
            'venue_id' => $this->venue->id, 'table_id' => $mesa->id,
            'guest_name' => 'Dona Ana', 'guest_count' => 2,
            'reserved_at' => $quando->format('Y-m-d H:i:s'), 'duration_minutes' => 120,
        ])->assertCreated();

        $reserva = Reservation::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame('pending', $reserva->status);

        // Confirmar reserva a mesa; o ecrã só oferece as transições possíveis.
        $this->postJson(self::RAIZ . "/reservas/{$reserva->id}/estado", ['estado' => 'confirmed'])->assertOk();

        $this->assertSame('confirmed', $reserva->fresh()->status);
        $this->assertSame('reserved', $mesa->fresh()->status);

        // E cancelar devolve a mesa ao serviço.
        $this->postJson(self::RAIZ . "/reservas/{$reserva->id}/estado", ['estado' => 'cancelled'])->assertOk();

        $this->assertSame('available', $mesa->fresh()->status);
    }

    /** Uma mesa pequena não aceita uma mesa cheia de gente. */
    public function test_a_reserva_nao_cabe_numa_mesa_pequena(): void
    {
        $this->comPermissoes('restaurant.reservations.view', 'restaurant.reservations.create');

        $mesa = DiningTable::create([
            'tenant_id' => $this->tenant->id, 'venue_id' => $this->venue->id,
            'code' => 'M02', 'name' => 'Mesa 02', 'capacity' => 2,
        ]);

        $this->postJson(self::RAIZ . '/reservas', [
            'venue_id' => $this->venue->id, 'table_id' => $mesa->id,
            'guest_name' => 'Um grupo', 'guest_count' => 8,
            'reserved_at' => now()->addDay()->format('Y-m-d H:i:s'), 'duration_minutes' => 120,
        ])->assertStatus(422);

        $this->assertSame(0, Reservation::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * A FICHA TÉCNICA conta o custo com a quebra — 200 g a 10% são 220 g
     * compradas, e é isso que decide se o prato dá dinheiro.
     */
    public function test_a_ficha_tecnica_soma_o_custo_com_a_quebra(): void
    {
        $this->comPermissoes('restaurant.recipes.view', 'restaurant.recipes.manage');

        $prato = $this->artigo('Bitoque', 5000, 0);
        $carne = $this->artigo('Carne', 0, 10);

        $ficha = $this->postJson(self::RAIZ . '/fichas', [
            'product_id' => $prato->id, 'yield_quantity' => 1, 'yield_unit' => 'UN',
        ])->assertCreated()->json('data');

        $this->postJson(self::RAIZ . "/fichas/{$ficha['id']}/ingredientes", [
            'ingredient_product_id' => $carne->id,
            'quantity' => 200, 'unit' => 'G', 'waste_percent' => 10,
        ])->assertOk();

        $actualizada = $this->getJson(self::RAIZ . '/fichas')->assertOk()->json('data.0');

        // 200 × 1,10 = 220 unidades a 10 de custo = 2200.
        $this->assertEqualsWithDelta(220, $actualizada['ingredientes'][0]['quantidade_com_quebra'], 0.0001);
        $this->assertEqualsWithDelta(2200, $actualizada['custo'], 0.01);
        $this->assertEqualsWithDelta(56.0, $actualizada['margem'], 0.1);
    }

    /** Um prato não é ingrediente de si próprio. */
    public function test_o_prato_nao_e_ingrediente_de_si_proprio(): void
    {
        $this->comPermissoes('restaurant.recipes.view', 'restaurant.recipes.manage');

        $prato = $this->artigo('Bitoque', 5000, 0);

        $ficha = $this->postJson(self::RAIZ . '/fichas', [
            'product_id' => $prato->id, 'yield_quantity' => 1, 'yield_unit' => 'UN',
        ])->assertCreated()->json('data');

        $this->postJson(self::RAIZ . "/fichas/{$ficha['id']}/ingredientes", [
            'ingredient_product_id' => $prato->id,
            'quantity' => 1, 'unit' => 'UN', 'waste_percent' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ingredient_product_id');
    }

    /** Uma ficha por prato: duas do mesmo bitoque não têm resposta. */
    public function test_ha_uma_ficha_por_prato(): void
    {
        $this->comPermissoes('restaurant.recipes.view', 'restaurant.recipes.manage');

        $prato = $this->artigo('Bitoque', 5000, 0);

        $this->postJson(self::RAIZ . '/fichas', ['product_id' => $prato->id, 'yield_quantity' => 1, 'yield_unit' => 'UN'])
            ->assertCreated();
        $this->postJson(self::RAIZ . '/fichas', ['product_id' => $prato->id, 'yield_quantity' => 2, 'yield_unit' => 'UN'])
            ->assertCreated();

        $this->assertSame(1, Recipe::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * O DESPERDÍCIO DE ARMAZÉM tem um mínimo de 0,01.
     *
     * A coluna é `decimal(10,2)`: 0,004 era aceite, gravava 0,00 e o stock não
     * mexia — ficava registado um desperdício que não existiu.
     */
    public function test_o_desperdicio_abaixo_do_centesimo_e_recusado(): void
    {
        $this->comPermissoes('restaurant.stock.view', 'restaurant.stock.waste');

        $artigo = $this->artigo('Tomate', 0, 100);
        $artigo->update(['manage_stock' => true]);

        // Há tomate no armazém: o que se prova aqui é o MÍNIMO da quantidade,
        // não a falta de existências (que tem ensaio próprio no stock).
        \App\Models\Invoicing\Stock::create([
            'tenant_id' => $this->tenant->id, 'warehouse_id' => $this->armazem->id,
            'product_id' => $artigo->id, 'quantity' => 50,
            'reserved_quantity' => 0, 'available_quantity' => 50,
        ]);

        $this->postJson(self::RAIZ . '/stock/desperdicio', [
            'product_id' => $artigo->id, 'warehouse_id' => $this->armazem->id,
            'quantity' => 0.004, 'reason' => 'Estragou-se',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');

        $this->postJson(self::RAIZ . '/stock/desperdicio', [
            'product_id' => $artigo->id, 'warehouse_id' => $this->armazem->id,
            'quantity' => 2, 'reason' => 'Estragou-se',
        ])->assertCreated();

        $this->assertDatabaseHas('invoicing_stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $artigo->id,
            'reference_type' => 'restaurant_waste',
        ]);
    }

    /** Ver o stock não dá o direito de lançar desperdício. */
    public function test_quem_so_ve_o_stock_nao_lanca_desperdicio(): void
    {
        $this->comPermissoes('restaurant.stock.view');

        $artigo = $this->artigo('Tomate', 0, 100);

        $this->getJson(self::RAIZ . '/stock')->assertOk();

        $this->postJson(self::RAIZ . '/stock/desperdicio', [
            'product_id' => $artigo->id, 'warehouse_id' => $this->armazem->id,
            'quantity' => 1, 'reason' => 'Estragou-se',
        ])->assertForbidden();
    }

    /* ─── As definições ────────────────────────────────────────────────── */

    /** Ver as definições não é mudá-las. */
    public function test_quem_so_ve_as_definicoes_nao_lhes_mexe(): void
    {
        $this->comPermissoes('restaurant.settings.view');

        $this->getJson(self::RAIZ . '/definicoes')->assertOk();

        $this->putJson(self::RAIZ . '/definicoes', ['tips_enabled' => false])->assertForbidden();
        $this->postJson(self::RAIZ . '/definicoes/zonas', ['venue_id' => $this->venue->id, 'name' => 'Esplanada'])
            ->assertForbidden();
    }

    /** O turno de caixa não tem interruptor — e continua ligado. */
    public function test_o_turno_obrigatorio_nao_se_desliga(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');

        $this->putJson(self::RAIZ . '/definicoes', [
            'require_open_shift' => false,
            'tips_enabled' => true,
            'service_charge_percent' => 0,
        ])->assertOk();

        $this->assertTrue((bool) RestaurantSettings::forTenant($this->tenant->id)->fresh()->require_open_shift);
    }

    /** A estrutura com histórico não se apaga — desactiva-se. */
    public function test_a_zona_com_mesas_nao_se_apaga(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');

        $zona = Area::create([
            'tenant_id' => $this->tenant->id, 'venue_id' => $this->venue->id, 'name' => 'Esplanada',
        ]);

        DiningTable::create([
            'tenant_id' => $this->tenant->id, 'venue_id' => $this->venue->id, 'area_id' => $zona->id,
            'code' => 'E01', 'name' => 'Esplanada 1', 'capacity' => 2,
        ]);

        $this->deleteJson(self::RAIZ . "/definicoes/zona/{$zona->id}")->assertStatus(422);

        $this->assertDatabaseHas('restaurant_areas', ['id' => $zona->id]);

        // Mas desliga-se.
        $this->postJson(self::RAIZ . "/definicoes/zona/{$zona->id}/alternar")->assertOk();

        $this->assertFalse((bool) $zona->fresh()->is_active);
    }

    /** A peça de outra empresa não se toca por esta porta. */
    public function test_a_peca_de_outra_empresa_nao_se_toca(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $alheio = KitchenStation::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'venue_id' => $this->venue->id,
            'code' => 'GRELHA', 'name' => 'Grelha alheia', 'sort_order' => 0, 'is_active' => true,
        ]);

        $this->postJson(self::RAIZ . "/definicoes/posto/{$alheio->id}/alternar")->assertNotFound();

        $this->assertTrue((bool) $alheio->fresh()->is_active);
    }

    /** A carta pública só se publica com endereço. */
    public function test_a_carta_publica_exige_endereco(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');

        $this->putJson(self::RAIZ . '/definicoes/carta', [
            'online_menu_enabled' => true, 'menu_slug' => '',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('menu_slug');

        $this->assertFalse((bool) RestaurantSettings::forTenant($this->tenant->id)->fresh()->online_menu_enabled);
    }

    /** E o botão de WhatsApp sem número não vale nada. */
    public function test_o_whatsapp_sem_numero_e_recusado(): void
    {
        $this->comPermissoes('restaurant.settings.view', 'restaurant.settings.edit');

        $this->putJson(self::RAIZ . '/definicoes/carta', [
            'online_menu_enabled' => true,
            'menu_slug' => 'casa-'.uniqid(),
            'menu_whatsapp_enabled' => true,
            'menu_whatsapp_number' => '',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('menu_whatsapp_number');
    }

    /* ─── Ferramentas ──────────────────────────────────────────────────── */

    private function artigo(string $nome, float $preco, float $custo): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id, 'type' => 'produto', 'name' => $nome,
            'price' => $preco, 'cost' => $custo, 'unit' => 'UN',
            'tax_type' => 'iva', 'tax_rate_id' => $this->imposto->id,
            'manage_stock' => false, 'is_active' => true,
        ]);
    }
}
