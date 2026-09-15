<?php

namespace Tests\Feature\Workshop;

use App\Models\Tenant;
use App\Models\Workshop\Appointment;
use App\Models\Workshop\Bay;
use App\Models\Workshop\Mechanic;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use Tests\TenantTestCase;

/**
 * A AGENDA DA OFICINA (15/09/2026, OF-05).
 */
class AgendaDaOficinaTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina/agenda';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function amanha(string $hora): string
    {
        return now()->addDay()->format('Y-m-d') . 'T' . $hora;
    }

    public function test_a_oficina_recebe_os_lugares_e_marca_uma_viatura_da_casa(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.create');
        $v = Vehicle::create(['plate' => 'LDA2862RP', 'vehicle_number' => 'VEH-AG1', 'owner_name' => 'João Manuel', 'owner_phone' => '923456789', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active']);

        $r = $this->getJson(self::API . '?de=' . now()->format('Y-m-d') . '&ate=' . now()->addDays(6)->format('Y-m-d'))->assertOk();
        $this->assertCount(3, $r->json('lugares'));
        $elevador = $r->json('lugares.0.valor');

        $this->postJson(self::API, ['vehicle_id' => $v->id, 'bay_id' => $elevador, 'inicio' => $this->amanha('09:00'), 'duracao' => 90, 'service' => 'Revisão 10 000 km'])->assertCreated()
            ->assertJsonPath('data.matricula', 'LDA2862RP')->assertJsonPath('data.fim', now()->addDay()->format('Y-m-d') . 'T10:30')->assertJsonPath('data.cliente', 'João Manuel');

        $lista = $this->getJson(self::API . '?de=' . now()->format('Y-m-d') . '&ate=' . now()->addDays(6)->format('Y-m-d'))->assertOk();
        $this->assertCount(1, $lista->json('marcacoes'));
    }

    public function test_o_mesmo_elevador_e_o_mesmo_mecanico_nao_se_marcam_duas_vezes(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.create', 'workshop.work-orders.edit');
        Bay::garantirCatalogo($this->tenant->id);
        $elevador = Bay::where('tenant_id', $this->tenant->id)->orderBy('sort_order')->first();
        $outro = Bay::where('tenant_id', $this->tenant->id)->orderBy('sort_order')->skip(1)->first();
        $m = Mechanic::create(['tenant_id' => $this->tenant->id, 'name' => 'Carlos', 'phone' => '923000000', 'is_active' => true]);

        $base = ['plate' => 'ld-11-22-ab', 'customer_name' => 'Maria', 'service' => 'Travões'];
        $primeira = $this->postJson(self::API, $base + ['bay_id' => $elevador->id, 'mechanic_id' => $m->id, 'inicio' => $this->amanha('09:00'), 'duracao' => 60])->assertCreated()->json('data.id');

        $this->postJson(self::API, $base + ['bay_id' => $elevador->id, 'inicio' => $this->amanha('09:30'), 'duracao' => 60])->assertStatus(422)->assertJsonValidationErrors('bay_id');
        $this->postJson(self::API, $base + ['bay_id' => $outro->id, 'mechanic_id' => $m->id, 'inicio' => $this->amanha('09:45'), 'duracao' => 30])->assertStatus(422)->assertJsonValidationErrors('mechanic_id');
        // Encostada (acaba quando a outra começa) não choca.
        $this->postJson(self::API, $base + ['bay_id' => $elevador->id, 'inicio' => $this->amanha('10:00'), 'duracao' => 60])->assertCreated();

        // Cancelada, o lugar fica livre.
        $this->postJson(self::API . "/$primeira/estado", ['estado' => 'cancelada'])->assertOk();
        $this->postJson(self::API, $base + ['bay_id' => $elevador->id, 'inicio' => $this->amanha('09:15'), 'duracao' => 30])->assertCreated();
        // E voltar a «marcada» já choca.
        $this->postJson(self::API . "/$primeira/estado", ['estado' => 'marcada'])->assertStatus(422);

        $this->assertSame('LD-11-22-AB', Appointment::find($primeira)->plate);
    }

    public function test_chegou_abre_a_ordem_e_cria_a_viatura_se_nao_existir(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.create', 'workshop.work-orders.edit');

        $id = $this->postJson(self::API, ['plate' => 'LDA9999ZZ', 'customer_name' => 'Cliente Novo', 'customer_phone' => '912345678', 'inicio' => $this->amanha('14:00'), 'duracao' => 120, 'service' => 'Pintura do capô', 'notes' => 'Traz o carro de manhã'])->json('data.id');

        $r = $this->postJson(self::API . "/$id/chegou")->assertOk();
        $ordem = WorkOrder::findOrFail($r->json('ordem_id'));

        $this->assertSame('LDA9999ZZ', $ordem->vehicle->plate);
        $this->assertSame('Cliente Novo', $ordem->vehicle->owner_name);
        $this->assertStringContainsString('Pintura do capô', $ordem->problem_description);
        $this->assertSame('pending', $ordem->status);
        $this->assertSame(['chegou', $ordem->id], [Appointment::find($id)->status, Appointment::find($id)->work_order_id]);

        $this->postJson(self::API . "/$id/chegou")->assertStatus(422);
        $this->deleteJson(self::API . "/$id")->assertStatus(422);
    }

    public function test_de_outra_empresa_nao_serve_e_sem_criar_nao_marca(): void
    {
        $this->comPermissoes('workshop.work-orders.view');
        $outra = Tenant::create(['name' => 'Outra ' . uniqid(), 'slug' => 'o-' . uniqid(), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $alheio = Bay::withoutGlobalScopes()->create(['tenant_id' => $outra->id, 'name' => 'Elevador deles']);

        $this->postJson(self::API, ['plate' => 'X', 'customer_name' => 'Y', 'inicio' => $this->amanha('09:00'), 'duracao' => 60, 'service' => 'z'])->assertForbidden();

        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.create');
        $this->postJson(self::API, ['plate' => 'X', 'customer_name' => 'Y', 'bay_id' => $alheio->id, 'inicio' => $this->amanha('09:00'), 'duracao' => 60, 'service' => 'z'])->assertStatus(422)->assertJsonValidationErrors('bay_id');
        $this->postJson(self::API, ['inicio' => $this->amanha('09:00'), 'duracao' => 60, 'service' => 'z'])->assertStatus(422)->assertJsonValidationErrors(['plate', 'customer_name']);
        $this->get('/workshop/schedule')->assertOk()->assertSee('data-ecra="oficina/agenda"', false);
    }
}
