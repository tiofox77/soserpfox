<?php

namespace Tests\Feature\Treasury;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Invoicing\Relatorios\LucrosEPerdas;
use App\Services\Treasury\DreIntegrado;
use App\Services\Treasury\RelatoriosDeTesouraria;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * O DRE INTEGRADO E AS CORRECÇÕES DOS OUTROS DOIS (27/09/2026, TKT-000003).
 *
 * Um mês montado à mão, com as contas feitas no papel:
 *
 *   vendas 12 000 (10 × 1 000 de mercadoria + um serviço de 2 000)
 *   − descontos 1 000 + nota de débito 300 − nota de crédito 1 000 = 10 300
 *   − CMV: 10 × 500 vendidos − 1 × 500 devolvido = 4 500      → lucro bruto 5 800
 *   − despesas: 800 (factura de serviço) + 3 000 salários + 1 500 renda
 *     + 200 comissões por classificar = 5 500                  → operacional 300
 *   − impostos 400                                              → PREJUÍZO de 100
 *
 * E o que NÃO pode contar: a compra de mercadoria (2 500), o pagamento dessa
 * factura (2 850), a transferência (5 000), o recebimento da venda, a
 * devolução paga da nota de crédito e o equipamento (10 000).
 */
class DreIntegradoTest extends TenantTestCase
{
    private string $de;
    private string $ate;
    private Product $mercadoria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comModulo('invoicing');

        $this->de = now()->startOfMonth()->toDateString();
        $this->ate = now()->endOfMonth()->toDateString();
        $dia = now()->startOfMonth()->addDays(9);

        $this->mercadoria = Product::create(['tenant_id' => $this->tenant->id, 'code' => 'M-'.uniqid(), 'name' => 'Mercadoria', 'type' => 'produto', 'price' => 1000, 'cost' => 900]);
        $servico = Product::create(['tenant_id' => $this->tenant->id, 'code' => 'S-'.uniqid(), 'name' => 'Instalação', 'type' => 'servico', 'price' => 2000, 'cost' => 0]);
        $fornecedor = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Fornecedor', 'type' => 'pessoa_juridica', 'is_active' => true]);

        // ── A compra de mercadoria: 5 × 500, entra em stock a 500 (antes da venda) ──
        $compra = $this->factura('invoicing_purchase_invoices', [
            'invoice_number' => 'FC '.uniqid(), 'supplier_id' => $fornecedor->id, 'invoice_date' => $dia->copy()->subDays(5),
            'status' => 'pending', 'subtotal' => 2500, 'net_total' => 2500, 'tax_amount' => 350, 'total' => 2850,
        ]);
        DB::table('invoicing_purchase_invoice_items')->insert([
            'purchase_invoice_id' => $compra, 'product_id' => $this->mercadoria->id, 'product_name' => 'Mercadoria',
            'quantity' => 5, 'unit_price' => 500, 'subtotal' => 2500, 'total' => 2850,
        ]);
        $this->entrada(500, $dia->copy()->subDays(5), PurchaseInvoice::class, $compra);
        // Uma compra MAIS CARA depois da venda: não pode mexer no CMV desta venda.
        $this->entrada(800, $dia->copy()->addDays(5), PurchaseInvoice::class, $compra);

        // ── A factura de um serviço comprado: despesa de 800 ──
        $servicoComprado = $this->factura('invoicing_purchase_invoices', [
            'invoice_number' => 'FC '.uniqid(), 'supplier_id' => $fornecedor->id, 'invoice_date' => $dia,
            'status' => 'pending', 'is_service' => true, 'subtotal' => 800, 'net_total' => 800, 'tax_amount' => 112, 'total' => 912,
        ]);
        DB::table('invoicing_purchase_invoice_items')->insert([
            'purchase_invoice_id' => $servicoComprado, 'product_id' => $servico->id, 'product_name' => 'Manutenção',
            'quantity' => 1, 'unit_price' => 800, 'subtotal' => 800, 'total' => 912,
        ]);

