<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * O comando que corrige códigos de barras já importados.
 *
 * O que interessa provar não é que ele renomeia — é que não estraga nada
 * quando o mundo não está limpo: destino ocupado, empresa vizinha, correr
 * duas vezes.
 */
class CorrigirCodigoDeBarrasTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(): Tenant
    {
        return Tenant::create([
            'name'  => 'Teste Códigos ' . uniqid(),
            'email' => uniqid() . '@teste.local',
            'nif'   => '5' . random_int(10000000, 99999999),
        ]);
    }

    private function artigo(Tenant $t, string $codigo, string $nome = 'Artigo'): Product
    {
        return Product::withoutGlobalScopes()->create([
            'tenant_id' => $t->id,
            'name'      => $nome,
            'barcode'   => $codigo,
            'code'      => $codigo,
            'sku'       => $codigo,
            'price'     => 100,
            'cost'      => 50,
        ]);
    }

    private function mapa(array $pares): string
    {
        $f = tempnam(sys_get_temp_dir(), 'mapa') . '.json';
        file_put_contents($f, json_encode($pares));

        return $f;
    }

    public function test_renomeia_o_codigo_e_tambem_code_e_sku(): void
    {
        $t = $this->empresa();
        $p = $this->artigo($t, '0108904182603610');

        $this->artisan('artigos:corrigir-codigo', [
            '--tenant'   => $t->id,
            '--ficheiro' => $this->mapa([['de' => '0108904182603610', 'para' => '8904182603610']]),
            '--aplicar'  => true,
        ])->assertSuccessful();

        $p->refresh();

        $this->assertSame('8904182603610', $p->barcode);
        $this->assertSame('8904182603610', $p->code, 'o code tinha o código errado e devia acompanhar');
        $this->assertSame('8904182603610', $p->sku);
    }

    public function test_simulacao_nao_grava_nada(): void
    {
        $t = $this->empresa();
        $p = $this->artigo($t, '0108904182603610');

        $this->artisan('artigos:corrigir-codigo', [
            '--tenant'   => $t->id,
            '--ficheiro' => $this->mapa([['de' => '0108904182603610', 'para' => '8904182603610']]),
        ])->assertSuccessful();

        $this->assertSame('0108904182603610', $p->refresh()->barcode);
    }

    public function test_nao_renomeia_quando_o_destino_ja_esta_ocupado(): void
    {
        $t = $this->empresa();
        $errado = $this->artigo($t, '0108904182603610', 'O errado');
        $certo = $this->artigo($t, '8904182603610', 'O certo');

        $this->artisan('artigos:corrigir-codigo', [
            '--tenant'   => $t->id,
            '--ficheiro' => $this->mapa([['de' => '0108904182603610', 'para' => '8904182603610']]),
            '--aplicar'  => true,
        ])->assertSuccessful();

        // Dois artigos com o mesmo código de barras seria pior do que um
        // código errado: o POS deixava de saber qual deles vender.
        $this->assertSame('0108904182603610', $errado->refresh()->barcode);
        $this->assertSame('8904182603610', $certo->refresh()->barcode);
    }

    public function test_nao_toca_em_artigos_de_outra_empresa(): void
    {
        $minha = $this->empresa();
        $vizinha = $this->empresa();

        $meu = $this->artigo($minha, '0108904182603610');
        $dela = $this->artigo($vizinha, '0108904182603610');

        $this->artisan('artigos:corrigir-codigo', [
            '--tenant'   => $minha->id,
            '--ficheiro' => $this->mapa([['de' => '0108904182603610', 'para' => '8904182603610']]),
            '--aplicar'  => true,
        ])->assertSuccessful();

        $this->assertSame('8904182603610', $meu->refresh()->barcode);
        $this->assertSame('0108904182603610', $dela->refresh()->barcode, 'a empresa vizinha não podia ser tocada');
    }

    public function test_correr_duas_vezes_nao_faz_mal(): void
    {
        $t = $this->empresa();
        $p = $this->artigo($t, '0108904182603610');
        $mapa = $this->mapa([['de' => '0108904182603610', 'para' => '8904182603610']]);

        foreach ([1, 2] as $_) {
            $this->artisan('artigos:corrigir-codigo', [
                '--tenant' => $t->id, '--ficheiro' => $mapa, '--aplicar' => true,
            ])->assertSuccessful();
        }

        $this->assertSame('8904182603610', $p->refresh()->barcode);
        $this->assertSame(1, Product::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }

    public function test_ignora_um_par_cuja_origem_nao_existe(): void
    {
        $t = $this->empresa();
        $this->artigo($t, '8904182603610');

        $this->artisan('artigos:corrigir-codigo', [
            '--tenant'   => $t->id,
            '--ficheiro' => $this->mapa([['de' => '0999999999999', 'para' => '8904182603610']]),
            '--aplicar'  => true,
        ])->assertSuccessful();

        $this->assertSame(1, Product::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }
}
