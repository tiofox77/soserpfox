<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\StockManagement;
use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * A lista de produtos do modal de movimentação de stock.
 *
 * Em produção: `Undefined array key "product_id"` num POST a /livewire/update,
 * no ecrã /invoicing/stock, com 34 linhas na lista.
 *
 * O MECANISMO
 * -----------
 * As ligações do formulário são por ÍNDICE — `entryItems.20.quantity` — e o
 * `removeEntryItem` fazia `array_splice`, que REINDEXA. A quantidade tem meio
 * segundo de espera antes de ser enviada.
 *
 * Escrever uma quantidade numa linha e, antes de o meio segundo passar, apagar
 * outra linha acima: a remoção chega primeiro e desloca tudo; a quantidade
 * chega a seguir para um índice que já não é o mesmo. Se ficou para lá do fim
 * da lista, o Livewire CRIA a entrada só com a quantidade — sem `product_id` —
 * e o próximo código que a leia rebenta.
 *
 * E há o caso pior, que não rebenta: se o índice ainda existir mas for de
 * outro produto, a quantidade escrita vai parar ao produto errado.
 */
class StockEntryItemsTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // `saveEntry` exige a permissão de editar stock — o utilizador do
        // TenantTestCase nasce sem papel nenhum, de propósito.
        $this->comPermissoes('invoicing.stock.edit');
    }

    private function artigo(string $nome): Product
    {
        return Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => $nome,
            'sku'            => 'SKU-' . uniqid(),
            'code'           => 'C-' . uniqid(),
            'price'          => 100,
            'cost'           => 50,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);
    }

    private function comTresArtigos()
    {
        $a = $this->artigo('Artigo A');
        $b = $this->artigo('Artigo B');
        $c = $this->artigo('Artigo C');

        return [
            Livewire::test(StockManagement::class)
                ->set('entryWarehouseId', $this->armazem->id)
                ->call('addEntryItem', $a->id)
                ->call('addEntryItem', $b->id)
                ->call('addEntryItem', $c->id),
            $a, $b, $c,
        ];
    }

    public function test_uma_quantidade_para_um_indice_que_ja_nao_existe_nao_rebenta(): void
    {
        // Exactamente o que aconteceu em produção: a linha foi removida e a
        // quantidade que estava a caminho chegou para lá do fim da lista.
        [$ecra] = $this->comTresArtigos();

        $ecra->call('removeEntryItem', 0)
             ->set('entryItems.9.quantity', 5)   // índice que nunca existiu
             ->call('addEntryItem', $this->artigo('Artigo D')->id)
             ->assertHasNoErrors();

        // Chegar aqui já é o teste: não lançou "Undefined array key product_id".
        $this->assertTrue(true);
    }

    public function test_uma_linha_sem_produto_nao_chega_a_gravacao(): void
    {
        [$ecra] = $this->comTresArtigos();

        $ecra->set('entryItems.9.quantity', 5)
             ->set('entryNotes', 'Entrada de teste')
             ->call('saveEntry');

        // Os três legítimos entram; a linha órfã é descartada em silêncio.
        $this->assertSame(
            3,
            StockMovement::where('tenant_id', $this->tenant->id)->count(),
            'só os produtos a sério podem gerar movimento'
        );
    }

    public function test_remover_uma_linha_nao_desloca_as_outras(): void
    {
        // A causa de raiz. Com `array_splice` as linhas de baixo mudavam de
        // índice, e uma quantidade a caminho ia parar ao produto errado — sem
        // erro nenhum, o que é pior do que rebentar.
        [$ecra, $a, $b, $c] = $this->comTresArtigos();

        $itens = $ecra->call('removeEntryItem', 0)->get('entryItems');

        // O que estava no índice 1 (artigo B) tem de CONTINUAR no índice 1.
        $this->assertArrayHasKey(1, $itens);
        $this->assertSame($b->id, (int) $itens[1]['product_id'], 'o artigo B não pode mudar de posição');

        $this->assertArrayHasKey(2, $itens);
        $this->assertSame($c->id, (int) $itens[2]['product_id']);

        $this->assertArrayNotHasKey(0, $itens, 'o removido sai');
    }

    public function test_a_quantidade_escrita_fica_no_produto_certo(): void
    {
        // O caso silencioso: a quantidade ia parar a outro produto.
        [$ecra, $a, $b, $c] = $this->comTresArtigos();

        $itens = $ecra
            ->set('entryItems.2.quantity', 7)    // artigo C
            ->call('removeEntryItem', 0)          // remove o artigo A
            ->get('entryItems');

        $this->assertSame(7.0, (float) $itens[2]['quantity']);
        $this->assertSame($c->id, (int) $itens[2]['product_id'], 'a quantidade tem de continuar no artigo C');
    }

    public function test_o_mesmo_produto_nao_entra_duas_vezes(): void
    {
        $a = $this->artigo('Artigo A');

        $itens = Livewire::test(StockManagement::class)
            ->set('entryWarehouseId', $this->armazem->id)
            ->call('addEntryItem', $a->id)
            ->call('addEntryItem', $a->id)
            ->get('entryItems');

        $this->assertCount(1, $itens);
    }

    public function test_gravar_com_a_lista_esburacada_grava_tudo_o_que_e_valido(): void
    {
        // Depois de remover do meio, os índices ficam com buracos. A gravação
        // tem de percorrer o que lá está, não assumir que vai de 0 a n-1.
        [$ecra, $a, $b, $c] = $this->comTresArtigos();

        $ecra->call('removeEntryItem', 1)   // sai o B, ficam os índices 0 e 2
             ->set('entryNotes', 'Entrada')
             ->call('saveEntry');

        $movimentos = StockMovement::where('tenant_id', $this->tenant->id)->pluck('product_id')->all();

        $this->assertCount(2, $movimentos);
        $this->assertContains($a->id, $movimentos);
        $this->assertContains($c->id, $movimentos);
        $this->assertNotContains($b->id, $movimentos);
    }
}
