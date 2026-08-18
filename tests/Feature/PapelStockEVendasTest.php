<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * O papel "Stock e Vendas".
 *
 * O que interessa provar é o que ele NÃO pode: criar um artigo é preciso a
 * quem recebe mercadoria; mexer no preço de um que já existe é decisão de
 * quem gere, e um preço mal mudado sai em facturas até alguém dar por isso.
 */
class PapelStockEVendasTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(): Tenant
    {
        return Tenant::create([
            'name'  => 'Teste Papel ' . uniqid(),
            'email' => uniqid() . '@teste.local',
            'nif'   => '5' . random_int(10000000, 99999999),
        ]);
    }

    /**
     * A base de testes nao traz todas as permissoes semeadas. O que se testa
     * aqui e o comando, nao o estado do ambiente.
     */
    private function garantirPermissoes(): void
    {
        foreach ([
            'invoicing.pos.access', 'invoicing.pos.sell',
            'invoicing.stock.view', 'invoicing.stock.edit',
            'invoicing.products.view', 'invoicing.products.create',
            'invoicing.products.edit', 'invoicing.products.delete',
            'invoicing.warehouse-transfer.view', 'invoicing.warehouse-transfer.create',
            'invoicing.inter-company-transfer.create',
            'invoicing.categories.view', 'invoicing.categories.create',
            'invoicing.categories.edit', 'invoicing.categories.delete',
            'invoicing.suppliers.view', 'invoicing.suppliers.create',
            'invoicing.suppliers.edit', 'invoicing.suppliers.delete',
            'invoicing.pos.reports', 'invoicing.pos.reports.all',
        ] as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
    }

    private function criar(Tenant $t): Role
    {
        $this->garantirPermissoes();

        $this->artisan('papel:stock-vendas', ['--tenant' => $t->id, '--aplicar' => true])
            ->assertSuccessful();

        return Role::where('name', 'Stock e Vendas')->where('tenant_id', $t->id)->firstOrFail();
    }

    public function test_simulacao_nao_cria_o_papel(): void
    {
        $t = $this->empresa();

        $this->artisan('papel:stock-vendas', ['--tenant' => $t->id])->assertSuccessful();

        $this->assertNull(Role::where('name', 'Stock e Vendas')->where('tenant_id', $t->id)->first());
    }

    public function test_pode_vender_gerir_stock_e_criar_artigos(): void
    {
        $t = $this->empresa();
        $papel = $this->criar($t);

        // Pela relação e não por hasPermissionTo: este lê a cache do
        // Spatie, que no mesmo processo do teste ainda é a de antes.
        $tem = $papel->permissions()->pluck('name')->all();

        foreach ([
            'invoicing.pos.access', 'invoicing.pos.sell',
            'invoicing.stock.view', 'invoicing.stock.edit',
            'invoicing.products.view', 'invoicing.products.create',
            'invoicing.products.edit',
            'invoicing.warehouse-transfer.create',
            'invoicing.categories.create', 'invoicing.categories.edit',
            'invoicing.suppliers.create', 'invoicing.suppliers.edit',
            'invoicing.pos.reports', 'invoicing.pos.reports.all',
        ] as $p) {
            $this->assertContains($p, $tem, "faltava poder {$p}");
        }
    }

    public function test_nao_pode_apagar_artigos(): void
    {
        $t = $this->empresa();
        $papel = $this->criar($t);

        $tem = $papel->permissions()->pluck('name')->all();

        // Editar passou a poder; APAGAR nao, e essa e a linha que fica.
        // A transferencia ENTRE EMPRESAS move mercadoria entre patrimonios
        // diferentes: e decisao de quem gere, nao de quem opera.
        foreach ([
            'invoicing.products.delete',
            'invoicing.categories.delete',
            'invoicing.suppliers.delete',
            'invoicing.inter-company-transfer.create',
        ] as $p) {
            $this->assertNotContains($p, $tem, "não podia poder {$p}");
        }
    }

    public function test_correr_duas_vezes_nao_duplica_o_papel(): void
    {
        $t = $this->empresa();
        $this->criar($t);
        $this->criar($t);

        $this->assertSame(
            1,
            Role::where('name', 'Stock e Vendas')->where('tenant_id', $t->id)->count()
        );
    }

    public function test_o_papel_e_da_empresa_e_nao_da_vizinha(): void
    {
        $minha = $this->empresa();
        $vizinha = $this->empresa();

        $papel = $this->criar($minha);

        $this->assertSame($minha->id, (int) $papel->tenant_id);
        $this->assertNull(Role::where('name', 'Stock e Vendas')->where('tenant_id', $vizinha->id)->first());
    }
}
