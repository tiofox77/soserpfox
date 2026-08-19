<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * O que o servidor manda ao POS offline.
 *
 * O dispositivo junta o que recebe e nunca apaga. Um artigo que deixe de ser
 * enviado fica lá com os dados da última vez que passou — o stock de então,
 * que era maior que zero, e o imposto de então. Mudava-se o regime da empresa
 * para isento e esse artigo continuava vendável offline a cobrar IVA.
 */
class SyncNaoDeixaArtigoVelhoNoDispositivoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.pos.access')->comModulo('invoicing');
    }

    /**
     * O armazem que a sincronizacao usa: o que a empresa ja tem por omissao.
     * O stock do POS sai dai e de mais lado nenhum.
     */
    private function armazem(bool $activo = true): Warehouse
    {
        $w = Warehouse::where('tenant_id', $this->tenant->id)->orderByDesc('is_default')->firstOrFail();

        if (!$activo) {
            Warehouse::where('tenant_id', $this->tenant->id)->update(['is_active' => false]);
        }

        return $w;
    }

    private function artigo(Warehouse $w, float $qtd): Product
    {
        $c = strtoupper(uniqid('S'));

        $p = Product::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Artigo ' . $c,
            'code'      => $c, 'sku' => $c, 'barcode' => $c,
            'price'     => 100, 'cost' => 50,
            'type'      => 'produto',
            // Este teste é sobre esconder por falta de stock — que só se
            // aplica a artigos que CONTROLAM stock.
            'manage_stock' => true,
            'is_active' => true,
        ]);

        Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $w->id,
            'product_id'   => $p->id,
            'quantity'     => $qtd,
            'unit_cost'    => 50,
        ]);

        return $p;
    }

    private function sincronizar(?string $desde): array
    {
        $url = '/api/v1/invoicing/sync' . ($desde ? '?since=' . urlencode($desde) : '');

        $resposta = $this->actingAs($this->user)->getJson($url)->assertOk()->json();

        // A carga vem aninhada em `data`.
        return $resposta['data'] ?? $resposta;
    }

    public function test_artigo_escondido_por_falta_de_stock_e_comunicado_como_retirado(): void
    {
        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['pos_hide_out_of_stock' => true]
        );
        InvoicingSettings::esquecerMemoria($this->tenant->id);

        $w = $this->armazem();
        $esgotado = $this->artigo($w, 0);
        $comStock = $this->artigo($w, 5);

        $dados = $this->sincronizar(now()->subDay()->toIso8601String());
        $enviados = collect($dados['products'] ?? [])->pluck('id')->all();
        $retirados = $dados['removed_products'] ?? [];

        $this->assertContains($comStock->id, $enviados);
        $this->assertNotContains($esgotado->id, $enviados, 'o esgotado continua escondido, como a definição pede');

        // ...mas o dispositivo tem de saber que o largue, senão fica com o
        // registo velho — stock antigo e imposto antigo — para sempre.
        $this->assertContains($esgotado->id, $retirados);
    }

    public function test_sem_armazem_activo_manda_tudo_em_vez_de_esvaziar_o_catalogo(): void
    {
        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['pos_hide_out_of_stock' => true]
        );
        InvoicingSettings::esquecerMemoria($this->tenant->id);

        $w = $this->armazem(activo: false);
        $um = $this->artigo($w, 5);
        $dois = $this->artigo($w, 0);

        $dados = $this->sincronizar(now()->subDay()->toIso8601String());
        $enviados = collect($dados['products'] ?? [])->pluck('id')->all();

        // Sem armazém a expressão de stock dá 0 a toda a gente. Esconder aqui
        // esvaziava o catálogo, e offline isso não se nota: o aparelho fica
        // com o que já tinha e continua a vender por dados velhos.
        $this->assertContains($um->id, $enviados);
        $this->assertContains($dois->id, $enviados);
        $this->assertEmpty(array_intersect([$um->id, $dois->id], $dados['removed_products'] ?? []));
    }
}
