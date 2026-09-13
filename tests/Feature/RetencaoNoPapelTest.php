<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesProforma;
use App\Support\TotaisDoPapel;
use Tests\TenantTestCase;

/**
 * A RETENÇÃO NA FONTE SAI DO TOTAL UMA VEZ — no papel também.
 *
 * Todos os sítios que gravam documentos (o emissor de facturas, o de compras,
 * o POS, os módulos, o PWA, as propostas) guardam o `total` JÁ SEM a retenção:
 * é o que o cliente paga. Os modelos de papel voltavam a descontá-la — «Total a
 * Pagar = total − retenção», e o valor por extenso atrás. Uma factura de
 * serviço de 2.000 Kz + IVA 280 com 130 de IRT dizia «Total a Pagar 2.020,00»
 * quando o documento vale 2.150,00; e o «Total da Fatura» mostrava 2.150,00,
 * que é já o valor depois da retenção.
 *
 * O papel passa a ler as duas coisas de um sítio só (App\Support\TotaisDoPapel):
 *   Total do documento = total + retenção (antes de reter)
 *   Retenção           = irt_amount
 *   Total a pagar      = total
 */
class RetencaoNoPapelTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes(
            'invoicing.sales.invoices.create', 'invoicing.sales.invoices.view',
            'invoicing.sales.proformas.create', 'invoicing.sales.proformas.view',
        );
    }

    /** Factura de serviço: 2 × 1000 = 2000 de base, IVA 14% = 280, IRT 6,5% = 130. */
    private function facturaDeServico(): SalesInvoice
    {
        $produto = $this->produtoComStock(50, 1000);

        $r = $this->postJson('/api/v1/invoicing/react/factura', [
            'client_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'is_service' => true,
            'linhas' => [['product_id' => $produto->id, 'quantity' => 2, 'price' => 1000]],
        ])->assertCreated();

        return SalesInvoice::findOrFail($r->json('id'));
    }

    public function test_o_documento_grava_o_total_ja_sem_a_retencao(): void
    {
        $f = $this->facturaDeServico();

        $this->assertEqualsWithDelta(130, (float) $f->irt_amount, 0.01);
        $this->assertEqualsWithDelta(2150, (float) $f->total, 0.01, 'o total gravado é o que se paga: 2000 + 280 − 130');
    }

    public function test_o_papel_da_factura_nao_desconta_a_retencao_duas_vezes(): void
    {
        $f = $this->facturaDeServico();

        $html = $this->get('/invoicing/sales/invoices/' . $f->id . '/preview')->assertOk()->getContent();
        $texto = preg_replace('/\s+/', ' ', strip_tags($html));

        $this->assertMatchesRegularExpression('/Total da Fatura 2\.280,00/', $texto, 'o total do documento é antes da retenção');
        $this->assertMatchesRegularExpression('/Retenção 130,00/', $texto);
        $this->assertMatchesRegularExpression('/Total a Pagar 2\.150,00/', $texto, 'a pagar é o total gravado, sem voltar a reter');
        $this->assertStringNotContainsString('2.020,00', $texto, 'a retenção saía duas vezes');
        $this->assertStringContainsString(numberToWords(2150, 'AOA'), $html, 'o extenso diz o valor a pagar');
    }

    public function test_o_talao_nao_desconta_a_retencao_duas_vezes(): void
    {
        $f = $this->facturaDeServico();
        // O POS antigo marcava as linhas de serviço assim — era por aí que o
        // talão recalculava a retenção e a tirava outra vez ao total.
        $f->items()->update(['description' => '[SERVIÇO] Montagem']);

        $texto = preg_replace('/\s+/', ' ', strip_tags($this->get('/invoicing/sales/invoices/' . $f->id . '/talao')->assertOk()->getContent()));

        $this->assertStringContainsString('Retenção IRT (6.5%): -130.00 Kz', $texto);
        $this->assertStringContainsString('TOTAL GERAL: 2,150.00 Kz', $texto, 'o total geral é o total gravado');
        $this->assertStringNotContainsString('2,020.00', $texto, 'a retenção saía duas vezes');
    }

    public function test_a_conformidade_agt_nao_desconta_a_retencao_do_gross_total(): void
    {
        $f = $this->facturaDeServico();

        $this->assertEqualsWithDelta((float) $f->net_total + (float) $f->tax_payable, (float) $f->gross_total, 0.01);

        $erros = \App\Helpers\AGTHelper::validateAGT($f)['errors'];

        $this->assertEmpty(
            array_filter($erros, fn ($e) => str_contains($e, 'Totais inconsistentes')),
            'a retenção saía do GrossTotal outra vez: ' . implode(' | ', $erros),
        );
    }

    public function test_a_proforma_de_servico_tambem(): void
    {
        $produto = $this->produtoComStock(50, 1000);

        $r = $this->postJson('/api/v1/invoicing/react/emissor/proformas-venda', [
            'parte_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'data' => now()->toDateString(),
            'is_service' => true,
            'linhas' => [['product_id' => $produto->id, 'quantity' => 2, 'price' => 1000]],
        ])->assertCreated();

        $p = SalesProforma::findOrFail($r->json('id'));
        $this->assertGreaterThan(0, (float) $p->irt_amount);

        $texto = preg_replace('/\s+/', ' ', strip_tags($this->get('/invoicing/sales/proformas/' . $p->id . '/preview')->assertOk()->getContent()));
        $aPagar = number_format((float) $p->total, 2, ',', '.');
        $doDocumento = number_format((float) $p->total + (float) $p->irt_amount, 2, ',', '.');

        $this->assertStringContainsString("Total da Proforma {$doDocumento}", $texto);
        $this->assertStringContainsString("Total a Pagar {$aPagar}", $texto);
    }

    public function test_nenhum_modelo_volta_a_descontar_a_retencao(): void
    {
        foreach (glob(resource_path('views/pdf/invoicing/*.blade.php')) as $modelo) {
            $conteudo = file_get_contents($modelo);

            $this->assertDoesNotMatchRegularExpression(
                '/->total\s*\??\?*\s*0?\s*\)?\s*-\s*\(?\s*\$\w+->irt_amount/',
                $conteudo,
                basename($modelo) . ' volta a descontar a retenção a um total que já a leva'
            );
            // E a variante com a retenção numa variável ($irtAmount, $retencao…).
            $this->assertDoesNotMatchRegularExpression(
                '/->total\s*-\s*\$(irt|reten)\w*/i',
                $conteudo,
                basename($modelo) . ' volta a descontar a retenção a um total que já a leva'
            );
        }
    }

    public function test_as_contas_do_papel(): void
    {
        $doc = new SalesInvoice();
        $doc->forceFill(['total' => 2150, 'irt_amount' => 130]);

        $this->assertEqualsWithDelta(2280, TotaisDoPapel::doDocumento($doc), 0.001);
        $this->assertEqualsWithDelta(2150, TotaisDoPapel::aPagar($doc), 0.001);

        $semRetencao = (new SalesInvoice())->forceFill(['total' => 1140, 'irt_amount' => null]);
        $this->assertEqualsWithDelta(1140, TotaisDoPapel::doDocumento($semRetencao), 0.001);
        $this->assertEqualsWithDelta(1140, TotaisDoPapel::aPagar($semRetencao), 0.001);
    }
}
