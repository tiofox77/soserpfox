<?php

namespace Tests\Feature;

use Tests\TenantTestCase;

class DiagnosticoDaEmpresaTest extends TenantTestCase
{
    /** Corre de ponta a ponta numa empresa e não escreve nada. @test */
    public function corre_e_so_le(): void
    {
        $antes = [\App\Models\Invoicing\Tax::withoutGlobalScopes()->count(), \Spatie\Permission\Models\Role::count(), \App\Models\Invoicing\Warehouse::withoutGlobalScopes()->count()];

        $this->artisan('empresa:diagnostico', ['--tenant' => $this->tenant->id])
            ->expectsOutputToContain('EMPRESA #' . $this->tenant->id)
            ->expectsOutputToContain('Facturação')
            ->assertSuccessful();

        $this->assertSame($antes, [\App\Models\Invoicing\Tax::withoutGlobalScopes()->count(), \Spatie\Permission\Models\Role::count(), \App\Models\Invoicing\Warehouse::withoutGlobalScopes()->count()]);
    }

    /**
     * O DESTINO DO PEDIDO: uma empresa sem plano tem de dizer se o pedido foi
     * recusado, por quem e porquê — sem mostrar a referência do pagamento.
     */
    public function test_mostra_quem_recusou_o_pedido_e_o_historico_das_subscricoes(): void
    {
        $plano = \App\Models\Plan::create([
            'name' => 'Plano ' . uniqid(), 'slug' => 'plano-' . uniqid(), 'price_monthly' => 4900, 'is_active' => true,
        ]);
        \App\Models\Order::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'plan_id' => $plano->id,
            'amount' => 4900, 'billing_cycle' => 'monthly', 'status' => 'rejected', 'payment_method' => 'transfer',
            'payment_reference' => 'REF-SECRETA-123', 'rejected_at' => now(), 'rejected_by' => $this->user->id,
            'rejection_reason' => 'Sem comprovativo',
        ]);

        $d = app(\App\Services\Plataforma\DiagnosticoDaEmpresa::class)->para($this->tenant);
        $pedido = $d['pedidos'][0];

        $this->assertSame('rejected', $pedido['estado']);
        $this->assertNotNull($pedido['recusado_em']);
        $this->assertStringContainsString('#' . $this->user->id, $pedido['recusado_por']);
        $this->assertSame('Sem comprovativo', $pedido['motivo_da_recusa']);
        $this->assertTrue($pedido['com_referencia']);
        $this->assertStringNotContainsString('REF-SECRETA', json_encode($d), 'a referência do pagamento não sai');
        $this->assertArrayHasKey('subscricoes', $d);
    }
}
