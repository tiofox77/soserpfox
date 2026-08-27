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

    /**
     * O ARTIGO QUE NÃO CONTROLA STOCK NÃO SE APAGA POR TER ZERO.
     *
     * Isto foi um defeito a sério, e silencioso. A lista do que se esconde
     * olhava só para o stock e para o tipo — e mandava apagar tudo o que
     * tivesse zero em armazém, mesmo o que nunca controla stock. Como o filtro
     * do envio deixa passar esses artigos, o servidor mandava-os e mandava
     * apagá-los NA MESMA RESPOSTA: o dispositivo gravava-os no bulkPut e
     * apagava-os a seguir no bulkDelete.
     *
     * Efeito no balcão: a primeira sincronização (completa) trazia o catálogo
     * todo e cada sincronização seguinte ia-o despindo. Um restaurante ficava
     * sem menu offline — os pratos não controlam stock, quem o controla é a
     * ficha técnica pelos ingredientes — e no sistema estava tudo activo, sem
     * nada que denunciasse porquê.
     */
    public function test_artigo_sem_gestao_de_stock_nao_e_mandado_apagar(): void
    {
        InvoicingSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['pos_hide_out_of_stock' => true]
        );
        InvoicingSettings::esquecerMemoria($this->tenant->id);

        $w = $this->armazem();

        // Um prato: zero em armazém e sem gestão de stock. É o caso do menu
        // de um restaurante e o de qualquer artigo à consignação.
        $prato = $this->artigo($w, 0);
        $prato->update(['manage_stock' => false]);

        $dados = $this->sincronizar(now()->subDay()->toIso8601String());

        $this->assertContains(
            $prato->id,
            collect($dados['products'] ?? [])->pluck('id')->all(),
            'quem não controla stock é sempre enviado'
        );
        $this->assertNotContains(
            $prato->id,
            $dados['removed_products'] ?? [],
            'e não pode ser mandado apagar na mesma resposta em que é enviado'
        );
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
