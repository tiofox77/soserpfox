<?php

namespace Tests\Feature;

use App\Livewire\POS\POSSystem;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O que o balcão tem de saber ANTES de fechar a venda.
 *
 * Depois de emitida a factura, o artigo já saiu da farmácia: um aviso que só
 * aparece no fim não serve para nada. Por isso a receita avisa e o psicotrópico
 * pergunta — e perguntam no momento em que o artigo entra no carrinho.
 */
class PosMedicamentosTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.pos.access', 'invoicing.pos.view')
             ->comModulo('invoicing');

        // Sem turno aberto o POS redirecciona no mount e o componente nem chega
        // a existir.
        \App\Models\Invoicing\PosShift::create([
            'tenant_id'    => $this->tenant->id,
            'user_id'      => $this->user->id,
            'shift_number' => 'T' . strtoupper(substr(uniqid(), -8)),
            'opened_at'    => now(),
            'status'       => 'open',
        ]);

        \Illuminate\Support\Facades\Cache::flush();
        \Darryldecode\Cart\Facades\CartFacade::session($this->chaveDoCarrinho())->clear();
    }

    /** A mesma chave do componente: o carrinho é por utilizador E empresa. */
    private function chaveDoCarrinho(): string
    {
        return $this->user->id . '_t' . $this->tenant->id;
    }

    private function carrinho()
    {
        return \Darryldecode\Cart\Facades\CartFacade::session($this->chaveDoCarrinho())->getContent();
    }

    private function medicamento(array $campos): Product
    {
        $produto = $this->produtoComStock(10);
        $produto->update($campos);

        return $produto->fresh();
    }

    public function test_um_artigo_com_receita_entra_no_carrinho_com_aviso(): void
    {
        // Avisa, NÃO trava: o operador pode ter a receita na mão.
        $p = $this->medicamento(['requires_prescription' => true]);

        Livewire::test(POSSystem::class)
            ->call('addToCart', $p->id)
            ->assertDispatched('notify', function (string $evento, array $dados) {
                $carga = $dados[0] ?? $dados;

                return ($carga['type'] ?? null) === 'warning'
                    && str_contains($carga['message'] ?? '', 'RECEITA MÉDICA');
            });

        $this->assertTrue(
            $this->carrinho()->contains(fn ($i) => (int) $i->id === $p->id),
            'o aviso de receita não pode impedir a venda'
        );
    }

    public function test_um_psicotropico_nao_entra_sem_resposta_humana(): void
    {
        $p = $this->medicamento(['is_controlled' => true]);

        Livewire::test(POSSystem::class)
            ->call('addToCart', $p->id)
            ->assertDispatched('pos-confirmar-controlado');

        $this->assertCount(
            0,
            $this->carrinho(),
            'o controlado só entra depois de alguém confirmar'
        );
    }

    public function test_confirmado_o_psicotropico_entra(): void
    {
        $p = $this->medicamento(['is_controlled' => true]);

        Livewire::test(POSSystem::class)
            ->call('addToCart', $p->id, true);

        $this->assertTrue(
            $this->carrinho()->contains(fn ($i) => (int) $i->id === $p->id),
            'confirmada a venda, o artigo tem de entrar'
        );
    }

    /** A grelha continua a esconder o que não tem stock, controlado ou não. */
    public function test_um_controlado_esgotado_nem_chega_a_perguntar(): void
    {
        $p = $this->produtoComStock(0);
        $p->update(['is_controlled' => true]);

        Livewire::test(POSSystem::class)
            ->call('addToCart', $p->id)
            ->assertNotDispatched('pos-confirmar-controlado');
    }

    public function test_procurar_pela_substancia_activa_encontra_o_medicamento(): void
    {
        // A pergunta que a farmácia faz todos os dias: quem pede "paracetamol"
        // não sabe o nome comercial da caixa.
        $p = $this->medicamento(['active_ingredient' => 'Paracetamol']);

        Livewire::test(POSSystem::class)
            ->set('search', 'paracet')
            ->assertViewHas('products', fn ($produtos) => $produtos->contains('id', $p->id));
    }

    public function test_procurar_pelo_tamanho_encontra_a_peca_de_roupa(): void
    {
        // Nome, código e SKU sem algarismos de propósito. O produtoComStock()
        // gera-os com uniqid(), que é hexadecimal — um "38" ao calhar no meio
        // fazia este teste passar pela procura no nome, mesmo com a procura por
        // tamanho desligada. Um teste que passa com a funcionalidade removida
        // não guarda nada.
        $p = $this->medicamento([
            'name' => 'Camisa de linho',
            'code' => 'CAMISALINHO',
            'sku'  => 'CAMISALINHO',
            'size' => '38',
        ]);

        Livewire::test(POSSystem::class)
            ->set('search', '38')
            ->assertViewHas('products', fn ($produtos) => $produtos->contains('id', $p->id));
    }
}
