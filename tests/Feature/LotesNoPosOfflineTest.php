<?php

namespace Tests\Feature;

use App\Models\Invoicing\BatchAllocation;
use App\Models\Invoicing\ProductBatch;
use App\Models\Invoicing\Stock;
use App\Models\Product;
use App\Services\POS\PosSaleService;
use Tests\TenantTestCase;

/**
 * Lotes e validades numa venda do POS offline.
 *
 * O POS online desconta o lote pelo caminho do SalesInvoiceObserver. A
 * sincronização do PWA não passa por lá: cria a factura, desconta o stock ela
 * própria e segue. A pergunta é se os lotes acompanham — porque se não
 * acompanharem, o armazém diz uma coisa e os lotes dizem outra, e é dos lotes
 * que sai o relatório de validade que manda abater produto.
 */
class LotesNoPosOfflineTest extends TenantTestCase
{
    private Product $artigo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        $this->artigo = Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'Paracetamol 500mg',
            'code'           => 'PARA-' . uniqid(),
            'price'          => 1000,
            'cost_price'     => 400,
            'type'           => 'produto',
            'is_active'      => true,
            'tax_id'         => $this->imposto->id,
            'track_batches'  => true,
            'track_expiry'   => true,
        ]);

        Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $this->artigo->id,
            'quantity'     => 100,
        ]);
    }

    private function lote(array $campos = []): ProductBatch
    {
        return ProductBatch::create(array_merge([
            'tenant_id'          => $this->tenant->id,
            'product_id'         => $this->artigo->id,
            'warehouse_id'       => $this->armazem->id,
            'batch_number'       => 'L' . uniqid(),
            'expiry_date'        => now()->addDays(120)->toDateString(),
            'quantity'           => 100,
            'quantity_available' => 100,
            'cost_price'         => 400,
            'status'             => 'active',
            'alert_days'         => 30,
        ], $campos));
    }

    private function sincronizarVenda(string $uuid, float $quantidade)
    {
        return app(PosSaleService::class)->createFromPayload([
            'local_uuid'     => $uuid,
            'payment_method' => 'cash',
            'items'          => [[
                'product_id'   => $this->artigo->id,
                'product_name' => $this->artigo->name,
                'quantity'     => $quantidade,
                'unit_price'   => 1000,
            ]],
        ], $this->tenant->id, $this->user->id);
    }

    /** O stock do armazém desce — isto já funcionava e tem de continuar. */
    public function test_a_venda_offline_desconta_o_stock_do_armazem(): void
    {
        $this->lote();

        $this->sincronizarVenda('venda-stock-1', 10);

        $this->assertEquals(
            90,
            Stock::where('warehouse_id', $this->armazem->id)
                ->where('product_id', $this->artigo->id)
                ->value('quantity')
        );
    }

    /**
     * E o LOTE tem de descer com ele.
     *
     * O SalesInvoiceObserver aloca o lote dentro do reduceStock — mas esse sai
     * logo à entrada quando já existe um movimento de saída a referenciar a
     * factura, e a sincronização do PWA cria esse movimento ela própria. Ou
     * seja: o caminho offline nunca chega à alocação.
     *
     * Consequência prática: o armazém diz 90 e os lotes continuam a dizer 100.
     * Numa farmácia ou num supermercado, é o relatório de validade a mandar
     * abater dez unidades que já foram vendidas.
     */
    public function test_a_venda_offline_consome_o_lote(): void
    {
        $lote = $this->lote();

        $this->sincronizarVenda('venda-lote-1', 10);

        $this->assertEquals(
            90,
            $lote->fresh()->quantity_available,
            'O lote ficou com a quantidade de antes da venda.'
        );
    }

    /** E fica registado de que lote saiu — é isso a rastreabilidade. */
    public function test_a_venda_offline_regista_de_que_lote_saiu(): void
    {
        $lote = $this->lote(['batch_number' => 'RASTREIO-1']);

        $factura = $this->sincronizarVenda('venda-lote-2', 4);

        $alocacao = BatchAllocation::where('document_id', $factura->id)
            ->where('product_id', $this->artigo->id)
            ->first();

        $this->assertNotNull($alocacao, 'Sem alocação não há rastreabilidade do lote.');
        $this->assertEquals(4, $alocacao->quantity_allocated);
        $this->assertSame('RASTREIO-1', $alocacao->batch_number_snapshot);
    }

    /**
     * O FIFO vale offline como vale online: sai primeiro o que expira antes.
     *
     * É a razão de ser dos lotes. Se a venda offline escolhesse ao acaso — ou
     * não escolhesse de todo — o produto mais perto da validade ficava para
     * trás e acabava por se estragar em prateleira.
     */
    public function test_a_venda_offline_gasta_primeiro_o_lote_que_expira_antes(): void
    {
        $tarde = $this->lote(['expiry_date' => now()->addDays(200)->toDateString(), 'quantity' => 10, 'quantity_available' => 10]);
        $cedo  = $this->lote(['expiry_date' => now()->addDays(20)->toDateString(), 'quantity' => 10, 'quantity_available' => 10]);

        $this->sincronizarVenda('venda-fifo-1', 10);

        $this->assertEquals(0, $cedo->fresh()->quantity_available, 'O lote mais perto da validade devia sair primeiro.');
        $this->assertEquals(10, $tarde->fresh()->quantity_available);
    }

    /**
     * Um lote expirado não é consumido por uma venda offline.
     *
     * A venda em si não se recusa — já aconteceu, o cliente já levou o
     * produto e o documento é fiscal. O que não pode é dar baixa no lote
     * expirado como se ele fosse vendável: isso apagava a prova de que há
     * produto fora de prazo no armazém.
     */
    public function test_uma_venda_offline_nao_consome_lote_expirado(): void
    {
        $expirado = $this->lote([
            'expiry_date' => now()->subDays(5)->toDateString(),
            'quantity'    => 10, 'quantity_available' => 10,
        ]);

        $bom = $this->lote([
            'expiry_date' => now()->addDays(90)->toDateString(),
            'quantity'    => 10, 'quantity_available' => 10,
        ]);

        $this->sincronizarVenda('venda-expirado-1', 6);

        $this->assertEquals(10, $expirado->fresh()->quantity_available, 'O lote expirado foi consumido.');
        $this->assertEquals(4, $bom->fresh()->quantity_available);
    }

    /**
     * Reenviar a mesma venda não desconta o lote duas vezes.
     *
     * A idempotência da factura já estava garantida pelo local_uuid; o lote
     * tem de andar com ela. Com a rede a oscilar o PWA reenvia, e um lote
     * descontado duas vezes some sem nada que o explique.
     */
    public function test_a_mesma_venda_reenviada_nao_desconta_o_lote_duas_vezes(): void
    {
        $lote = $this->lote();

        $this->sincronizarVenda('venda-repetida-1', 10);
        $this->sincronizarVenda('venda-repetida-1', 10);

        $this->assertEquals(90, $lote->fresh()->quantity_available);

        $this->assertSame(
            1,
            BatchAllocation::where('tenant_id', $this->tenant->id)
                ->where('product_id', $this->artigo->id)
                ->count()
        );
    }

    /**
     * Um artigo sem rastreio por lote continua a funcionar como sempre.
     *
     * A maioria do catálogo não usa lotes. Não pode passar a falhar por
     * causa de uma funcionalidade que não usa.
     */
    public function test_um_artigo_sem_lotes_vende_se_na_mesma(): void
    {
        $simples = Product::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Saco plástico',
            'code'      => 'SACO-' . uniqid(),
            'price'     => 50,
            'type'      => 'produto',
            // Artigo gerido sem lotes: controla stock, logo desconta.
            'manage_stock' => true,
            'is_active' => true,
            'tax_id'    => $this->imposto->id,
        ]);

        Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id'   => $simples->id,
            'quantity'     => 20,
        ]);

        $factura = app(PosSaleService::class)->createFromPayload([
            'local_uuid'     => 'venda-simples-1',
            'payment_method' => 'cash',
            'items'          => [[
                'product_id'   => $simples->id,
                'product_name' => $simples->name,
                'quantity'     => 3,
                'unit_price'   => 50,
            ]],
        ], $this->tenant->id, $this->user->id);

        $this->assertNotNull($factura->invoice_number);

        $this->assertEquals(
            17,
            Stock::where('warehouse_id', $this->armazem->id)
                ->where('product_id', $simples->id)
                ->value('quantity')
        );
    }
}
