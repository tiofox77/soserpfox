<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Services\Audit\AuditRecorder;
use App\Services\Invoicing\ModuleInvoiceService;
use Tests\TenantTestCase;

/**
 * Cobertura da trilha de auditoria no módulo de faturação.
 *
 * A auditoria falha em silêncio por desenho (não pode derrubar uma venda), o
 * que torna "não registou" indistinguível de "não aconteceu". São estes testes
 * que fazem a diferença aparecer.
 */
class AuditCoberturaTest extends TenantTestCase
{
    private function trilha(): \Illuminate\Support\Collection
    {
        app(AuditRecorder::class)->despejar();

        return AuditTrail::where('tenant_id', $this->tenant->id)->get();
    }

    public function test_um_movimento_de_stock_de_venda_fica_auditado(): void
    {
        // Os 10 sítios que criam movimentos de venda suprimiam TODOS os eventos
        // (withoutEvents) só para o hook não voltar a debitar o stock. Nisso
        // levavam a auditoria à frente: os movimentos que mais importam, os das
        // vendas, eram os únicos sem rasto.
        $produto = $this->produtoComStock(10);

        StockMovement::semAplicarStock(function () use ($produto) {
            StockMovement::create([
                'tenant_id'    => $this->tenant->id,
                'warehouse_id' => $this->armazem->id,
                'product_id'   => $produto->id,
                'type'         => StockMovement::TYPE_OUT,
                'quantity'     => 2,
                'unit_cost'    => 100,
                'user_id'      => $this->user->id,
                'notes'        => 'Venda de teste',
            ]);
        });

        $stock = (float) Stock::where('product_id', $produto->id)->sum('quantity');

        $this->assertEquals(10, $stock, 'semAplicarStock não pode mexer no stock');

        $this->assertTrue(
            $this->trilha()->contains(fn ($l) => $l->auditable_type === StockMovement::class),
            'o movimento tem de deixar rasto na trilha'
        );
    }

    public function test_uma_venda_audita_documento_linhas_e_stock(): void
    {
        $produto = $this->produtoComStock(10);

        app(ModuleInvoiceService::class)->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'status'    => 'paid',
            'lines' => [
                ['product_id' => $produto->id, 'name' => $produto->name, 'quantity' => 2, 'unit_price' => 5000],
            ],
        ]);

        $tipos = $this->trilha()->pluck('auditable_type')->unique();

        foreach ([
            \App\Models\Invoicing\SalesInvoice::class,
            \App\Models\Invoicing\SalesInvoiceItem::class,
            \App\Models\Invoicing\Stock::class,
            \App\Models\Invoicing\StockMovement::class,
        ] as $esperado) {
            $this->assertTrue(
                $tipos->contains($esperado),
                class_basename($esperado) . ' não ficou auditado numa venda'
            );
        }
    }

    public function test_as_linhas_do_documento_nao_se_perdem_por_falta_de_tenant(): void
    {
        // SalesInvoiceItem não tem coluna tenant_id: sem resolver a empresa pelo
        // documento pai, as linhas eram descartadas em silêncio — auditava-se a
        // factura e perdia-se o imposto e a quantidade, que vivem na linha.
        app(ModuleInvoiceService::class)->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'lines' => [
                ['name' => 'A', 'quantity' => 1, 'unit_price' => 1000, 'is_service' => true],
                ['name' => 'B', 'quantity' => 1, 'unit_price' => 2000, 'is_service' => true],
                ['name' => 'C', 'quantity' => 1, 'unit_price' => 3000, 'is_service' => true],
            ],
        ]);

        $linhas = $this->trilha()
            ->where('auditable_type', \App\Models\Invoicing\SalesInvoiceItem::class);

        $this->assertCount(3, $linhas, 'as três linhas do documento têm de estar na trilha');
        $this->assertTrue($linhas->every(fn ($l) => $l->tenant_id === $this->tenant->id));
    }

    public function test_a_alteracao_de_um_artigo_regista_o_antes_e_o_depois(): void
    {
        $produto = $this->produtoComStock(5, 1000);

        $produto->update(['price' => 2500]);

        $linha = $this->trilha()
            ->where('auditable_type', \App\Models\Product::class)
            ->firstWhere('event', 'updated');

        $this->assertNotNull($linha, 'mudar o preço de um artigo é um acto auditável');
        $this->assertEquals(1000, (float) ($linha->old_values['price'] ?? 0));
        $this->assertEquals(2500, (float) ($linha->new_values['price'] ?? 0));
    }

    public function test_um_armazem_novo_fica_auditado(): void
    {
        \App\Models\Invoicing\Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Armazém Auditado',
            'code'      => 'AUD' . random_int(100, 999),
            'is_active' => true,
        ]);

        $this->assertTrue(
            $this->trilha()->contains(fn ($l) => $l->auditable_type === \App\Models\Invoicing\Warehouse::class),
            'criar um armazém é um acto auditável'
        );
    }

    public function test_uma_venda_desfeita_nao_deixa_rasto(): void
    {
        // Auditar um facto que foi revertido é pior do que não o auditar: fica
        // no registo uma venda que nunca existiu.
        $antes = AuditTrail::where('tenant_id', $this->tenant->id)->count();

        try {
            \Illuminate\Support\Facades\DB::transaction(function () {
                app(ModuleInvoiceService::class)->emitir([
                    'tenant_id' => $this->tenant->id,
                    'client_id' => $this->cliente->id,
                    'lines'     => [['name' => 'X', 'quantity' => 1, 'unit_price' => 100, 'is_service' => true]],
                ]);

                throw new \RuntimeException('rollback proposital');
            });
        } catch (\RuntimeException) {
        }

        app(AuditRecorder::class)->despejar();

        $this->assertSame(
            $antes,
            AuditTrail::where('tenant_id', $this->tenant->id)->count(),
            'um rollback não pode deixar linhas de auditoria para trás'
        );
    }
}
