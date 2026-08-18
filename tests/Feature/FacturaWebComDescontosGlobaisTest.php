<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Sales\InvoiceCreate;
use App\Models\Invoicing\SalesInvoice;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Descontos GLOBAIS no ecrã web de factura de venda.
 *
 * O Resumo do ecrã distribuía os descontos globais pelas linhas antes de
 * apurar o IVA; o save() ignorava-os e aplicava o IVA ao valor cheio. O
 * utilizador aprovava um total e a base de dados guardava outro, e a soma
 * das linhas não fechava com o cabeçalho — que é o que a AGT valida.
 *
 * Regra fixada aqui, a mesma do caminho do PWA:
 *   base do IVA = líquido − desconto comercial (de linha e global)
 *   o desconto FINANCEIRO sai do total, já depois do imposto
 */
class FacturaWebComDescontosGlobaisTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.sales.invoices.create')->comModulo('invoicing');

        // O DiscountHelper compara o desconto (em kwanzas) contra
        // max_discount_percent (uma percentagem) e repoe a zero tudo o que
        // exceda 100. Isso e um defeito a parte; aqui levanta-se o limite
        // para o teste poder exercitar o CALCULO e nao a validacao.
        $def = \App\Models\Invoicing\InvoicingSettings::forTenant(activeTenantId());
        $def->max_discount_percent = 999;
        $def->allow_commercial_discount = true;
        $def->allow_financial_discount = true;
        $def->save();
    }

    /** Emite uma factura de 2 linhas × 1000 (bruto 2000) com os descontos dados. */
    private function emitir(array $campos = []): SalesInvoice
    {
        $produto = $this->produtoComStock(50, 1000);
        $cliente = $this->clienteEmpresa();

        $componente = Livewire::actingAs($this->user)
            ->test(InvoiceCreate::class)
            ->call('clearCart');

        $componente->call('addProduct', $produto->id)
                   ->call('updateQuantity', $produto->id, 2);

        $componente->set('client_id', $cliente->id);

        foreach ($campos as $campo => $valor) {
            $componente->set($campo, $valor);
        }

        $componente->call('save', 'draft');

        $doc = SalesInvoice::withoutGlobalScopes()->latest('id')->first();
        $this->assertNotNull($doc, 'a factura não chegou a ser gravada');

        return $doc;
    }

    public function test_o_desconto_comercial_global_baixa_o_imposto(): void
    {
        $semDesconto = $this->emitir();
        $comDesconto = $this->emitir(['discount_commercial' => 200]);

        $this->assertGreaterThan(
            (float) $comDesconto->tax_amount,
            (float) $semDesconto->tax_amount,
            'o desconto comercial global tem de baixar a base do IVA'
        );
    }

    public function test_o_desconto_financeiro_NAO_baixa_o_imposto(): void
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

    public function test_o_desconto_financeiro_baixa_o_total(): void
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
     */
    public function test_as_linhas_fecham_com_o_cabecalho(): void
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
     * A divergência propriamente dita: o que o Resumo mostra tem de ser o
     * que fica gravado.
     */
    public function test_o_resumo_do_ecra_bate_certo_com_o_documento_gravado(): void
    {
        $produto = $this->produtoComStock(50, 1000);
        $cliente = $this->clienteEmpresa();

        $componente = Livewire::actingAs($this->user)
            ->test(InvoiceCreate::class)
            ->call('clearCart');

        $componente->call('addProduct', $produto->id)
                   ->call('updateQuantity', $produto->id, 2)
                   ->set('client_id', $cliente->id)
                   ->set('discount_commercial', 200)
                   ->set('discount_financial', 100);

        // O que o utilizador vê no Resumo antes de gravar. O refresh força
        // um render depois dos set(), senão lê-se o render do mount.
        $componente->call('$refresh');
        $impostoNoEcra = (float) $componente->viewData('tax_amount');
        $totalNoEcra   = (float) $componente->viewData('total');

        $componente->call('save', 'draft');
        $doc = SalesInvoice::withoutGlobalScopes()->latest('id')->first();

        $this->assertEqualsWithDelta($impostoNoEcra, (float) $doc->tax_amount, 0.02,
            'o imposto mostrado no ecrã não é o que ficou gravado');

        $this->assertEqualsWithDelta($totalNoEcra, (float) $doc->total, 0.02,
            'o total mostrado no ecrã não é o que ficou gravado');
    }
}
