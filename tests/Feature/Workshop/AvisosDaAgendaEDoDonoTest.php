<?php

namespace Tests\Feature\Workshop;

use App\Models\TenantNotificationSetting;
use App\Models\User;
use App\Models\Workshop\Appointment;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderItem;
use App\Models\Workshop\WorkOrderSurvey;
use App\Services\Notifications\EnvioDeNotificacoes;
use App\Services\Workshop\AvisosDaOficina;
use Illuminate\Support\Str;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TenantTestCase;

/**
 * A AGENDA E O DONO DA EMPRESA NOS AVISOS DA OFICINA (28/09/2026).
 *
 * As marcações não avisavam ninguém e o dono da empresa não sabia de nada —
 * nem quando o cliente respondia ao orçamento. Agora cada acontecimento avisa
 * o cliente e o responsável da empresa (o primeiro utilizador activo: aqui, o
 * utilizador do setUp). Quem faz a acção é um empregado, para o dono não
 * ficar calado por ter sido ele a fazer.
 */
class AvisosDaAgendaEDoDonoTest extends TenantTestCase
{
    private const AGENDA = '/api/v1/invoicing/react/oficina/agenda';

    private const ORDENS = '/api/v1/invoicing/react/oficina/ordens';

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @var list<array{chave:string, canal:string, destino:string, variaveis:array}> */
    private array $enviados = [];

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('oficina')->comModulo('notifications');
        $this->dono = $this->user;
        $this->dono->update(['email' => 'dono' . uniqid() . '@oficina.ao']);

        // O SMS e o email da empresa ligados: os dois canais.
        TenantNotificationSetting::getForTenant($this->tenant->id)->update([
            'sms_enabled' => true, 'sms_provider' => 'telcosms', 'sms_api_token' => 'x',
            'email_enabled' => true, 'smtp_host' => 'smtp.oficina.ao',
        ]);

        $envio = Mockery::mock(EnvioDeNotificacoes::class);
        $envio->shouldReceive('enviar')->andReturnUsing(function ($modelo, $canal, $destino, $variaveis) {
            $this->enviados[] = [
                'chave' => preg_replace('~^oficina-(.+)-\d+$~', '$1', $modelo->slug),
                'canal' => $canal, 'destino' => $destino, 'variaveis' => $variaveis,
            ];

            return true;
        });
        $this->app->instance(EnvioDeNotificacoes::class, $envio);

