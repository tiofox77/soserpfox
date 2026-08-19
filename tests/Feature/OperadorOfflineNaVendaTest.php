<?php

namespace Tests\Feature;

use App\Models\Invoicing\PosShift;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * O operador de uma venda/turno feito offline é quem entrou por PIN, não a
 * sessão do último a sincronizar. Sem isto, a venda de B era comunicada à
 * AGT em nome de A.
 */
class OperadorOfflineNaVendaTest extends TenantTestCase
{
    private function funcionarioB(): User
    {
        $b = User::create([
            'name' => 'Caixa B',
            'email' => 'caixab@empresa.ao',
            'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $b->tenants()->syncWithoutDetaching([$this->tenant->id]);
        return $b;
    }

    private function itens(): array
    {
        $p = $this->produtoComStock(10, 1000);
        return [[
            'product_id' => $p->id,
            'product_name' => $p->name,
            'quantity' => 1,
            'unit_price' => 1000,
            'tax_rate' => 0,
        ]];
    }

    public function test_a_venda_offline_fica_no_operador_do_pin(): void
    {
        $b = $this->funcionarioB();

        // A sessão é do utilizador A (o do TenantTestCase), mas a venda diz B.
        $r = $this->actingAs($this->user)->postJson('/api/v1/invoicing/pos/sale', [
            'local_uuid' => 'venda-b-1',
            'operator_id' => $b->id,
            'operator_email' => $b->email,
            'payment_method' => 'cash',
            'items' => $this->itens(),
        ])->assertSuccessful();

        $invoice = \App\Models\Invoicing\SalesInvoice::withoutGlobalScopes()
            ->where('local_uuid', 'venda-b-1')->firstOrFail();

        $this->assertSame($b->id, (int) $invoice->created_by,
            'a venda tinha de ficar atribuída ao operador que entrou por PIN (B)');
        $this->assertNotSame($this->user->id, (int) $invoice->created_by);
    }

    public function test_operador_forjado_de_outra_empresa_cai_na_sessao(): void
    {
        // Um utilizador que NÃO pertence a este tenant.
        $intruso = User::create([
            'name' => 'Intruso', 'email' => 'intruso@outra.ao',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);

        $this->actingAs($this->user)->postJson('/api/v1/invoicing/pos/sale', [
            'local_uuid' => 'venda-forjada',
            'operator_id' => $intruso->id,
            'payment_method' => 'cash',
            'items' => $this->itens(),
        ])->assertSuccessful();

        $invoice = \App\Models\Invoicing\SalesInvoice::withoutGlobalScopes()
            ->where('local_uuid', 'venda-forjada')->firstOrFail();

        // Rejeitado o operador forjado → fica na sessão (A), nunca no intruso.
        $this->assertSame($this->user->id, (int) $invoice->created_by);
    }

    public function test_o_turno_offline_fica_no_operador_do_pin(): void
    {
        $b = $this->funcionarioB();

        $this->actingAs($this->user)->postJson('/api/v1/invoicing/pos/shift/open', [
            'local_uuid' => 'turno-b-1',
            'operator_id' => $b->id,
            'opening_balance' => 5000,
        ])->assertSuccessful();

        $shift = PosShift::where('tenant_id', $this->tenant->id)->latest('id')->first();
        $this->assertSame($b->id, (int) $shift->user_id);
    }
}
