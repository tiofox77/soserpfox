<?php

namespace Tests\Feature;

use App\Services\Invoicing\ModuleInvoiceService;
use App\Services\Invoicing\TaxResolver;
use Tests\TenantTestCase;

/**
 * Emissor fiscal partilhado pelos módulos de negócio.
 *
 * Cada asserção aqui corresponde a um defeito real encontrado em auditoria:
 * IVA fixo a 14%, IRT sobre peças, descontos de linha perdidos, totais SAFT a
 * zero e linhas isentas sem código de isenção.
 */
class ModuleInvoiceServiceTest extends TenantTestCase
{
    private function servico(): ModuleInvoiceService
    {
        return app(ModuleInvoiceService::class);
    }

    public function test_imposto_vem_do_regime_da_empresa_e_nao_de_uma_constante(): void
    {
        $factura = $this->servico()->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'lines' => [
                ['name' => 'Serviço', 'quantity' => 1, 'unit_price' => 10000, 'is_service' => true],
            ],
        ]);

        $this->assertEquals(1400, round((float) $factura->tax_amount, 2), 'IVA devia seguir os 14% do regime');
        $this->assertEquals(14, (float) $factura->items->first()->tax_rate);
    }

    public function test_artigo_isento_sai_a_zero_com_codigo_de_isencao(): void
    {
        $produto = $this->produtoComStock(0, 10000);
        $produto->update(['tax_type' => 'isento', 'exemption_reason' => null]);
        TaxResolver::clearCache();

        $factura = $this->servico()->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'lines' => [
                ['product_id' => $produto->id, 'name' => $produto->name, 'quantity' => 1, 'unit_price' => 10000],
            ],
        ]);

        $linha = $factura->items->first();

        $this->assertEquals(0.0, (float) $linha->tax_rate);
        $this->assertSame('ISE', $linha->tax_code);
        $this->assertNotEmpty($linha->tax_exemption_code, 'A AGT recusa uma linha isenta sem motivo');
    }

    public function test_retencao_de_irt_incide_so_sobre_servicos(): void
    {
        $factura = $this->servico()->emitir([
            'tenant_id'    => $this->tenant->id,
            'client_id'    => $this->cliente->id,
            'retencao_irt' => true,
            'lines' => [
                ['name' => 'Mão-de-obra', 'quantity' => 1, 'unit_price' => 10000, 'is_service' => true],
                ['name' => 'Peça',        'quantity' => 1, 'unit_price' => 20000, 'is_service' => false],
            ],
        ]);

        // 6,5% de 10.000 (só o serviço) = 650. Sobre tudo dariam 1.950.
        $this->assertEquals(650, round((float) $factura->irt_amount, 2));
    }

    public function test_sem_retencao_pedida_nao_retem(): void
    {
        $factura = $this->servico()->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'lines' => [
                ['name' => 'Serviço', 'quantity' => 1, 'unit_price' => 10000, 'is_service' => true],
            ],
        ]);

        $this->assertEquals(0.0, (float) $factura->irt_amount, 'Um particular não retém');
    }

    public function test_descontos_de_linha_reduzem_o_total_do_documento(): void
    {
        $factura = $this->servico()->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'lines' => [
                ['name' => 'Serviço', 'quantity' => 1, 'unit_price' => 10000, 'discount_percent' => 10, 'is_service' => true],
            ],
        ]);

        $this->assertEquals(9000, round((float) $factura->net_total, 2), 'O desconto de linha tem de entrar no documento');
    }

    public function test_identidade_saft_net_mais_imposto_igual_a_gross(): void
    {
        $factura = $this->servico()->emitir([
            'tenant_id'    => $this->tenant->id,
            'client_id'    => $this->cliente->id,
            'retencao_irt' => true,
            'lines' => [
                ['name' => 'Serviço', 'quantity' => 1, 'unit_price' => 10000, 'is_service' => true],
            ],
        ]);

        $this->assertEqualsWithDelta(
            (float) $factura->net_total + (float) $factura->tax_payable,
            (float) $factura->gross_total,
            0.02,
            'A AGT recusa o documento se netTotal + taxPayable ≠ grossTotal'
        );

        // A retenção NÃO se abate ao grossTotal; abate-se ao que o cliente paga.
        $this->assertLessThan((float) $factura->gross_total, (float) $factura->total);
    }

    public function test_totais_saft_nunca_ficam_a_zero(): void
    {
        $factura = $this->servico()->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'lines' => [
                ['name' => 'Serviço', 'quantity' => 1, 'unit_price' => 10000, 'is_service' => true],
            ],
        ]);

        // Têm default 0.00 (não NULL): se não forem preenchidos, o `??` dos
        // serviços da AGT nunca cai no fallback e o documento é assinado a zero.
        $this->assertGreaterThan(0, (float) $factura->net_total);
        $this->assertGreaterThan(0, (float) $factura->gross_total);
        $this->assertNotEmpty($factura->saft_hash);
        $this->assertNotEmpty($factura->system_entry_date);
        $this->assertSame('F', $factura->invoice_status);
    }

    public function test_cada_linha_fica_ligada_a_um_artigo_do_catalogo(): void
    {
        $factura = $this->servico()->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'lines' => [
                ['name' => 'Mão-de-obra avulsa', 'quantity' => 1, 'unit_price' => 5000, 'is_service' => true],
            ],
        ]);

        $this->assertNotNull(
            $factura->items->first()->product_id,
            'A AGT exige artigo do catálogo na linha, como já exige cliente no documento'
        );
    }

    public function test_a_origem_do_modulo_fica_gravada(): void
    {
        $factura = $this->servico()->emitir([
            'tenant_id'     => $this->tenant->id,
            'client_id'     => $this->cliente->id,
            'origem_modulo' => 'hotel',
            'origem'        => 'RES-000123',
            'lines' => [
                ['name' => 'Hospedagem', 'quantity' => 1, 'unit_price' => 10000, 'is_service' => true],
            ],
        ]);

        $this->assertSame('hotel', $factura->source_module);
        $this->assertSame('RES-000123', $factura->source_reference);
    }

    public function test_uma_venda_de_modulo_desconta_o_stock(): void
    {
        // O observer desconta no evento `created` da factura, mas nessa altura
        // as linhas ainda não existem — a colecção é criada a seguir. Sem a
        // chamada explícita, restaurante, hotel e salão vendiam produtos sem
        // nunca tocar no stock.
        $produto = $this->produtoComStock(10);

        $this->servico()->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'status'    => 'paid',
            'lines' => [
                ['product_id' => $produto->id, 'name' => $produto->name, 'quantity' => 3, 'unit_price' => 5000],
            ],
        ]);

        $stock = (float) \App\Models\Invoicing\Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $produto->id)
            ->sum('quantity');

        $this->assertEquals(7, $stock, 'vendidas 3 de 10 — o stock tem de acompanhar');
    }

    public function test_um_rascunho_nao_desconta_stock(): void
    {
        $produto = $this->produtoComStock(10);

        $this->servico()->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'status'    => 'draft',
            'lines' => [
                ['product_id' => $produto->id, 'name' => $produto->name, 'quantity' => 3, 'unit_price' => 5000],
            ],
        ]);

        $stock = (float) \App\Models\Invoicing\Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $produto->id)
            ->sum('quantity');

        $this->assertEquals(10, $stock, 'um rascunho ainda não vendeu nada');
    }

    public function test_documento_sem_linhas_e_recusado(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->servico()->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'lines'     => [],
        ]);
    }

    public function test_cliente_de_outra_empresa_e_recusado(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => '5' . random_int(100000000, 999999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $alheio = \App\Models\Client::create([
            'tenant_id' => $outra->id, 'name' => 'Alheio',
            'nif' => (string) random_int(300000000, 399999999),
            'type' => 'pessoa_fisica', 'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->servico()->emitir([
            'tenant_id' => $this->tenant->id,
            'client_id' => $alheio->id,
            'lines' => [['name' => 'X', 'quantity' => 1, 'unit_price' => 100]],
        ]);
    }
}
