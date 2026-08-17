<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O comando que apaga artigos da base a sério.
 *
 * O que interessa provar é o que ele NÃO apaga: o que está em documentos, e
 * o que é de outra empresa. Um erro aqui não se desfaz.
 */
class ApagarArtigosDaEmpresaTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(): Tenant
    {
        return Tenant::create([
            'name'  => 'Teste Apagar ' . uniqid(),
            'email' => uniqid() . '@teste.local',
            'nif'   => '5' . random_int(10000000, 99999999),
        ]);
    }

    private function artigo(Tenant $t, string $nome): Product
    {
        $c = strtoupper(uniqid('A'));

        return Product::withoutGlobalScopes()->create([
            'tenant_id' => $t->id,
            'name'      => $nome,
            'barcode'   => $c,
            'code'      => $c,
            'sku'       => $c,
            'price'     => 100,
            'cost'      => 50,
        ]);
    }

    public function test_simulacao_nao_apaga_nada(): void
    {
        $t = $this->empresa();
        $this->artigo($t, 'Um');

        $this->artisan('artigos:apagar', ['--tenant' => $t->id])->assertSuccessful();

        $this->assertSame(1, Product::withTrashed()->where('tenant_id', $t->id)->count());
    }

    public function test_aplicar_sem_a_segunda_confirmacao_recusa(): void
    {
        $t = $this->empresa();
        $this->artigo($t, 'Um');

        $this->artisan('artigos:apagar', ['--tenant' => $t->id, '--aplicar' => true])->assertFailed();

        $this->assertSame(1, Product::withTrashed()->where('tenant_id', $t->id)->count());
    }

    public function test_apaga_da_base_e_leva_a_reciclagem_junto(): void
    {
        $t = $this->empresa();
        $this->artigo($t, 'Vivo');
        $this->artigo($t, 'Apagado')->delete();

        $this->artisan('artigos:apagar', [
            '--tenant' => $t->id, '--aplicar' => true, '--confirmo-que-apaga' => true,
        ])->assertSuccessful();

        // A reciclagem tem de sair também: uma linha apagada continua a
        // ocupar o índice único do `code` e faria a reimportação rebentar.
        $this->assertSame(0, Product::withTrashed()->where('tenant_id', $t->id)->count());
    }

    public function test_nao_apaga_um_artigo_que_esta_preso_a_outra_tabela(): void
    {
        $t = $this->empresa();
        $vendido = $this->artigo($t, 'Tem lote registado');
        $livre = $this->artigo($t, 'Não tem nada');

        // Um lote é o caso mais barato de montar, mas a guarda é a mesma
        // para facturas e notas de crédito: o artigo está numa das tabelas
        // que lhe apontam, logo não sai.
        DB::table('invoicing_product_batches')->insert([
            'tenant_id'  => $t->id,
            'product_id' => $vendido->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('artigos:apagar', [
            '--tenant' => $t->id, '--aplicar' => true, '--confirmo-que-apaga' => true,
        ])->assertSuccessful();

        $this->assertNotNull(Product::withTrashed()->find($vendido->id), 'o artigo preso tinha de ficar');
        $this->assertNull(Product::withTrashed()->find($livre->id));
    }

    public function test_nao_toca_nos_artigos_de_outra_empresa(): void
    {
        $minha = $this->empresa();
        $vizinha = $this->empresa();

        $meu = $this->artigo($minha, 'Meu');
        $dela = $this->artigo($vizinha, 'Dela');

        $this->artisan('artigos:apagar', [
            '--tenant' => $minha->id, '--aplicar' => true, '--confirmo-que-apaga' => true,
        ])->assertSuccessful();

        $this->assertNull(Product::withTrashed()->find($meu->id));
        $this->assertNotNull(Product::withTrashed()->find($dela->id), 'a empresa vizinha não podia ser tocada');
    }
}
