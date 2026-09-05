<?php

namespace Tests\Feature\Restaurant;

use App\Models\Invoicing\PosShift;
use App\Models\Product;
use App\Models\Restaurant\Area;
use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\OrderItem;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Restaurant\Venue;
use App\Models\Treasury\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * A comanda feita sem rede, quando chega ao servidor.
 *
 * É aqui que vivem os defeitos caros do restaurante offline, e são três:
 *
 *   · a comanda sobe DUAS vezes e a mesa fica com a conta a dobrar;
 *   · a mesa foi ocupada entretanto e a comanda — já comida e já paga —
 *     desaparece numa fila com um erro que ninguém lê;
 *   · a conta fecha por um valor e a factura sai por outro.
 *
 * Cada um deles tem aqui o seu ensaio. O resto é o caminho normal.
 */
class ComandaOfflineTest extends TenantTestCase
{
    public function test_copia_recupera_comanda_sem_duplicar_artigos(): void
    {
        $carga = $this->comanda(['confirmar' => false, 'notes' => 'Sem sal']);
        $copia = ['formato' => 'soserp.pwa.copia', 'versao' => 2,
            'tenant_id' => $this->tenant->id,
            'dados' => ['sync_queue' => [['op' => 'sync_restaurant_order', 'payload' => $carga]]]];
        $servico = app(\App\Services\POS\ImportacaoDeCopiaOffline::class);
        $this->assertNull($servico->validar($copia, $this->tenant->id));
        foreach ([1, 2] as $tentativa) {
            $resultado = $servico->importar($copia, $this->tenant->id, $this->user->id);
            $this->assertSame(0, $resultado['falhadas'], json_encode($resultado['erros']));
            $this->assertSame(1, $resultado['comandas']);
        }
        $pedidos = Order::where('local_uuid', $carga['local_uuid'])->get();
        $this->assertCount(1, $pedidos);
        $this->assertSame($this->mesa->id, $pedidos->first()->table_id);
        $this->assertSame('Sem sal', $pedidos->first()->notes);
        $this->assertSame(1, OrderItem::where('order_id', $pedidos->first()->id)->count());
        $this->assertEquals(2, OrderItem::where('order_id', $pedidos->first()->id)->value('quantity'));
    }

    public function test_copia_incompleta_nao_descarta_comanda_silenciosamente(): void
    {
        $resultado = app(\App\Services\POS\ImportacaoDeCopiaOffline::class)->importar(
            ['dados' => ['sync_queue' => [['op' => 'sync_restaurant_order',
                'payload' => ['local_uuid' => (string) Str::uuid()]]]]],
            $this->tenant->id, $this->user->id
        );
        $this->assertSame(1, $resultado['falhadas']);
        $this->assertSame(0, $resultado['comandas']);
        $this->assertStringContainsString('dados completos', $resultado['erros'][0]);
    }

    private Venue $sala;

    private DiningTable $mesa;

    private Product $prato;

    private PaymentMethod $metodo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('restaurant');
        $this->comModulo('invoicing');
        $this->comModulo('treasury');

        RestaurantSettings::forTenant($this->tenant->id)->update([
            'default_warehouse_id' => $this->armazem->id,
            'use_kitchen_workflow' => true,
            'require_recipe_for_products' => false,
        ]);

