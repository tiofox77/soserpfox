<?php

namespace Tests\Feature\Workshop;

use App\Models\NotificationTemplate;
use App\Models\TenantNotificationSetting;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use App\Services\Notifications\EnvioDeNotificacoes;
use Mockery;
use Tests\TenantTestCase;

/**
 * OS AVISOS AO CLIENTE DA OFICINA (15/09/2026, OF-10).
 */
class AvisosAoClienteTest extends TenantTestCase
{
    private const API = '/api/v1/invoicing/react/oficina/ordens';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina');
    }

    private function ordem(): WorkOrder
    {
        $v = Vehicle::create(['plate' => 'LDA2862RP', 'vehicle_number' => 'VEH-' . substr(uniqid(), -5), 'owner_name' => 'João Manuel', 'owner_phone' => '923456789', 'owner_email' => 'joao@exemplo.ao', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active']);

        return WorkOrder::create(['order_number' => 'OS-AV-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'x', 'status' => 'in_progress', 'priority' => 'normal', 'total' => 35000]);
    }

    private function comSmsConfigurado(): void
    {
        $this->comModulo('notifications');
        TenantNotificationSetting::getForTenant($this->tenant->id)->update(['sms_enabled' => true, 'sms_provider' => 'telcosms', 'sms_api_token' => 'x']);
    }

    public function test_sem_o_modulo_notificacoes_nao_sai_nada(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $envio = Mockery::mock(EnvioDeNotificacoes::class);
        $envio->shouldNotReceive('enviar');
        $this->app->instance(EnvioDeNotificacoes::class, $envio);
        $o = $this->ordem();

        $this->postJson(self::API . "/{$o->id}/estado", ['estado' => 'completed'])->assertOk();
        $this->assertFalse($this->getJson(self::API . '/opcoes')->json('avisos_ao_cliente'));
        $this->assertSame(0, NotificationTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('module', 'workshop')->count());
    }

    public function test_viatura_pronta_manda_sms_ao_dono_com_as_variaveis(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $this->comSmsConfigurado();
        $o = $this->ordem();

        $recebido = null;
        $envio = Mockery::mock(EnvioDeNotificacoes::class);
        $envio->shouldReceive('enviar')->once()->withArgs(function ($modelo, $canal, $destino, $variaveis, $registo) use (&$recebido, $o) {
            $recebido = compact('canal', 'destino', 'variaveis', 'registo');

            return $modelo->slug === "oficina-pronta-{$this->tenant->id}" && $registo === $o->id;
        })->andReturn(true);
        $this->app->instance(EnvioDeNotificacoes::class, $envio);

        $this->postJson(self::API . "/{$o->id}/estado", ['estado' => 'completed'])->assertOk();

        $this->assertSame('sms', $recebido['canal']);
        $this->assertSame('923456789', $recebido['destino']);
        $this->assertSame(['LDA2862RP', 'João Manuel', '35.000,00'], [$recebido['variaveis']['matricula'], $recebido['variaveis']['cliente'], $recebido['variaveis']['total']]);
        $this->assertTrue($o->history()->where('description', 'like', '%enviado ao cliente por SMS%')->exists());
        $this->assertTrue($this->getJson(self::API . '/opcoes')->json('avisos_ao_cliente'));

        // Os modelos nascem na empresa, editáveis no ecrã das Notificações.
        $this->assertSame(4, NotificationTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('module', 'workshop')->count());
    }

    public function test_um_modelo_desligado_nao_avisa_e_o_orcamento_leva_o_link(): void
    {
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.edit');
        $this->comSmsConfigurado();
        \App\Services\Workshop\AvisosDaOficina::garantirModelos($this->tenant->id);
        NotificationTemplate::withoutGlobalScopes()->where('slug', "oficina-pronta-{$this->tenant->id}")->update(['is_active' => false]);
        $o = $this->ordem();
        WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'service', 'name' => 'Discos', 'quantity' => 1, 'unit_price' => 1000, 'approval' => 'pending']);

        $slugs = [];
        $envio = Mockery::mock(EnvioDeNotificacoes::class);
        $envio->shouldReceive('enviar')->andReturnUsing(function ($modelo, $canal, $destino, $variaveis) use (&$slugs) {
            $slugs[] = [$modelo->slug, $variaveis['link'] ?? null];

            return true;
        });
        $this->app->instance(EnvioDeNotificacoes::class, $envio);

        $this->postJson(self::API . "/{$o->id}/estado", ['estado' => 'completed'])->assertOk();
        $this->assertSame([], $slugs, 'o aviso de pronta está desligado');

        $o->update(['status' => 'in_progress']);
        $link = $this->postJson(self::API . "/{$o->id}/aprovacao")->assertOk()->json('data.link');
        $this->assertSame("oficina-orcamento-{$this->tenant->id}", $slugs[0][0]);
        $this->assertSame($link, $slugs[0][1]);
    }
}
