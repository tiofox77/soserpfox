<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoicing\Stock;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * O que o dispositivo PUXA do servidor.
 *
 * A outra metade da sincronização. O PWA guarda tudo em IndexedDB com
 * `bulkPut`, que junta e actualiza mas nunca apaga — portanto o que o servidor
 * deixar de enviar fica lá para sempre. É essa assimetria que faz os defeitos
 * desta área serem silenciosos: nada falha, o ecrã enche-se na mesma, e os
 * números é que deixam de corresponder ao armazém.
 */
class SyncDescarregamentoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function artigo(array $campos = []): Product
    {
        return Product::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Artigo ' . uniqid(),
            'code'      => 'ART-' . uniqid(),
            'price'     => 1000,
            'type'      => 'produto',
            'is_active' => true,
            'tax_id'    => $this->imposto->id,
        ], $campos));
    }

    private function comStock(Product $p, float $quantidade): Stock
    {
        return Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $p->id,
            'quantity'     => $quantidade,
        ]);
    }

    private function sincronizar(?string $desde = null): array
    {
        $url = '/api/v1/invoicing/sync' . ($desde ? '?since=' . urlencode($desde) : '');

        return $this->getJson($url)->assertOk()->json();
    }

    /** @return array<int> ids dos produtos devolvidos */
    private function idsDosProdutos(array $resposta): array
    {
        return array_column($resposta['data']['products'] ?? [], 'id');
    }

    // ==================== o essencial ====================

    public function test_a_sincronizacao_completa_traz_produtos_e_clientes(): void
    {
        $p = $this->artigo();
        $this->comStock($p, 10);

        $resposta = $this->sincronizar();

        $this->assertContains($p->id, $this->idsDosProdutos($resposta));
        $this->assertNotEmpty($resposta['data']['clients']);
        $this->assertSame($this->tenant->id, $resposta['tenant_id']);
    }

    /** O stock enviado é o do armazém activo, não o agregado. */
    public function test_o_stock_enviado_e_o_do_armazem_activo(): void
    {
        $p = $this->artigo();
        $this->comStock($p, 7);

        $produtos = collect($this->sincronizar()['data']['products'])->keyBy('id');

        $this->assertEquals(7, $produtos[$p->id]['stock_quantity']);
        $this->assertSame($this->armazem->id, $produtos[$p->id]['warehouse_id']);
    }

    // ==================== o que estava partido ====================

    /**
     * Um produto REPOSTO em stock tem de voltar ao dispositivo.
     *
     * O filtro incremental é `invoicing_products.updated_at >= since`. Mas o
     * stock vive noutra tabela, e o StockObserver actualiza o agregado por
     * query builder — de propósito, para não disparar eventos do Product — o
     * que significa que NÃO toca no updated_at.
     *
     * Somando a isso o `pos_hide_out_of_stock`, que está ligado por omissão:
     * o produto cai a zero e deixa de ser enviado; quando é reposto, o seu
     * updated_at continua velho e nunca mais é enviado. E como o PWA junta com
     * bulkPut e nunca apaga, o dispositivo fica com o valor de stock congelado
     * na última vez que o produto passou por aqui.
     *
     * Resultado no balcão: repõe-se o stock no armazém e o POS offline continua
     * a dizer que não há — ou pior, continua a mostrar o número antigo.
     */
    public function test_um_produto_reposto_em_stock_volta_na_sincronizacao_incremental(): void
    {
        $p = $this->artigo();
        $stock = $this->comStock($p, 0);

        // O marco tem de ficar CLARAMENTE depois da criação: o ISO8601 corta
        // os microssegundos, e um marco tirado no mesmo segundo do produto
        // ficaria antes dele — o teste passava sem provar nada.
        $this->travel(10)->seconds();
        $marco = now()->toIso8601String();
        $this->travel(10)->seconds();

        // Reposição no armazém — sem tocar no produto.
        $stock->update(['quantity' => 50]);

        $this->assertContains(
            $p->id,
            $this->idsDosProdutos($this->sincronizar($marco)),
            'O produto reposto não voltou ao dispositivo.'
        );
    }

    /**
     * Um produto DESACTIVADO tem de ser dito ao dispositivo.
     *
     * A consulta filtra por is_active, portanto um produto desactivado
     * simplesmente deixa de vir. E o PWA nunca apaga nada: continua a mostrá-lo
     * e a vendê-lo, offline, indefinidamente.
     *
     * Não basta deixar de o enviar — é preciso dizer que saiu.
     */
    public function test_um_produto_desactivado_e_comunicado_como_removido(): void
    {
        $p = $this->artigo();
        $this->comStock($p, 10);

        $this->travel(10)->seconds();
        $marco = now()->toIso8601String();
        $this->travel(10)->seconds();

        $p->update(['is_active' => false]);

        $resposta = $this->sincronizar($marco);

        $this->assertContains(
            $p->id,
            $resposta['data']['removed_products'] ?? [],
            'O dispositivo continua a vender um produto desactivado.'
        );

        $this->assertNotContains($p->id, $this->idsDosProdutos($resposta));
    }

    /** E um produto eliminado também. */
    public function test_um_produto_eliminado_e_comunicado_como_removido(): void
    {
        $p = $this->artigo();
        $this->comStock($p, 10);

        $this->travel(10)->seconds();
        $marco = now()->toIso8601String();
        $this->travel(10)->seconds();

        $p->delete();

        $this->assertContains(
            $p->id,
            $this->sincronizar($marco)['data']['removed_products'] ?? []
        );
    }

    /** O mesmo para clientes eliminados. */
    public function test_um_cliente_eliminado_e_comunicado_como_removido(): void
    {
        $c = Client::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Cliente a Sair',
            'nif'       => (string) random_int(500000000, 599999999),
            'type'      => 'pessoa_fisica',
        ]);

        $this->travel(10)->seconds();
        $marco = now()->toIso8601String();
        $this->travel(10)->seconds();

        $c->delete();

        $this->assertContains(
            $c->id,
            $this->sincronizar($marco)['data']['removed_clients'] ?? []
        );
    }

    /**
     * Numa sincronização COMPLETA não se manda lista de removidos.
     *
     * O dispositivo limpa tudo e recarrega — a lista seria peso morto, e num
     * catálogo grande com anos de produtos apagados seria peso a sério.
     */
    public function test_a_sincronizacao_completa_nao_manda_removidos(): void
    {
        $p = $this->artigo();
        $p->delete();

        $resposta = $this->sincronizar();

        $this->assertSame([], $resposta['data']['removed_products'] ?? []);
        $this->assertSame([], $resposta['data']['removed_clients'] ?? []);
    }

    // ==================== robustez ====================

    /**
     * O dispositivo tem de AGIR sobre a lista de removidos.
     *
     * O servidor passar a mandá-la não serve de nada se o PWA a ignorar — e
     * ignorava: o merge é feito com bulkPut, que junta e actualiza mas nunca
     * apaga. Verifica-se na fonte porque é JavaScript, como no
     * TraducoesJavaScriptTest.
     */
    public function test_o_pwa_apaga_o_que_o_servidor_diz_ter_saido(): void
    {
        $js = file_get_contents(resource_path('js/pwa/motor/sincronizar.ts'));

        $this->assertStringContainsString(
            'db.products.bulkDelete(dados.removed_products)',
            $js,
            'O PWA tem de apagar os produtos que saíram do catálogo.'
        );

        $this->assertStringContainsString(
            'db.clients.bulkDelete(dados.removed_clients)',
            $js,
            'O PWA tem de apagar os clientes eliminados.'
        );
    }

    /**
     * Um `since` inválido não pode derrubar a sincronização.
     *
     * O Carbon::parse rebenta com lixo, e isso dava 500. O dispositivo ficava
     * sem catálogo por causa de um parâmetro mal formado — que é exactamente o
     * que acontece quando um relógio local está errado ou um valor guardado se
     * corrompe.
     */
    public function test_um_since_invalido_nao_derruba_a_sincronizacao(): void
    {
        $resposta = $this->getJson('/api/v1/invoicing/sync?since=isto-nao-e-uma-data')
            ->assertOk()
            ->json();

        // Trata-se como sincronização completa: mais vale mandar tudo do que
        // deixar o dispositivo sem nada.
        $this->assertFalse($resposta['meta']['incremental']);
    }
}