        $this->empregado();
    }

    /** A rececionista: é ela que marca, abre e muda — o dono é avisado. */
    private function empregado(): User
    {
        $u = User::create(['name' => 'Rececionista', 'email' => 'rec' . uniqid() . '@oficina.ao', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $u->tenants()->syncWithoutDetaching([$this->tenant->id]);

        setPermissionsTeamId($this->tenant->id);
        $nomes = ['workshop.work-orders.view', 'workshop.work-orders.create', 'workshop.work-orders.edit'];
        foreach ($nomes as $nome) {
            Permission::findOrCreate($nome, 'web');
        }
        $u->givePermissionTo($nomes);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($u);
        session(['active_tenant_id' => $this->tenant->id]);

        return $u;
    }

    private function viatura(): Vehicle
    {
        return Vehicle::create(['plate' => 'LD-' . random_int(10, 99) . '-' . random_int(10, 99) . '-AG', 'vehicle_number' => 'VEH-' . substr(uniqid(), -5), 'owner_name' => 'João Manuel', 'owner_phone' => '923456789', 'owner_email' => 'joao@exemplo.ao', 'brand' => 'Toyota', 'model' => 'Hilux', 'status' => 'active']);
    }

    private function amanha(string $hora): string
    {
        return now()->addDay()->format('Y-m-d') . ' ' . $hora;
    }

    /** @return list<string> «chave:canal:destino» do que saiu */
    private function sairam(): array
    {
        return array_map(fn ($e) => "{$e['chave']}:{$e['canal']}:{$e['destino']}", $this->enviados);
    }

    private function do(string $chave): ?array
    {
        return collect($this->enviados)->firstWhere('chave', $chave);
    }

    public function test_a_marcacao_avisa_o_cliente_e_o_dono(): void
    {
        $v = $this->viatura();

        $this->postJson(self::AGENDA, ['vehicle_id' => $v->id, 'inicio' => $this->amanha('09:00'), 'duracao' => 60, 'service' => 'Revisão 10 000 km'])->assertCreated();

        $this->assertContains('marcacao:sms:923456789', $this->sairam());
        $this->assertContains('marcacao:email:joao@exemplo.ao', $this->sairam());
        $this->assertContains("dono-marcacao:email:{$this->dono->email}", $this->sairam());
        // O SMS do dono nasce desligado: o email chega.
        $this->assertNotContains('dono-marcacao:sms', array_map(fn ($e) => "{$e['chave']}:{$e['canal']}", $this->enviados));

        $cliente = $this->do('marcacao')['variaveis'];
        $this->assertSame($v->plate, $cliente['matricula']);
        $this->assertSame(now()->addDay()->format('d/m/Y') . ' 09:00', $cliente['quando']);
        $this->assertSame('Revisão 10 000 km', $cliente['servico']);
        $this->assertSame('Rececionista', $this->do('dono-marcacao')['variaveis']['quem']);
        $this->assertStringContainsString('/workshop/schedule', $this->do('dono-marcacao')['variaveis']['link']);
    }

    /** Sem viatura na casa, a marcação traz o nome e o telefone — o SMS vai para esse. */
    public function test_marcacao_sem_viatura_usa_o_telefone_da_marcacao(): void
    {
        $this->postJson(self::AGENDA, ['plate' => 'ld-11-22-zz', 'customer_name' => 'Ana Paula', 'customer_phone' => '912000111', 'inicio' => $this->amanha('10:00'), 'duracao' => 60, 'service' => 'Travões'])->assertCreated();

        $this->assertContains('marcacao:sms:912000111', $this->sairam());
        $this->assertSame(['Ana Paula', 'LD-11-22-ZZ'], [$this->do('marcacao')['variaveis']['cliente'], $this->do('marcacao')['variaveis']['matricula']]);
    }

    public function test_mudar_a_hora_avisa_e_mudar_so_o_elevador_nao(): void
    {
        $v = $this->viatura();
        $id = $this->postJson(self::AGENDA, ['vehicle_id' => $v->id, 'inicio' => $this->amanha('09:00'), 'duracao' => 60, 'service' => 'Revisão'])->json('data.id');
        $this->enviados = [];

        // A mesma hora (só a nota muda): não se avisa ninguém.
        $this->putJson(self::AGENDA . "/{$id}", ['vehicle_id' => $v->id, 'inicio' => $this->amanha('09:00'), 'duracao' => 60, 'service' => 'Revisão', 'notes' => 'Traz o livrete'])->assertOk();
        $this->assertSame([], $this->enviados);

        $this->putJson(self::AGENDA . "/{$id}", ['vehicle_id' => $v->id, 'inicio' => $this->amanha('14:30'), 'duracao' => 60, 'service' => 'Revisão'])->assertOk();
        $this->assertContains('marcacao-alterada:sms:923456789', $this->sairam());
        $this->assertContains("dono-marcacao-alterada:email:{$this->dono->email}", $this->sairam());
        $this->assertSame(now()->addDay()->format('d/m/Y') . ' 09:00', $this->do('dono-marcacao-alterada')['variaveis']['antes']);
        $this->assertSame(now()->addDay()->format('d/m/Y') . ' 14:30', $this->do('marcacao-alterada')['variaveis']['quando']);
    }

    public function test_cancelar_e_faltar_avisam_como_devem(): void
    {
        $v = $this->viatura();
        $id = $this->postJson(self::AGENDA, ['vehicle_id' => $v->id, 'inicio' => $this->amanha('09:00'), 'duracao' => 60, 'service' => 'Revisão'])->json('data.id');
        $this->enviados = [];

        $this->postJson(self::AGENDA . "/{$id}/estado", ['estado' => 'cancelada'])->assertOk();
        $this->assertContains('marcacao-cancelada:sms:923456789', $this->sairam());
        $this->assertContains("dono-marcacao-cancelada:email:{$this->dono->email}", $this->sairam());

        // Confirmar: o modelo do cliente nasce desligado, e o dono não tem modelo para isso.
        $this->enviados = [];
        $this->postJson(self::AGENDA . "/{$id}/estado", ['estado' => 'confirmada'])->assertOk();
        $this->assertSame([], $this->enviados);

        // Faltou: o dono sabe; o convite ao cliente para remarcar nasce desligado.
        $this->postJson(self::AGENDA . "/{$id}/estado", ['estado' => 'faltou'])->assertOk();
        $this->assertSame(["dono-marcacao-faltou:email:{$this->dono->email}"], $this->sairam());
    }

    public function test_o_carro_chegou_abre_a_ordem_e_avisa_os_dois(): void
    {
        $v = $this->viatura();
        $id = $this->postJson(self::AGENDA, ['vehicle_id' => $v->id, 'inicio' => $this->amanha('09:00'), 'duracao' => 60, 'service' => 'Revisão'])->json('data.id');
        $this->enviados = [];

        $ordem = $this->postJson(self::AGENDA . "/{$id}/chegou")->assertOk()->json('ordem_id');

        $this->assertContains('aberta:sms:923456789', $this->sairam());
        $this->assertContains("dono-aberta:email:{$this->dono->email}", $this->sairam());
        $this->assertSame(WorkOrder::find($ordem)->order_number, $this->do('aberta')['variaveis']['ordem']);
        $this->assertStringContainsString("ordem={$ordem}", $this->do('dono-aberta')['variaveis']['link']);
        $this->assertTrue(WorkOrder::find($ordem)->history()->where('description', 'like', '%enviado ao responsável da empresa por email%')->exists());
    }

    public function test_pronta_e_entregue_avisam_o_dono_tambem(): void
    {
        $v = $this->viatura();
        $o = WorkOrder::create(['order_number' => 'OS-DN-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'x', 'status' => 'in_progress', 'priority' => 'normal', 'total' => 35000]);
        $this->app->terminate();
        $this->enviados = [];

        $this->postJson(self::ORDENS . "/{$o->id}/estado", ['estado' => 'completed'])->assertOk();

        $this->assertContains('pronta:sms:923456789', $this->sairam());
        $this->assertContains("dono-pronta:email:{$this->dono->email}", $this->sairam());
        $this->assertSame('35.000,00', $this->do('dono-pronta')['variaveis']['total']);
    }

    /** O que o cliente faz pelo link chega ao dono — por email e por SMS. */
    public function test_a_resposta_ao_orcamento_chega_ao_dono(): void
    {
        $this->tenant->update(['phone' => '222333444']);
        $v = $this->viatura();
        $o = WorkOrder::create(['order_number' => 'OS-OR-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'x', 'status' => 'in_progress', 'priority' => 'normal']);
        $a = WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'service', 'name' => 'Pastilhas', 'quantity' => 1, 'unit_price' => 20000, 'subtotal' => 20000, 'approval' => 'pending']);
        $b = WorkOrderItem::create(['work_order_id' => $o->id, 'type' => 'service', 'name' => 'Discos', 'quantity' => 1, 'unit_price' => 45000, 'subtotal' => 45000, 'approval' => 'pending']);
        $o->forceFill(['approval_token' => Str::random(48), 'approval_requested_at' => now(), 'approval_expires_at' => now()->addDays(7)])->save();

        auth()->logout();
        $this->enviados = [];

        $this->postJson("/oficina/aprovar/{$o->approval_token}", ['decisoes' => [$a->id => 'approved', $b->id => 'declined'], 'nome' => 'João Manuel', 'assinatura' => self::PNG])->assertOk();

        $this->assertContains("dono-orcamento-respondido:email:{$this->dono->email}", $this->sairam());
        $sms = collect($this->enviados)->first(fn ($e) => $e['chave'] === 'dono-orcamento-respondido' && $e['canal'] === 'sms');
        $this->assertNotNull($sms, 'a resposta do cliente vai também por SMS ao dono');
        $d = $this->do('dono-orcamento-respondido')['variaveis'];
        $this->assertSame(['João Manuel', 1, 1], [$d['nome'], $d['aprovadas'], $d['recusadas']]);
    }

    public function test_a_avaliacao_do_cliente_chega_ao_dono(): void
    {
        $v = $this->viatura();
        $o = WorkOrder::create(['order_number' => 'OS-AV-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'x', 'status' => 'delivered', 'priority' => 'normal']);
        $s = WorkOrderSurvey::create(['tenant_id' => $this->tenant->id, 'work_order_id' => $o->id, 'vehicle_id' => $v->id, 'token' => Str::random(48)]);

        auth()->logout();
        $this->enviados = [];

        $this->postJson("/oficina/avaliar/{$s->token}", ['nota' => 2, 'recomenda' => false, 'comentario' => 'Demorou muito.'])->assertOk();

        $d = $this->do('dono-avaliacao');
        $this->assertNotNull($d);
        $this->assertSame([2, 'Não', 'Demorou muito.'], [$d['variaveis']['nota'], $d['variaveis']['recomenda'], $d['variaveis']['comentario']]);
    }

    /** O dono que marca ele próprio não recebe email a dizer o que acabou de fazer. */
    public function test_o_dono_nao_e_avisado_do_que_ele_proprio_fez(): void
    {
        $this->actingAs($this->dono);
        setPermissionsTeamId($this->tenant->id);
        $this->comPermissoes('workshop.work-orders.view', 'workshop.work-orders.create', 'workshop.work-orders.edit');
        $v = $this->viatura();

        $this->postJson(self::AGENDA, ['vehicle_id' => $v->id, 'inicio' => $this->amanha('09:00'), 'duracao' => 60, 'service' => 'Revisão'])->assertCreated();

        $this->assertContains('marcacao:sms:923456789', $this->sairam());
        $this->assertSame([], array_filter($this->sairam(), fn ($s) => str_starts_with($s, 'dono-')));
    }

    public function test_o_lembrete_da_vespera_sai_uma_vez(): void
    {
        $v = $this->viatura();
        $amanha = Appointment::create(['tenant_id' => $this->tenant->id, 'vehicle_id' => $v->id, 'starts_at' => now()->addHours(20), 'ends_at' => now()->addHours(21), 'service' => 'Revisão', 'status' => 'confirmada']);
        $longe = Appointment::create(['tenant_id' => $this->tenant->id, 'vehicle_id' => $v->id, 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'service' => 'Revisão', 'status' => 'marcada']);
        $cancelada = Appointment::create(['tenant_id' => $this->tenant->id, 'vehicle_id' => $v->id, 'starts_at' => now()->addHours(10), 'ends_at' => now()->addHours(11), 'service' => 'Revisão', 'status' => 'cancelada']);

        $this->assertSame(1, AvisosDaOficina::lembrarMarcacoes($this->tenant->id));
        $this->assertSame(0, AvisosDaOficina::lembrarMarcacoes($this->tenant->id), 'uma vez só');

        $this->assertNotNull($amanha->fresh()->reminder_sent_at);
        $this->assertNull($longe->fresh()->reminder_sent_at);
        $this->assertNull($cancelada->fresh()->reminder_sent_at);
        $this->assertContains('marcacao-lembrete:sms:923456789', $this->sairam());
    }

    /** O sino: o orçamento respondido, as marcações de hoje e a avaliação baixa. */
    public function test_o_sino_mostra_a_oficina(): void
    {
        $v = $this->viatura();
        $o = WorkOrder::create(['order_number' => 'OS-SN-' . substr(uniqid(), -5), 'vehicle_id' => $v->id, 'received_at' => now(), 'problem_description' => 'x', 'status' => 'in_progress', 'priority' => 'normal']);
        $o->forceFill(['approval_signed_at' => now()->subHour()])->save();
        Appointment::create(['tenant_id' => $this->tenant->id, 'vehicle_id' => $v->id, 'starts_at' => now()->endOfDay()->subHour(), 'ends_at' => now()->endOfDay()->subMinutes(10), 'service' => 'Revisão', 'status' => 'marcada']);
        WorkOrderSurvey::create(['tenant_id' => $this->tenant->id, 'work_order_id' => $o->id, 'vehicle_id' => $v->id, 'token' => Str::random(48), 'score' => 1, 'answered_at' => now()]);

        $titulos = array_column(app(\App\Services\Casca\NotificacoesDoSistema::class)->para(auth()->user()), 'titulo');

        $this->assertContains('Orçamentos respondidos', $titulos);
        $this->assertContains('Marcações de hoje', $titulos);
        $this->assertContains('Avaliações baixas', $titulos);
    }
}
