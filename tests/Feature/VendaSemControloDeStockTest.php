<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use App\Services\POS\PosSaleService;
use Tests\TenantTestCase;

/**
 * Produtos com "Gerenciar Stock" desligado vendem-se livremente: sem
 * validação de disponibilidade, sem baixar stock, sem movimento.
 */
class VendaSemControloDeStockTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A API do PWA pede permissão desde 2026-09-13 (AutorizaApiDoPwa): o
        // utilizador do ensaio é um caixa a sério, não um membro sem papel.
        $this->comPermissoesDoPwa();
    }

    private function produtoSemControlo(): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Serviço de entrega',
            'code' => 'SVC' . uniqid(),
            'type' => 'produto',
            'price' => 5000,
            'manage_stock' => false,   // não controla stock
            'stock_quantity' => 0,
            'is_active' => true,
            'tax_type' => 'isento',
        ]);
    }

    public function test_o_helper_controla_stock(): void
    {
        $semControlo = $this->produtoSemControlo();
        $this->assertFalse($semControlo->controlaStock());

        $comControlo = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Caixa',
            'code' => 'P' . uniqid(), 'type' => 'produto', 'price' => 100,
            'manage_stock' => true, 'stock_quantity' => 10, 'is_active' => true,
            'tax_type' => 'isento',
        ]);
        $this->assertTrue($comControlo->controlaStock());
    }

    public function test_vende_sem_stock_e_nao_gera_movimento(): void
    {
        $p = $this->produtoSemControlo();

        $service = app(PosSaleService::class);
        $invoice = $service->createFromPayload([
            'local_uuid' => 'venda-sem-stock-1',
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $p->id,
                'product_name' => $p->name,
                'quantity' => 3,          // muito acima de 0 stock
                'unit_price' => 5000,
                'tax_rate' => 0,
            ]],
        ], $this->tenant->id, $this->user->id);

        $this->assertNotNull($invoice->invoice_number, 'a venda tinha de ser emitida');

        // Nenhum movimento de saída para este produto.
        $movimentos = StockMovement::where('tenant_id', $this->tenant->id)
            ->where('product_id', $p->id)->where('type', 'out')->count();
        $this->assertSame(0, $movimentos, 'produto sem controlo não pode gerar movimento de stock');

        // O stock continua em 0, não foi para -3.
        $this->assertSame(0.0, (float) $p->fresh()->stock_quantity);
    }

    public function test_o_sync_envia_o_produto_sem_stock_e_o_flag(): void
    {
        $p = $this->produtoSemControlo(); // stock 0, manage_stock false

        $json = $this->actingAs($this->user)
            ->getJson('/api/v1/invoicing/sync')->assertOk()->json();

        $enviado = collect($json['data']['products'])->firstWhere('id', $p->id);
        $this->assertNotNull($enviado, 'produto sem controlo de stock (e sem stock) tinha de ser enviado');
        $this->assertFalse($enviado['manage_stock'], 'o flag tem de ir no payload');
    }

    /**
     * Um SERVIÇO vendido sem o flag is_service no payload não pode descontar
     * stock. Era esta a origem dos serviços do salão a aparecer com stock
     * negativo (-2, -5, -9) e marcados como "Esgotado" no POS: o serviço
     * confiava no flag do dispositivo em vez do tipo real do artigo.
     */
    public function test_um_servico_nunca_desconta_stock_mesmo_sem_o_flag(): void
    {
        $servico = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Corte de Cabelo',
            'code' => 'SVC' . uniqid(),
            'type' => 'servico',
            'price' => 3000,
            'manage_stock' => false,
            'stock_quantity' => 0,
            'is_active' => true,
            'tax_type' => 'isento',
        ]);

        $this->assertFalse($servico->controlaStock());

        app(PosSaleService::class)->createFromPayload([
            'local_uuid' => 'venda-servico-' . uniqid(),
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $servico->id,
                'product_name' => $servico->name,
                'quantity' => 2,
                'unit_price' => 3000,
                'tax_rate' => 0,
                // NOTA: sem 'is_service' de propósito — é o caso que partia.
            ]],
        ], $this->tenant->id, $this->user->id);

        $movimentos = StockMovement::where('tenant_id', $this->tenant->id)
            ->where('product_id', $servico->id)->count();
        $this->assertSame(0, $movimentos, 'um serviço não pode gerar movimento de stock');

        $linhas = \App\Models\Invoicing\Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $servico->id)->sum('quantity');
        $this->assertEquals(0, (float) $linhas, 'um serviço não pode ficar com stock negativo');
    }
}
