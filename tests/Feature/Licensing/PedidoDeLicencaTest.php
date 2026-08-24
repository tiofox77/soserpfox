<?php

namespace Tests\Feature\Licensing;

use App\Livewire\SuperAdmin\Licenciamento;
use App\Models\LicenseRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Licensing\LicenseIssuer;
use App\Services\Licensing\LicenseState;
use App\Services\Licensing\LicenseVerifier;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O ciclo completo do pedido de licença: a instalação pede, o super admin
 * aprova (criando a empresa e emitindo a licença), a instalação vai buscá-la.
 */
class PedidoDeLicencaTest extends TenantTestCase
{
    private User $super;
    private string $publica;

    protected function setUp(): void
    {
        parent::setUp();
        $par = LicenseIssuer::gerarParDeChaves();
        $this->publica = $par['publica'];
        config([
            'licensing.public_key'   => $par['publica'],
            'licensing.signing_key'  => $par['privada'],
            'licensing.bind_machine' => true,
        ]);

        $this->super = User::create([
            'name' => 'Root', 'email' => 'root_' . uniqid() . '@x.com', 'password' => bcrypt('x'),
        ]);
        $this->super->forceFill(['is_super_admin' => true])->save();
    }

    private function semMiddlewareDeTenant()
    {
        return $this->withoutMiddleware([
            \App\Http\Middleware\IdentifyTenant::class,
            \App\Http\Middleware\CheckTenantActive::class,
            \App\Http\Middleware\CheckSubscription::class,
        ]);
    }

    public function test_instalacao_submete_pedido(): void
    {
        $r = $this->semMiddlewareDeTenant()->postJson('/api/license/request', [
            'empresa'      => 'Farmácia Nova',
            'nif'          => '5417000000',
            'utilizadores' => 4,
            'fingerprint'  => 'fp-maquina-teste',
        ]);

        $r->assertCreated()->assertJson(['estado' => 'pendente']);
        $this->assertDatabaseHas('license_requests', ['empresa' => 'Farmácia Nova', 'estado' => 'pendente']);
    }

    public function test_pedido_repetido_da_mesma_maquina_nao_duplica(): void
    {
        $dados = ['empresa' => 'Repetida', 'fingerprint' => 'fp-igual'];
        $primeiro = $this->semMiddlewareDeTenant()->postJson('/api/license/request', $dados)->json('codigo');
        $segundo = $this->semMiddlewareDeTenant()->postJson('/api/license/request', $dados)->json('codigo');

        $this->assertSame($primeiro, $segundo);
        $this->assertSame(1, LicenseRequest::where('fingerprint', 'fp-igual')->count());
    }

    public function test_aprovacao_cria_empresa_e_emite_licenca_presa_a_maquina(): void
    {
        $pedido = LicenseRequest::create([
            'codigo' => LicenseRequest::gerarCodigo(),
            'empresa' => 'Padaria do Bairro',
            'nif' => '5410000001',
            'utilizadores' => 3,
            'fingerprint' => 'fp-da-padaria',
            'estado' => LicenseRequest::PENDENTE,
        ]);

        $antes = Tenant::count();

        Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->call('abrirPedido', $pedido->id)
            ->set('pedDias', 90)
            ->set('pedMaxUsers', 3)
            ->set('pedTodosModulos', false)
            ->set('pedModulos', ['invoicing'])
            ->set('pedPrenderMaquina', true)
            ->call('aprovarPedido')
            ->assertHasNoErrors();

        // Criou a empresa (billing) na cloud
        $this->assertSame($antes + 1, Tenant::count());
        $pedido->refresh();
        $this->assertSame(LicenseRequest::APROVADO, $pedido->estado);
        $this->assertNotNull($pedido->tenant_id);

        // A licença emitida é válida, presa à máquina e com os limites certos
        $estado = (new LicenseVerifier($this->publica, config('licensing')))
            ->verificar($pedido->licenca, [
                'fingerprint'    => 'fp-da-padaria',
                'ultimo_checkin' => CarbonImmutable::now(),
            ]);

        $this->assertTrue($estado->valida);
        $this->assertSame(3, $estado->payload->maxUtilizadores());
        $this->assertTrue($estado->payload->temModulo('invoicing'));
        $this->assertFalse($estado->payload->temModulo('hotel'));

        // Noutra máquina, a mesma licença é recusada
        $noutra = (new LicenseVerifier($this->publica, config('licensing')))
            ->verificar($pedido->licenca, ['fingerprint' => 'outra-maquina']);
        $this->assertSame(LicenseState::INVALIDA, $noutra->estado);
    }

