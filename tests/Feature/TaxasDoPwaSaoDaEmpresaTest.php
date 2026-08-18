<?php

namespace Tests\Feature;

use App\Models\Invoicing\Tax;
use Tests\TenantTestCase;

/**
 * As taxas que o PWA oferece são as DA EMPRESA.
 *
 * Era uma lista fixa — 0, 5, 7 e 14 — igual para toda a gente. Numa empresa em
 * não sujeição punha os 14% ao alcance de um toque, e um documento emitido
 * assim leva IVA que a empresa não pode cobrar.
 */
class TaxasDoPwaSaoDaEmpresaTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.pos.access')->comModulo('invoicing');
    }

    private function taxas(): array
    {
        $r = $this->actingAs($this->user)->getJson('/api/v1/invoicing/sync')->assertOk()->json();

        return $r['data']['tax_rates'] ?? [];
    }

    public function test_uma_empresa_isenta_nao_recebe_a_taxa_de_14(): void
    {
        Tax::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();

        Tax::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'Isento - Regime de Exclusão',
            'code'           => 'ISENTO-EXCL',
            'rate'           => 0,
            'type'           => 'iva',
            'saft_code'      => 'ISE',
            'saft_type'      => 'ISE',
            'exemption_code' => 'M04',
            'is_default'     => true,
            'is_active'      => true,
        ]);

        $taxas = $this->taxas();

        $this->assertNotEmpty($taxas);
        $this->assertSame([0.0], array_map(fn ($t) => (float) $t['rate'], $taxas));
        $this->assertSame('M04', $taxas[0]['exemption_code'], 'a AGT recusa 0% sem motivo');
    }

    public function test_uma_empresa_com_iva_recebe_as_taxas_que_tem(): void
    {
        Tax::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();

        foreach ([['IVA 14%', 'IVA14', 14, true], ['IVA 7%', 'IVA7', 7, false]] as [$nome, $codigo, $taxa, $omissao]) {
            Tax::create([
                'tenant_id' => $this->tenant->id, 'name' => $nome, 'code' => $codigo,
                'rate' => $taxa, 'type' => 'iva', 'saft_code' => 'NOR',
                'is_default' => $omissao, 'is_active' => true,
            ]);
        }

        $taxas = $this->taxas();

        $this->assertSame([7.0, 14.0], array_map(fn ($t) => (float) $t['rate'], $taxas));
    }

    public function test_uma_taxa_desactivada_nao_e_oferecida(): void
    {
        Tax::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();

        Tax::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Antiga', 'code' => 'VELHA',
            'rate' => 10, 'type' => 'iva', 'is_default' => false, 'is_active' => false,
        ]);
        Tax::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Actual', 'code' => 'IVA14',
            'rate' => 14, 'type' => 'iva', 'is_default' => true, 'is_active' => true,
        ]);

        $this->assertSame([14.0], array_map(fn ($t) => (float) $t['rate'], $this->taxas()));
    }

    public function test_sem_taxas_configuradas_vale_a_do_regime_e_nao_uma_lista_inventada(): void
    {
        Tax::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();

        $taxas = $this->taxas();

        $this->assertCount(1, $taxas, 'uma empresa sem taxas não pode receber quatro opções à escolha');
    }
}
