<?php

namespace Tests\Feature\Restaurant;

use App\Models\Category;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\PosShift;
use App\Models\Product;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\OrderItem;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Models\Treasury\PaymentMethod;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * A COMANDA, do lado da API que os ecrãs em React usam.
 *
 * O QUE ESTES ENSAIOS PRENDEM é o que a migração pôs em risco: as regras
 * viviam metade no serviço e metade nos componentes Livewire, e o que estava
 * nos componentes — a conta dividida, o motivo da anulação, o escopo de
 * empresa dos ids que vêm do browser — tinha de renascer na porta nova sem se
 * perder pelo caminho.
 *
 * A CONTA DIVIDIDA é o caso mais delicado: escolhem-se as linhas que vão nesta
 * factura, e o resto FICA na comanda. Um id de outra mesa a passar por aqui
 * facturava o jantar dos vizinhos.
 */
class ComandasEmReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/restaurant/comandas';

    private Venue $venue;
    private DiningTable $mesa;
    private Product $prato;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');
        $this->comModulo('invoicing');
        $this->comPermissoes(
            'restaurant.orders.view', 'restaurant.orders.create', 'restaurant.orders.edit',
            'restaurant.orders.cancel', 'restaurant.orders.transfer', 'restaurant.orders.split',
            'restaurant.checkout.charge', 'restaurant.floor.view', 'restaurant.floor.manage',
            'restaurant.menu.manage',
        );

        RestaurantSettings::forTenant($this->tenant->id)->update([
            'default_warehouse_id' => $this->armazem->id,
            'require_open_shift' => true,
            // Sem bilhetes de cozinha, os artigos ficam servidos assim que
            // entram — é o caminho curto para chegar ao fecho da conta.
            'use_kitchen_workflow' => false,
        ]);

        // A empresa de ensaio já nasce com as formas de pagamento da casa.
        PaymentMethod::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'cash'],
            ['name' => 'Dinheiro', 'is_active' => true, 'sort_order' => 1],
        );

        $this->venue = Venue::create([
            'tenant_id' => $this->tenant->id, 'code' => 'PRINCIPAL',
            'name' => 'Restaurante Principal', 'warehouse_id' => $this->armazem->id,
        ]);

        $area = Area::create([
            'tenant_id' => $this->tenant->id, 'venue_id' => $this->venue->id, 'name' => 'Sala',
        ]);

        $this->mesa = DiningTable::create([
            'tenant_id' => $this->tenant->id, 'venue_id' => $this->venue->id, 'area_id' => $area->id,
            'code' => 'M01', 'name' => 'Mesa 01', 'capacity' => 4,
        ]);

        $categoria = Category::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Geral'],
            ['slug' => 'geral', 'is_active' => true, 'order' => 0],
        );

        $this->prato = Product::create([
            'tenant_id' => $this->tenant->id, 'category_id' => $categoria->id, 'type' => 'produto',
            'name' => 'Muamba de galinha', 'price' => 5000, 'cost' => 0, 'unit' => 'UN',
            'tax_type' => 'iva', 'tax_rate_id' => $this->imposto->id,
            'manage_stock' => false, 'is_active' => true,
        ]);

        PosShift::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'shift_number' => 'T-'.uniqid(), 'status' => 'open',
            'opened_at' => now(), 'opening_balance' => 0,
        ]);
    }

    /** Uma comanda aberta na mesa, com um prato lá dentro. */
    private function comandaComUmPrato(float $quantidade = 2): Order
    {
        $id = $this->postJson('/api/v1/invoicing/react/restaurant/sala/abrir', [
            'table_id' => $this->mesa->id, 'guest_count' => 2,
        ])->assertCreated()->json('order_id');

        $this->postJson(self::RAIZ . "/{$id}/artigos", [
            'product_id' => $this->prato->id, 'quantity' => $quantidade,
        ])->assertCreated();

        return Order::withoutGlobalScopes()->findOrFail($id);
    }

    /** A forma de pagamento ACTIVA — o fecho recusa uma desligada. */
    private function metodo(): int
    {
        return (int) PaymentMethod::where('tenant_id', $this->tenant->id)
            ->where('is_active', true)->orderBy('sort_order')->value('id');
    }

    /* ─── Os artigos ───────────────────────────────────────────────────── */

    public function test_o_artigo_entra_na_comanda_com_o_preco_do_catalogo(): void
    {
        $comanda = $this->comandaComUmPrato();

        $linha = OrderItem::withoutGlobalScopes()->where('order_id', $comanda->id)->firstOrFail();

        $this->assertSame('Muamba de galinha', $linha->product_name);
        $this->assertEqualsWithDelta(2, (float) $linha->quantity, 0.0001);
        $this->assertEqualsWithDelta(5000, (float) $linha->unit_price, 0.01);
    }

    public function test_a_quantidade_sobe_e_desce_por_passos(): void
    {
        $comanda = $this->comandaComUmPrato(2);
        $linha = OrderItem::withoutGlobalScopes()->where('order_id', $comanda->id)->firstOrFail();

        $this->putJson(self::RAIZ . "/{$comanda->id}/artigos/{$linha->id}", ['delta' => 1])->assertOk();
        $this->assertEqualsWithDelta(3, (float) $linha->fresh()->quantity, 0.0001);

        $this->putJson(self::RAIZ . "/{$comanda->id}/artigos/{$linha->id}", ['delta' => -1])->assertOk();
        $this->assertEqualsWithDelta(2, (float) $linha->fresh()->quantity, 0.0001);
    }

    /**
     * CHEGAR A ZERO É REMOVER.
     *
     * Uma linha de quantidade nenhuma numa comanda é uma linha que ninguém
     * consegue apagar — e que continua a aparecer na conta.
     */
    public function test_baixar_a_zero_remove_a_linha(): void
    {
        $comanda = $this->comandaComUmPrato(1);
        $linha = OrderItem::withoutGlobalScopes()->where('order_id', $comanda->id)->firstOrFail();

        $this->putJson(self::RAIZ . "/{$comanda->id}/artigos/{$linha->id}", ['delta' => -1])->assertOk();

        $this->assertSame(0, OrderItem::withoutGlobalScopes()->where('order_id', $comanda->id)->count());
    }

    /** A linha de OUTRA comanda não se mexe pela morada desta. */
    public function test_a_linha_de_outra_comanda_nao_se_mexe(): void
    {
        $primeira = $this->comandaComUmPrato();

        $segunda = $this->postJson('/api/v1/invoicing/react/restaurant/sala/abrir-sem-mesa', [
            'venue_id' => $this->venue->id, 'channel' => 'counter',
        ])->assertCreated()->json('order_id');

        $linha = OrderItem::withoutGlobalScopes()->where('order_id', $primeira->id)->firstOrFail();

        $this->deleteJson(self::RAIZ . "/{$segunda}/artigos/{$linha->id}")->assertNotFound();

        $this->assertDatabaseHas('restaurant_order_items', ['id' => $linha->id]);
    }

    /* ─── O fecho ──────────────────────────────────────────────────────── */

    /** A comanda por servir não se factura: é a regra de sempre. */
    public function test_a_comanda_por_servir_nao_se_factura(): void
    {
        $comanda = $this->comandaComUmPrato();

        $comanda->update(['status' => 'draft']);

        $linha = OrderItem::withoutGlobalScopes()->where('order_id', $comanda->id)->firstOrFail();

        $this->postJson(self::RAIZ . "/{$comanda->id}/fechar", [
            'document_type' => 'FR',
            'payment_method_id' => $this->metodo(),
            'idempotency_key' => (string) Str::uuid(),
            'item_ids' => [$linha->id],
        ])->assertStatus(422);
    }

    /**
     * A CONTA DIVIDE-SE, e o que fica por facturar FICA.
     *
     * É a única coisa que o ecrã em Livewire fazia com `billItemIds` e que se
     * podia perder sem se dar por isso: facturar tudo é o caso fácil.
     */
    public function test_a_conta_divide_se_e_o_resto_fica_na_comanda(): void
    {
        $comanda = $this->comandaComUmPrato(1);

        // Um segundo prato, para haver o que dividir.
        $segundo = Product::create([
            'tenant_id' => $this->tenant->id, 'type' => 'produto', 'name' => 'Cerveja',
            'price' => 1000, 'cost' => 0, 'unit' => 'UN', 'tax_type' => 'iva',
            'tax_rate_id' => $this->imposto->id, 'manage_stock' => false, 'is_active' => true,
        ]);

        $this->postJson(self::RAIZ . "/{$comanda->id}/artigos", [
            'product_id' => $segundo->id, 'quantity' => 1,
        ])->assertCreated();

        $linhas = OrderItem::withoutGlobalScopes()->where('order_id', $comanda->id)->orderBy('id')->get();

        $this->assertCount(2, $linhas);

        // Acrescentar um artigo devolve a comanda a 'draft'; servida é o
        // estado em que uma conta se fecha.
        $comanda->fresh()->update(['status' => 'served']);

        $resposta = $this->postJson(self::RAIZ . "/{$comanda->id}/fechar", [
            'document_type' => 'FR',
            'payment_method_id' => $this->metodo(),
            'idempotency_key' => (string) Str::uuid(),
            'item_ids' => [$linhas[0]->id],
        ])->assertOk();

        $this->assertNotEmpty($resposta->json('factura.numero'));

        // A primeira linha ficou facturada; a segunda não.
        $this->assertEqualsWithDelta(1, (float) $linhas[0]->fresh()->billed_quantity, 0.0001);
        $this->assertEqualsWithDelta(0, (float) $linhas[1]->fresh()->billed_quantity, 0.0001);

        $this->assertSame('partially_billed', $comanda->fresh()->status);
    }

    /** Uma linha que não é desta comanda não entra na factura dela. */
    public function test_uma_linha_de_fora_nao_entra_na_factura(): void
    {
        $comanda = $this->comandaComUmPrato();

        $outraId = $this->postJson('/api/v1/invoicing/react/restaurant/sala/abrir-sem-mesa', [
            'venue_id' => $this->venue->id, 'channel' => 'counter',
        ])->assertCreated()->json('order_id');

        $this->postJson(self::RAIZ . "/{$outraId}/artigos", [
            'product_id' => $this->prato->id, 'quantity' => 1,
        ])->assertCreated();

        $daOutra = OrderItem::withoutGlobalScopes()->where('order_id', $outraId)->firstOrFail();

        $this->postJson(self::RAIZ . "/{$comanda->id}/fechar", [
            'document_type' => 'FR',
            'payment_method_id' => $this->metodo(),
            'idempotency_key' => (string) Str::uuid(),
            'item_ids' => [$daOutra->id],
        ])->assertStatus(422);

        $this->assertEqualsWithDelta(0, (float) $daOutra->fresh()->billed_quantity, 0.0001);
    }

    /** O pagamento repartido tem de somar o total — nem a mais nem a menos. */
    public function test_o_pagamento_repartido_tem_de_bater_certo(): void
    {
        $comanda = $this->comandaComUmPrato(1);
        $linha = OrderItem::withoutGlobalScopes()->where('order_id', $comanda->id)->firstOrFail();

        $forma = $this->metodo();

        $segunda = PaymentMethod::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'tpa'],
            ['name' => 'TPA', 'is_active' => true, 'sort_order' => 2],
        );

        $this->postJson(self::RAIZ . "/{$comanda->id}/fechar", [
            'document_type' => 'FR',
            'payment_method_id' => $forma,
            'idempotency_key' => (string) Str::uuid(),
            'item_ids' => [$linha->id],
            'payments' => [
                ['payment_method_id' => $forma, 'amount' => 1],
                ['payment_method_id' => $segunda->id, 'amount' => 1],
            ],
        ])->assertStatus(422);

        $this->assertEqualsWithDelta(0, (float) $linha->fresh()->billed_quantity, 0.0001);
    }

    /** E não se repete o mesmo método: somam-se numa linha só. */
    public function test_o_pagamento_repartido_nao_repete_o_metodo(): void
    {
        $comanda = $this->comandaComUmPrato(1);
        $linha = OrderItem::withoutGlobalScopes()->where('order_id', $comanda->id)->firstOrFail();
        $forma = $this->metodo();
        $total = (float) $linha->line_total;

        $this->postJson(self::RAIZ . "/{$comanda->id}/fechar", [
            'document_type' => 'FR',
            'payment_method_id' => $forma,
            'idempotency_key' => (string) Str::uuid(),
            'item_ids' => [$linha->id],
            'payments' => [
                ['payment_method_id' => $forma, 'amount' => $total / 2],
                ['payment_method_id' => $forma, 'amount' => $total / 2],
            ],
        ])->assertStatus(422);
    }

    /* ─── A anulação ───────────────────────────────────────────────────── */

    /** Um artigo produzido só se anula COM MOTIVO — e escreve desperdício. */
    public function test_anular_um_artigo_produzido_exige_motivo(): void
    {
        $comanda = $this->comandaComUmPrato(1);
        $linha = OrderItem::withoutGlobalScopes()->where('order_id', $comanda->id)->firstOrFail();

        $linha->update(['kitchen_status' => 'ready']);

        $this->postJson(self::RAIZ . "/{$comanda->id}/artigos/{$linha->id}/anular", ['reason' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->postJson(self::RAIZ . "/{$comanda->id}/artigos/{$linha->id}/anular", [
            'reason' => 'Caiu no chão',
        ])->assertOk();

        $this->assertSame('voided', $linha->fresh()->kitchen_status);

        $this->assertDatabaseHas('restaurant_wastes', [
            'tenant_id' => $this->tenant->id,
            'order_item_id' => $linha->id,
            'reason' => 'Caiu no chão',
        ]);
    }

    /* ─── As guardas ───────────────────────────────────────────────────── */

    /** Sem a permissão de facturar, a conta não se fecha. */
    public function test_sem_permissao_nao_se_factura(): void
    {
        $comanda = $this->comandaComUmPrato(1);
        $linha = OrderItem::withoutGlobalScopes()->where('order_id', $comanda->id)->firstOrFail();

        $outro = \App\Models\User::create([
            'name' => 'Sem fecho', 'email' => 'sf'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);

        $outro->tenants()->syncWithoutDetaching([$this->tenant->id]);

        setPermissionsTeamId($this->tenant->id);
        $outro->givePermissionTo('restaurant.orders.view');
        $outro->forgetCachedPermissions();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($outro)->postJson(self::RAIZ . "/{$comanda->id}/fechar", [
            'document_type' => 'FR',
            'idempotency_key' => (string) Str::uuid(),
            'item_ids' => [$linha->id],
        ])->assertForbidden();
    }

    /** Um utilizador desta empresa só com estas permissões, e com turno aberto. */
    private function utilizadorCom(string ...$permissoes): \App\Models\User
    {
        $u = \App\Models\User::create([
            'name' => 'Papel '.uniqid(), 'email' => 'p'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);
        $u->tenants()->syncWithoutDetaching([$this->tenant->id]);

        setPermissionsTeamId($this->tenant->id);
        foreach ($permissoes as $nome) {
            \Spatie\Permission\Models\Permission::findOrCreate($nome, 'web');
        }
        $u->givePermissionTo($permissoes);
        $u->forgetCachedPermissions();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        PosShift::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $u->id,
            'shift_number' => 'T-'.uniqid(), 'status' => 'open',
            'opened_at' => now(), 'opening_balance' => 0,
        ]);

        return $u;
    }

    /**
     * O CAIXA DO RESTAURANTE abre o balcão, põe o artigo na conta e dá a mesa
     * como limpa — tinha só `checkout.*` e o ecrã novo pedia `orders.*` e
     * `floor.manage` (auditoria das permissões, 2026-09-13). Abrir uma mesa
     * continua a ser do empregado.
     */
    public function test_o_caixa_do_restaurante_abre_o_balcao_acrescenta_e_limpa_a_mesa(): void
    {
        $caixa = $this->utilizadorCom('restaurant.checkout.view', 'restaurant.checkout.charge', 'restaurant.orders.view', 'restaurant.floor.view');

        $this->actingAs($caixa)->getJson('/api/v1/invoicing/react/restaurant/sala/opcoes')->assertOk()
            ->assertJsonPath('permissoes.pode_abrir_balcao', true)
            ->assertJsonPath('permissoes.pode_limpar', true)
            ->assertJsonPath('permissoes.pode_abrir', false);

        $id = $this->actingAs($caixa)->postJson('/api/v1/invoicing/react/restaurant/sala/abrir-sem-mesa', [
            'venue_id' => $this->venue->id, 'channel' => 'counter',
        ])->assertCreated()->json('order_id');

        $this->actingAs($caixa)->postJson(self::RAIZ . "/{$id}/artigos", [
            'product_id' => $this->prato->id, 'quantity' => 1,
        ])->assertCreated();

        $this->actingAs($caixa)->getJson(self::RAIZ . '/opcoes')->assertOk()
            ->assertJsonPath('permissoes.pode_editar', true)
            ->assertJsonPath('permissoes.pode_libertar_mesa', true);

        $this->actingAs($caixa)->postJson('/api/v1/invoicing/react/restaurant/sala/abrir', [
            'table_id' => $this->mesa->id, 'guest_count' => 2,
        ])->assertForbidden();

        // O observador do modelo repõe o estado: a mesa em limpeza põe-se por baixo.
        \Illuminate\Support\Facades\DB::table('restaurant_tables')->where('id', $this->mesa->id)->update(['status' => 'cleaning']);

        $this->actingAs($caixa)->postJson("/api/v1/invoicing/react/restaurant/sala/mesas/{$this->mesa->id}/limpar")->assertOk();
    }

    /** Só a ver, não se abre nada nem se mexe na conta. */
    public function test_quem_so_ve_as_comandas_nao_abre_o_balcao(): void
    {
        $ve = $this->utilizadorCom('restaurant.orders.view', 'restaurant.floor.view');

        $this->actingAs($ve)->postJson('/api/v1/invoicing/react/restaurant/sala/abrir-sem-mesa', [
            'venue_id' => $this->venue->id, 'channel' => 'counter',
        ])->assertForbidden();
    }

    /** A comanda de outra empresa não se abre por aqui. */
    public function test_a_comanda_de_outra_empresa_nao_se_ve(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $alheia = Order::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'venue_id' => $this->venue->id, 'order_number' => 'X-1', 'status' => 'draft',
            'channel' => 'counter', 'guest_count' => 1, 'subtotal' => 0,
            'tax_total' => 0, 'grand_total' => 0,
        ]);

        $this->getJson(self::RAIZ . "/{$alheia->id}")->assertNotFound();
    }
}
