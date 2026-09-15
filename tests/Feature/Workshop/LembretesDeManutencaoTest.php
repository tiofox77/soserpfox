<?php

namespace Tests\Feature\Workshop;

use App\Models\TenantNotificationSetting;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\VehicleReminder;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkshopSetting;
use App\Services\Notifications\EnvioDeNotificacoes;
use App\Services\Workshop\LembretesDaOficina;
use Mockery;
use Tests\TenantTestCase;

/**
 * OS LEMBRETES DE MANUTENÇÃO (15/09/2026, OF-11).
 */
class LembretesDeManutencaoTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function viatura(array $mais = []): Vehicle
    {
        return Vehicle::create($mais + ['plate' => 'LD' . random_int(10, 99) . random_int(10, 99) . 'AB', 'vehicle_number' => 'VEH-' . substr(uniqid(), -6), 'owner_name' => 'Ana Paula', 'owner_phone' => '923000111', 'brand' => 'Toyota', 'model' => 'Corolla', 'status' => 'active', 'mileage' => 0]);
    }

    private function ordem(Vehicle $v, array $mais = []): WorkOrder
    {
        return WorkOrder::create($mais + ['order_number' => 'OS-LB-' . substr(uniqid(), -6), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'x', 'status' => 'in_progress', 'priority' => 'normal']);
    }

    public function test_concluir_a_ordem_marca_a_proxima_revisao_e_uma_chapa_a_meio_nao_a_empurra(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $v = $this->viatura(['mileage' => 50000]);
        $o = $this->ordem($v, ['mileage_in' => 52000]);

        $this->postJson(self::API . "/ordens/{$o->id}/estado", ['estado' => 'completed'])->assertOk();

        $v->refresh();
        $this->assertSame(62000, $v->next_service_km);
        $this->assertSame(today()->addMonthsNoOverflow(6)->toDateString(), $v->next_service_date->toDateString());
        $this->assertSame(52000, $v->last_service_km);
        $this->assertTrue($o->history()->where('description', 'like', 'Próxima revisão da viatura marcada%')->exists());

        // Uma chapa um mês depois, aos 53 000 km: a revisão continua nos 62 000.
        $o2 = $this->ordem($v, ['mileage_in' => 53000]);
        $this->postJson(self::API . "/ordens/{$o2->id}/estado", ['estado' => 'completed'])->assertOk();
        $this->assertSame(62000, $v->fresh()->next_service_km);

        // O intervalo próprio da viatura manda sobre o da oficina.
        $v->update(['next_service_km' => 53500, 'service_interval_km' => 5000, 'service_interval_months' => 0]);
        $o3 = $this->ordem($v, ['mileage_in' => 53200]);
        $this->postJson(self::API . "/ordens/{$o3->id}/estado", ['estado' => 'completed'])->assertOk();
        $this->assertSame(58200, $v->fresh()->next_service_km);
        $this->assertNull($v->fresh()->next_service_date);
    }

    public function test_a_lista_junta_revisoes_por_data_e_por_km_estimados_e_documentos_a_caducar(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.edit');

        $porData = $this->viatura(['next_service_date' => today()->addDays(5)]);
        $seguro = $this->viatura(['insurance_expiry' => today()->subDay()]);
        $longe = $this->viatura(['next_service_date' => today()->addMonths(4), 'insurance_expiry' => today()->addMonths(6)]);

        // 40 000 km há 100 dias, 50 000 há 50: 200 km por dia — hoje terá ~60 000.
        $porKm = $this->viatura(['next_service_km' => 60000, 'mileage' => 50000]);
        $this->ordem($porKm, ['mileage_in' => 40000, 'received_at' => today()->subDays(100), 'status' => 'delivered']);
        $this->ordem($porKm, ['mileage_in' => 50000, 'received_at' => today()->subDays(50), 'status' => 'delivered']);

        $naOficina = $this->viatura(['inspection_expiry' => today()->addDays(10)]);
        $this->ordem($naOficina);

        $r = $this->getJson(self::API . '/lembretes')->assertOk();
        $itens = collect($r->json('data'))->keyBy('chave');

        $this->assertArrayNotHasKey("{$longe->id}-revisao", $itens->all());
        $this->assertArrayNotHasKey("{$longe->id}-seguro", $itens->all());

        $this->assertSame(5, $itens["{$porData->id}-revisao"]['dias']);
        $this->assertFalse($itens["{$porData->id}-revisao"]['vencido']);

        $this->assertTrue($itens["{$seguro->id}-seguro"]['vencido']);
        $this->assertSame(-1, $itens["{$seguro->id}-seguro"]['dias']);
        $this->assertStringContainsString(today()->subDay()->format('d/m/Y'), $itens["{$seguro->id}-seguro"]['mensagem']);

        $km = $itens["{$porKm->id}-revisao"];
        $this->assertEquals(200, $km['km_por_dia']);
        $this->assertSame(60000, $km['km_estimados']);
        $this->assertTrue($km['vencido']);

        $this->assertTrue($itens["{$naOficina->id}-inspeccao"]['na_oficina']);

        // Os vencidos primeiro.
        $this->assertTrue($r->json('data.0.vencido'));
        $this->assertFalse($r->json('canais.sms'));
    }

    public function test_o_contacto_fica_preso_ao_vencimento_e_o_sms_so_sai_com_o_modulo(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.edit');
        $v = $this->viatura(['insurance_expiry' => today()->addDays(3)]);

        $this->postJson(self::API . "/lembretes/{$v->id}/contacto", ['tipo' => 'seguro', 'canal' => 'sms'])->assertStatus(422);
        $this->postJson(self::API . "/lembretes/{$v->id}/contacto", ['tipo' => 'revisao', 'canal' => 'telefone'])->assertStatus(422);

        $this->postJson(self::API . "/lembretes/{$v->id}/contacto", ['tipo' => 'seguro', 'canal' => 'whatsapp'])->assertOk();
        $item = collect($this->getJson(self::API . '/lembretes')->json('data'))->firstWhere('chave', "{$v->id}-seguro");
        $this->assertSame('whatsapp', $item['ultimo_contacto']['canal']);
        $this->assertSame($this->user->name, $item['ultimo_contacto']['por']);

        // O seguro renovado mas ainda a caducar noutra data é outro vencimento: volta a poder ser chamado.
        $v->update(['insurance_expiry' => today()->addDays(20)]);
        $item = collect($this->getJson(self::API . '/lembretes')->json('data'))->firstWhere('chave', "{$v->id}-seguro");
        $this->assertNull($item['ultimo_contacto']);

        $this->postJson(self::API . "/lembretes/{$v->id}/adiar", ['dias' => 7])->assertOk();
        $item = collect($this->getJson(self::API . '/lembretes')->json('data'))->firstWhere('chave', "{$v->id}-seguro");
        $this->assertSame(today()->addDays(7)->toDateString(), $item['pausado_ate']);
    }

    public function test_so_quem_edita_viaturas_contacta_e_muda_as_definicoes(): void
    {
        $this->comPermissoes('workshop.vehicles.view');
        $v = $this->viatura(['insurance_expiry' => today()->addDays(3)]);

        $this->getJson(self::API . '/lembretes')->assertOk()->assertJsonPath('pode_gerir', false);
        $this->postJson(self::API . "/lembretes/{$v->id}/contacto", ['tipo' => 'seguro', 'canal' => 'nota'])->assertForbidden();
        $this->putJson(self::API . '/lembretes/definicoes', ['service_interval_km' => 5000, 'service_interval_months' => 12, 'remind_days_before' => 10, 'remind_km_before' => 500, 'documents_days_before' => 15, 'auto_reminders' => true])->assertForbidden();

        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.edit');
        $this->putJson(self::API . '/lembretes/definicoes', ['service_interval_km' => 5000, 'service_interval_months' => 12, 'remind_days_before' => 10, 'remind_km_before' => 500, 'documents_days_before' => 15, 'auto_reminders' => true])->assertOk();
        $this->assertSame(5000, WorkshopSetting::getForTenant($this->tenant->id)->service_interval_km);

        // «Revisão feita hoje» noutro lado: conta dos km de agora e do intervalo.
        $v->update(['mileage' => 30000]);
        $this->putJson(self::API . "/lembretes/{$v->id}/revisao", ['feita' => true])->assertOk();
        $this->assertSame(35000, $v->fresh()->next_service_km);
        $this->assertSame(today()->addMonthsNoOverflow(12)->toDateString(), $v->fresh()->next_service_date->toDateString());
    }

    public function test_o_envio_automatico_so_sai_com_a_opcao_e_o_modulo_uma_vez_por_dia(): void
    {
        $v = $this->viatura(['inspection_expiry' => today()->addDays(4)]);
        $contactado = $this->viatura(['insurance_expiry' => today()->addDays(4)]);
        LembretesDaOficina::registar($contactado, 'seguro', today()->addDays(4)->toDateString(), 'telefone');

        $envio = Mockery::mock(EnvioDeNotificacoes::class);
        $this->app->instance(EnvioDeNotificacoes::class, $envio);

        // Sem a opção ligada, nada.
        $envio->shouldNotReceive('enviar');
        $this->assertSame(0, LembretesDaOficina::despachar($this->tenant->id));

        WorkshopSetting::getForTenant($this->tenant->id)->update(['auto_reminders' => true]);
        $this->assertSame(0, LembretesDaOficina::despachar($this->tenant->id), 'sem o módulo Notificações');

        $this->comModulo('notifications');
        TenantNotificationSetting::getForTenant($this->tenant->id)->update(['sms_enabled' => true]);
        \Illuminate\Support\Facades\Cache::flush();

        $envio = Mockery::mock(EnvioDeNotificacoes::class);
        $envio->shouldReceive('enviar')->once()->withArgs(fn ($modelo, $canal, $destino, $variaveis, $registo) => $modelo->slug === "oficina-inspeccao-{$this->tenant->id}"
            && $canal === 'sms' && $destino === '923000111' && $variaveis['data'] === today()->addDays(4)->format('d/m/Y') && $registo === $v->id)->andReturn(true);
        $this->app->instance(EnvioDeNotificacoes::class, $envio);

        $this->assertSame(1, LembretesDaOficina::despachar($this->tenant->id));
        $this->assertSame('sms', VehicleReminder::where('vehicle_id', $v->id)->value('channel'));
        $this->assertSame(0, LembretesDaOficina::despachar($this->tenant->id), 'uma volta por dia');
    }

    public function test_a_viatura_grava_a_revisao_no_catalogo(): void
    {
        $this->comPermissoes('workshop.vehicles.view', 'workshop.vehicles.create', 'workshop.vehicles.edit');

        $this->postJson('/api/v1/invoicing/react/catalogos/viaturas', [
            'plate' => 'LD-99-88-ZZ', 'owner_name' => 'Rui', 'brand' => 'Kia', 'model' => 'Rio', 'status' => 'active',
            'next_service_km' => '90000', 'next_service_date' => today()->addMonth()->toDateString(), 'service_interval_km' => '', 'service_interval_months' => '12',
        ])->assertCreated();

        $v = Vehicle::where('plate', 'LD-99-88-ZZ')->firstOrFail();
        $this->assertSame(90000, $v->next_service_km);
        $this->assertNull($v->service_interval_km);
        $this->assertSame(12, $v->service_interval_months);
    }
}
