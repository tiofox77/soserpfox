<?php

namespace Tests\Feature;

use App\Models\Invoicing\Stock;
use App\Models\Invoicing\Warehouse;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A importação em modo --so-stock.
 *
 * Serve para lançar um segundo armazém a partir de uma folha nova sem que
 * essa folha mande nos preços do catálogo. O que interessa provar é o que
 * ela NÃO faz: não cria artigos e não mexe em preços.
 */
class ImportarSoStockTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(): Tenant
    {
        return Tenant::create([
            'name'  => 'Teste Só Stock ' . uniqid(),
            'email' => uniqid() . '@teste.local',
            'nif'   => '5' . random_int(10000000, 99999999),
        ]);
    }

    private function csv(array $linhas): string
    {
        $f = tempnam(sys_get_temp_dir(), 'stk') . '.csv';
        $h = fopen($f, 'w');
        fputcsv($h, ['codigo_barras', 'descricao', 'preco_compra', 'preco_venda', 'quantidade']);

        foreach ($linhas as $l) {
            fputcsv($h, $l);
        }

        fclose($h);

        return $f;
    }

    public function test_lanca_stock_sem_mexer_no_preco_do_artigo(): void
    {
        $t = $this->empresa();

        $p = Product::withoutGlobalScopes()->create([
            'tenant_id' => $t->id,
            'name'      => 'Nome afinado no sistema',
            'barcode'   => '0108902292003269',
            'code'      => '0108902292003269',
            'sku'       => '0108902292003269',
            'price'     => 1900,
            'cost'      => 1000,
        ]);

        $this->artisan('artigos:importar', [
            '--tenant'   => $t->id,
            '--armazem'  => 'stock',
            '--ficheiro' => $this->csv([['0108902292003269', 'NOME DA FOLHA NOVA', 555, 777, 12]]),
            '--so-stock' => true,
            '--aplicar'  => true,
        ])->assertSuccessful();

        $p->refresh();

        $this->assertSame('Nome afinado no sistema', $p->name, 'a folha nova não podia mandar no nome');
        $this->assertEquals(1900, $p->price, 'a folha nova não podia mandar no preço de venda');
        $this->assertEquals(1000, $p->cost, 'a folha nova não podia mandar no preço de compra');

        $armazem = Warehouse::withoutGlobalScopes()->where('tenant_id', $t->id)->where('name', 'stock')->first();
        $this->assertNotNull($armazem);

        $stock = Stock::withoutGlobalScopes()
            ->where('warehouse_id', $armazem->id)->where('product_id', $p->id)->first();

        $this->assertNotNull($stock);
        $this->assertEquals(12, $stock->quantity);
    }

    public function test_nao_cria_artigos_que_nao_existam(): void
    {
        $t = $this->empresa();

        $this->artisan('artigos:importar', [
            '--tenant'   => $t->id,
            '--armazem'  => 'stock',
            '--ficheiro' => $this->csv([['9999999999999', 'NÃO EXISTE', 10, 20, 5]]),
            '--so-stock' => true,
            '--aplicar'  => true,
        ])->assertSuccessful();

        $this->assertSame(0, Product::withTrashed()->where('tenant_id', $t->id)->count());
    }

    public function test_o_stock_de_um_armazem_nao_apaga_o_do_outro(): void
    {
        $t = $this->empresa();

        $p = Product::withoutGlobalScopes()->create([
            'tenant_id' => $t->id, 'name' => 'Artigo', 'barcode' => 'AAA',
            'code' => 'AAA', 'sku' => 'AAA', 'price' => 100, 'cost' => 50,
        ]);

        $ficheiro = $this->csv([['AAA', 'Artigo', 50, 100, 7]]);

        foreach (['Loja', 'stock'] as $armazem) {
            $this->artisan('artigos:importar', [
                '--tenant' => $t->id, '--armazem' => $armazem,
                '--ficheiro' => $ficheiro, '--so-stock' => true, '--aplicar' => true,
            ])->assertSuccessful();
        }

        // Dois armazéns, duas linhas de stock para o mesmo artigo.
        $this->assertSame(2, Stock::withoutGlobalScopes()->where('product_id', $p->id)->count());
    }
}
