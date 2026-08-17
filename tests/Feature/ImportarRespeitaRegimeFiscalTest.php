<?php

namespace Tests\Feature;

use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A importação tem de nascer com o regime fiscal da empresa.
 *
 * A folha importada não traz regime nenhum e o valor por omissão da tabela é
 * "iva". Numa empresa em não sujeição isso punha o catálogo inteiro a
 * liquidar IVA que ela não pode cobrar — e só se dava por isso na primeira
 * factura.
 */
class ImportarRespeitaRegimeFiscalTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(string $regime): Tenant
    {
        return Tenant::create([
            'name'   => 'Teste Regime ' . uniqid(),
            'email'  => uniqid() . '@teste.local',
            'nif'    => '5' . random_int(10000000, 99999999),
            'regime' => $regime,
        ]);
    }

    private function csv(string $codigo): string
    {
        $f = tempnam(sys_get_temp_dir(), 'reg') . '.csv';
        $h = fopen($f, 'w');
        fputcsv($h, ['codigo_barras', 'descricao', 'preco_compra', 'preco_venda', 'quantidade']);
        fputcsv($h, [$codigo, 'Artigo importado', 50, 100, 3]);
        fclose($h);

        return $f;
    }

    private function importar(Tenant $t, string $codigo): Product
    {
        $this->artisan('artigos:importar', [
            '--tenant'   => $t->id,
            '--armazem'  => 'Loja',
            '--ficheiro' => $this->csv($codigo),
            '--aplicar'  => true,
        ])->assertSuccessful();

        return Product::query()->where('tenant_id', $t->id)->where('barcode', $codigo)->firstOrFail();
    }

    public function test_empresa_em_nao_sujeicao_importa_artigos_isentos(): void
    {
        $t = $this->empresa(Tenant::REGIME_NAO_SUJEICAO);
        $p = $this->importar($t, 'ISENTO-1');

        $this->assertSame('isento', $p->tax_type);
        $this->assertNull($p->tax_rate_id);
        $this->assertSame('M04', $p->exemption_reason, 'o motivo de isenção é obrigatório no documento');
    }

    public function test_empresa_no_regime_geral_importa_artigos_com_iva(): void
    {
        $t = $this->empresa(Tenant::REGIME_GERAL);

        $taxa = Tax::create([
            'tenant_id' => $t->id,
            'name'      => 'IVA 14%',
            'code'      => 'IVA14',
            'rate'      => 14,
            'type'      => 'iva',
            'is_active' => true,
        ]);

        $p = $this->importar($t, 'IVA-1');

        $this->assertSame('iva', $p->tax_type);
        $this->assertSame($taxa->id, (int) $p->tax_rate_id);
        $this->assertNull($p->exemption_reason);
    }

    public function test_nao_mexe_no_regime_de_um_artigo_que_ja_existia(): void
    {
        $t = $this->empresa(Tenant::REGIME_NAO_SUJEICAO);

        // Alguém decidiu, artigo a artigo, que este leva IVA. Uma reimportação
        // da mesma folha não pode desfazer essa decisão.
        $p = Product::withoutGlobalScopes()->create([
            'tenant_id'        => $t->id,
            'name'             => 'Decidido à mão',
            'barcode'          => 'MANUAL-1',
            'code'             => 'MANUAL-1',
            'sku'              => 'MANUAL-1',
            'price'            => 100,
            'cost'             => 50,
            'tax_type'         => 'iva',
            'exemption_reason' => null,
        ]);

        $this->importar($t, 'MANUAL-1');

        $this->assertSame('iva', $p->refresh()->tax_type);
    }
}