    public function test_instalacao_vai_buscar_a_licenca_aprovada(): void
    {
        $pedido = LicenseRequest::create([
            'codigo' => 'AAAA-BBBB-CCCC',
            'empresa' => 'Loja X',
            'estado' => LicenseRequest::APROVADO,
            'licenca' => 'SOSERP-LIC.v1.xxx.yyy',
        ]);

        $this->semMiddlewareDeTenant()->getJson('/api/license/request/AAAA-BBBB-CCCC')
            ->assertOk()
            ->assertJson(['estado' => 'aprovado', 'licenca' => 'SOSERP-LIC.v1.xxx.yyy']);

        // Fica registado que a instalação já a levou
        $this->assertNotNull($pedido->fresh()->entregue_em);
    }

    public function test_aprovacao_regista_a_instalacao_para_a_lista(): void
    {
        $pedido = LicenseRequest::create([
            'codigo' => LicenseRequest::gerarCodigo(),
            'empresa' => 'Mercearia Central',
            'utilizadores' => 2,
            'fingerprint' => 'fp-mercearia',
            'estado' => LicenseRequest::PENDENTE,
        ]);

        Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->call('abrirPedido', $pedido->id)
            ->set('pedMaxUsers', 2)
            ->call('aprovarPedido')
            ->assertHasNoErrors();

        // A instalação passa a existir na lista de clientes offline
        $inst = \App\Models\LicencaEmitida::where('fingerprint', 'fp-mercearia')->first();
        $this->assertNotNull($inst, 'a instalação devia ter ficado registada');
        $this->assertSame(2, $inst->max_users);
        $this->assertSame($pedido->fresh()->tenant_id, $inst->tenant_id);
        // Ainda não ligou — é o que a lista deve mostrar
        $this->assertNull($inst->ultimo_checkin);
        $this->assertSame('nunca_ligou', $inst->situacao());
    }

    public function test_checkin_actualiza_o_ultimo_contacto(): void
    {
        $token = (new LicenseIssuer())->emitir([
            'tenant_id' => $this->tenant->id,
            'fp'        => 'fp-checkin',
            'exp'       => CarbonImmutable::now()->addDays(10)->getTimestamp(),
        ], config('licensing.signing_key'));

        $this->semMiddlewareDeTenant()->postJson('/api/license/checkin', [
            'token'  => $token,
            'versao' => '1.1.0',
        ])->assertOk();

        $inst = \App\Models\LicencaEmitida::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($inst, 'o check-in devia registar a instalação');
        $this->assertNotNull($inst->ultimo_checkin);
        $this->assertSame('1.1.0', $inst->versao_instalada);
        $this->assertSame('activa', $inst->situacao());
    }

    public function test_renovar_instalacao_mantem_modulos_maquina_e_tecto(): void
    {
        $inst = \App\Models\LicencaEmitida::create([
            'tenant_id'   => $this->tenant->id,
            'fingerprint' => 'fp-renovar',
            'plano'       => 'Business',
            'modulos'     => ['invoicing', 'rh'],
            'max_users'   => 4,
            'expira_em'   => CarbonImmutable::now()->subDay(),   // já expirada
        ]);

        Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->call('renovarInstalacao', $inst->id, 365)
            ->assertHasNoErrors();

        $inst->refresh();
        $this->assertNotNull($inst->token, 'a renovação devia guardar o token');
        $this->assertTrue($inst->expira_em->isFuture(), 'a validade devia ter sido estendida');

        // O que estava definido não se perde ao renovar.
        $estado = (new LicenseVerifier($this->publica, config('licensing')))
            ->verificar($inst->token, ['fingerprint' => 'fp-renovar', 'ultimo_checkin' => CarbonImmutable::now()]);

        $this->assertTrue($estado->valida);
        $this->assertSame(4, $estado->payload->maxUtilizadores());
        $this->assertTrue($estado->payload->temModulo('rh'));
        $this->assertFalse($estado->payload->temModulo('hotel'));
    }

    public function test_pedido_recusado_diz_o_motivo(): void
    {
        $pedido = LicenseRequest::create([
            'codigo' => 'DDDD-EEEE-FFFF', 'empresa' => 'Y', 'estado' => LicenseRequest::PENDENTE,
        ]);

        Livewire::actingAs($this->super)->test(Licenciamento::class)
            ->call('abrirPedido', $pedido->id)
            ->set('pedMotivoRecusa', 'Empresa sem contrato assinado')
            ->call('recusarPedido')
            ->assertHasNoErrors();

        $this->semMiddlewareDeTenant()->getJson('/api/license/request/DDDD-EEEE-FFFF')
            ->assertOk()
            ->assertJson(['estado' => 'recusado', 'motivo' => 'Empresa sem contrato assinado']);
    }
}
