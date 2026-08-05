<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Product;
use App\Services\Invoicing\BaixaDeStock;
use Tests\TenantTestCase;

/**
 * Vender desconta a quantidade TODA, mesmo que o armazém fique negativo.
 *
 * Três caminhos davam baixa de stock — o observer das facturas, o POS e a
 * sincronização do PWA — e as três versões divergiam:
 *
 *   - o observer, sem linha naquele armazém, fazia `continue`: não descontava
 *     E não escrevia movimento. A venda desaparecia do rastreio.
 *   - os outros dois travavam em zero com max(0, …), enquanto o movimento
 *     registava a quantidade toda.
 *
 * Era daí que vinha "vendidas 12, saíram 10" sem nada que explicasse a
 * diferença. Um armazém negativo diz que se vendeu mais do que lá havia e é
 * reparável; um travão em zero apaga essa informação para sempre.
 */
class BaixaDeStockTest extends TenantTestCase
{
    private function armazem(string $nome = 'Sala de Vendas', bool $porOmissao = true): \App\Models\Invoicing\Warehouse
    {
        return \App\Models\Invoicing\Warehouse::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => $nome,
            'code'       => 'ARM-' . substr(md5($nome), 0, 5),
            'is_active'  => true,
            'is_default' => $porOmissao,
        ]);
    }

    private function produto(): Product
    {
        return Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'ASPIRINA GR 100mg',
            'sku'            => 'ASP-100',
            'price'          => 500,
            'cost'           => 300,
            'type'           => 'produto',
            'manage_stock'   => true,
            'stock_quantity' => 0,
        ]);
    }

    public function test_desconta_tudo_e_deixa_negativo(): void
    {
        // O caso que o utilizador decidiu: vender 12 com 10 em stock deixa −2,
        // em vez de parar em 0 e perder a diferença.
        $arm = $this->armazem();
        $p = $this->produto();
        Stock::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $arm->id,
            'product_id' => $p->id,
            'quantity' => 10,
        ]);

        $ficou = BaixaDeStock::aplicar($this->tenant->id, $arm->id, $p, 12);

        $this->assertSame(-2.0, $ficou);
        $this->assertSame(-2.0, (float) Stock::where('product_id', $p->id)->first()->quantity);
    }

    public function test_sem_linha_no_armazem_cria_a_linha_negativa(): void
    {
        // Antes fazia `continue`: a venda não descontava nada e nem sequer
        // deixava movimento. Agora fica registada a falta.
        $vendas = $this->armazem('Sala de Vendas');
        $deposito = $this->armazem('Depósito', false);
        $p = $this->produto();

        // O produto tem linha NOUTRO armazém: já está em multi-armazém.
        Stock::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $deposito->id,
            'product_id' => $p->id,
            'quantity' => 50,
        ]);

        $ficou = BaixaDeStock::aplicar($this->tenant->id, $vendas->id, $p, 3);

        $this->assertSame(-3.0, $ficou);
        $this->assertSame(
            -3.0,
            (float) Stock::where('product_id', $p->id)->where('warehouse_id', $vendas->id)->first()->quantity
        );
        $this->assertSame(
            50.0,
            (float) Stock::where('product_id', $p->id)->where('warehouse_id', $deposito->id)->first()->quantity,
            'o outro armazém não pode ser tocado'
        );
    }

    public function test_produto_sem_linha_nenhuma_desconta_o_agregado(): void
    {
        // O aviso que estava no código: criar aqui uma linha passava o produto
        // a multi-armazém, e o POS deixava de usar o agregado — os OUTROS
        // armazéns passavam a ler 0 e o produto aparecia esgotado na caixa por
        // causa de uma venda.
        $arm = $this->armazem();
        $p = $this->produto();
        $p->update(['stock_quantity' => 8]);

        $ficou = BaixaDeStock::aplicar($this->tenant->id, $arm->id, $p, 3);

        $this->assertSame(5.0, $ficou);
        $this->assertSame(5.0, (float) $p->fresh()->stock_quantity);
        $this->assertSame(
            0,
            Stock::where('product_id', $p->id)->count(),
            'não pode criar linha a um produto que vive do agregado'
        );
    }

    public function test_o_agregado_tambem_pode_ficar_negativo(): void
    {
        $arm = $this->armazem();
        $p = $this->produto();
        $p->update(['stock_quantity' => 2]);

        $this->assertSame(-3.0, BaixaDeStock::aplicar($this->tenant->id, $arm->id, $p, 5));
    }

    public function test_o_agregado_segue_a_soma_das_linhas(): void
    {
        // O StockObserver mantém products.stock_quantity = SUM(linhas). Com
        // negativos permitidos, o agregado tem de os reflectir — senão o ecrã
        // mostra um número e os armazéns outro.
        $vendas = $this->armazem('Sala de Vendas');
        $deposito = $this->armazem('Depósito', false);
        $p = $this->produto();

        Stock::create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $vendas->id, 'product_id' => $p->id, 'quantity' => 1]);
        Stock::create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $deposito->id, 'product_id' => $p->id, 'quantity' => 4]);

        BaixaDeStock::aplicar($this->tenant->id, $vendas->id, $p, 3);

        $this->assertSame(-2.0, (float) Stock::where('warehouse_id', $vendas->id)->first()->quantity);
        $this->assertSame(2.0, (float) $p->fresh()->stock_quantity, '-2 + 4 = 2');
    }

    public function test_quantidade_zero_ou_negativa_nao_faz_nada(): void
    {
        $arm = $this->armazem();
        $p = $this->produto();
        Stock::create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $arm->id, 'product_id' => $p->id, 'quantity' => 5]);

        $this->assertNull(BaixaDeStock::aplicar($this->tenant->id, $arm->id, $p, 0));
        $this->assertSame(5.0, (float) Stock::where('product_id', $p->id)->first()->quantity);
    }
}
