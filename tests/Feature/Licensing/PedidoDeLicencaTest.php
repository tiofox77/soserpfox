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
