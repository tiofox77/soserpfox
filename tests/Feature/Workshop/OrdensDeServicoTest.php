<?php

namespace Tests\Feature\Workshop;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use App\Models\Workshop\Mechanic;
use App\Models\Workshop\Service;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use App\Models\Workshop\WorkOrderItem;
use Tests\TenantTestCase;

/**
 * AS ORDENS DE SERVIÇO — e a porta única da mudança de estado.
 *
 * A REGRA QUE MANDA NESTE ECRÃ, e a razão de ser destes ensaios:
 *
 *   Passar uma ordem a «Concluída» ou a «Entregue» DESCONTA as peças do stock.
 *   Anular DEVOLVE-AS. O ecrã em Livewire teve o buraco de gravar o estado
 *   pelo formulário de edição sem passar por aí — as peças ficavam no stock
 *   para sempre e o inventário passava a mentir.
 *
 *   Ao migrar para React havia DUAS rotas capazes de mudar o estado: `PUT` da
 *   ficha e `POST /estado`. Estes ensaios provam que as duas entram pela mesma
 *   porta, que é o que impede o buraco de voltar.
 */
class OrdensDeServicoTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina/ordens';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    /* ─── A lista e a ficha ───────────────────────────────────────────── */

    public function test_a_lista_conta_a_casa_e_nao_a_pagina(): void
    {
        $this->comPermissoes('workshop.work-orders.view');

        $v = $this->viatura();
        $this->ordem($v, ['status' => 'pending']);
        $this->ordem($v, ['status' => 'in_progress']);
        $this->ordem($v, ['status' => 'completed']);

        // Com o filtro posto, a lista mostra uma — e o resumo continua a
        // contar a casa: «3 em curso» com filtros é outra pergunta.
        $r = $this->getJson(self::API . '?estado=in_progress')->assertOk();

        $this->assertCount(1, $r->json('data'));
        $this->assertSame(3, $r->json('resumo.total'));
        $this->assertSame(1, $r->json('resumo.em_aberto'));
        $this->assertSame(1, $r->json('resumo.em_curso'));
        $this->assertSame(1, $r->json('resumo.concluidas'));
    }

    public function test_a_procura_apanha_o_numero_a_matricula_e_o_dono(): void
    {
        $this->comPermissoes('workshop.work-orders.view');

        $meu = $this->viatura(['plate' => 'LD-12-34-AA', 'owner_name' => 'Dona Ana']);
        $outro = $this->viatura(['plate' => 'LD-99-99-ZZ', 'owner_name' => 'Sr. Bento']);

        $this->ordem($meu);
        $this->ordem($outro);

        foreach (['LD-12-34', 'Dona Ana'] as $termo) {
            $r = $this->getJson(self::API . '?procura=' . urlencode($termo))->assertOk();

            $this->assertCount(1, $r->json('data'), "a procura por «{$termo}» falhou");
            $this->assertSame('LD-12-34-AA', $r->json('data.0.matricula'));
        }
    }

    /**
     * A FICHA TRAZ A VIATURA POR EXTENSO.
     *
     * O `+` de arrays em PHP mantém a chave da ESQUERDA: com o mesmo nome da
     * coluna da lista, este bloco era descartado em silêncio e o separador de
     * informação abria sem viatura nenhuma.
     */
    public function test_a_ficha_traz_a_viatura_as_linhas_e_o_historico(): void
    {
        $this->comPermissoes('workshop.work-orders.view');

        $ordem = $this->ordem($this->viatura(['plate' => 'LD-12-34-AA', 'brand' => 'Toyota', 'model' => 'Hilux']));

        WorkOrderItem::create([
            'work_order_id' => $ordem->id, 'type' => 'service', 'name' => 'Alinhamento',
            'quantity' => 1, 'unit_price' => 9000,
        ]);

        $r = $this->getJson(self::API . "/{$ordem->id}")->assertOk();

        $this->assertSame('LD-12-34-AA', $r->json('data.viatura_ficha.matricula'));
        $this->assertSame('Toyota', $r->json('data.viatura_ficha.marca'));
        // E a coluna da lista continua lá, com o texto curto.
        $this->assertSame('Toyota Hilux', $r->json('data.viatura'));
        $this->assertCount(1, $r->json('data.linhas'));
        $this->assertEquals(9000, $r->json('data.linhas.0.subtotal'));
    }

    /* ─── Gravar ──────────────────────────────────────────────────────── */

    public function test_criar_uma_ordem_gera_o_numero_e_deixa_rasto(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.create');

        $v = $this->viatura();

        $this->postJson(self::API, [
            'vehicle_id' => $v->id,
            'received_at' => now()->toDateTimeString(),
            'problem_description' => 'Faz um barulho ao travar.',
            'status' => 'pending',
            'priority' => 'high',
        ])->assertCreated();

        $ordem = WorkOrder::first();

        $this->assertStringStartsWith('OS-', $ordem->order_number);
        $this->assertSame('high', $ordem->priority);

        // O HISTÓRICO passa a ter o que é preciso: o separador existia e só
        // registava anexos.
        $this->assertTrue(
            WorkOrderHistory::where('work_order_id', $ordem->id)
                ->where('action', WorkOrderHistory::ACTION_CREATED)->exists()
        );
    }

    /** Os campos opcionais vazios chegam como '' e não como nulo. */
    public function test_uma_ordem_sem_mecanico_nem_data_agendada_grava(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.create');

        $this->postJson(self::API, [
            'vehicle_id' => $this->viatura()->id,
            'mechanic_id' => '',
            'scheduled_for' => '',
            'received_at' => now()->toDateTimeString(),
            'problem_description' => 'Não pega.',
            'status' => 'pending',
            'priority' => 'normal',
        ])->assertCreated();

        $ordem = WorkOrder::first();

        $this->assertNull($ordem->mechanic_id);
        $this->assertNull($ordem->scheduled_for);
    }

    /** Uma viatura de outra empresa não entra numa ordem desta. */
    public function test_a_viatura_tem_de_ser_desta_empresa(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.create');

        $outra = \App\Models\Tenant::create([
            'name' => 'Oficina do Lado', 'slug' => 'lado-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'lado' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        $alheia = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'plate' => 'LD-00-00-XX',
            'vehicle_number' => 'VEH-LADO', 'owner_name' => 'Dono do Lado',
            'brand' => 'Alheia', 'model' => 'X',
        ]);

        $this->postJson(self::API, [
            'vehicle_id' => $alheia->id,
            'received_at' => now()->toDateTimeString(),
            'problem_description' => 'Não pega.',
            'status' => 'pending', 'priority' => 'normal',
        ])->assertStatus(422);

        $this->assertSame(0, WorkOrder::count());
    }

    /* ─── A porta única do estado ─────────────────────────────────────── */

    /**
     * PASSAR A «CONCLUÍDA» DESCONTA AS PEÇAS.
     */
    public function test_concluir_desconta_as_pecas_do_stock(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        [$ordem, $artigo] = $this->ordemComPeca(quantidade: 3, emArmazem: 10);

        $this->postJson(self::API . "/{$ordem->id}/estado", ['estado' => 'completed'])
            ->assertOk()
            ->assertJsonPath('falhas', []);

        $this->assertSame(7.0, (float) Stock::where('product_id', $artigo->id)->value('quantity'));
        $this->assertSame('completed', $ordem->fresh()->status);
    }

    /**
     * E O FORMULÁRIO DE EDIÇÃO FAZ O MESMO — é a mesma porta.
     *
     * Este é o ensaio que impede o buraco de voltar: se o `PUT` da ficha
     * gravasse o estado por fora, as peças ficavam no stock para sempre.
     */
    public function test_gravar_a_ficha_com_outro_estado_tambem_desconta(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        [$ordem, $artigo] = $this->ordemComPeca(quantidade: 2, emArmazem: 10);

        $this->putJson(self::API . "/{$ordem->id}", [
            'vehicle_id' => $ordem->vehicle_id,
            'received_at' => $ordem->received_at->toDateTimeString(),
            'problem_description' => $ordem->problem_description,
            'status' => 'completed',
            'priority' => 'normal',
        ])->assertOk();

        $this->assertSame('completed', $ordem->fresh()->status);
        $this->assertSame(8.0, (float) Stock::where('product_id', $artigo->id)->value('quantity'));
    }

    /** Anular devolve ao stock o que já tinha saído. */
    public function test_anular_devolve_as_pecas(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        [$ordem, $artigo] = $this->ordemComPeca(quantidade: 4, emArmazem: 10);

        $this->postJson(self::API . "/{$ordem->id}/estado", ['estado' => 'completed'])->assertOk();
        $this->assertSame(6.0, (float) Stock::where('product_id', $artigo->id)->value('quantity'));

        $this->postJson(self::API . "/{$ordem->id}/estado", ['estado' => 'cancelled'])->assertOk();
        $this->assertSame(10.0, (float) Stock::where('product_id', $artigo->id)->value('quantity'));
    }

    /**
     * QUANDO NÃO HÁ STOCK, DIZ-SE.
     *
     * Antes dizia sempre «Estoque baixado automaticamente», mesmo quando a
     * baixa rebentava por falta de stock e o erro só ia para o log: quem
     * estava no balcão ficava a acreditar que a peça tinha saído.
     */
    public function test_sem_stock_a_resposta_diz_que_a_peca_nao_saiu(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        [$ordem] = $this->ordemComPeca(quantidade: 50, emArmazem: 2);

        $r = $this->postJson(self::API . "/{$ordem->id}/estado", ['estado' => 'completed'])->assertOk();

        $this->assertNotEmpty($r->json('falhas'), 'a peça que não saiu tem de vir na resposta');
        $this->assertStringContainsString('NÃO', $r->json('message'));
        // A ordem fica concluída na mesma — o carro saiu da oficina.
        $this->assertSame('completed', $ordem->fresh()->status);
    }

    /** A mudança de estado fica escrita no histórico, com o antes e o depois. */
    public function test_a_mudanca_de_estado_fica_no_historico(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $ordem = $this->ordem($this->viatura());

        $this->postJson(self::API . "/{$ordem->id}/estado", ['estado' => 'in_progress'])->assertOk();

        $evento = WorkOrderHistory::where('work_order_id', $ordem->id)
            ->where('action', WorkOrderHistory::ACTION_STATUS_CHANGED)->first();

        $this->assertNotNull($evento);
        $this->assertSame('pending', $evento->old_value);
        $this->assertSame('in_progress', $evento->new_value);
    }

    /**
     * UMA MUDANÇA, UMA LINHA.
     *
     * O `WorkOrderObserver` já escrevia a mudança de estado; a migração
     * escreveu-a outra vez no serviço e o histórico passou a ter DUAS linhas
     * por cada mudança. Apareceu na primeira prova no browser, e fica aqui
     * para não voltar.
     */
    public function test_a_mudanca_de_estado_nao_se_escreve_a_dobrar(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $ordem = $this->ordem($this->viatura());

        $this->postJson(self::API . "/{$ordem->id}/estado", ['estado' => 'in_progress'])->assertOk();

        $this->assertSame(1, WorkOrderHistory::where('work_order_id', $ordem->id)
            ->where('action', WorkOrderHistory::ACTION_STATUS_CHANGED)->count());
    }

    /**
     * E O RÓTULO DO ESTADO NO HISTÓRICO É O DA LISTA ÚNICA.
     *
     * O observador tinha a sua própria cópia dos rótulos: dizia «Em Andamento»
     * onde o resto do produto diz «Em curso», e não conhecia o `scheduled` —
     * uma ordem que passasse a Agendada ficava com o nome cru escrito.
     */
    public function test_o_historico_escreve_o_estado_por_extenso(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $ordem = $this->ordem($this->viatura());

        $this->postJson(self::API . "/{$ordem->id}/estado", ['estado' => 'scheduled'])->assertOk();

        $descricao = WorkOrderHistory::where('work_order_id', $ordem->id)
            ->where('action', WorkOrderHistory::ACTION_STATUS_CHANGED)->value('description');

        $this->assertStringContainsString(__('Agendada'), $descricao);
        $this->assertStringNotContainsString('scheduled', $descricao);
    }

    public function test_um_estado_inventado_e_recusado(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $ordem = $this->ordem($this->viatura());

        $this->postJson(self::API . "/{$ordem->id}/estado", ['estado' => 'inventado'])->assertStatus(422);
    }

    /* ─── As linhas ───────────────────────────────────────────────────── */

    /**
     * O SUBTOTAL É DO SERVIDOR, e não do pedido.
     *
     * Mandá-lo no corpo era o browser a escolher o número que a factura
     * depois usa.
     */
    public function test_o_subtotal_da_linha_e_calculado_e_nao_recebido(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $ordem = $this->ordem($this->viatura());

        $this->postJson(self::API . "/{$ordem->id}/linhas", [
            'type' => 'service',
            'name' => 'Alinhamento',
            'quantity' => 2,
            'unit_price' => 10000,
            'discount_percent' => 10,
            // Isto é uma tentativa: o servidor não pode aceitá-lo.
            'subtotal' => 1,
        ])->assertCreated();

        $linha = WorkOrderItem::first();

        $this->assertSame(18000.0, (float) $linha->subtotal);
        $this->assertSame(2000.0, (float) $linha->discount_amount);

        // E os totais da ordem seguem as linhas.
        $this->assertSame(18000.0, (float) $ordem->fresh()->labor_total);
        $this->assertSame(18000.0, (float) $ordem->fresh()->total);
    }

    public function test_tirar_uma_linha_refaz_os_totais(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $ordem = $this->ordem($this->viatura());

        $this->postJson(self::API . "/{$ordem->id}/linhas", [
            'type' => 'service', 'name' => 'Alinhamento', 'quantity' => 1, 'unit_price' => 9000,
        ])->assertCreated();

        $linha = WorkOrderItem::first();

        $this->deleteJson(self::API . "/{$ordem->id}/linhas/{$linha->id}")->assertOk();

        $this->assertSame(0.0, (float) $ordem->fresh()->total);
    }

    /** Uma linha de outra ordem não se apaga por esta. */
    public function test_uma_linha_de_outra_ordem_nao_se_tira(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $v = $this->viatura();
        $minha = $this->ordem($v);
        $outra = $this->ordem($v);

        $linha = WorkOrderItem::create([
            'work_order_id' => $outra->id, 'type' => 'service', 'name' => 'Alheia',
            'quantity' => 1, 'unit_price' => 100,
        ]);

        $this->deleteJson(self::API . "/{$minha->id}/linhas/{$linha->id}")->assertNotFound();

        $this->assertNotNull(WorkOrderItem::find($linha->id));
    }

    /** Um serviço de outra empresa não entra na linha. */
    public function test_um_servico_de_outra_empresa_nao_entra_na_linha(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $outra = \App\Models\Tenant::create([
            'name' => 'Oficina do Lado', 'slug' => 'lado-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'lado' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);

        $alheio = Service::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'service_code' => 'SRV-LADO', 'name' => 'Serviço do Lado',
        ]);

        $ordem = $this->ordem($this->viatura());

        $this->postJson(self::API . "/{$ordem->id}/linhas", [
            'type' => 'service', 'name' => 'Qualquer', 'quantity' => 1, 'unit_price' => 100,
            'service_id' => $alheio->id,
        ])->assertStatus(422);
    }

    /** O desconto da ordem refaz os totais — senão o total mente. */
    public function test_o_desconto_refaz_os_totais(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $ordem = $this->ordem($this->viatura());

        $this->postJson(self::API . "/{$ordem->id}/linhas", [
            'type' => 'service', 'name' => 'Alinhamento', 'quantity' => 1, 'unit_price' => 10000,
        ])->assertCreated();

        $this->putJson(self::API . "/{$ordem->id}/desconto", ['desconto' => 1500])->assertOk();

        $this->assertSame(8500.0, (float) $ordem->fresh()->total);
    }

    /* ─── Apagar ──────────────────────────────────────────────────────── */

    public function test_apagar_devolve_as_pecas_ao_stock(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit', 'workshop.work-orders.delete');

        [$ordem, $artigo] = $this->ordemComPeca(quantidade: 3, emArmazem: 10);

        $this->postJson(self::API . "/{$ordem->id}/estado", ['estado' => 'completed'])->assertOk();
        $this->assertSame(7.0, (float) Stock::where('product_id', $artigo->id)->value('quantity'));

        $this->deleteJson(self::API . "/{$ordem->id}")->assertOk();

        $this->assertSame(10.0, (float) Stock::where('product_id', $artigo->id)->value('quantity'));
        $this->assertNull(WorkOrder::find($ordem->id));
    }

    /**
     * UMA ORDEM JÁ FACTURADA NÃO SE APAGA: a factura é um documento fiscal e
     * ficaria a apontar para uma ordem que já não existe.
     */
    public function test_uma_ordem_facturada_nao_se_apaga(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.delete');

        // A factura tem de existir: a coluna tem chave estrangeira, e é ela
        // que faz a ordem ser um documento fiscal a que outro aponta.
        $factura = \App\Models\Invoicing\SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT OFICINA/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'total' => 1000,
            'created_by' => $this->user->id,
        ]);

        $ordem = $this->ordem($this->viatura(), ['invoice_id' => $factura->id]);

        $this->deleteJson(self::API . "/{$ordem->id}")->assertStatus(422);

        $this->assertNotNull(WorkOrder::find($ordem->id));
    }

    /* ─── As permissões ───────────────────────────────────────────────── */

    public function test_cada_verbo_pede_a_sua_permissao(): void
    {
        $this->comPermissoes('workshop.work-orders.view');

        $ordem = $this->ordem($this->viatura());

        $this->getJson(self::API)->assertOk();
        $this->getJson(self::API . '/opcoes')->assertOk()
            ->assertJsonPath('permissoes.pode_criar', false)
            ->assertJsonPath('permissoes.pode_editar', false);

        $this->postJson(self::API, [
            'vehicle_id' => $ordem->vehicle_id, 'received_at' => now()->toDateTimeString(),
            'problem_description' => 'x', 'status' => 'pending', 'priority' => 'normal',
        ])->assertForbidden();

        $this->putJson(self::API . "/{$ordem->id}", [])->assertForbidden();
        $this->postJson(self::API . "/{$ordem->id}/estado", ['estado' => 'completed'])->assertForbidden();
        $this->postJson(self::API . "/{$ordem->id}/linhas", [])->assertForbidden();
        $this->putJson(self::API . "/{$ordem->id}/desconto", ['desconto' => 0])->assertForbidden();
        $this->deleteJson(self::API . "/{$ordem->id}")->assertForbidden();
    }

    /**
     * FACTURAR PEDE A PERMISSÃO DA FACTURAÇÃO.
     *
     * Emitir uma factura é emitir um documento fiscal assinado: não é a mesma
     * decisão que mexer numa ordem de serviço.
     */
    public function test_facturar_pede_a_permissao_da_facturacao(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');

        $ordem = $this->ordem($this->viatura());

        $this->postJson(self::API . "/{$ordem->id}/facturar")->assertForbidden();

        $this->getJson(self::API . '/opcoes')->assertOk()
            ->assertJsonPath('permissoes.pode_facturar', false);
    }

    public function test_sem_permissao_de_ver_nada_abre(): void
    {
        $this->getJson(self::API)->assertForbidden();
        $this->getJson(self::API . '/opcoes')->assertForbidden();
        $this->getJson(self::API . '/artigos')->assertForbidden();
    }

    /* ─── As peças do catálogo ────────────────────────────────────────── */

    /**
     * O STOCK QUE SE MOSTRA É O DO ARMAZÉM DE ONDE A PEÇA VAI SAIR.
     *
     * Somando todos, uma peça com existência só noutro armazém aparecia
     * disponível e a baixa rebentava depois — já com a ordem dada por
     * concluída.
     */
    public function test_o_stock_da_peca_e_o_do_armazem_de_saida(): void
    {
        $this->comPermissoes('workshop.work-orders.view');

        $artigo = $this->artigo('Filtro de óleo');

        // No armazém por omissão há 4; noutro há 100.
        Stock::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $artigo->id,
            'warehouse_id' => $this->armazem->id, 'quantity' => 4, 'available_quantity' => 4, 'unit_cost' => 100,
        ]);

        $outro = \App\Models\Invoicing\Warehouse::create([
            'tenant_id' => $this->tenant->id, 'code' => 'A2', 'name' => 'Armazém Dois', 'is_active' => true,
        ]);

        Stock::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $artigo->id,
            'warehouse_id' => $outro->id, 'quantity' => 100, 'available_quantity' => 100, 'unit_cost' => 100,
        ]);

        $linha = collect($this->getJson(self::API . '/artigos?procura=Filtro')->assertOk()->json('data'))
            ->firstWhere('rotulo', 'Filtro de óleo');

        $this->assertEquals(4, $linha['stock'], 'o stock é o do armazém de saída, e não a soma');
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function viatura(array $campos = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'plate' => 'LD-' . random_int(10, 99) . '-' . random_int(10, 99) . '-AA',
            'vehicle_number' => 'VEH-' . substr(uniqid(), -5),
            'owner_name' => 'Dono', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active',
        ], $campos));
    }

    private function ordem(Vehicle $v, array $campos = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'order_number' => 'OS-' . substr(uniqid(), -6),
            'vehicle_id' => $v->id,
            'received_at' => now(),
            'problem_description' => 'Não pega.',
            'status' => 'pending',
            'priority' => 'normal',
        ], $campos));
    }

    private function artigo(string $nome): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => $nome,
            'code' => 'P-' . substr(uniqid(), -6),
            'price' => 5000, 'cost' => 3000, 'type' => 'produto', 'unit' => 'un',
            'is_active' => true, 'manage_stock' => true, 'stock_quantity' => 0,
        ]);
    }

    /** @return array{0: WorkOrder, 1: Product} */
    private function ordemComPeca(float $quantidade, float $emArmazem): array
    {
        $artigo = $this->artigo('Filtro de óleo');

        Stock::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $artigo->id,
            'warehouse_id' => $this->armazem->id,
            'quantity' => $emArmazem, 'available_quantity' => $emArmazem, 'unit_cost' => 3000,
        ]);

        $ordem = $this->ordem($this->viatura());

        WorkOrderItem::create([
            'work_order_id' => $ordem->id,
            'product_id' => $artigo->id,
            'type' => 'part',
            'name' => 'Filtro de óleo',
            'quantity' => $quantidade,
            'unit_price' => 5000,
        ]);

        return [$ordem->fresh(), $artigo];
    }
}
