<?php

namespace Tests;

use App\Models\Client;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\Warehouse;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Base dos testes que precisam de uma empresa montada e de um utilizador
 * autenticado — ou seja, praticamente todos, porque o sistema é multi-empresa
 * e quase tudo passa por activeTenantId().
 *
 * Usa DatabaseTransactions (e não RefreshDatabase): o esquema de teste é
 * construído uma vez por scripts/prepare_test_db.php e cada teste corre dentro
 * de uma transacção que é revertida no fim. Remigrar 272 migrações por teste
 * seria impraticável — e, hoje, impossível: a ordem das migrações está partida
 * e uma instalação de raiz falha.
 */
abstract class TenantTestCase extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;
    protected User $user;
    protected Warehouse $armazem;
    protected Tax $imposto;
    protected Client $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'     => 'Empresa de Teste',
            'slug'     => 'empresa-teste-' . uniqid(),
            'nif'      => (string) random_int(500000000, 599999999),
            'email'    => 'teste' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name'      => 'Operador de Teste',
            'email'     => 'op' . uniqid() . '@exemplo.ao',
            'password'  => bcrypt('secret'),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->user->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $this->actingAs($this->user);
        session(['active_tenant_id' => $this->tenant->id]);
        setPermissionsTeamId($this->tenant->id);

        // Subscrição activa: sem ela o middleware CheckSubscription redirecciona
        // tudo para /subscription-expired e nenhum pedido HTTP chega ao ecrã.
        $plano = \App\Models\Plan::firstOrCreate(
            ['slug' => 'plano-teste'],
            ['name' => 'Plano de Teste', 'price' => 0, 'is_active' => true]
        );

        \App\Models\Subscription::create([
            'tenant_id'          => $this->tenant->id,
            'plan_id'            => $plano->id,
            'amount'             => 0,
            'status'             => 'active',
            'current_period_end' => now()->addYear(),
        ]);

        // ATENÇÃO: criar um Tenant já provisiona automaticamente armazém,
        // impostos e mais alguma coisa. Reutilizar o que existe em vez de criar
        // um segundo — dois armazéns marcados is_default fazem o
        // Warehouse::getDefault() devolver o outro, e a baixa de stock ia dar
        // "Stock insuficiente" num armazém que nunca recebeu nada.
        $this->imposto = Tax::where('tenant_id', $this->tenant->id)
            ->where('is_default', true)
            ->first()
            ?? Tax::create([
                'tenant_id'  => $this->tenant->id,
                'code'       => 'IVA14',
                'name'       => 'IVA 14%',
                'rate'       => 14,
                'type'       => 'iva',
                'saft_code'  => 'NOR',
                'saft_type'  => 'NOR',
                'is_default' => true,
                'is_active'  => true,
            ]);

        // Os testes fiscais assumem 14%: garantir que o imposto por omissão o é.
        if ((float) $this->imposto->rate !== 14.0 || $this->imposto->saft_type === 'ISE') {
            $this->imposto->update(['rate' => 14, 'saft_code' => 'NOR', 'saft_type' => 'NOR', 'type' => 'iva']);
        }

        \App\Services\Invoicing\TaxResolver::clearCache();

        $this->armazem = Warehouse::getDefault($this->tenant->id)
            ?? Warehouse::create([
                'tenant_id'  => $this->tenant->id,
                'name'       => 'Armazém Principal',
                'code'       => 'PRINCIPAL',
                'is_default' => true,
                'is_active'  => true,
            ]);

        $this->cliente = Client::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Cliente Particular',
            'nif'       => (string) random_int(100000000, 199999999),
            'type'      => 'pessoa_fisica',
            'is_active' => true,
        ]);

        // Séries de emissão. Sem elas o sistema recusa numerar qualquer
        // documento ("Nenhuma serie activa de homologação..."), e bem: um
        // documento fiscal sem série não é rastreável.
        // A coluna que o getIssuanceSeries() filtra é `document_type`
        // ('invoice' para FT, 'pos' para FR), não `type`.
        foreach (['FT' => 'invoice', 'FR' => 'pos'] as $codigo => $documento) {
            $jaExiste = \App\Models\Invoicing\InvoicingSeries::where('tenant_id', $this->tenant->id)
                ->where('document_type', $documento)
                ->where('is_active', true)
                ->exists();

            if ($jaExiste) {
                continue;
            }

            \App\Models\Invoicing\InvoicingSeries::create([
                'tenant_id'       => $this->tenant->id,
                'series_code'     => $codigo,
                'name'            => $codigo . ' (teste)',
                'document_type'   => $documento,
                'agt_environment' => 'sandbox',
                'is_default'      => true,
                'is_active'       => true,
            ]);
        }
    }

    /** Cliente pessoa colectiva — retém IRT sobre serviços. */
    protected function clienteEmpresa(): Client
    {
        return Client::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Cliente Empresa',
            'nif'       => (string) random_int(200000000, 299999999),
            'type'      => 'pessoa_juridica',
            'is_active' => true,
        ]);
    }

    /** Produto do catálogo com stock materializado em linha de armazém. */
    protected function produtoComStock(float $quantidade = 10, float $preco = 5000): \App\Models\Product
    {
        $produto = \App\Models\Product::create([
            'tenant_id'   => $this->tenant->id,
            'name'        => 'Peça ' . uniqid(),
            'code'        => 'P' . strtoupper(substr(uniqid(), -8)),
            'sku'         => 'SKU' . strtoupper(substr(uniqid(), -8)),
            'type'        => 'produto',
            'price'       => $preco,
            'cost'        => $preco / 2,
            'unit'        => 'UN',
            'tax_type'    => 'iva',
            'tax_rate_id' => $this->imposto->id,
            'manage_stock' => true,
            'is_active'   => true,
        ]);

        if ($quantidade > 0) {
            \App\Models\Invoicing\StockMovement::createEntry([
                'warehouse_id' => $this->armazem->id,
                'product_id'   => $produto->id,
                'quantity'     => $quantidade,
                'unit_cost'    => $preco / 2,
                'notes'        => 'Stock inicial de teste',
            ]);
        }

        return $produto->fresh();
    }
}
