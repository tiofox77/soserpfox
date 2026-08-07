<?php

namespace Tests\Feature;

use App\Livewire\POS\POSSystem;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Leitura de código de barras no POS.
 *
 * O leitor escreve o código no campo de procura. Até aqui isso só filtrava a
 * grelha — e a grelha esconde o que está sem stock, por isso ler um artigo
 * esgotado devolvia um ecrã vazio, indistinguível de "este código não existe".
 */
class PosBarcodeTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.pos.access', 'invoicing.pos.view')
             ->comModulo('invoicing');

        // Sem turno aberto o POS redirecciona no mount e o componente nem chega
        // a existir — o teste morria com "array offset on null".
        \App\Models\Invoicing\PosShift::create([
            'tenant_id'    => $this->tenant->id,
            'user_id'      => $this->user->id,
            'shift_number' => 'T' . strtoupper(substr(uniqid(), -8)),
            'opened_at'    => now(),
            'status'       => 'open',
        ]);

        \Illuminate\Support\Facades\Cache::flush();
        \Darryldecode\Cart\Facades\CartFacade::session($this->user->id)->clear();
    }

    private function carrinho()
    {
        return \Darryldecode\Cart\Facades\CartFacade::session($this->user->id)->getContent();
    }

    private function comCodigo(string $codigo, float $stock = 10): Product
    {
        $p = $this->produtoComStock($stock);
        $p->update(['barcode' => $codigo]);

        return $p->fresh();
    }

    /** A mesma chave do componente: o carrinho e por utilizador E empresa. */
    private function chaveDoCarrinho(): string
    {
        return auth()->id() . "_t" . (activeTenantId() ?: 0);
    }

    public function test_ler_um_codigo_poe_o_artigo_no_carrinho(): void
    {
        $p = $this->comCodigo('5601234567890', 10);

        Livewire::test(POSSystem::class)
            ->set('search', '5601234567890')
            ->assertSet('search', '', 'o campo limpa-se para a leitura seguinte');

        $carrinho = \Darryldecode\Cart\Facades\CartFacade::session($this->chaveDoCarrinho())->getContent();

        $this->assertTrue(
            $carrinho->contains(fn ($i) => (int) $i->id === $p->id),
            'o artigo lido tinha de entrar no carrinho'
        );
    }

    public function test_um_artigo_esgotado_diz_porque_nao_entra(): void
    {
        // É este o caso que fazia parecer que a leitura não funcionava: a grelha
        // esconde o que não tem stock e o operador via um ecrã vazio.
        $this->comCodigo('5609999999999', 0);

        Livewire::test(POSSystem::class)
            ->set('search', '5609999999999')
            ->assertDispatched('notify', function (string $evento, array $dados) {
                $carga = $dados[0] ?? $dados;

                return ($carga['type'] ?? null) === 'error'
                    && str_contains($carga['message'] ?? '', 'sem stock');
            });
    }

    public function test_um_artigo_inactivo_nao_se_vende(): void
    {
        $p = $this->comCodigo('5608888888888', 10);
        $p->update(['is_active' => false]);

        Livewire::test(POSSystem::class)
            ->set('search', '5608888888888')
            ->assertDispatched('notify', function (string $evento, array $dados) {
                $carga = $dados[0] ?? $dados;

                return str_contains($carga['message'] ?? '', 'inactivo');
            });

        $carrinho = \Darryldecode\Cart\Facades\CartFacade::session($this->chaveDoCarrinho())->getContent();

        $this->assertCount(0, $carrinho);
    }

    public function test_escrever_o_nome_de_um_artigo_nao_o_atira_para_o_carrinho(): void
    {
        // A leitura só dispara com correspondência EXACTA do código: quem procura
        // à mão pelo nome não pode ver artigos a saltar para o carrinho.
        $p = $this->comCodigo('5607777777777', 10);

        Livewire::test(POSSystem::class)
            ->set('search', mb_substr($p->name, 0, 10))
            ->assertSet('search', mb_substr($p->name, 0, 10));

        $carrinho = \Darryldecode\Cart\Facades\CartFacade::session($this->chaveDoCarrinho())->getContent();

        $this->assertCount(0, $carrinho);
    }

    public function test_um_codigo_parcial_nao_dispara(): void
    {
        $this->comCodigo('5606666666666', 10);

        Livewire::test(POSSystem::class)
            ->set('search', '560666')
            ->assertSet('search', '560666');

        $this->assertCount(0, \Darryldecode\Cart\Facades\CartFacade::session($this->chaveDoCarrinho())->getContent());
    }

    public function test_o_codigo_de_outra_empresa_nao_entra(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Alheia', 'slug' => 'alheia-' . uniqid(),
            'nif' => (string) random_int(700000000, 799999999),
            'email' => 'a' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        Product::create([
            'tenant_id' => $outra->id, 'name' => 'Alheio ' . uniqid(),
            'code' => 'AL' . strtoupper(substr(uniqid(), -6)), 'barcode' => '5605555555555',
            'type' => 'produto', 'price' => 100, 'cost' => 50, 'unit' => 'UN',
            'manage_stock' => true, 'is_active' => true, 'stock_quantity' => 99,
        ]);

        Livewire::test(POSSystem::class)->set('search', '5605555555555');

        $this->assertCount(0, \Darryldecode\Cart\Facades\CartFacade::session($this->chaveDoCarrinho())->getContent());
    }
}