        // ── A venda: 10 × 1 000 com 10 % de desconto + um serviço de 2 000 ──
        $venda = $this->factura('invoicing_sales_invoices', [
            'invoice_number' => 'FT '.uniqid(), 'client_id' => $this->cliente->id, 'invoice_date' => $dia, 'created_by' => $this->user->id,
            'status' => 'pending', 'subtotal' => 11000, 'net_total' => 11000, 'tax_amount' => 1540, 'total' => 12540,
        ]);
        DB::table('invoicing_sales_invoice_items')->insert([
            ['sales_invoice_id' => $venda, 'product_id' => $this->mercadoria->id, 'product_name' => 'Mercadoria', 'quantity' => 10, 'unit_price' => 1000, 'discount_amount' => 1000, 'subtotal' => 9000, 'total' => 10260],
            ['sales_invoice_id' => $venda, 'product_id' => $servico->id, 'product_name' => 'Instalação', 'quantity' => 1, 'unit_price' => 2000, 'discount_amount' => 0, 'subtotal' => 2000, 'total' => 2280],
        ]);

        // Um rascunho não é venda.
        $rascunho = $this->factura('invoicing_sales_invoices', [
            'invoice_number' => 'RASC '.uniqid(), 'client_id' => $this->cliente->id, 'invoice_date' => $dia, 'created_by' => $this->user->id,
            'status' => 'draft', 'subtotal' => 99999, 'net_total' => 99999, 'tax_amount' => 0, 'total' => 99999,
        ]);
        DB::table('invoicing_sales_invoice_items')->insert([
            'sales_invoice_id' => $rascunho, 'product_id' => $this->mercadoria->id, 'product_name' => 'Mercadoria', 'quantity' => 50, 'unit_price' => 1999.98, 'subtotal' => 99999, 'total' => 99999,
        ]);

        // ── Nota de crédito de DEVOLUÇÃO de 1 unidade, e uma nota de débito ──
        $nc = $this->factura('invoicing_credit_notes', [
            'credit_note_number' => 'NC '.uniqid(), 'client_id' => $this->cliente->id, 'invoice_id' => $venda, 'issue_date' => $dia->copy()->addDay(),
            'reason' => 'return', 'status' => 'issued', 'subtotal' => 1000, 'net_total' => 1000, 'tax_amount' => 140, 'total' => 1140,
        ]);
        DB::table('invoicing_credit_note_items')->insert([
            'credit_note_id' => $nc, 'product_id' => $this->mercadoria->id, 'description' => 'Mercadoria', 'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000, 'total' => 1140,
        ]);
        $this->factura('invoicing_debit_notes', [
            'debit_note_number' => 'ND '.uniqid(), 'client_id' => $this->cliente->id, 'invoice_id' => $venda, 'issue_date' => $dia->copy()->addDay(),
            'status' => 'issued', 'subtotal' => 300, 'net_total' => 300, 'tax_amount' => 42, 'total' => 342,
        ]);

