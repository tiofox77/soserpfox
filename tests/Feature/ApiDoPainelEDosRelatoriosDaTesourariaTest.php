<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Treasury\Account;
use App\Models\Treasury\Bank;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Transaction;
use Tests\TenantTestCase;

/**
 * O PAINEL E OS RELATÓRIOS DA TESOURARIA — a API.
 *
 * Os dois só lêem, e é por isso que o que se guarda aqui são NÚMEROS:
 *
 * 1. **O SALDO NÃO SEGUE O PERÍODO.** Um saldo é o que está na conta hoje;
 *    entradas e saídas são as do período escolhido. Misturá-los faz o número
 *    do topo mudar ao trocar de semana, e ninguém percebe porquê.
 *
 * 2. **O QUE ESTÁ POR RECEBER É O QUE TEM SALDO**, e não o que tem um nome.
 *    O relatório procurava `status IN ('pending', 'partially_paid')` — e a
 *    coluna muitas vezes não diz isso numa factura por pagar. Saía com MENOS
 *    dívida do que a empresa tem, que é o pior erro num mapa de cobranças.
 *
 * 3. **AS CONTAS SÃO AS MESMAS DO PDF.** Vêm da `RelatoriosDeTesouraria`, e
 *    não daqui: um relatório que dá números diferentes no ecrã e no ficheiro
 *    é pior do que não existir.
 *
 * 4. **A DESCARGA PEDE A MESMA PERMISSÃO QUE O ECRÃ.** Não pedia nenhuma.
 */
class ApiDoPainelEDosRelatoriosDaTesourariaTest extends TenantTestCase
{
    private const PAINEL = '/api/v1/invoicing/react/tesouraria/painel';

