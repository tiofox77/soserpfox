<?php

namespace Tests\Feature\Tesouraria;

use App\Models\Category;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * O BALCÃO TEM DE SABER O IMPOSTO DE CADA ARTIGO (21/09/2026).
 *
 * O POS em Livewire mostrava «IVA (taxa) +X Kz» e um «TOTAL A PAGAR» COM
 * imposto. A migração para React (07/09/2026) perdeu a linha e deixou o rótulo
 * «A pagar» por cima da BASE — e o imposto é somado POR CIMA do preço do
 * artigo (ver `InvoiceCalculationHelper`).
 *
 * O resultado ao balcão: o ecrã dizia 17.000, o operador dizia 17.000 ao
 * cliente, recebia 17.000 e dava o troco sobre 17.000 — e a factura saía a
 * 19.380. A gaveta e o documento deixavam de bater e o troco estava errado.
 *
 * O ecrã não calcula taxa nenhuma: mostra a que o servidor lhe manda por
 * artigo, resolvida pelo `TaxResolver` — a MESMA fonte que ele usa a emitir.
 * Estes ensaios prendem essa promessa na API.
 */
class ImpostoNoBalcaoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes('invoicing.pos.access', 'invoicing.pos.sell');
    }

    private function artigo(array $troca = []): Product
    {
        $categoria = Category::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Geral'],
            ['is_active' => true],
        );

        return Product::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'OLEO DE CARRO ' . uniqid(),
            'type' => 'produto',
            'price' => 17000,
            'unit' => 'UN',
            'category_id' => $categoria->id,
            'manage_stock' => false,
            'is_active' => true,
        ], $troca));
    }

    /** O artigo como a grelha do balcão o devolve. */
    private function daGrelha(Product $p): ?array
    {
        $resposta = $this->getJson('/api/v1/invoicing/react/pos/artigos')->assertOk();

        return collect($resposta->json('data'))->firstWhere('id', $p->id);
    }

    public function test_a_grelha_manda_a_taxa_do_artigo(): void
    {
        $iva = Tax::create([
            'tenant_id' => $this->tenant->id, 'name' => 'IVA 14%', 'code' => 'IVA14' . random_int(100, 999), 'rate' => 14,
            'is_active' => true, 'saft_code' => 'NOR',
        ]);

        $artigo = $this->artigo(['tax_type' => 'iva', 'tax_rate_id' => $iva->id]);

        $linha = $this->daGrelha($artigo);

        $this->assertNotNull($linha, 'o artigo tem de vir na grelha do balcão');
        $this->assertArrayHasKey('taxa', $linha, 'sem a taxa o balcão não consegue mostrar o IVA');
        $this->assertEqualsWithDelta(14, (float) $linha['taxa'], 0.01);
    }

    public function test_um_artigo_isento_vem_com_taxa_zero(): void
    {
        $artigo = $this->artigo(['tax_type' => 'isento', 'exemption_reason' => 'M99']);

        $linha = $this->daGrelha($artigo);

        $this->assertNotNull($linha);
        $this->assertEqualsWithDelta(0, (float) $linha['taxa'], 0.01,
            'isento é zero, e o balcão escreve «Isento» — não se cala');
    }

    /**
     * SEM TAXA NO ARTIGO, MANDA A DA EMPRESA. É a regra do `TaxResolver`, e o
     * balcão tem de ver o mesmo que a factura vai levar.
     */
    public function test_sem_taxa_no_artigo_vale_o_imposto_por_omissao_da_empresa(): void
    {
        Tax::where('tenant_id', $this->tenant->id)->update(['is_default' => false]);

        Tax::create([
            'tenant_id' => $this->tenant->id, 'name' => 'IVA 7%', 'code' => 'IVA7' . random_int(100, 999), 'rate' => 7,
            'is_active' => true, 'is_default' => true, 'saft_code' => 'RED',
        ]);

        $artigo = $this->artigo(['tax_type' => 'iva', 'tax_rate_id' => null]);

        $linha = $this->daGrelha($artigo);

        $this->assertNotNull($linha);
        $this->assertEqualsWithDelta(7, (float) $linha['taxa'], 0.01);
    }

    /**
     * A conta do ecrã tem de dar o mesmo que a do servidor — senão volta-se ao
     * defeito: um número dito ao cliente e outro na factura.
     */
    public function test_a_conta_do_ecra_bate_com_a_do_servidor(): void
    {
        $iva = Tax::create([
            'tenant_id' => $this->tenant->id, 'name' => 'IVA 14%', 'code' => 'IVA14' . random_int(100, 999), 'rate' => 14,
            'is_active' => true, 'saft_code' => 'NOR',
        ]);

        $artigo = $this->artigo(['tax_type' => 'iva', 'tax_rate_id' => $iva->id]);
        $taxa = (float) $this->daGrelha($artigo)['taxa'];

        // O que o ecrã mostra: base 17.000 + 14% = 19.380.
        $doEcra = round(17000 + 17000 * $taxa / 100, 2);

        // O que o servidor apura para a mesma linha.
        $doServidor = \App\Helpers\InvoiceCalculationHelper::calculateTotals(
            collect([(object) [
                'price' => 17000.0,
                'quantity' => 1,
                'attributes' => ['tax_rate' => $taxa, 'discount_percent' => 0],
            ]]),
            0, 0, 0, false,
        );

        $this->assertEqualsWithDelta($doEcra, (float) $doServidor['total'], 0.01,
            'o que o operador diz ao cliente tem de ser o que a factura leva');
        $this->assertEqualsWithDelta(19380, $doEcra, 0.01);
    }
}