        // ── A tesouraria ──
        $this->movimento('expense', 'supplier_payment', 2850, ['purchase_id' => $compra]);   // paga a compra: fora
        $this->movimento('expense', 'salary', 3000);
        $this->movimento('expense', 'rent', 1500);
        $this->movimento('expense', 'transfer', 5000);                                     // transferência: fora
        $this->movimento('income', 'customer_payment', 12540, ['invoice_id' => $venda]);   // recebimento: fora
        $this->movimento('expense', 'credit_note', 1140, ['invoice_id' => $venda]);        // devolução paga: fora
        $this->movimento('expense', 'comissoes_bancarias', 200);                           // por classificar → despesa
        $this->movimento('expense', 'tax', 400);                                           // imposto
        $this->movimento('expense', 'equipamento', 10000);
        DB::table('treasury_naturezas')->insert(['tenant_id' => $this->tenant->id, 'categoria' => 'equipamento', 'natureza' => 'activo', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function factura(string $tabela, array $dados): int
    {
        return DB::table($tabela)->insertGetId(['tenant_id' => $this->tenant->id, 'created_at' => now(), 'updated_at' => now()] + $dados);
    }

    private function entrada(float $custo, $quando, string $tipo, int $id): void
    {
        DB::table('invoicing_stock_movements')->insert([
            'tenant_id' => $this->tenant->id, 'warehouse_id' => $this->armazem->id, 'product_id' => $this->mercadoria->id,
            'type' => 'in', 'quantity' => 5, 'unit_cost' => $custo, 'reference_type' => $tipo, 'reference_id' => $id,
            'user_id' => $this->user->id, 'created_at' => $quando, 'updated_at' => $quando,
        ]);
    }

    private function movimento(string $tipo, string $categoria, float $valor, array $extra = []): int
    {
        return DB::table('treasury_transactions')->insertGetId($extra + [
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'transaction_number' => 'MOV-'.uniqid(),
            'type' => $tipo, 'category' => $categoria, 'amount' => $valor, 'status' => 'completed',
            'transaction_date' => now()->startOfMonth()->addDays(12)->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function dre(): DreIntegrado
    {
        return new DreIntegrado($this->tenant->id, $this->de, $this->ate);
    }

    // ─────────────────────────────────────────────────────────────────────

    public function test_a_receita_reconcilia_vendas_descontos_e_notas_sem_iva(): void
    {
        $r = $this->dre()->receita();

        $this->assertSame(12000.0, $r['vendas'], 'o rascunho não é venda');
        $this->assertSame(1000.0, $r['descontos']);
        $this->assertSame(300.0, $r['notas_debito']);
        $this->assertSame(1000.0, $r['notas_credito']);
        $this->assertSame(10300.0, $r['receita_liquida'], 'vendas − descontos + débitos − créditos');
    }

    public function test_o_cmv_usa_o_custo_a_data_da_venda_e_a_devolucao_devolve_o(): void
    {
        $cmv = $this->dre()->cmv();

        $this->assertSame(5000.0, $cmv['vendido'], '10 × 500 — não o custo de hoje (900) nem a compra de depois (800)');
        $this->assertSame(500.0, $cmv['devolvido']);
        $this->assertSame(4500.0, $cmv['liquido']);
    }

    public function test_o_resultado_nao_desconta_duas_vezes_e_mostra_o_prejuizo(): void
    {
        $d = $this->dre()->dados();

        $this->assertSame(5800.0, $d['lucro_bruto']);
        $this->assertSame(800.0, $d['despesas']['documentos'], 'só a parte de serviços das facturas de compra');
        $this->assertSame(4700.0, $d['despesas']['movimentos'], 'salários + renda + comissões — sem compra, transferência nem activo');
        $this->assertSame(300.0, $d['resultado_operacional']);
        $this->assertSame(-100.0, $d['resultado_liquido']);
        $this->assertSame('Prejuízo do período', collect($d['linhas'])->last()['rotulo']);

        // O que não entra está à vista, para bater certo com a tesouraria.
        $fora = collect($d['fora_do_resultado'])->keyBy('rubrica');
        $this->assertSame(2850.0, $fora['facturas_de_compra']['saidas']);
        $this->assertSame(5000.0, $fora['transferencia']['saidas']);
        $this->assertSame(10000.0, $fora['activo']['saidas']);
        $this->assertSame(1140.0, $fora['devolucao']['saidas']);
        $this->assertSame(12540.0, $fora['recebimento']['entradas']);
        $this->assertSame(2500.0, $fora['compras_stock_documentos']['saidas']);

        // A categoria nova, nunca classificada, é pedida a quem lê.
        $this->assertSame(['comissoes_bancarias'], array_column($d['por_classificar'], 'categoria'));
    }

    public function test_classificar_uma_categoria_muda_a_linha_e_nao_o_resultado(): void
    {
        $this->comPermissoes('treasury.reports.view', 'treasury.transactions.edit');

        $this->putJson('/api/v1/invoicing/react/tesouraria/relatorios/naturezas', ['naturezas' => ['comissoes_bancarias' => 'encargo_financeiro']])
            ->assertOk();

        $d = $this->dre()->dados();
        $this->assertSame(4500.0, $d['despesas']['movimentos']);
        $this->assertSame(500.0, $d['resultado_operacional']);
        $this->assertSame(-100.0, $d['resultado_liquido'], 'muda a linha, não o lucro');
        $this->assertSame([], $d['por_classificar']);
    }

    public function test_cada_valor_abre_os_documentos_e_movimentos_que_o_compoem(): void
    {
        $this->comPermissoes('treasury.reports.view');
        $url = '/api/v1/invoicing/react/tesouraria/relatorios/dre-integrado/detalhe';
        $periodo = ['periodo' => 'custom', 'de' => $this->de, 'ate' => $this->ate];

        $cmv = $this->getJson($url.'?'.http_build_query(['rubrica' => 'cmv'] + $periodo))->assertOk();
        $this->assertSame(4500.0, (float) $cmv->json('total'));
        $this->assertCount(2, $cmv->json('linhas'), 'a linha vendida e a devolvida');
        $this->assertStringContainsString('/invoicing/sales/invoices/', $cmv->json('linhas.0.ligacao'));

        $despesas = $this->getJson($url.'?'.http_build_query(['rubrica' => 'despesas_movimentos'] + $periodo))->assertOk();
        $this->assertSame(4700.0, (float) $despesas->json('total'));
        $this->assertStringContainsString('/treasury/transactions?ver=', $despesas->json('linhas.0.ligacao'));
    }

    public function test_o_dre_da_faturacao_reconcilia_e_o_mensal_usa_a_mesma_base(): void
    {
        $d = (new LucrosEPerdas())->dados($this->tenant->id, ['period' => 'custom', 'dateFrom' => $this->de, 'dateTo' => $this->ate]);

        $this->assertSame(10300.0, $d['netRevenue']);
        $this->assertSame(round($d['grossRevenue'] - $d['discounts'] + $d['debits'] - $d['returns'], 2), $d['netRevenue'], 'os descontos entram na receita líquida');
        $this->assertSame(5800.0, $d['grossProfit']);

        $esteMes = collect($d['monthly'])->last();
        $this->assertSame($d['netRevenue'], $esteMes['revenue'], 'o mês e o quadro principal são a mesma conta');
        $this->assertSame($d['grossProfit'], $esteMes['profit']);
    }

    /** O suporte vê os três lado a lado, sem entrar na conta do cliente. */
    public function test_o_comando_mostra_os_tres_dre_da_empresa(): void
    {
        $this->artisan('relatorios:dre-integrado', ['--tenant' => $this->tenant->id, '--de' => $this->de, '--ate' => $this->ate])
            ->expectsOutputToContain('DRE Integrado')
            ->expectsOutputToContain('Prejuízo do período')
            ->assertSuccessful();
    }

    public function test_o_dre_da_tesouraria_abate_as_notas_e_nao_repete_as_compras(): void
    {
        $d = (new RelatoriosDeTesouraria($this->tenant->id, $this->de, $this->ate))->demonstracaoDeResultados();

        $this->assertSame(1140.0, (float) $d['deductions'], 'a nota de crédito abate à receita');
        $this->assertSame(round(12540 - 1140, 2), round((float) $d['netRevenue'], 2));

        $categorias = collect($d['expensesByCategory'])->pluck('category')->all();
        $this->assertNotContains('supplier_payment', $categorias, 'a compra já está nos custos operacionais');
        $this->assertNotContains('credit_note', $categorias, 'a devolução já está nas deduções');
        $this->assertContains('salary', $categorias);
    }
}
