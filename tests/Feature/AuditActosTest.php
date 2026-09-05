<?php

namespace Tests\Feature;

use App\Livewire\TenantSwitcher;
use App\Models\AuditTrail;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use App\Services\Audit\AuditRecorder;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Os ACTOS: o que se faz e não é alteração de modelo.
 *
 * O observer só vê escritas. Levar o SAFT para fora, imprimir uma factura,
 * entrar na casa de um cliente, correr um comando contra a produção — nada
 * disso muda uma linha de tabela, e por isso nada disso aparecia na trilha.
 */
class AuditActosTest extends TenantTestCase
{
    private function trilha(): \Illuminate\Support\Collection
    {
        app(AuditRecorder::class)->despejar();

        return AuditTrail::where('tenant_id', $this->tenant->id)->get();
    }

    /** @test */
    public function uma_exportacao_deixa_rasto(): void
    {
        app(AuditRecorder::class)->exportou('SAFT-AO', 'xml', $this->tenant->id, [
            'de' => '2026-01-01', 'ate' => '2026-01-31',
        ]);

        $linha = $this->trilha()->firstWhere('event', 'exportacao');

        $this->assertNotNull($linha, 'levar dados para fora tem de deixar rasto');
        $this->assertSame('SAFT-AO', $linha->metadata['o_que']);
        $this->assertSame('xml', $linha->metadata['formato']);
        $this->assertSame('2026-01-31', $linha->metadata['ate']);
    }

    /** Imprimir um documento fica ligado ao documento que saiu em papel. */
    public function test_uma_impressao_fica_ligada_ao_documento(): void
    {
        $factura = SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'invoice_date' => now(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        app(AuditRecorder::class)->imprimiu('factura de venda', $factura, ['formato' => 'pdf']);

        $linha = $this->trilha()->firstWhere('event', 'impressao');

        $this->assertNotNull($linha);
        $this->assertSame(SalesInvoice::class, $linha->auditable_type);
        $this->assertSame($factura->id, (int) $linha->auditable_id);
        $this->assertSame('factura de venda', $linha->metadata['o_que']);
    }

    /** @test */
    public function trocar_de_empresa_deixa_rasto_na_empresa_de_destino(): void
    {
        $outra = Tenant::create([
            'name' => 'Segunda Casa', 'slug' => 'segunda-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'seg'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);
        $this->user->tenants()->syncWithoutDetaching([$outra->id]);

        Livewire::actingAs($this->user)->test(TenantSwitcher::class)
            ->call('switchTenant', $outra->id);

        app(AuditRecorder::class)->despejar();

        $linha = AuditTrail::where('tenant_id', $outra->id)
            ->where('event', 'empresa.trocada')->first();

        $this->assertNotNull($linha, 'entrar numa empresa deixa rasto NESSA empresa');
        $this->assertSame($this->tenant->id, $linha->metadata['de'] ?? null);
    }

    /**
     * UM COMANDO CORRIDO CONTRA A PRODUÇÃO DEIXA RASTO.
     *
     * @test
     */
    public function um_comando_de_manutencao_com_empresa_fica_registado(): void
    {
        $token = config('maintenance.token');

        // `module:detach` a seco (sem --aplicar) não muda nada: serve só para
        // provar que a passagem pelo comando fica registada.
        $this->get("/maintenance/{$token}/command/module:detach?args=module_slug=crm tenant_id={$this->tenant->id}");

        app(AuditRecorder::class)->despejar();

        $linha = AuditTrail::where('tenant_id', $this->tenant->id)
            ->where('event', 'manutencao.comando')->latest('id')->first();

        $this->assertNotNull($linha, 'correr um comando na produção tem de deixar rasto');
        $this->assertSame('module:detach', $linha->metadata['comando']);
        $this->assertSame($this->tenant->id, (int) ($linha->metadata['argumentos']['tenant_id'] ?? 0));
    }

    /**
     * Um comando que REBENTA é o que mais interessa ter na trilha.
     *
     * Sem o `finally`, a excepção subia e levava o registo à frente: ficava
     * rasto dos comandos que correram bem e de nenhum dos que falharam.
     *
     * @test
     */
    public function um_comando_que_rebenta_tambem_fica_registado(): void
    {
        $token = config('maintenance.token');

        // Empresa inexistente: o comando não encontra o alvo e falha.
        $this->get("/maintenance/{$token}/command/module:detach?args=module_slug=nao-existe tenant_id={$this->tenant->id}");

        app(AuditRecorder::class)->despejar();

        $linha = AuditTrail::where('tenant_id', $this->tenant->id)
            ->where('event', 'manutencao.comando')->latest('id')->first();

        $this->assertNotNull($linha, 'um comando falhado tem de deixar rasto na mesma');
        $this->assertSame('nao-existe', $linha->metadata['argumentos']['module_slug']);
    }

    /**
     * OS SEGREDOS DOS ARGUMENTOS NUNCA ENTRAM.
     *
     * Os metadados de um acto não passam pela lista `redacted` do config —
     * essa só cobre valores de modelo. Um `--pin=1234` escrevia o PIN em claro
     * numa tabela append-only e selada, de onde não sai sem partir a cadeia.
     *
     * @test
     */
    public function um_segredo_nos_argumentos_nao_entra_na_trilha(): void
    {
        $token = config('maintenance.token');

        $this->get("/maintenance/{$token}/command/pwa:definir-pin?args=--tenant={$this->tenant->id} --pin=4321 --email=x@y.ao");

        app(AuditRecorder::class)->despejar();

        $linha = AuditTrail::where('tenant_id', $this->tenant->id)
            ->where('event', 'manutencao.comando')->latest('id')->first();

        $this->assertNotNull($linha);

        $argumentos = $linha->metadata['argumentos'];

        $this->assertSame('[oculto]', $argumentos['--pin'], 'o PIN nunca pode ficar em claro na trilha');
        $this->assertSame('x@y.ao', $argumentos['--email'], 'o que não é segredo fica, senão não se percebe nada');

        $this->assertStringNotContainsString('4321', json_encode($linha->metadata));
    }

    /**
     * Um comando global (sem empresa) não se regista — e isso é a fronteira
     * conhecida, não um esquecimento: a trilha é por empresa e atribuir o acto
     * a uma empresa qualquer seria pior do que não o escrever.
     */
    public function test_comando_sem_empresa_nao_inventa_uma(): void
    {
        $token = config('maintenance.token');
        $antes = AuditTrail::where('event', 'manutencao.comando')->count();

        $this->get("/maintenance/{$token}/command/cache:clear")->assertOk();

        app(AuditRecorder::class)->despejar();

        $this->assertSame($antes, AuditTrail::where('event', 'manutencao.comando')->count());
    }
}