    private const RELATORIOS = '/api/v1/invoicing/react/tesouraria/relatorios';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('treasury');
    }

    /* ─── As peças ────────────────────────────────────────────────────── */

    private function conta(float $saldo = 0): Account
    {
        $banco = Bank::firstOrCreate(
            ['code' => 'BFA'],
            ['name' => 'Banco de Fomento Angola', 'country' => 'AO', 'is_active' => true]
        );

        return Account::create([
            'tenant_id' => $this->tenant->id, 'bank_id' => $banco->id,
            'account_name' => 'Conta ' . uniqid(), 'account_number' => (string) random_int(100000, 999999),
            'currency' => 'AOA', 'initial_balance' => $saldo, 'current_balance' => $saldo, 'is_active' => true,
        ]);
    }

    private function caixa(float $saldo = 0): CashRegister
    {
        return CashRegister::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'name' => 'Caixa ' . uniqid(), 'code' => 'CX' . random_int(1000, 9999),
            'opening_balance' => $saldo, 'current_balance' => $saldo,
            'status' => 'open', 'is_active' => true,
        ]);
    }

    private function movimento(array $por = []): Transaction
    {
        return Transaction::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'transaction_number' => 'TRX-' . uniqid(),
            'type' => 'income',
            'amount' => 1000,
            'currency' => 'AOA',
            'transaction_date' => now()->toDateString(),
            'description' => 'Movimento',
            'status' => 'completed',
        ], $por));
    }

    private function factura(array $por = []): SalesInvoice
    {
        return SalesInvoice::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT ' . strtoupper(substr(uniqid(), -8)),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'invoice_status' => 'F',
            'status' => 'sent',
            'subtotal' => 10000, 'tax_amount' => 0, 'total' => 10000, 'paid_amount' => 0,
            'created_by' => $this->user->id,
        ], $por));
    }

    /* ─── O painel ────────────────────────────────────────────────────── */

    /** @test */
    public function sem_permissao_o_painel_esta_fechado(): void
    {
        $this->getJson(self::PAINEL)->assertForbidden();
    }

    /**
     * O SALDO NÃO SEGUE O PERÍODO, e as entradas seguem.
     *
     * @test
     */
    public function o_saldo_e_de_agora_e_as_entradas_sao_do_periodo(): void
    {
        $this->comPermissoes('treasury.transactions.view');

        $this->conta(30000);
        $this->caixa(5000);

        $this->movimento(['amount' => 800, 'transaction_date' => now()->toDateString()]);
        $this->movimento(['amount' => 9999, 'transaction_date' => now()->subMonths(3)->toDateString()]);

        $hoje = $this->getJson(self::PAINEL . '?periodo=today')->assertOk();

        $this->assertEqualsWithDelta(35000, $hoje->json('saldos.total'), 0.01, 'o saldo é o que lá está hoje');
        $this->assertEqualsWithDelta(30000, $hoje->json('saldos.contas'), 0.01);
        $this->assertEqualsWithDelta(5000, $hoje->json('saldos.caixas'), 0.01);
        $this->assertEqualsWithDelta(800, $hoje->json('movimento.entradas'), 0.01, 'só o de hoje');

        $ano = $this->getJson(self::PAINEL . '?periodo=year')->assertOk();

        $this->assertEqualsWithDelta(35000, $ano->json('saldos.total'), 0.01, 'o saldo não mexeu com o período');
        $this->assertEqualsWithDelta(10799, $ano->json('movimento.entradas'), 0.01, 'e as entradas mexeram');
    }

    /** Uma saída conta como saída, e o saldo do período é a diferença. @test */
    public function o_saldo_do_periodo_e_a_diferenca(): void
    {
        $this->comPermissoes('treasury.transactions.view');

        $this->movimento(['type' => 'income', 'amount' => 5000]);
        $this->movimento(['type' => 'expense', 'amount' => 1800]);
        // O pendente não conta: ainda não mexeu em saldo nenhum.
        $this->movimento(['type' => 'income', 'amount' => 9999, 'status' => 'pending']);

        $r = $this->getJson(self::PAINEL . '?periodo=today')->assertOk();

        $this->assertEqualsWithDelta(5000, $r->json('movimento.entradas'), 0.01);
        $this->assertEqualsWithDelta(1800, $r->json('movimento.saidas'), 0.01);
        $this->assertEqualsWithDelta(3200, $r->json('movimento.saldo'), 0.01);
    }

    /**
     * O QUE PRECISA DE CONSERTO — e a causa ao lado.
     *
     * Um movimento sem conta e sem caixa é dinheiro registado que não mexeu
     * saldo nenhum, e vem quase sempre de uma forma de pagamento sem destino.
     *
     * @test
     */
    public function o_painel_conta_o_que_ficou_por_arrumar(): void
    {
        $this->comPermissoes('treasury.transactions.view');

        $this->movimento(['account_id' => null, 'cash_register_id' => null]);

        PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Numerário solto',
            'code' => 'NS' . random_int(100, 999), 'type' => 'cash',
            'default_cash_register_id' => null, 'is_active' => true,
        ]);

        $r = $this->getJson(self::PAINEL)->assertOk();

        $this->assertSame(1, $r->json('por_consertar.movimentos_sem_destino'));
        $this->assertGreaterThanOrEqual(1, $r->json('por_consertar.formas_sem_destino'));
    }

    /** Facturar não é receber, e o painel mostra os dois. @test */
    public function o_painel_separa_o_facturado_do_cobrado(): void
    {
        $this->comPermissoes('treasury.transactions.view');

        $this->factura(['total' => 10000, 'paid_amount' => 4000]);

        $r = $this->getJson(self::PAINEL . '?periodo=year')->assertOk();

        $this->assertEqualsWithDelta(10000, $r->json('facturacao.facturado'), 0.01);
        $this->assertEqualsWithDelta(4000, $r->json('facturacao.cobrado'), 0.01);
        $this->assertEqualsWithDelta(6000, $r->json('facturacao.a_receber'), 0.01);
    }

    /** A linha dos sete dias tem sete pontos, e os dias vazios vão a zero. @test */
    public function o_grafico_tem_sete_dias_e_os_vazios_vao_a_zero(): void
    {
        $this->comPermissoes('treasury.transactions.view');

        $this->movimento(['amount' => 1500]);

        $r = $this->getJson(self::PAINEL)->assertOk();

        $this->assertCount(7, $r->json('grafico.dias'));
        $this->assertCount(7, $r->json('grafico.entradas'));
        $this->assertCount(7, $r->json('grafico.saidas'));
        $this->assertEqualsWithDelta(1500, $r->json('grafico.entradas.6'), 0.01, 'o último ponto é hoje');
    }

    /** As categorias saem com o nome, não com o código. @test */
    public function as_categorias_saem_legiveis(): void
    {
        $this->comPermissoes('treasury.transactions.view');

        $this->movimento(['category' => 'digital_payment', 'amount' => 700]);

        $r = $this->getJson(self::PAINEL)->assertOk();

        $this->assertSame('Pagamento digital', $r->json('categorias.entradas.0.rotulo'));
    }

    /* ─── Os relatórios ───────────────────────────────────────────────── */

    /** @test */
    public function sem_permissao_de_relatorios_nao_se_ve_nem_se_descarrega(): void
    {
        // Ver movimentos NÃO abre os relatórios: a demonstração de resultados
        // diz o lucro da empresa, e isso é outra conversa.
        $this->comPermissoes('treasury.transactions.view');

        $this->getJson(self::RELATORIOS)->assertForbidden();
        $this->get('/treasury/reports/pdf')->assertForbidden();
        $this->get('/treasury/reports/excel')->assertForbidden();
    }

    /** @test */
    public function o_fluxo_de_caixa_fecha_a_conta(): void
    {
        $this->comPermissoes('treasury.reports.view');

        // Antes do período: é o saldo inicial.
        $this->movimento(['amount' => 2000, 'transaction_date' => now()->subYear()->toDateString()]);

        $this->movimento(['type' => 'income', 'amount' => 5000, 'category' => 'sale']);
        $this->movimento(['type' => 'expense', 'amount' => 1200, 'category' => 'rent']);

        $r = $this->getJson(self::RELATORIOS . '?tipo=cash_flow&periodo=month')->assertOk();

        $this->assertEqualsWithDelta(2000, $r->json('dados.initialBalance'), 0.01);
        $this->assertEqualsWithDelta(5000, $r->json('dados.totalIncome'), 0.01);
        $this->assertEqualsWithDelta(1200, $r->json('dados.totalExpense'), 0.01);
        $this->assertEqualsWithDelta(5800, $r->json('dados.finalBalance'), 0.01, '2000 + 5000 − 1200');

        // E as categorias vêm com nome, não com código.
        $this->assertSame('Venda', $r->json('dados.incomeByCategory.0.rotulo'));
        $this->assertSame('sale', $r->json('dados.incomeByCategory.0.codigo'));
    }

    /**
     * O QUE ESTÁ POR RECEBER É O QUE TEM SALDO.
     *
     * A factura abaixo tem `status = 'sent'` — nunca `pending`. O relatório
     * de sempre procurava `status IN ('pending', 'partially_paid')` e não a
     * via: saía com menos dívida do que a empresa tem.
     *
     * @test
     */
    public function uma_factura_por_pagar_aparece_mesmo_sem_dizer_pending(): void
    {
        $this->comPermissoes('treasury.reports.view');

        $this->factura(['status' => 'sent', 'total' => 10000, 'paid_amount' => 0]);

        $r = $this->getJson(self::RELATORIOS . '?tipo=receivables&periodo=month')->assertOk();

        $this->assertCount(1, $r->json('dados.receivables'));
        $this->assertEqualsWithDelta(10000, $r->json('dados.totalReceivables'), 0.01);
    }

    /**
     * E O QUE NÃO TEM SALDO NÃO APARECE: nem a paga, nem o rascunho, nem a
     * factura-recibo do balcão, que é paga no acto.
     *
     * @test
     */
    public function o_que_esta_liquidado_ou_por_acabar_nao_e_divida(): void
    {
        $this->comPermissoes('treasury.reports.view');

        $this->factura(['status' => 'paid', 'total' => 5000, 'paid_amount' => 5000]);
        $this->factura(['status' => 'draft', 'total' => 7000, 'paid_amount' => 0]);
        $this->factura(['status' => 'sent', 'invoice_type' => 'FR', 'total' => 900, 'paid_amount' => 900]);
        $this->factura(['status' => 'cancelled', 'total' => 4000, 'paid_amount' => 0]);

        $r = $this->getJson(self::RELATORIOS . '?tipo=receivables&periodo=month')->assertOk();

        $this->assertCount(0, $r->json('dados.receivables'));
        $this->assertEqualsWithDelta(0, $r->json('dados.totalReceivables'), 0.01);
    }

    /** Uma factura vencida conta à parte. @test */
    public function o_que_ja_passou_do_vencimento_conta_a_parte(): void
    {
        $this->comPermissoes('treasury.reports.view');

        $this->factura([
            'status' => 'sent', 'total' => 3000, 'paid_amount' => 0,
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        $r = $this->getJson(self::RELATORIOS . '?tipo=receivables&periodo=month')->assertOk();

        $this->assertTrue($r->json('dados.receivables.0.overdue'));
        $this->assertEqualsWithDelta(3000, $r->json('dados.totalOverdue'), 0.01);
    }

    /** As moradas de descarga vêm montadas, com o tipo e as datas. @test */
    public function as_moradas_de_descarga_vem_do_servidor(): void
    {
        $this->comPermissoes('treasury.reports.view');

        $r = $this->getJson(self::RELATORIOS . '?tipo=dre&periodo=custom&de=2026-03-01&ate=2026-03-31')->assertOk();

        $this->assertSame('2026-03-01', $r->json('de'));
        $this->assertSame('2026-03-31', $r->json('ate'));
        $this->assertStringContainsString('tipo=dre', $r->json('descargas.pdf'));
        $this->assertStringContainsString('de=2026-03-01', $r->json('descargas.excel'));
    }

    /**
     * UM INTERVALO AO CONTRÁRIO TROCA-SE, em vez de devolver um relatório
     * vazio que ninguém percebe porque está vazio.
     *
     * @test
     */
    public function um_intervalo_ao_contrario_e_trocado(): void
    {
        $this->comPermissoes('treasury.reports.view');

        $r = $this->getJson(self::RELATORIOS . '?periodo=custom&de=2026-03-31&ate=2026-03-01')->assertOk();

        $this->assertSame('2026-03-01', $r->json('de'));
        $this->assertSame('2026-03-31', $r->json('ate'));
    }

    /** Os quatro relatórios vêm listados, e os nomes traduzem-se. @test */
    public function os_quatro_relatorios_vem_do_servidor(): void
    {
        $this->comPermissoes('treasury.reports.view');

        $tipos = collect($this->getJson(self::RELATORIOS)->assertOk()->json('tipos'))->pluck('valor')->all();

        $this->assertSame(['cash_flow', 'dre', 'receivables', 'payables'], $tipos);
    }
}
