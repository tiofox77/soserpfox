<?php

namespace Tests\Feature\Workshop;

use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use Tests\TenantTestCase;

/**
 * O ORÇAMENTO APROVADO PELO CLIENTE (15/09/2026, OF-03).
 */
class AprovacaoDoOrcamentoTest extends TenantTestCase
{
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private const ORDENS = '/api/v1/invoicing/react/oficina/ordens';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function ordem(): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LDA-' . random_int(10, 99) . '-' . random_int(10, 99) . '-AP', 'vehicle_number' => 'VEH-' . substr(uniqid(), -5), 'owner_name' => 'João Manuel', 'owner_phone' => '923456789', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active']);

        return WorkOrder::create(['order_number' => 'OS-AP-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'Barulho.', 'diagnosis' => 'Pastilhas gastas.', 'status' => 'in_progress', 'priority' => 'normal']);
    }

    private function linha(WorkOrder $o, string $nome, float $preco, bool $proposta): WorkOrderItem
    {
        $this->postJson(self::ORDENS . "/{$o->id}/linhas", ['type' => 'service', 'name' => $nome, 'quantity' => 1, 'unit_price' => $preco, 'precisa_aprovacao' => $proposta])->assertSuccessful();

        return WorkOrderItem::where('work_order_id', $o->id)->where('name', $nome)->firstOrFail();
    }

    public function test_a_linha_proposta_fica_a_espera_e_fora_dos_totais(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();

        $this->linha($o, 'Diagnóstico', 5000, false);
        $proposta = $this->linha($o, 'Trocar pastilhas', 30000, true);

        $this->assertSame('pending', $proposta->approval);
        $this->assertEqualsWithDelta(5000, (float) $o->fresh()->total, 0.01, 'a linha à espera não conta');

        $ficha = $this->getJson(self::ORDENS . "/{$o->id}")->assertOk();
        $this->assertSame(1, $ficha->json('data.aprovacao.a_espera'));
        $this->assertEqualsWithDelta(30000, $ficha->json('data.aprovacao.valor_a_espera'), 0.01);
        $this->assertNull($ficha->json('data.aprovacao.link'));
    }

    public function test_nao_se_factura_com_linhas_a_espera(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit', 'invoicing.sales.invoices.create');
        $o = $this->ordem();
        $this->linha($o, 'Trocar pastilhas', 30000, true);

        $this->postJson(self::ORDENS . "/{$o->id}/facturar")->assertStatus(422)->assertJsonFragment(['message' => 'Há 1 linha à espera da aprovação do cliente. Registe a decisão antes de facturar.']);
    }

    public function test_o_cliente_decide_pelo_link_e_assina(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();
        $this->linha($o, 'Diagnóstico', 5000, false);
        $a = $this->linha($o, 'Trocar pastilhas', 30000, true);
        $b = $this->linha($o, 'Trocar discos', 45000, true);

        $link = $this->postJson(self::ORDENS . "/{$o->id}/aprovacao")->assertOk()->json('data.link');
        $token = $o->fresh()->approval_token;
        $this->assertStringEndsWith("/oficina/aprovar/$token", $link);

        // Pedir outra vez não troca o link que o cliente já recebeu.
        $this->postJson(self::ORDENS . "/{$o->id}/aprovacao")->assertOk();
        $this->assertSame($token, $o->fresh()->approval_token);

        auth()->logout();

        $this->get("/oficina/aprovar/$token")->assertOk()->assertSee('data-ecra="oficina/aprovar-orcamento"', false);
        $ver = $this->getJson("/oficina/aprovar/$token/dados")->assertOk();
        $this->assertTrue($ver->json('aberto'));
        $this->assertCount(3, $ver->json('linhas'));
        $this->assertSame('Pastilhas gastas.', $ver->json('ordem.diagnostico'));

        // Tem de decidir as duas.
        $this->postJson("/oficina/aprovar/$token", ['decisoes' => [$a->id => 'approved'], 'nome' => 'João', 'assinatura' => self::PNG])->assertStatus(422);
        // Uma linha que não está à espera não entra.
        $this->postJson("/oficina/aprovar/$token", ['decisoes' => [$a->id => 'approved', $b->id => 'declined', 999999 => 'approved'], 'nome' => 'João', 'assinatura' => self::PNG])->assertStatus(422);
        $this->postJson("/oficina/aprovar/$token", ['decisoes' => [$a->id => 'approved', $b->id => 'declined'], 'nome' => 'João', 'assinatura' => 'nada'])->assertStatus(422);

        $this->postJson("/oficina/aprovar/$token", ['decisoes' => [$a->id => 'approved', $b->id => 'declined'], 'nome' => 'João Manuel', 'assinatura' => self::PNG])->assertOk();

        $this->assertSame(['approved', 'declined'], [$a->fresh()->approval, $b->fresh()->approval]);
        $this->assertStringContainsString('João Manuel', $a->fresh()->approval_by);
        $ordem = $o->fresh();
        $this->assertEqualsWithDelta(35000, (float) $ordem->total, 0.01, 'diagnóstico + pastilhas; os discos recusados ficam de fora');
        $this->assertSame('João Manuel', $ordem->approval_signed_by);
        $this->assertTrue($ordem->history()->where('description', 'like', '%respondeu ao orçamento%')->exists());

        // Respondido, o link fecha.
        $depois = $this->getJson("/oficina/aprovar/$token/dados");
        $this->assertFalse($depois->json('aberto'));
        $this->postJson("/oficina/aprovar/$token", ['decisoes' => [$a->id => 'declined'], 'nome' => 'Outro', 'assinatura' => self::PNG])->assertStatus(422);
        $this->assertSame('approved', $a->fresh()->approval, 'uma linha já decidida não muda pelo link');
    }

    public function test_link_expirado_ou_anulado_nao_serve(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();
        $a = $this->linha($o, 'Trocar pastilhas', 30000, true);

        $this->postJson(self::ORDENS . "/{$o->id}/aprovacao")->assertOk();
        $token = $o->fresh()->approval_token;
        $o->forceFill(['approval_expires_at' => now()->subMinute()])->save();

        auth()->logout();
        $ver = $this->getJson("/oficina/aprovar/$token/dados")->assertOk();
        $this->assertFalse($ver->json('aberto'));
        $this->assertStringContainsString('expirou', $ver->json('motivo'));
        $this->postJson("/oficina/aprovar/$token", ['decisoes' => [$a->id => 'approved'], 'nome' => 'João', 'assinatura' => self::PNG])->assertStatus(422);

        $this->getJson('/oficina/aprovar/' . str_repeat('a', 48) . '/dados')->assertNotFound();
    }

    public function test_a_oficina_regista_a_decisao_a_mao(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $o = $this->ordem();
        $a = $this->linha($o, 'Trocar pastilhas', 30000, true);

        $this->postJson(self::ORDENS . "/{$o->id}/linhas/{$a->id}/aprovacao", ['decisao' => 'approved'])->assertOk();
        $this->assertSame('approved', $a->fresh()->approval);
        $this->assertStringContainsString('Oficina', $a->fresh()->approval_by);
        $this->assertEqualsWithDelta(30000, (float) $o->fresh()->total, 0.01);

        $this->postJson(self::ORDENS . "/{$o->id}/linhas/{$a->id}/aprovacao", ['decisao' => 'declined'])->assertOk();
        $this->assertEqualsWithDelta(0, (float) $o->fresh()->total, 0.01);

        $this->postJson(self::ORDENS . "/{$o->id}/linhas/{$a->id}/aprovacao", ['decisao' => 'talvez'])->assertStatus(422);
    }

    public function test_as_pecas_a_espera_nao_saem_do_stock(): void
    {
        $o = $this->ordem();
        WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'part', 'name' => 'Filtro', 'quantity' => 1, 'unit_price' => 1000, 'approval' => 'pending']);
        WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'part', 'name' => 'Óleo', 'quantity' => 4, 'unit_price' => 2000, 'approval' => 'approved']);

        $this->assertSame(['Óleo'], $o->parts()->pluck('name')->all());
    }
}
