<?php

namespace Tests\Feature;

use App\Models\Invoicing\LineTax;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Services\Invoicing\ImpostosDaLinha;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * IEC e Imposto de Selo de uma linha, no sítio certo.
 *
 * A regra vivia dentro do ecrã de criar factura de venda. Os outros documentos
 * — proforma de venda, factura e proforma de compra — não os calculavam: o
 * mesmo artigo com IEC saía com o imposto na factura e sem ele na proforma que
 * a antecedeu.
 *
 * Ficam gravados em `invoicing_line_taxes`, a tabela polimórfica de impostos
 * por linha, que é a forma que a AGT usa: `taxes[]` é uma LISTA, porque a
 * mesma linha pode ter IVA, IEC e IS ao mesmo tempo. Chegaram a ser criadas
 * colunas próprias de IEC e de IS nas linhas, por engano meu, e foram
 * removidas — este teste também serve para essa ideia não voltar.
 */
class ImpostosDaLinhaTest extends TenantTestCase
{
    private function comPautal(string $codigo, float $taxa): void
    {
        DB::table('agt_iec_pautal_codes')->updateOrInsert(
            ['pautal_code' => $codigo],
            [
                'description'     => 'Artigo de teste',
                'category'        => 'Teste',
                'rate_percentage' => $taxa,
                'is_active'       => true,
            ]
        );
    }

    private function comVerba(string $numero, string $taxa, string $tipo): void
    {
        DB::table('agt_is_verbas')->updateOrInsert(
            ['verba_no' => $numero],
            [
                'description' => 'Verba de teste',
                'rate'        => $taxa,
                'rate_type'   => $tipo,
                'is_active'   => true,
            ]
        );
    }

    /** Uma linha a sério: a tabela tem chave estrangeira para a factura. */
    private function linha(): SalesInvoiceItem
    {
        $factura = SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'created_by'     => $this->user->id,
            'invoice_number' => 'FT S/' . random_int(100000, 999999),
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->toDateString(),
            'status'         => 'sent',
            'subtotal'       => 1000,
            'total'          => 1140,
        ]);

        $linha = SalesInvoiceItem::create([
            'sales_invoice_id' => $factura->id,
            'product_name'     => 'Artigo',
            'description'      => 'Artigo',
            'quantity'         => 1,
            'unit'             => 'UN',
            'unit_price'       => 1000,
            'subtotal'         => 1000,
            'tax_rate'         => 14,
            'tax_amount'       => 140,
            'total'            => 1140,
            'order'            => 1,
        ]);
        $linha->tenant_id = $this->tenant->id;

        return $linha;
    }

    public function test_o_iec_e_percentual_sobre_a_base(): void
    {
        $this->comPautal('2203.00', 19);

        $taxas = ImpostosDaLinha::calcular(1000, '2203.00', null);

        $this->assertCount(1, $taxas);
        $this->assertSame('IEC', $taxas[0]['tax_type']);
        $this->assertSame(190.0, $taxas[0]['tax_amount']);
    }

    public function test_uma_verba_de_valor_fixo_nao_depende_da_base(): void
    {
        // "AOA 100" é 100 quer a linha valha 1.000 quer valha 50.000.
        $this->comVerba('23.1', 'AOA 100', 'FIXED');

        $mil   = ImpostosDaLinha::calcular(1000, null, '23.1');
        $muito = ImpostosDaLinha::calcular(50000, null, '23.1');

        $this->assertSame(100.0, $mil[0]['tax_amount']);
        $this->assertSame(100.0, $muito[0]['tax_amount']);
        $this->assertSame(0.0, (float) $mil[0]['tax_percentage'], 'numa verba fixa não há percentagem');
    }

    public function test_uma_linha_pode_ter_iec_e_is_ao_mesmo_tempo(): void
    {
        // É por isto que os impostos são uma LISTA e não colunas na linha.
        $this->comPautal('2402.20', 30);
        $this->comVerba('1.1', '1%', 'PERCENTAGE');

        $taxas = ImpostosDaLinha::calcular(1000, '2402.20', '1.1');

        $this->assertCount(2, $taxas);
        $this->assertSame(['IEC', 'IS'], array_column($taxas, 'tax_type'));
    }

    public function test_sem_escolha_nao_ha_imposto_extra(): void
    {
        $this->assertSame([], ImpostosDaLinha::calcular(1000, null, null));
    }

    public function test_grava_na_tabela_de_impostos_por_linha(): void
    {
        $this->comPautal('2203.00', 19);
        $linha = $this->linha();

        $total = ImpostosDaLinha::gravar($linha, ImpostosDaLinha::calcular(1000, '2203.00', null), 'AO');

        $this->assertSame(190.0, $total);
        $this->assertSame(1, LineTax::where('line_id', $linha->id)->count());
    }

    public function test_regravar_substitui_em_vez_de_somar(): void
    {
        // Numa edição, os impostos antigos deixam de valer. Somar-lhes os novos
        // daria imposto a dobrar no documento.
        $this->comPautal('2203.00', 19);
        $linha = $this->linha();

        $taxas = ImpostosDaLinha::calcular(1000, '2203.00', null);
        ImpostosDaLinha::gravar($linha, $taxas, 'AO');
        ImpostosDaLinha::gravar($linha, $taxas, 'AO');

        $this->assertSame(1, LineTax::where('line_id', $linha->id)->count());
    }

    public function test_no_selo_o_codigo_e_a_verba(): void
    {
        // 'NOR' é código de IVA; a AGT recusa a combinação com Imposto de Selo.
        $this->comVerba('1.1', '1%', 'PERCENTAGE');
        $linha = $this->linha();

        ImpostosDaLinha::gravar($linha, ImpostosDaLinha::calcular(1000, null, '1.1'), 'AO');

        $this->assertSame('1.1', LineTax::where('line_id', $linha->id)->value('tax_code'));
    }
}
