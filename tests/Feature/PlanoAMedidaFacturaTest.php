<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Module;
use App\Models\Order;
use App\Services\Plataforma\PlanoAMedida;
use App\Support\CicloDeFacturacao;
use Tests\TenantTestCase;

/**
 * O plano à medida entra na facturação: emite contrato (pedido) e factura.
 *
 * Sem isto, o cliente ficava com acesso total e nenhum documento — não
 * aparecia na facturação, não contava na receita, e ninguém sabia se havia
 * dinheiro a receber.
 */
class PlanoAMedidaFacturaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['invoicing', 'treasury', 'rh'] as $slug) {
            Module::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'is_core' => false]);
        }
    }

    private function montar(array $extra = [])
    {
        return app(PlanoAMedida::class)->criarEAtribuir($this->tenant, array_merge([
            'nome'         => 'Plano X',
            'modulos'      => ['invoicing', 'rh'],
            'preco_mensal' => 30000,
            'ciclo'        => 'monthly',
        ], $extra));
    }

    public function test_emite_factura(): void
    {
        $plano = $this->montar();

        $factura = Invoice::where('tenant_id', $this->tenant->id)->latest('id')->first();

        $this->assertNotNull($factura, 'tinha de ser emitida factura');
        $this->assertSame(30000.0, (float) $factura->total);
        $this->assertNotNull($factura->invoice_number);
    }

    public function test_sem_pagamento_a_factura_fica_pendente_com_vencimento(): void
    {
        $this->montar(['ja_pago' => false]);

        $factura = Invoice::where('tenant_id', $this->tenant->id)->latest('id')->first();

        $this->assertSame('pending', $factura->status);
        $this->assertNull($factura->paid_at);
        $this->assertNotNull($factura->due_date, 'o que se deve tem de ter prazo');
    }

    public function test_com_pagamento_a_factura_sai_paga(): void
    {
        $this->montar(['ja_pago' => true]);

        $factura = Invoice::where('tenant_id', $this->tenant->id)->latest('id')->first();

        $this->assertSame('paid', $factura->status);
        $this->assertNotNull($factura->paid_at);
    }

    public function test_o_contrato_fica_registado_como_pedido_aprovado(): void
    {
        $plano = $this->montar();

        $pedido = Order::where('tenant_id', $this->tenant->id)->latest('id')->first();

        $this->assertNotNull($pedido);
        $this->assertSame($plano->id, $pedido->plan_id);
        $this->assertSame(30000.0, (float) $pedido->amount);
        // Aprovado: a subscrição já foi aplicada, não há nada a decidir.
        $this->assertSame('approved', $pedido->status);
    }

    public function test_o_pedido_nao_cria_uma_segunda_subscricao(): void
    {
        $this->montar();

        // O observer só reage à MUDANÇA de estado, não à criação.
        $this->assertSame(
            1,
            $this->tenant->subscriptions()->whereIn('status', ['active', 'trial'])->count(),
            'criar o pedido aprovado não pode duplicar a subscrição'
        );
    }

    public function test_o_ciclo_anual_usa_o_preco_e_o_periodo_anuais(): void
    {
        $plano = $this->montar(['ciclo' => 'yearly', 'preco_mensal' => 10000]);

        $factura = Invoice::where('tenant_id', $this->tenant->id)->latest('id')->first();
        $pedido  = Order::where('tenant_id', $this->tenant->id)->latest('id')->first();

        // Anual em branco = 12x o mensal.
        $this->assertSame(120000.0, (float) $factura->total);
        $this->assertSame('yearly', $pedido->billing_cycle);

        // E o período do plano anual dá 14 meses (12 + 2 de oferta).
        $sub = $this->tenant->subscriptions()->latest('id')->first();
        $this->assertEqualsWithDelta(
            14,
            now()->diffInMonths($sub->current_period_end),
            1
        );
    }

    public function test_o_ciclo_e_o_mesmo_dos_planos_normais(): void
    {
        // A fonte única que todos os caminhos usam.
        $this->assertSame(14, CicloDeFacturacao::meses('yearly'));
        $this->assertSame(6, CicloDeFacturacao::meses('semiannual'));
        $this->assertSame(3, CicloDeFacturacao::meses('quarterly'));
        $this->assertSame(1, CicloDeFacturacao::meses('monthly'));
        $this->assertSame(1, CicloDeFacturacao::meses('lixo'), 'ciclo desconhecido é mensal');
    }
}
