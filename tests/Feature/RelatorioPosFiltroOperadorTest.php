<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * O filtro por operador no relatório do POS.
 *
 * O que interessa provar é que ele NÃO dá a volta à permissão: quem só pode
 * ver as suas vendas continua a ver só as suas, escolha o nome que escolher.
 *
 * A DECISÃO É DO SERVIDOR e não do ecrã. A lista, os totais e o Excel saem
 * todos do mesmo `PosSalesReportQuery`; um filtro posto no browser não prende
 * nada, e foi por isso que a regra teve de nascer outra vez na porta em React
 * — a migração tinha-a deixado para trás, e qualquer operador com o direito de
 * VER relatórios passou a ver as vendas dos colegas, totais incluídos.
 */
class RelatorioPosFiltroOperadorTest extends TenantTestCase
{
    private const MAPA = '/api/v1/invoicing/react/pos/relatorio';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.pos.reports')->comModulo('invoicing');

        // O utilizador de teste pode trazer papéis com tudo. Aqui interessa
        // exactamente o contrário: alguém SEM o direito de ver as vendas de
        // todos, que é o caso que o filtro não pode contornar.
        $this->semVerTudo();
    }

    /** Tira o direito de ver as vendas de todos, venha ele de onde vier. */
    private function semVerTudo(): void
    {
        setPermissionsTeamId($this->tenant->id);

        $p = Permission::findOrCreate('invoicing.pos.reports.all', 'web');

        $this->user->revokePermissionTo($p);

        foreach ($this->user->roles as $papel) {
            $papel->revokePermissionTo($p);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->forgetCachedPermissions();
    }

    /** Um segundo operador, com uma venda sua. */
    private function outroOperador(): User
    {
        $u = User::create([
            'name' => 'Outra Caixa', 'email' => uniqid().'@t.local',
            'password' => bcrypt(uniqid()), 'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);

        $u->tenants()->syncWithoutDetaching([$this->tenant->id]);

        SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FR OUTRA/'.random_int(1000, 9999),
            'invoice_date' => now(),
            'status' => 'paid',
            'total' => 5000,
            'created_by' => $u->id,
        ]);

        return $u;
    }

    /** Uma venda do próprio, para haver com que comparar. */
    private function vendaMinha(): SalesInvoice
    {
        return SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FR MINHA/'.random_int(1000, 9999),
            'invoice_date' => now(),
            'status' => 'paid',
            'total' => 1000,
            'created_by' => $this->user->id,
        ]);
    }

    private function mapa(array $filtros = []): array
    {
        return $this->getJson(self::MAPA.'?'.http_build_query(array_merge([
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
        ], $filtros)))->assertOk()->json();
    }

    /**
     * A PROVA: sem o direito de ver todas, o mapa fica preso às suas.
     *
     * E o número escrito à mão no pedido não muda nada — era o furo que o
     * ecrã em Livewire fechava e que a porta nova tinha deixado aberto.
     */
    public function test_sem_o_direito_de_ver_todas_o_mapa_fica_nas_suas(): void
    {
        $outro = $this->outroOperador();
        $minha = $this->vendaMinha();

        $numeros = collect($this->mapa()['data'])->pluck('numero');

        $this->assertContains($minha->invoice_number, $numeros);
        $this->assertStringNotContainsString('FR OUTRA', $numeros->implode(' '));

        // Nem sequer escolhendo o colega à mão.
        $comEscolha = collect($this->mapa(['user_id' => $outro->id])['data'])->pluck('numero');

        $this->assertSame($numeros->all(), $comEscolha->all(),
            'escolher um operador não pode dar acesso ao que a permissão recusa');
    }

    /** E o selector nem aparece a quem não pode usá-lo. */
    public function test_sem_o_direito_o_selector_nao_aparece(): void
    {
        $this->outroOperador();

        $meta = $this->mapa()['meta'];

        $this->assertFalse($meta['pode_ver_todas']);
        $this->assertSame([], $meta['operadores']);
    }

    /** Com a permissão de todos, a lista de operadores aparece. */
    public function test_com_a_permissao_de_todos_a_lista_aparece(): void
    {
        $outro = $this->outroOperador();

        $this->comPermissoes('invoicing.pos.reports', 'invoicing.pos.reports.all');

        $meta = $this->mapa()['meta'];

        $this->assertTrue($meta['pode_ver_todas']);
        $this->assertContains((string) $outro->id, collect($meta['operadores'])->pluck('valor')->all());
    }

    /** E escolher um operador muda a LISTA, não só as estatísticas. */
    public function test_escolher_um_operador_muda_a_lista(): void
    {
        $outro = $this->outroOperador();
        $minha = $this->vendaMinha();

        $this->comPermissoes('invoicing.pos.reports', 'invoicing.pos.reports.all');

        $todas = collect($this->mapa()['data'])->pluck('numero');

        $this->assertContains($minha->invoice_number, $todas);

        $so = collect($this->mapa(['user_id' => $outro->id])['data'])->pluck('numero');

        $this->assertNotContains($minha->invoice_number, $so,
            'a lista vai por outra consulta que não o escopo: sem isto, escolher um nome mudava os totais e deixava a lista igual');
    }

    /** Sem escolher ninguém, quem pode ver todas vê todas. */
    public function test_sem_escolher_ninguem_a_lista_mostra_todos(): void
    {
        $this->outroOperador();
        $minha = $this->vendaMinha();

        $this->comPermissoes('invoicing.pos.reports', 'invoicing.pos.reports.all');

        $numeros = collect($this->mapa()['data'])->pluck('numero')->implode(' ');

        $this->assertStringContainsString($minha->invoice_number, $numeros);
        $this->assertStringContainsString('FR OUTRA', $numeros);
    }

    /**
     * A PERMISSÃO CHAMA-SE `invoicing.pos.reports`, sem `.view`.
     *
     * A porta pedia `invoicing.pos.reports.view`, que não existe na base — e
     * com os curingas desligados, `can()` de uma permissão inexistente é
     * sempre falso. O caixa com exactamente a permissão que a ROTA exige abria
     * a página e via um 403 em cada pedido.
     */
    public function test_a_permissao_da_rota_chega_para_abrir_o_mapa(): void
    {
        $caixa = User::create([
            'name' => 'Caixa Comum', 'email' => uniqid().'@t.local',
            'password' => bcrypt(uniqid()), 'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);

        $caixa->tenants()->syncWithoutDetaching([$this->tenant->id]);

        setPermissionsTeamId($this->tenant->id);
        $caixa->givePermissionTo('invoicing.pos.reports');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($caixa)->getJson(self::MAPA)->assertOk();
    }
}
