<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * Isolamento entre empresas nas acções do Livewire.
 *
 * O `IdentifyTenant` saltava por completo os pedidos `livewire/*`, e como é o
 * único sítio do caminho web que chama `setPermissionsTeamId()`, cada acção
 * Livewire corria com a equipa de permissões por omissão — `users.tenant_id` —
 * em vez da empresa activa da sessão. Quem pertencesse a duas empresas fazia na
 * segunda o que só tinha autorização para fazer na primeira.
 */
class TenantIsolationLivewireTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.stock.view', 'invoicing.stock.edit')
             ->comModulo('invoicing');
    }

    /** Segunda empresa do mesmo utilizador, onde ele NÃO tem permissão nenhuma. */
    private function segundaEmpresa(): Tenant
    {
        $b = Tenant::create([
            'name' => 'Empresa B', 'slug' => 'b-' . uniqid(),
            'nif' => (string) random_int(600000000, 699999999),
            'email' => 'b' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $this->user->tenants()->syncWithoutDetaching([$b->id]);

        $modulo = \App\Models\Module::where('slug', 'invoicing')->first();
        $b->modules()->syncWithoutDetaching([$modulo->id => ['is_active' => true, 'activated_at' => now()]]);

        \App\Models\Subscription::create([
            'tenant_id' => $b->id,
            'plan_id'   => \App\Models\Plan::where('slug', 'plano-teste')->value('id'),
            'amount'    => 0, 'status' => 'active',
            'current_period_end' => now()->addYear(),
        ]);

        return $b;
    }

    /** Snapshot do componente de stock, tal como o navegador o recebe. */
    private function snapshotDoStock(): string
    {
        $html = $this->get('/invoicing/stock')->assertOk()->getContent();

        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $m);

        foreach ($m[1] as $bruto) {
            $cru = html_entity_decode($bruto, ENT_QUOTES);

            if (str_contains($cru, 'entryWarehouseId')) {
                return $cru;
            }
        }

        $this->fail('snapshot do StockManagement não encontrado na página');
    }

    public function test_uma_accao_livewire_usa_as_permissoes_da_empresa_activa(): void
    {
        $b = $this->segundaEmpresa();

        // O separador foi aberto na empresa A, onde tem permissão.
        $snapshot = $this->snapshotDoStock();

        // Noutro separador o utilizador trocou para a empresa B.
        session(['active_tenant_id' => $b->id]);

        // Em produção cada pedido resolve o utilizador de novo a partir da
        // sessão; nos testes o `actingAs` guarda a MESMA instância, com a
        // relação de permissões já carregada sob a empresa A. Sem esta
        // instância fresca o teste mediria o cache do modelo, não a guarda.
        $this->actingAs(User::findOrFail($this->user->id));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $armazemB = Warehouse::withoutGlobalScopes()->where('tenant_id', $b->id)->first();
        $produtoB = Product::create([
            'tenant_id' => $b->id, 'name' => 'Peça B ' . uniqid(),
            'code' => 'B' . strtoupper(substr(uniqid(), -8)),
            'type' => 'produto', 'price' => 1000, 'cost' => 500, 'unit' => 'UN',
            'manage_stock' => true, 'is_active' => true,
        ]);

        // Clique no separador antigo: um POST /livewire/update verdadeiro.
        $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => $snapshot,
                'updates'  => [
                    'entryWarehouseId' => (string) $armazemB->id,
                    'entryItems'       => [[
                        'product_id'   => $produtoB->id,
                        'product_name' => $produtoB->name,
                        'product_code' => $produtoB->code,
                        'unit' => 'UN', 'op' => 'add',
                        'quantity' => 7, 'unit_cost' => 500, 'current_qty' => 0,
                    ]],
                ],
                'calls' => [['path' => '', 'method' => 'saveEntry', 'params' => []]],
            ]],
        ]);

        $this->assertDatabaseMissing('invoicing_stock_movements', [
            'tenant_id'  => $b->id,
            'product_id' => $produtoB->id,
        ]);

        $this->assertEquals(
            0,
            (float) Stock::withoutGlobalScopes()
                ->where('tenant_id', $b->id)->where('product_id', $produtoB->id)->sum('quantity'),
            'o stock da empresa B não pode ter mexido: lá o utilizador não tem permissão'
        );
    }

    public function test_o_armazem_de_outra_empresa_e_recusado(): void
    {
        // A outra metade da mesma falha: as regras `exists:` não filtravam por
        // empresa. Bastava um id alheio no pedido para escrever stock num
        // armazém de outra empresa — e como o ecrã filtra por empresa, a linha
        // fantasma nem sequer aparecia.
        $outra = Tenant::create([
            'name' => 'Alheia', 'slug' => 'alheia-' . uniqid(),
            'nif' => (string) random_int(700000000, 799999999),
            'email' => 'a' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $armazemAlheio = Warehouse::withoutGlobalScopes()->where('tenant_id', $outra->id)->first();

        $this->assertNotNull($armazemAlheio);

        $p = $this->produtoComStock(10);

        \Livewire\Livewire::test(\App\Livewire\Invoicing\StockManagement::class)
            ->call('openEntryModal')
            ->set('entryWarehouseId', $armazemAlheio->id)
            ->set('entryItems', [[
                'product_id' => $p->id, 'product_name' => $p->name, 'product_code' => $p->code,
                'unit' => 'UN', 'op' => 'add', 'quantity' => 5, 'unit_cost' => 100, 'current_qty' => 0,
            ]])
            ->call('saveEntry')
            ->assertHasErrors('entryWarehouseId');

        $this->assertEquals(
            0,
            (float) Stock::withoutGlobalScopes()
                ->where('warehouse_id', $armazemAlheio->id)->where('product_id', $p->id)->sum('quantity')
        );
    }

    public function test_um_artigo_de_outra_empresa_e_recusado(): void
    {
        $outra = Tenant::create([
            'name' => 'Alheia', 'slug' => 'alheia-' . uniqid(),
            'nif' => (string) random_int(700000000, 799999999),
            'email' => 'a' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $artigoAlheio = Product::create([
            'tenant_id' => $outra->id, 'name' => 'Alheio ' . uniqid(),
            'code' => 'AL' . strtoupper(substr(uniqid(), -8)),
            'type' => 'produto', 'price' => 100, 'cost' => 50, 'unit' => 'UN',
            'manage_stock' => true, 'is_active' => true,
        ]);

        \Livewire\Livewire::test(\App\Livewire\Invoicing\StockManagement::class)
            ->call('openEntryModal')
            ->set('entryWarehouseId', $this->armazem->id)
            ->set('entryItems', [[
                'product_id' => $artigoAlheio->id, 'product_name' => $artigoAlheio->name,
                'product_code' => $artigoAlheio->code,
                'unit' => 'UN', 'op' => 'add', 'quantity' => 5, 'unit_cost' => 100, 'current_qty' => 0,
            ]])
            ->call('saveEntry')
            ->assertHasErrors('entryItems.0.product_id');

        $this->assertEquals(
            0,
            (float) Stock::withoutGlobalScopes()->where('product_id', $artigoAlheio->id)->sum('quantity')
        );
    }
}
