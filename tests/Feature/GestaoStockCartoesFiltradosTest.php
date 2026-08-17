<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\StockManagement;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Os cartões do topo da Gestão de Stock.
 *
 * Contavam a empresa toda enquanto a lista mostrava o filtro: escolher um
 * armazém filtrava as linhas e deixava os totais quietos. Quem confere a
 * prateleira lê o número grande, não conta as linhas — e o número grande
 * estava a dizer outra coisa.
 */
class GestaoStockCartoesFiltradosTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.stock.view')->comModulo('invoicing');
    }

    private function armazem(string $nome): Warehouse
    {
        return Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name'      => $nome,
            'code'      => strtoupper(substr($nome, 0, 6)) . random_int(10, 99),
            'is_active' => true,
        ]);
    }

    private function artigoComStock(Warehouse $w, string $nome, float $qtd, float $custo, float $minimo = 0): Product
    {
        $c = strtoupper(uniqid('P'));

        $p = Product::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => $nome,
            'code'      => $c,
            'sku'       => $c,
            'barcode'   => $c,
            'price'     => 100,
            'cost'      => $custo,
            'stock_min' => $minimo,
        ]);

        Stock::create([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $w->id,
            'product_id'   => $p->id,
            'quantity'     => $qtd,
            'unit_cost'    => $custo,
        ]);

        return $p;
    }

    public function test_os_cartoes_seguem_o_filtro_de_armazem(): void
    {
        $loja = $this->armazem('Loja');
        $deposito = $this->armazem('Deposito');

        $this->artigoComStock($loja, 'Na loja', 10, 100);
        $this->artigoComStock($deposito, 'No deposito', 5, 200);

        $componente = Livewire::test(StockManagement::class);

        // Sem filtro: os dois armazéns.
        $componente->assertViewHas('stats', fn ($s) => $s['total_products'] === 2
            && (float) $s['total_quantity'] === 15.0
            && (float) $s['total_value'] === 2000.0);

        // Com filtro: só a loja — 10 unidades a 100.
        $componente->set('warehouseFilter', $loja->id)
            ->assertViewHas('stats', fn ($s) => $s['total_products'] === 1
                && (float) $s['total_quantity'] === 10.0
                && (float) $s['total_value'] === 1000.0);
    }

    public function test_os_cartoes_seguem_a_procura(): void
    {
        $w = $this->armazem('Loja');
        $this->artigoComStock($w, 'PARACETAMOL 500', 7, 50);
        $this->artigoComStock($w, 'IBUPROFENO 400', 3, 80);

        Livewire::test(StockManagement::class)
            ->set('search', 'PARACETAMOL')
            ->assertViewHas('stats', fn ($s) => $s['total_products'] === 1
                && (float) $s['total_quantity'] === 7.0
                && (float) $s['total_value'] === 350.0);
    }

    public function test_o_cartao_de_stock_baixo_tambem_segue_o_filtro(): void
    {
        $loja = $this->armazem('Loja');
        $deposito = $this->armazem('Deposito');

        // Um abaixo do mínimo em cada armazém.
        $this->artigoComStock($loja, 'Pouco na loja', 1, 10, 5);
        $this->artigoComStock($deposito, 'Pouco no deposito', 1, 10, 5);
        $this->artigoComStock($loja, 'Cheio na loja', 50, 10, 5);

        $componente = Livewire::test(StockManagement::class);

        $componente->assertViewHas('stats', fn ($s) => $s['low_stock'] === 2);

        $componente->set('warehouseFilter', $loja->id)
            ->assertViewHas('stats', fn ($s) => $s['low_stock'] === 1);
    }

    public function test_ligar_o_filtro_de_stock_baixo_volta_a_primeira_pagina(): void
    {
        $w = $this->armazem('Loja');

        foreach (range(1, 20) as $i) {
            $this->artigoComStock($w, 'Artigo ' . $i, 50, 10, 5);
        }

        $this->artigoComStock($w, 'Abaixo do minimo', 1, 10, 5);

        $componente = Livewire::test(StockManagement::class)->call('gotoPage', 2);

        $this->assertSame(2, $componente->instance()->getPage());

        $componente->set('lowStockFilter', true);

        $this->assertSame(1, $componente->instance()->getPage(), 'ficar na página 2 dava um ecrã vazio');
    }
}
