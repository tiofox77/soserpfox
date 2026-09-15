<?php

namespace Tests\Feature\Workshop;

use App\Models\Workshop\CourtesyCar;
use App\Models\Workshop\CourtesyLoan;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use Tests\TenantTestCase;

/**
 * AS VIATURAS DE CORTESIA (15/09/2026, OF-17).
 */
class ViaturasDeCortesiaTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function viatura(array $mais = []): CourtesyCar
    {
        return CourtesyCar::create($mais + ['tenant_id' => $this->tenant->id, 'plate' => 'LD-' . random_int(10, 99) . '-' . random_int(10, 99) . '-CT', 'brand' => 'Kia', 'model' => 'Picanto', 'mileage' => 42000, 'fuel_level' => 6, 'status' => 'disponivel']);
    }

    private function ordem(): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LD' . random_int(10, 99) . random_int(10, 99) . 'OR', 'vehicle_number' => 'VEH-' . substr(uniqid(), -6), 'owner_name' => 'Joana Silva', 'owner_phone' => '923999000', 'brand' => 'VW', 'model' => 'Polo', 'status' => 'active']);

        return WorkOrder::create(['order_number' => 'OS-CT-' . substr(uniqid(), -6), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'Motor', 'status' => 'in_progress', 'priority' => 'normal']);
    }

    public function test_emprestar_e_receber_com_km_e_combustivel_ligado_a_ordem(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $carro = $this->viatura();
        $o = $this->ordem();

        $this->postJson(self::API . "/cortesia/{$carro->id}/emprestar", ['condutor' => 'Joana Silva', 'km_saida' => 41000])->assertStatus(422)->assertJsonValidationErrors('km_saida');
        $this->postJson(self::API . "/cortesia/{$carro->id}/emprestar", [
            'ordem_id' => $o->id, 'condutor' => 'Joana Silva', 'telefone' => '923999000', 'carta' => 'LA-12345', 'km_saida' => 42010, 'combustivel' => 6, 'devolver_ate' => now()->addDays(3)->toDateTimeString(),
        ])->assertOk();

        $l = CourtesyLoan::where('courtesy_car_id', $carro->id)->firstOrFail();
        $this->assertSame($o->id, $l->work_order_id);
        $this->assertTrue($o->history()->where('description', 'like', 'Viatura de cortesia%emprestada a Joana Silva%')->exists());

        // Emprestada: não sai outra vez; aparece no quadro e na entrega da ordem.
        $this->postJson(self::API . "/cortesia/{$carro->id}/emprestar", ['condutor' => 'Outro', 'km_saida' => 42010])->assertStatus(422);
        $q = $this->getJson(self::API . '/cortesia')->assertOk();
        $cartao = collect($q->json('data'))->firstWhere('id', $carro->id);
        $this->assertSame('emprestada', $cartao['estado']);
        $this->assertSame(1, $q->json('contas.emprestadas'));
        $this->assertSame($l->id, $this->getJson(self::API . "/ordens/{$o->id}/entrega")->json('cortesia.id'));

        $this->postJson(self::API . "/cortesia/emprestimos/{$l->id}/devolver", ['km_entrada' => 42000])->assertStatus(422)->assertJsonValidationErrors('km_entrada');
        $r = $this->postJson(self::API . "/cortesia/emprestimos/{$l->id}/devolver", ['km_entrada' => 42180, 'combustivel' => 3, 'danos' => 'Risco no pára-choques'])->assertOk();
        $this->assertStringContainsString('170 km', $r->json('message'));
        $this->assertStringContainsString('menos combustível', $r->json('message'));

        $carro->refresh();
        $this->assertSame([42180, 3], [$carro->mileage, $carro->fuel_level]);
        $this->assertSame('disponivel', collect($this->getJson(self::API . '/cortesia')->json('data'))->firstWhere('id', $carro->id)['estado']);
        $this->assertNull($this->getJson(self::API . "/ordens/{$o->id}/entrega")->json('cortesia'));
        $this->postJson(self::API . "/cortesia/emprestimos/{$l->id}/devolver", ['km_entrada' => 42200])->assertStatus(422);
    }

    public function test_em_manutencao_nao_se_empresta_atrasos_contam_e_so_quem_edita(): void
    {
        $this->comPermissoes('workshop.work-orders.view');
        $oficina = $this->viatura(['status' => 'manutencao']);
        $livre = $this->viatura();

        $this->postJson(self::API . "/cortesia/{$livre->id}/emprestar", ['condutor' => 'X', 'km_saida' => 42000])->assertForbidden();

        $this->comPermissoes('workshop.work-orders.edit');
        $this->postJson(self::API . "/cortesia/{$oficina->id}/emprestar", ['condutor' => 'X', 'km_saida' => 42000])->assertStatus(422);

        CourtesyLoan::create(['tenant_id' => $this->tenant->id, 'courtesy_car_id' => $livre->id, 'driver_name' => 'Atrasado', 'out_at' => now()->subDays(5), 'expected_return_at' => now()->subDay(), 'mileage_out' => 42000]);
        $q = $this->getJson(self::API . '/cortesia')->assertOk();
        $this->assertSame(1, $q->json('contas.atrasadas'));
        $this->assertTrue(collect($q->json('data'))->firstWhere('id', $livre->id)['emprestimo']['atrasado']);

        // O catálogo não deixa apagar uma viatura emprestada.
        $this->deleteJson("/api/v1/invoicing/react/catalogos/viaturas-de-cortesia/{$livre->id}")->assertStatus(422);
    }
}
