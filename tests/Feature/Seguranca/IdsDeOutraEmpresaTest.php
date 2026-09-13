<?php

namespace Tests\Feature\Seguranca;

use App\Models\Client;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * UM ID DE OUTRA EMPRESA NÃO ENTRA NUM DOCUMENTO NEM NO STOCK DESTA.
 *
 * O documento guardava o cliente de outra casa e a ficha devolvia o nome, o NIF
 * e o email dele; um ajuste de stock com o artigo de outra casa devolvia o nome
 * e criava-lhe stock aqui (auditoria de segurança de 2026-09-13).
 */
class IdsDeOutraEmpresaTest extends TenantTestCase
{
    private function outraEmpresa(): Tenant
    {
        return Tenant::create(['name' => 'Vizinha', 'slug' => 'vizinha-' . uniqid(), 'nif' => (string) random_int(500000000, 599999999), 'email' => 'v' . uniqid() . '@v.ao', 'is_active' => true]);
    }

    public function test_a_proforma_nao_aceita_o_cliente_de_outra_empresa(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.sales.proformas.view', 'invoicing.sales.proformas.create');

        $vizinha = $this->outraEmpresa();
        $alheio = Client::withoutGlobalScopes()->create(['tenant_id' => $vizinha->id, 'type' => 'pessoa_juridica', 'name' => 'Cliente da Vizinha', 'nif' => '5000000123', 'country' => 'AO', 'is_active' => true]);

        $this->postJson('/api/v1/invoicing/react/emissor/proformas-venda', [
            'parte_id' => $alheio->id,
            'data' => now()->toDateString(),
            'linhas' => [['description' => 'Serviço', 'quantity' => 1, 'price' => 1000]],
        ])->assertStatus(422)->assertJsonValidationErrors('parte_id');
    }

    public function test_o_ajuste_de_stock_recusa_o_artigo_de_outra_empresa(): void
    {
        $this->comModulo('invoicing');
        $this->comPermissoes('invoicing.stock.view', 'invoicing.stock.edit');

        $vizinha = $this->outraEmpresa();
        $alheio = Product::withoutGlobalScopes()->create([
            'tenant_id' => $vizinha->id, 'name' => 'Artigo Secreto da Vizinha', 'code' => 'VIZ-' . uniqid(),
            'type' => 'produto', 'price' => 999, 'cost' => 500, 'unit' => 'UN', 'is_active' => true,
        ]);

        $r = $this->postJson('/api/v1/invoicing/react/transferencias/ajuste', [
            'armazem' => $this->armazem->id, 'tipo' => 'in', 'motivo' => 'ajuste',
            'itens' => [['product_id' => $alheio->id, 'quantity' => 1]],
        ]);

        $this->assertNotEquals(200, $r->status());
        $this->assertStringNotContainsString('Artigo Secreto da Vizinha', $r->getContent());
        $this->assertSame(0, \App\Models\Invoicing\Stock::withoutGlobalScopes()->where('product_id', $alheio->id)->count());
    }
}
