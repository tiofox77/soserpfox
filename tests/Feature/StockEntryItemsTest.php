<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * As linhas da movimentação de stock — hoje pela API do ecrã em React.
 *
 * A HISTÓRIA
 * ----------
 * Em produção: `Undefined array key "product_id"` num POST a /livewire/update,
 * no ecrã /invoicing/stock, com 34 linhas na lista.
 *
 * As ligações do formulário Livewire eram por ÍNDICE — `entryItems.20.quantity`
 * — e o `removeEntryItem` fazia `array_splice`, que REINDEXA. A quantidade
 * tinha meio segundo de espera antes de ser enviada. Escrever uma quantidade
 * numa linha e, antes desse meio segundo, apagar outra linha acima: a remoção
 * chegava primeiro e deslocava tudo; a quantidade chegava a seguir para um
 * índice que já não era o mesmo. Se fosse para lá do fim da lista, o Livewire
 * CRIAVA a entrada só com a quantidade — sem `product_id` — e o próximo código
 * que a lesse rebentava. E havia o caso pior, que não rebentava: a quantidade
 * ia parar ao produto errado, em silêncio.
 *
 * O QUE MUDOU
 * -----------
 * O ecrã é React e a lista do lote vive no cliente: cada linha leva o seu
 * `product_id` colado e o pedido chega inteiro, de uma vez, a
 * `POST /api/v1/invoicing/react/stock/entrada`. Não há índice a correr atrás de
 * uma quantidade. O que se guarda aqui é a outra metade da lição, que continua
 * a valer e agora é do SERVIDOR: uma linha sem artigo nunca gera movimento, e a
 * quantidade de cada linha fica no artigo com que veio.
 *
 * Nota de paridade: o Livewire descartava a linha órfã em silêncio e gravava as
 * boas; a API recusa o pedido inteiro com 422. É mais forte, e no ecrã novo não
 * há como fabricar uma linha sem artigo — quem a manda é um cliente avariado.
 */
class StockEntryItemsTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/stock';

    protected function setUp(): void
    {
        parent::setUp();

        // A movimentação em lote exige a permissão de editar stock — o
        // utilizador do TenantTestCase nasce sem papel nenhum, de propósito.
        $this->comPermissoes('invoicing.stock.edit')->comModulo('invoicing');
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

    private function linha(Product $p, float $qtd): array
    {
        return ['product_id' => $p->id, 'product_name' => $p->name, 'op' => 'add', 'quantity' => $qtd, 'unit_cost' => 50];
    }

    private function noArmazem(Product $p): float
    {
        return (float) Stock::where('tenant_id', $this->tenant->id)
            ->where('warehouse_id', $this->armazem->id)
            ->where('product_id', $p->id)
            ->value('quantity');
    }

    /** @test */
    public function uma_linha_sem_artigo_nao_chega_a_gravacao(): void
    {
        $a = $this->artigo('Artigo A');

        $this->postJson(self::RAIZ . '/entrada', [
            'armazem_id' => $this->armazem->id,
            'notas' => 'Entrada de teste',
            'itens' => [
                $this->linha($a, 3),
                ['quantity' => 5],   // a órfã: quantidade sem artigo nenhum
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('itens.1.product_id');

        $this->assertSame(
            0,
            StockMovement::where('tenant_id', $this->tenant->id)->count(),
            'só os produtos a sério podem gerar movimento'
        );
    }

    /**
     * O CASO SILENCIOSO, que era o pior: a quantidade escrita numa linha ia
     * parar a outro produto. Cada linha viaja com o seu artigo e o servidor
     * grava-a nesse — nunca no vizinho.
     *
     * @test
     */
    public function a_quantidade_de_cada_linha_fica_no_artigo_com_que_veio(): void
    {
        $a = $this->artigo('Artigo A');
        $b = $this->artigo('Artigo B');
        $c = $this->artigo('Artigo C');

        $this->postJson(self::RAIZ . '/entrada', [
            'armazem_id' => $this->armazem->id,
            'itens' => [$this->linha($a, 1), $this->linha($b, 2), $this->linha($c, 7)],
        ])->assertCreated()->assertJsonPath('ok', 3);

        $this->assertEqualsWithDelta(1, $this->noArmazem($a), 0.001);
        $this->assertEqualsWithDelta(2, $this->noArmazem($b), 0.001);
        $this->assertEqualsWithDelta(7, $this->noArmazem($c), 0.001, 'a quantidade tem de continuar no artigo C');
    }

    /**
     * Tirar uma linha do meio deixa buracos nos índices — no cliente e no JSON
     * que chega. A gravação percorre o que lá está; não assume que vai de 0 a
     * n-1.
     *
     * @test
     */
    public function gravar_com_a_lista_esburacada_grava_tudo_o_que_e_valido(): void
    {
        $a = $this->artigo('Artigo A');
        $c = $this->artigo('Artigo C');

        $this->postJson(self::RAIZ . '/entrada', [
            'armazem_id' => $this->armazem->id,
            'itens' => [0 => $this->linha($a, 4), 2 => $this->linha($c, 6)],   // o índice 1 saiu
        ])->assertCreated()->assertJsonPath('ok', 2);

        $movimentos = StockMovement::where('tenant_id', $this->tenant->id)->pluck('product_id')->all();

        $this->assertCount(2, $movimentos);
        $this->assertContains($a->id, $movimentos);
        $this->assertContains($c->id, $movimentos);
    }

    /**
     * O MESMO ARTIGO NÃO ENTRA DUAS VEZES.
     *
     * Guarda de fonte: a regra é do ecrã — as sugestões da procura tiram o que
     * já está na lista, e é por isso que nunca chegam duas linhas do mesmo
     * artigo ao servidor. Sem isto, uma segunda linha do mesmo produto passava
     * despercebida e somava duas vezes.
     *
     * @test
     */
    public function o_mesmo_artigo_nao_se_junta_duas_vezes_ao_lote(): void
    {
        $ecra = file_get_contents(base_path('resources/js/ecras/facturacao/Stock.tsx'));

        $this->assertStringContainsString(
            '!itens.some((i) => i.product_id === a.id)',
            $ecra,
            'as sugestões do lote têm de esconder os artigos que já estão na lista'
        );
    }
}
