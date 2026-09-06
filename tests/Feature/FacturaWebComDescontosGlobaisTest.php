<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

/**
 * Descontos GLOBAIS na factura de venda emitida pelo ecrã da web.
 *
 * O Resumo do ecrã distribuía os descontos globais pelas linhas antes de
 * apurar o IVA; o save() ignorava-os e aplicava o IVA ao valor cheio. O
 * utilizador aprovava um total e a base de dados guardava outro, e a soma
 * das linhas não fechava com o cabeçalho — que é o que a AGT valida.
 *
 * Regra fixada aqui, a mesma do caminho do PWA:
 *   base do IVA = líquido − desconto comercial (de linha e global)
 *   o desconto FINANCEIRO sai do total, já depois do imposto
 *
 * O ECRÃ É AGORA EM REACT e não faz contas nenhumas: pergunta os totais ao
 * servidor (`/factura/calcular`) e grava pela mesma porta (`/factura`), que
 * chama o `EmissorDeFacturas`. A divergência que este ensaio guarda passou a
 * ser entre esses dois — o que o Resumo mostra e o que fica gravado.
 */
class FacturaWebComDescontosGlobaisTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/factura';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.sales.invoices.create')->comModulo('invoicing');
    }

    /** Emite uma factura de 1 artigo × 2 × 1000 (bruto 2000) com os descontos dados. */
    private function emitir(array $campos = []): SalesInvoice
    {
        $produto = $this->produtoComStock(50, 1000);

        $resposta = $this->postJson(self::RAIZ, array_merge([
            'client_id'    => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(),
            'status'       => 'draft',
            'linhas'       => [['product_id' => $produto->id, 'quantity' => 2, 'price' => 1000]],
        ], $campos))->assertCreated();

        return SalesInvoice::findOrFail($resposta->json('id'));
    }

    /** @test */
    public function o_desconto_comercial_global_baixa_o_imposto(): void
    {
        $semDesconto = $this->emitir();
        $comDesconto = $this->emitir(['discount_commercial' => 200]);

        $this->assertGreaterThan(
            (float) $comDesconto->tax_amount,
            (float) $semDesconto->tax_amount,
            'o desconto comercial global tem de baixar a base do IVA'
        );
    }

    /** @test */
    public function o_desconto_financeiro_NAO_baixa_o_imposto(): void
    {
        $semDesconto = $this->emitir();
        $comDesconto = $this->emitir(['discount_financial' => 200]);

        $this->assertEqualsWithDelta(
            (float) $semDesconto->tax_amount,
            (float) $comDesconto->tax_amount,
            0.01,
            'o desconto financeiro é posterior ao IVA e não lhe pode tocar'
        );
    }

    /** @test */
    public function o_desconto_financeiro_baixa_o_total(): void
    {
        $semDesconto = $this->emitir();
        $comDesconto = $this->emitir(['discount_financial' => 200]);

        $this->assertEqualsWithDelta(
            (float) $semDesconto->total - 200,
            (float) $comDesconto->total,
            0.01
        );
    }

    /**
     * O fecho que a AGT valida: o cabeçalho tem de ser a soma das linhas.
     * Era isto que partia quando os descontos globais não desciam às linhas.
     *
     * @test
     */
    public function as_linhas_fecham_com_o_cabecalho(): void
    {
        $doc = $this->emitir([
            'discount_commercial' => 200,
            'discount_financial'  => 100,
        ]);

        $somaLiquidos = 0.0;
        $somaImpostos = 0.0;
        foreach ($doc->items as $linha) {
            $somaLiquidos += (float) $linha->subtotal - (float) $linha->discount_amount;
            $somaImpostos += (float) $linha->tax_amount;
        }

        $this->assertEqualsWithDelta($somaLiquidos, (float) $doc->net_total, 0.02,
            'net_total não é a soma dos líquidos das linhas');

        $this->assertEqualsWithDelta($somaImpostos, (float) $doc->tax_amount, 0.02,
            'o imposto do cabeçalho não é a soma do imposto das linhas');

        $this->assertEqualsWithDelta(
            (float) $doc->net_total + (float) $doc->tax_payable,
            (float) $doc->gross_total,
            0.02,
            'netTotal + taxPayable ≠ grossTotal — a AGT recusa'
        );
    }

    /**
     * A DIVERGÊNCIA PROPRIAMENTE DITA: o que o Resumo mostra tem de ser o que
     * fica gravado.
     *
     * O ecrã em React não conta nada — pede a conta a `/factura/calcular` e é
     * isso que mostra. Se essa conta e a do `EmissorDeFacturas` divergirem, o
     * utilizador volta a aprovar um total e a base de dados a guardar outro.
     *
     * @test
     */
    public function o_resumo_do_ecra_bate_certo_com_o_documento_gravado(): void
    {
        $produto = $this->produtoComStock(50, 1000);
        $linhas = [['product_id' => $produto->id, 'quantity' => 2, 'price' => 1000]];

        $resumo = $this->postJson(self::RAIZ . '/calcular', [
            'linhas'              => $linhas,
            'discount_commercial' => 200,
            'discount_financial'  => 100,
        ])->assertOk()->json('totais');

        $gravada = $this->postJson(self::RAIZ, [
            'client_id'           => $this->clienteEmpresa()->id,
            'warehouse_id'        => $this->armazem->id,
            'invoice_type'        => 'FT',
            'invoice_date'        => now()->toDateString(),
            'status'              => 'draft',
            'discount_commercial' => 200,
            'discount_financial'  => 100,
            'linhas'              => $linhas,
        ])->assertCreated();

        $doc = SalesInvoice::findOrFail($gravada->json('id'));

        $this->assertEqualsWithDelta((float) $resumo['imposto'], (float) $doc->tax_amount, 0.02,
            'o imposto mostrado no ecrã não é o que ficou gravado');

        $this->assertEqualsWithDelta((float) $resumo['total'], (float) $doc->total, 0.02,
            'o total mostrado no ecrã não é o que ficou gravado');
    }
}
