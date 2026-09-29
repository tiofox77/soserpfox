<?php

namespace Tests\Feature\Tesouraria;

use App\Models\Treasury\Account;
use App\Models\Treasury\Bank;
use App\Models\Treasury\CashRegister;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * CAIXAS PARA O GERENTE, CAIXAS E BANCO PARA O TESOUREIRO (29/09/2026).
 *
 * O pedido do cliente, com o fluxo dele:
 *
 *  1. o operador entrega o fecho ao gerente, que regista Caixa 1 → Cofre;
 *  2. o gerente deposita no banco e manda o comprovativo; o tesoureiro, que
 *     trabalha à distância, confere e regista Cofre → Conta Corrente.
 *
 * O gerente não vê o saldo da Conta Corrente, não a escolhe numa transferência
 * e não desfaz o depósito do tesoureiro. As permissões vão por PAPEL, como a
 * empresa as configura.
 */
class TransferenciasPorAlcanceTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/tesouraria/transferencias';

    private const BASE = ['treasury.transfers.view', 'treasury.transfers.create', 'treasury.transfers.delete'];

    private User $gerente;

    private User $tesoureiro;

    private CashRegister $caixa1;

    private CashRegister $caixa2;

    private CashRegister $cofre;

    private Account $contaCorrente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('treasury');

        $this->gerente = $this->comPapel('Gerente', [...self::BASE, 'treasury.transfers.caixas']);
        $this->tesoureiro = $this->comPapel('Tesoureiro', [...self::BASE, 'treasury.transfers.caixas', 'treasury.transfers.contas']);

        $this->caixa1 = $this->caixa('Caixa 1 - Cleison', 50000);
        $this->caixa2 = $this->caixa('Caixa 2 - Cleiton', 30000);
        $this->cofre = $this->caixa('Caixa Cofre – Gerência', 0);

        $banco = Bank::firstOrCreate(['code' => 'BFA'], ['name' => 'Banco de Fomento Angola', 'country' => 'AO', 'is_active' => true]);
        $this->contaCorrente = Account::create([
            'tenant_id' => $this->tenant->id, 'bank_id' => $banco->id, 'account_name' => 'Conta Corrente Empresa',
            'account_number' => '123456789', 'currency' => 'AOA',
            'initial_balance' => 1234567, 'current_balance' => 1234567, 'is_active' => true,
        ]);
    }

    /** Um utilizador da empresa com um papel feito à mão, como no ecrã de papéis. */
    private function comPapel(string $nome, array $permissoes): User
    {
        setPermissionsTeamId($this->tenant->id);

        $papel = Role::create(['name' => $nome . ' ' . uniqid(), 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        foreach ($permissoes as $p) {
            $papel->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }

        $u = User::create([
            'name' => $nome, 'email' => strtolower($nome) . uniqid() . '@exemplo.ao',
            'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);
        $u->tenants()->syncWithoutDetaching([$this->tenant->id]);
        $u->assignRole($papel);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $u;
    }

    private function caixa(string $nome, float $saldo): CashRegister
    {
        return CashRegister::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'name' => $nome,
            'code' => 'CX' . random_int(1000, 9999), 'is_active' => true, 'is_default' => false, 'status' => 'open',
            'opening_balance' => $saldo, 'current_balance' => $saldo, 'expected_balance' => $saldo,
        ]);
    }

    private function como(User $u): static
    {
        $this->actingAs($u);
        session(['active_tenant_id' => $this->tenant->id]);
        setPermissionsTeamId($this->tenant->id);

        return $this;
    }

    private function transferir(string $de, string $para, float $valor)
    {
        return $this->postJson(self::RAIZ, [
            'de' => $de, 'para' => $para, 'amount' => $valor, 'fee' => 0, 'currency' => 'AOA',
            'transfer_date' => now()->toDateString(), 'description' => 'Entrega do fecho',
        ]);
    }

    private function saldo($bolso): float
    {
        return round((float) $bolso->fresh()->current_balance, 2);
    }

    public function test_o_gerente_ve_so_os_caixas_e_nao_recebe_o_saldo_do_banco(): void
    {
        $r = $this->como($this->gerente)->getJson(self::RAIZ . '/opcoes')->assertOk();

        $this->assertSame([], $r->json('contas'));
        $this->assertEqualsCanonicalizing(
            ['Caixa 1 - Cleison', 'Caixa 2 - Cleiton', 'Caixa Cofre – Gerência'],
            collect($r->json('caixas'))->pluck('nome')->all()
        );
        $this->assertTrue($r->json('permissoes.caixas'));
        $this->assertFalse($r->json('permissoes.contas'));

        // Nem o nome nem o saldo do banco viajam para o browser do gerente.
        $this->assertStringNotContainsString('Conta Corrente', $r->getContent());
        $this->assertStringNotContainsString('1234567', $r->getContent());
    }

    public function test_o_tesoureiro_ve_caixas_e_banco_com_o_saldo(): void
    {
        $r = $this->como($this->tesoureiro)->getJson(self::RAIZ . '/opcoes')->assertOk();

        $this->assertEqualsWithDelta(1234567, collect($r->json('contas'))->firstWhere('id', $this->contaCorrente->id)['saldo'], 0.01);
        $this->assertCount(3, $r->json('caixas'));
    }

    /** O fluxo inteiro, com as duas pessoas. */
    public function test_o_fluxo_entrega_ao_gerente_e_deposito_conferido_pelo_tesoureiro(): void
    {
        // 1. O gerente recebe a entrega do Caixa 1 no Cofre.
        $entrega = $this->como($this->gerente)
            ->transferir("cash:{$this->caixa1->id}", "cash:{$this->cofre->id}", 50000)->assertCreated()->json('id');

        $this->assertSame(0.0, $this->saldo($this->caixa1));
        $this->assertSame(50000.0, $this->saldo($this->cofre));

        // O gerente NÃO credita o banco por conta própria, nem tira de lá.
        $this->transferir("cash:{$this->cofre->id}", "account:{$this->contaCorrente->id}", 50000)
            ->assertUnprocessable()->assertJsonValidationErrors('para');
        $this->transferir("account:{$this->contaCorrente->id}", "cash:{$this->cofre->id}", 1000)
            ->assertUnprocessable()->assertJsonValidationErrors('de');

        $this->assertSame(50000.0, $this->saldo($this->cofre));
        $this->assertSame(1234567.0, $this->saldo($this->contaCorrente));

        // 2. O tesoureiro confere o comprovativo e regista o depósito.
        $deposito = $this->como($this->tesoureiro)
            ->transferir("cash:{$this->cofre->id}", "account:{$this->contaCorrente->id}", 50000)->assertCreated()->json('id');

        $this->assertSame(0.0, $this->saldo($this->cofre));
        $this->assertSame(1284567.0, $this->saldo($this->contaCorrente));

        // 3. O gerente vê o depósito que esvaziou o Cofre — só para consulta.
        $lista = $this->como($this->gerente)->getJson(self::RAIZ)->assertOk();
        $linhas = collect($lista->json('data'))->keyBy('id');

        $this->assertTrue($linhas[$entrega]['pode_anular'], 'a entrega entre caixas é dele');
        $this->assertFalse($linhas[$deposito]['pode_anular'], 'o depósito é do tesoureiro');
        $this->assertSame(2, $lista->json('resumo.transferencias'));

        // E não o desfaz, nem pela porta de trás.
        $this->deleteJson(self::RAIZ . '/' . $deposito)->assertForbidden();
        $this->assertSame(1284567.0, $this->saldo($this->contaCorrente));
        $this->assertSame(0.0, $this->saldo($this->cofre));
    }

    /** Uma transferência só entre contas bancárias não aparece ao gerente. */
    public function test_o_gerente_nao_ve_as_transferencias_so_entre_contas(): void
    {
        $banco = Bank::firstOrCreate(['code' => 'BFA'], ['name' => 'Banco de Fomento Angola', 'country' => 'AO', 'is_active' => true]);
        $poupanca = Account::create([
            'tenant_id' => $this->tenant->id, 'bank_id' => $banco->id, 'account_name' => 'Conta Poupança',
            'account_number' => '987654321', 'currency' => 'AOA', 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true,
        ]);

        $this->como($this->tesoureiro)
            ->transferir("account:{$this->contaCorrente->id}", "account:{$poupanca->id}", 1000)->assertCreated();

        $lista = $this->como($this->gerente)->getJson(self::RAIZ)->assertOk();

        $this->assertSame([], $lista->json('data'));
        $this->assertSame(0, $lista->json('resumo.transferencias'));
        $this->assertSame(0.0, (float) $lista->json('resumo.movido'));
    }

    /** Ver sem bolso nenhum: nem opções, nem lista — e o ecrã explica porquê. */
    public function test_ver_sem_caixas_nem_contas_nao_mostra_nada(): void
    {
        $leitor = $this->comPapel('Leitor', ['treasury.transfers.view']);

        $this->como($this->tesoureiro)
            ->transferir("cash:{$this->caixa2->id}", "cash:{$this->cofre->id}", 30000)->assertCreated();

        $this->como($leitor);
        $o = $this->getJson(self::RAIZ . '/opcoes')->assertOk();
        $this->assertSame([], $o->json('contas'));
        $this->assertSame([], $o->json('caixas'));

        $this->assertSame([], $this->getJson(self::RAIZ)->assertOk()->json('data'));
    }

    /**
     * O DEPLOY NÃO TIRA NADA A NINGUÉM: quem via transferências continua a ver
     * caixas e banco. O administrador tira depois o banco ao gerente.
     */
    public function test_o_alinhamento_da_os_dois_bolsos_a_quem_ja_via_transferencias(): void
    {
        Permission::whereIn('name', ['treasury.transfers.caixas', 'treasury.transfers.contas'])->delete();

        $antigo = Role::create(['name' => 'Caixa antigo ' . uniqid(), 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $antigo->givePermissionTo(Permission::findOrCreate('treasury.transfers.view', 'web'));

        $alheio = Role::create(['name' => 'Vendedor ' . uniqid(), 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $alheio->givePermissionTo(Permission::findOrCreate('invoicing.pos.sell', 'web'));

        $this->artisan('permissoes:alinhar', ['--aplicar' => true, '--so' => 'treasury.transfers.caixas,treasury.transfers.contas'])
            ->assertSuccessful();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($antigo->fresh()->hasPermissionTo('treasury.transfers.caixas'));
        $this->assertTrue($antigo->fresh()->hasPermissionTo('treasury.transfers.contas'));
        $this->assertFalse($alheio->fresh()->hasPermissionTo('treasury.transfers.contas'), 'quem não via transferências não ganha nada');
        $this->assertNotSame('', (string) Permission::where('name', 'treasury.transfers.contas')->value('description'));
    }

    /** Os papéis-modelo das empresas novas: quem vê transferências leva os dois bolsos. */
    public function test_os_papeis_de_uma_empresa_nova_levam_os_bolsos_com_o_ver(): void
    {
        foreach (['treasury.transfers.view', 'treasury.transfers.caixas', 'treasury.transfers.contas', 'accounting.x.view'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $mapa = getDefaultRolePermissionMap(Permission::all());

        foreach (['Contabilista', 'Utilizador', 'Gestor', 'Admin'] as $papel) {
            $this->assertContains('treasury.transfers.view', $mapa[$papel], "{$papel} vê transferências");
            $this->assertContains('treasury.transfers.caixas', $mapa[$papel], "{$papel} continua a ver os caixas");
            $this->assertContains('treasury.transfers.contas', $mapa[$papel], "{$papel} continua a ver o banco");
        }

        $this->assertNotContains('treasury.transfers.contas', $mapa['Vendedor']);
    }
}
