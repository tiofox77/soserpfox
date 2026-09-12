<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Plan;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * O ecrã de aprovação tem de dizer se houve pagamento.
 *
 * O botão do comprovativo só aparecia quando havia comprovativo. Quando não
 * havia, o ecrã ficava calado — e silêncio não se distingue de "ainda não
 * verifiquei". O que está em jogo é aprovar uma subscrição sem saber se
 * alguém pagou.
 *
 * O ecrã passou a React: a API entrega o método, a referência e o comprovativo
 * (ou nulo), e o ecrã tem as duas frases — a do botão e a do caso vazio.
 */
class AprovarPedidoMostraPagamentoTest extends TenantTestCase
{
    private function pedido(array $troca = []): Order
    {
        $plano = Plan::create([
            'name' => 'Plano', 'slug' => 'plano-'.uniqid(), 'description' => 'x',
            'price_monthly' => 1000, 'price_yearly' => 10000, 'is_active' => true,
        ]);

        return Order::create(array_merge([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'plan_id' => $plano->id,
            'amount' => 1000, 'billing_cycle' => 'monthly', 'status' => 'pending',
            'payment_method' => 'multicaixa', 'payment_reference' => 'TRF-2026-77',
        ], $troca));
    }

    private function linha(Order $pedido): array
    {
        $this->user->forceFill(['is_super_admin' => true])->save();

        return collect(
            $this->actingAs($this->user->fresh())->getJson('/api/v1/plataforma/react/facturacao')->assertOk()->json('pedidos')
        )->firstWhere('id', $pedido->id);
    }

    private function ecra(): string
    {
        return file_get_contents(resource_path('js/ecras/plataforma/Facturacao.tsx'));
    }

    public function test_com_comprovativo_ha_botao_para_o_ver(): void
    {
        Storage::fake('public');
        $pedido = $this->pedido(['payment_proof' => 'comprovativos/talao.pdf']);

        $this->assertStringEndsWith('comprovativos/talao.pdf', (string) $this->linha($pedido)['comprovativo']);
        $this->assertStringContainsString("t('Ver comprovativo')", $this->ecra());
    }

    public function test_sem_comprovativo_o_ecra_diz_que_nao_ha(): void
    {
        // É esta a parte que faltava: o caso vazio tem de ser visível.
        $this->assertNull($this->linha($this->pedido())['comprovativo']);
        $this->assertStringContainsString("t('Sem comprovativo anexado')", $this->ecra());
    }

    public function test_mostra_como_foi_pago_e_a_referencia(): void
    {
        $linha = $this->linha($this->pedido());

        $this->assertSame('multicaixa', $linha['metodo']);
        $this->assertSame('TRF-2026-77', $linha['referencia']);
    }
}
