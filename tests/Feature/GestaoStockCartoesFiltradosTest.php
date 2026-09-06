<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * Os cartões do topo da Gestão de Stock.
 *
 * Contavam a empresa toda enquanto a lista mostrava o filtro: escolher um
 * armazém filtrava as linhas e deixava os totais quietos. Quem confere a
 * prateleira lê o número grande, não conta as linhas — e o número grande
 * estava a dizer outra coisa.
 *
 * O ecrã é React e os cartões vêm no `resumo` da mesma resposta que traz a
 * lista (`GET /api/v1/invoicing/react/stock`), calculado sobre a MESMA consulta
 * filtrada. É essa promessa que estes ensaios seguram.
 */
class GestaoStockCartoesFiltradosTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/stock';

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

    /** @return array{artigos: int, quantidade: float, valor: float, baixo: int} */
    private function cartoes(array $filtros = []): array
    {
        return $this->getJson(self::RAIZ . '?' . http_build_query($filtros))->assertOk()->json('resumo');
    }

    public function test_os_cartoes_seguem_o_filtro_de_armazem(): void
    {
        $loja = $this->armazem('Loja');
        $deposito = $this->armazem('Deposito');

        $this->artigoComStock($loja, 'Na loja', 10, 100);
        $this->artigoComStock($deposito, 'No deposito', 5, 200);

        // Sem filtro: os dois armazéns.
        $todos = $this->cartoes();

        $this->assertSame(2, $todos['artigos']);
        $this->assertEqualsWithDelta(15, $todos['quantidade'], 0.001);
        $this->assertEqualsWithDelta(2000, $todos['valor'], 0.01);

        // Com filtro: só a loja — 10 unidades a 100.
        $so = $this->cartoes(['armazem' => $loja->id]);

        $this->assertSame(1, $so['artigos']);
        $this->assertEqualsWithDelta(10, $so['quantidade'], 0.001);
        $this->assertEqualsWithDelta(1000, $so['valor'], 0.01);
    }

    public function test_os_cartoes_seguem_a_procura(): void
    {
        $w = $this->armazem('Loja');
        $this->artigoComStock($w, 'PARACETAMOL 500', 7, 50);
        $this->artigoComStock($w, 'IBUPROFENO 400', 3, 80);

        $r = $this->cartoes(['procura' => 'PARACETAMOL']);

        $this->assertSame(1, $r['artigos']);
        $this->assertEqualsWithDelta(7, $r['quantidade'], 0.001);
        $this->assertEqualsWithDelta(350, $r['valor'], 0.01);
    }

    public function test_o_cartao_de_stock_baixo_tambem_segue_o_filtro(): void
    {
        $loja = $this->armazem('Loja');
        $deposito = $this->armazem('Deposito');

        // Um abaixo do mínimo em cada armazém.
        $this->artigoComStock($loja, 'Pouco na loja', 1, 10, 5);
        $this->artigoComStock($deposito, 'Pouco no deposito', 1, 10, 5);
        $this->artigoComStock($loja, 'Cheio na loja', 50, 10, 5);

        $this->assertSame(2, $this->cartoes()['baixo']);
        $this->assertSame(1, $this->cartoes(['armazem' => $loja->id])['baixo']);
    }

    /**
     * Ligar um filtro volta à primeira página.
     *
     * Guarda de fonte: a paginação passou para o cliente, e é o ecrã que tem de
     * repor `page: 1` sempre que um filtro muda. Ficar na página 2 com a lista
     * filtrada dava um ecrã vazio — e a lista e os cartões a discordar.
     *
     * @test
     */
    public function mudar_um_filtro_volta_a_primeira_pagina(): void
    {
        $ecra = file_get_contents(base_path('resources/js/ecras/facturacao/Stock.tsx'));

        foreach (['procura: e.target.value', 'armazem: e.target.value', 'conservacao: e.target.value', 'baixo: e.target.checked'] as $filtro) {
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($filtro, '/') . ',\s*page:\s*1/',
                $ecra,
                "o filtro «{$filtro}» tem de repor a página"
            );
        }
    }
}
