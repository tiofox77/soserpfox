<?php

namespace Tests\Feature;

use App\Livewire\POS\SalesReport;
use App\Models\Invoicing\SalesInvoice;
use App\Models\User;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O filtro por operador no relatório do POS.
 *
 * O que interessa provar é que ele NÃO dá a volta à permissão: quem só pode
 * ver as suas vendas continua a ver só as suas, escolha o nome que escolher.
 */
class RelatorioPosFiltroOperadorTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.pos.reports')->comModulo('invoicing');

        // O utilizador de teste pode trazer papeis com tudo. Aqui interessa
        // exactamente o contrario: alguem SEM o direito de ver as vendas de
        // todos, que e o caso que o filtro nao pode contornar.
        $this->semVerTudo();
    }

    /**
     * Uma caixa comum, criada aqui.
     *
     * O utilizador do fixture traz papeis com tudo, e o que este teste precisa
     * e do contrario: alguem SEM o direito de ver as vendas de todos, que e o
     * caso que o filtro nao pode contornar.
     */
    private function caixaComum(): User
    {
        $u = User::create([
            'name' => 'Caixa Comum', 'email' => uniqid() . '@t.local',
            'password' => bcrypt(uniqid()), 'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);

        setPermissionsTeamId($this->tenant->id);
        $u->givePermissionTo('invoicing.pos.reports');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $u;
    }

    /** Tira o direito de ver as vendas de todos, venha ele de onde vier. */
    private function semVerTudo(): void
    {
        setPermissionsTeamId($this->tenant->id);

        $p = \Spatie\Permission\Models\Permission::findOrCreate('invoicing.pos.reports.all', 'web');

        $this->user->revokePermissionTo($p);

        foreach ($this->user->roles as $papel) {
            $papel->revokePermissionTo($p);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->forgetCachedPermissions();
    }

    private function outroOperador(): User
    {
        $u = User::create([
            'name' => 'Outra Caixa', 'email' => uniqid() . '@t.local',
            'password' => bcrypt(uniqid()), 'tenant_id' => $this->tenant->id, 'is_active' => true,
        ]);

        SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->clienteEmpresa()->id,
            'invoice_number' => 'FR OUTRA/' . random_int(1000, 9999),
            'invoice_date'   => now(),
            'status'         => 'paid',
            'total'          => 5000,
            'created_by'     => $u->id,
        ]);

        return $u;
    }

    public function test_com_a_permissao_de_todos_a_lista_aparece(): void
    {
        $outro = $this->outroOperador();

        $this->comPermissoes('invoicing.pos.reports', 'invoicing.pos.reports.all');

        $c = Livewire::test(SalesReport::class);

        $this->assertFalse($c->instance()->ownOnly);
        $this->assertTrue($c->instance()->operadores->contains('id', $outro->id));
    }

}
