<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * O ecrã de aprovação tem de dizer se houve pagamento.
 *
 * O botão do comprovativo só aparecia quando havia comprovativo. Quando não
 * havia, o ecrã ficava calado — e silêncio não se distingue de "ainda não
 * verifiquei". O que está em jogo é aprovar uma subscrição sem saber se
 * alguém pagou.
 */
class AprovarPedidoMostraPagamentoTest extends TestCase
{
    private function ecra(): string
    {
        return file_get_contents(resource_path('views/livewire/super-admin/billing/billing.blade.php'));
    }

    public function test_com_comprovativo_ha_botao_para_o_ver(): void
    {
        $this->assertStringContainsString('Ver comprovativo', $this->ecra());
        $this->assertStringContainsString('Storage::url($order->payment_proof)', $this->ecra());
    }

    public function test_sem_comprovativo_o_ecra_diz_que_nao_ha(): void
    {
        // É esta a parte que faltava: o caso vazio tem de ser visível.
        $this->assertStringContainsString('Sem comprovativo anexado', $this->ecra());
    }

    public function test_mostra_como_foi_pago_e_a_referencia(): void
    {
        $ecra = $this->ecra();

        $this->assertStringContainsString('$order->payment_method', $ecra);
        $this->assertStringContainsString('$order->payment_reference', $ecra);
    }
}