        $this->sala = Venue::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'S1',
            'name' => 'Salão',
            'warehouse_id' => $this->armazem->id,
            'is_active' => true,
        ]);

        $zona = Area::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $this->sala->id,
            'name' => 'Esplanada',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->mesa = DiningTable::create([
            'tenant_id' => $this->tenant->id,
            'venue_id' => $this->sala->id,
            'area_id' => $zona->id,
            'code' => 'M1',
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->prato = Product::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'produto',
            'name' => 'Muamba de Galinha',
            'price' => 5000,
            'cost' => 0,
            'unit' => 'UN',
            'tax_type' => 'iva',
            'tax_rate_id' => $this->imposto->id,
            'manage_stock' => false,
            'is_active' => true,
        ]);

        // Criar um Tenant já provisiona métodos de pagamento: criar outro com
        // o mesmo código rebenta o índice único (tenant, code). Reutiliza-se.
        $this->metodo = PaymentMethod::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'CASH'],
            ['name' => 'Dinheiro', 'type' => 'cash', 'is_active' => true, 'sort_order' => 1]
        );

        $this->abrirTurno($this->user->id);
    }

    private function abrirTurno(int $userId): PosShift
    {
        return PosShift::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $userId,
            'shift_number' => 'T-'.uniqid(),
            'status' => 'open',
            'opened_at' => now(),
            'opening_balance' => 0,
        ]);
    }

    /** O corpo de uma comanda tal como o aparelho a manda. */
    private function comanda(array $extra = []): array
    {
        return array_merge([
            'local_uuid' => (string) Str::uuid(),
            'venue_id' => $this->sala->id,
            'table_id' => $this->mesa->id,
            'channel' => 'table',
            'guest_count' => 2,
            'items' => [[
                'local_uuid' => (string) Str::uuid(),
                'product_id' => $this->prato->id,
                'quantity' => 2,
                'unit_price' => 5000,
            ]],
            'confirmar' => true,
        ], $extra);
    }

    private function enviar(array $corpo)
    {
        return $this->postJson('/api/v1/restaurant/offline/comanda', $corpo);
    }

    // ── O caminho normal ──────────────────────────────────────────────

    public function test_repoe_a_comanda_com_numero_do_servidor(): void
    {
        $resposta = $this->enviar($this->comanda());

        $resposta->assertStatus(201)->assertJson(['success' => true]);

        $comanda = Order::withoutGlobalScopes()->find($resposta->json('id'));

        $this->assertSame($this->tenant->id, $comanda->tenant_id);
        $this->assertSame($this->mesa->id, $comanda->table_id);
        // O número é do SERVIDOR: offline não há sequência que se possa inventar.
        $this->assertStringStartsWith('CMD-', $comanda->order_number);
        $this->assertCount(1, $comanda->items);
        $this->assertEqualsWithDelta(11400, (float) $comanda->grand_total, 0.01);   // 10000 + 14%
    }

    /** A mesa fica ocupada, como ficaria se a comanda tivesse sido aberta online. */
    public function test_a_mesa_fica_ocupada(): void
    {
        $this->enviar($this->comanda())->assertStatus(201);

        $this->assertSame('waiting_kitchen', $this->mesa->fresh()->status);
    }

    // ── Reenviar ──────────────────────────────────────────────────────

    /**
     * O ENSAIO QUE MAIS CUSTA. Uma rede aos soluços reenvia — é o caso normal.
     * Se o reenvio criasse uma segunda comanda, a mesa ficava com a conta a
     * dobrar e alguém pagava duas vezes o mesmo jantar.
     */
    public function test_reenviar_nao_cria_uma_segunda_comanda(): void
    {
        $corpo = $this->comanda();

        $primeiro = $this->enviar($corpo)->assertStatus(201);
        $segundo = $this->enviar($corpo)->assertStatus(201);

        $this->assertSame($primeiro->json('id'), $segundo->json('id'));
        $this->assertSame(1, Order::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /** E os ARTIGOS também não. Foi para isto que o artigo ganhou identificador. */
    public function test_reenviar_nao_duplica_os_artigos(): void
    {
        $corpo = $this->comanda();

        $this->enviar($corpo);
        $this->enviar($corpo);

        $comanda = Order::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();

        $this->assertSame(1, OrderItem::withoutGlobalScopes()->where('order_id', $comanda->id)->count());
        $this->assertEqualsWithDelta(11400, (float) $comanda->fresh()->grand_total, 0.01);
    }

    /** Artigos novos juntam-se à comanda que já existe, sem repetir os antigos. */
    public function test_reenviar_com_artigos_novos_junta_so_os_novos(): void
    {
        $corpo = $this->comanda();
        $this->enviar($corpo)->assertStatus(201);

        $corpo['items'][] = [
            'local_uuid' => (string) Str::uuid(),
            'product_id' => $this->prato->id,
            'quantity' => 1,
            'unit_price' => 5000,
        ];

        $this->enviar($corpo)->assertStatus(201);

        $comanda = Order::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();

        $this->assertSame(2, OrderItem::withoutGlobalScopes()->where('order_id', $comanda->id)->count());
    }

    // ── A mesa ocupada ────────────────────────────────────────────────

    /**
     * Dois empregados abrem a mesma mesa sem rede. O segundo a sincronizar não
     * pode perder a comanda: a comida foi servida e o dinheiro foi recebido.
     * Abre ao balcão, com nota, e o aviso volta para o aparelho.
     */
    public function test_mesa_ocupada_abre_ao_balcao_e_nada_se_perde(): void
    {
        $this->enviar($this->comanda())->assertStatus(201);

        $resposta = $this->enviar($this->comanda())->assertStatus(201);

        $segunda = Order::withoutGlobalScopes()->find($resposta->json('id'));

        $this->assertNull($segunda->table_id, 'a segunda comanda não pode ficar na mesa de outra');
        $this->assertSame('counter', $segunda->channel);
        $this->assertStringContainsString('Mesa 1', $segunda->notes);
        $this->assertNotEmpty($resposta->json('avisos'), 'quem está na sala tem de saber que a mesa mudou');
        // E os artigos vieram todos.
        $this->assertCount(1, $segunda->items);
    }

    // ── Fechar a conta ────────────────────────────────────────────────

    public function test_fecha_a_conta_e_emite_o_documento(): void
    {
        $resposta = $this->enviar($this->comanda([
            'checkout' => [
                'document_type' => 'FR',
                'payment_method_id' => $this->metodo->id,
            ],
        ]));

        $resposta->assertStatus(201);

        $this->assertNotNull($resposta->json('invoice.invoice_number'));
        $this->assertEqualsWithDelta(11400, (float) $resposta->json('invoice.total'), 0.01);
        $this->assertSame('billed', Order::withoutGlobalScopes()->find($resposta->json('id'))->status);
    }

    /**
     * Reenviar uma conta já fechada não pode emitir uma SEGUNDA factura — seria
     * receita a dobrar nos livros e um número fiscal gasto por nada. A chave de
     * idempotência sai da comanda, e é por isso que se repete igual.
     */
    public function test_reenviar_a_conta_fechada_devolve_a_mesma_factura(): void
    {
        $corpo = $this->comanda([
            'checkout' => ['document_type' => 'FR', 'payment_method_id' => $this->metodo->id],
        ]);

        $primeiro = $this->enviar($corpo)->assertStatus(201);
        $segundo = $this->enviar($corpo)->assertStatus(201);

        $this->assertSame($primeiro->json('invoice.id'), $segundo->json('invoice.id'));
        $this->assertSame(
            1,
            \App\Models\Invoicing\SalesInvoice::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant->id)->count()
        );
    }

    /**
     * Sem rede não há cozinha: o ecrã da cozinha é outro aparelho. Uma comanda
     * que traz recebimento sobe já servida — porque foi isso que aconteceu — e
     * o histórico regista-o em vez de fingir que passou pela cozinha.
     */
    public function test_a_conta_paga_sem_rede_regista_que_foi_servida_offline(): void
    {
        $resposta = $this->enviar($this->comanda([
            'confirmar' => false,
            'checkout' => ['document_type' => 'FR', 'payment_method_id' => $this->metodo->id],
        ]));

        $resposta->assertStatus(201);

        $this->assertDatabaseHas('restaurant_order_events', [
            'order_id' => $resposta->json('id'),
            'event' => 'order_served_offline',
        ]);
    }

    /**
     * O preço mudou entre a venda e a sincronização. O catálogo manda — como no
     * POS online — mas o recebimento reparte-se pelo total do documento em vez
     * de rebentar, e fica um aviso. Uma conta já paga não pode ficar presa numa
     * fila por causa de uma diferença de preço.
     */
    public function test_preco_alterado_avisa_e_o_recebimento_acompanha_o_documento(): void
    {
        $this->prato->update(['price' => 6000]);

        $resposta = $this->enviar($this->comanda([
            'checkout' => [
                'document_type' => 'FR',
                'payments' => [['payment_method_id' => $this->metodo->id, 'amount' => 11400]],
            ],
        ]));

        $resposta->assertStatus(201);

        $avisos = implode(' | ', $resposta->json('avisos'));

        $this->assertStringContainsString('Muamba', $avisos);
        // 2 × 6000 = 12000, +14% = 13680: é o documento que manda no valor.
        $this->assertEqualsWithDelta(13680, (float) $resposta->json('invoice.total'), 0.01);
        $this->assertDatabaseHas('treasury_transactions', [
            'invoice_id' => $resposta->json('invoice.id'),
            'amount' => 13680.00,
        ]);
    }

    // ── As portas fechadas ────────────────────────────────────────────

    /** Sem o módulo não entra nada — nem que se escreva o endereço à mão. */
    public function test_empresa_sem_o_modulo_e_recusada(): void
    {
        $modulo = \App\Models\Module::where('slug', 'restaurant')->first();
        $this->tenant->modules()->updateExistingPivot($modulo->id, ['is_active' => false]);

        $this->enviar($this->comanda())->assertStatus(403);

        $this->assertSame(0, Order::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * Sem turno aberto o servidor recusa — e recusa com 422, não com 500: a
     * fila do PWA distingue-os, e um 500 fica a ser tentado para sempre.
     */
    public function test_sem_turno_aberto_e_recusada_com_motivo(): void
    {
        PosShift::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();

        $resposta = $this->enviar($this->comanda())->assertStatus(422);

        $this->assertStringContainsString('turno', mb_strtolower($resposta->json('error')));
    }

    /**
     * Quando a empresa exige ficha técnica, um prato sem ela faz a comanda
     * INTEIRA parar — com o motivo. Facturar sem esse prato seria receita por
     * cobrar e stock por descontar, em silêncio.
     */
    public function test_prato_sem_ficha_tecnica_para_a_comanda_toda(): void
    {
        RestaurantSettings::forTenant($this->tenant->id)->update(['require_recipe_for_products' => true]);

        $resposta = $this->enviar($this->comanda())->assertStatus(422);

        $this->assertStringContainsString('ficha técnica', $resposta->json('error'));
    }

    /** Um artigo sem identificador é recusado: sem ele o reenvio duplicava. */
    public function test_artigo_sem_identificador_e_recusado(): void
    {
        $corpo = $this->comanda();
        unset($corpo['items'][0]['local_uuid']);

        $this->enviar($corpo)->assertStatus(422);
    }

    /** Uma sala de outra empresa nunca serve para abrir comandas nesta. */
    public function test_sala_de_outra_empresa_e_recusada(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@x.ao', 'is_active' => true,
        ]);

        $salaAlheia = Venue::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => 'X', 'name' => 'Sala Alheia', 'is_active' => true,
        ]);

        $this->enviar($this->comanda(['venue_id' => $salaAlheia->id, 'table_id' => null]))
            ->assertStatus(422);
    }

    /**
     * O operador que entrou por PIN é quem fica na comanda — mas só se
     * pertencer a esta empresa. Um id inventado cai no utilizador da sessão em
     * vez de escrever nos livros em nome de um estranho.
     */
    public function test_operador_de_outra_empresa_cai_no_utilizador_da_sessao(): void
    {
        $estranho = User::create([
            'name' => 'De Fora', 'email' => 'fora'.uniqid().'@x.ao',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);

        $resposta = $this->enviar($this->comanda(['operator_id' => $estranho->id]))->assertStatus(201);

        $this->assertSame(
            $this->user->id,
            Order::withoutGlobalScopes()->find($resposta->json('id'))->waiter_id
        );
    }

    /**
     * O operador certo — activo e ligado a esta empresa — fica registado como
     * o empregado da comanda, e não quem por acaso sincronizou o aparelho.
     */
    public function test_operador_da_empresa_fica_registado_na_comanda(): void
    {
        $colega = User::create([
            'name' => 'Colega', 'email' => 'colega'.uniqid().'@x.ao',
            'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $colega->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_active' => true]]);
        $this->abrirTurno($colega->id);

        $resposta = $this->enviar($this->comanda(['operator_id' => $colega->id]))->assertStatus(201);

        $this->assertSame(
            $colega->id,
            Order::withoutGlobalScopes()->find($resposta->json('id'))->waiter_id
        );
    }
}
