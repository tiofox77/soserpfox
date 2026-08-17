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

    public function test_sincronizar_poe_a_zero_o_que_a_folha_diz_zero(): void
    {
        $t = $this->empresa();

        $p = Product::withoutGlobalScopes()->create([
            'tenant_id' => $t->id, 'name' => 'Vendeu-se tudo', 'barcode' => 'ZZZ',
            'code' => 'ZZZ', 'sku' => 'ZZZ', 'price' => 100, 'cost' => 50,
        ]);

        $ficheiro = $this->csv([['ZZZ', 'Vendeu-se tudo', 50, 100, 9]]);

        $this->artisan('artigos:importar', [
            '--tenant' => $t->id, '--armazem' => 'Loja',
            '--ficheiro' => $ficheiro, '--so-stock' => true, '--aplicar' => true,
        ])->assertSuccessful();

        $armazem = Warehouse::withoutGlobalScopes()->where('tenant_id', $t->id)->where('name', 'Loja')->first();
        $this->assertEquals(9, Stock::withoutGlobalScopes()
            ->where('warehouse_id', $armazem->id)->where('product_id', $p->id)->value('quantity'));

        // A contagem nova diz zero: vendeu-se tudo.
        $this->artisan('artigos:importar', [
            '--tenant' => $t->id, '--armazem' => 'Loja',
            '--ficheiro' => $this->csv([['ZZZ', 'Vendeu-se tudo', 50, 100, 0]]),
            '--sincronizar' => true, '--aplicar' => true,
        ])->assertSuccessful();

        $this->assertEquals(0, Stock::withoutGlobalScopes()
            ->where('warehouse_id', $armazem->id)->where('product_id', $p->id)->value('quantity'));
    }

    public function test_so_stock_sem_sincronizar_nao_baixa_nada(): void
    {
        $t = $this->empresa();

        $p = Product::withoutGlobalScopes()->create([
            'tenant_id' => $t->id, 'name' => 'Artigo', 'barcode' => 'YYY',
            'code' => 'YYY', 'sku' => 'YYY', 'price' => 100, 'cost' => 50,
        ]);

        $this->artisan('artigos:importar', [
            '--tenant' => $t->id, '--armazem' => 'Loja',
            '--ficheiro' => $this->csv([['YYY', 'Artigo', 50, 100, 9]]),
            '--so-stock' => true, '--aplicar' => true,
        ])->assertSuccessful();

        // Sem --sincronizar, zero quer dizer "nada a lançar" e não "esvazia".
        // É o que serve para lançar um armazém novo sem mexer nos outros.
        $this->artisan('artigos:importar', [
            '--tenant' => $t->id, '--armazem' => 'Loja',
            '--ficheiro' => $this->csv([['YYY', 'Artigo', 50, 100, 0]]),
            '--so-stock' => true, '--aplicar' => true,
        ])->assertSuccessful();

        $armazem = Warehouse::withoutGlobalScopes()->where('tenant_id', $t->id)->where('name', 'Loja')->first();
        $this->assertEquals(9, Stock::withoutGlobalScopes()
            ->where('warehouse_id', $armazem->id)->where('product_id', $p->id)->value('quantity'));
    }

    public function test_sincronizar_nao_toca_no_stock_do_outro_armazem(): void
    {
        $t = $this->empresa();

        $p = Product::withoutGlobalScopes()->create([
            'tenant_id' => $t->id, 'name' => 'Artigo', 'barcode' => 'XXX',
            'code' => 'XXX', 'sku' => 'XXX', 'price' => 100, 'cost' => 50,
        ]);

        foreach (['Loja', 'stock'] as $armazem) {
            $this->artisan('artigos:importar', [
                '--tenant' => $t->id, '--armazem' => $armazem,
                '--ficheiro' => $this->csv([['XXX', 'Artigo', 50, 100, 4]]),
                '--so-stock' => true, '--aplicar' => true,
            ])->assertSuccessful();
        }

        $this->artisan('artigos:importar', [
            '--tenant' => $t->id, '--armazem' => 'Loja',
            '--ficheiro' => $this->csv([['XXX', 'Artigo', 50, 100, 0]]),
            '--sincronizar' => true, '--aplicar' => true,
        ])->assertSuccessful();

        $loja = Warehouse::withoutGlobalScopes()->where('tenant_id', $t->id)->where('name', 'Loja')->first();
        $outro = Warehouse::withoutGlobalScopes()->where('tenant_id', $t->id)->where('name', 'stock')->first();

        $this->assertEquals(0, Stock::withoutGlobalScopes()->where('warehouse_id', $loja->id)->where('product_id', $p->id)->value('quantity'));
        $this->assertEquals(4, Stock::withoutGlobalScopes()->where('warehouse_id', $outro->id)->where('product_id', $p->id)->value('quantity'));
    }
}
