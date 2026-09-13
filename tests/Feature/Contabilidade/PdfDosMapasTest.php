<?php

namespace Tests\Feature\Contabilidade;

use App\Models\Accounting\Account;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\Period;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\CashFlowService;
use App\Services\Accounting\IncomeStatementFunctionService;
use App\Services\Accounting\IncomeStatementNatureService;
use Tests\TenantTestCase;

/**
 * O PDF DOS QUATRO MAPAS LEGAIS.
 *
 * O botão «PDF» do ecrã dos relatórios dava 500 no Balanço, nas duas
 * Demonstrações de Resultados e nos Fluxos de Caixa: o `ReportExportService`
 * carregava quatro vistas (`accounting.exports.pdf.*`) que NUNCA EXISTIRAM.
 * Só a das retenções estava escrita.
 */
class PdfDosMapasTest extends TenantTestCase
{
    private const MAPAS = ['balance_sheet', 'income_statement_nature', 'income_statement_function', 'cash_flow'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('contabilidade')->comPermissoes('accounting.reports.view');
    }

    private function conta(string $codigo, string $nome, string $tipo, ?string $chave = null): Account
    {
        return Account::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => $codigo],
            [
                'name' => $nome, 'type' => $tipo,
                'nature' => in_array($tipo, ['asset', 'expense'], true) ? 'debit' : 'credit',
                'level' => strlen($codigo), 'is_view' => false, 'blocked' => false,
                'integration_key' => $chave,
            ]
        );
    }

    /** Um lançamento confirmado de duas linhas: débito numa conta, crédito noutra. */
    private function lancamento(Account $debito, Account $credito, float $valor): void
    {
        $periodo = Period::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'P-'.now()->format('Y-m')],
            [
                'name' => 'Período', 'state' => 'open',
                'date_start' => now()->startOfYear()->format('Y-m-d'),
                'date_end' => now()->endOfYear()->format('Y-m-d'),
            ]
        );

        $diario = Journal::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'DG'],
            ['name' => 'Geral', 'type' => 'general', 'sequence_prefix' => 'DG-', 'last_number' => 0, 'active' => true]
        );

        $move = Move::create([
            'tenant_id' => $this->tenant->id, 'journal_id' => $diario->id,
            'period_id' => $periodo->id, 'date' => now()->format('Y-m-d'), 'ref' => 'DG-'.uniqid(),
            'state' => 'posted', 'total_debit' => $valor, 'total_credit' => $valor,
            'created_by' => $this->user->id,
        ]);

        MoveLine::create([
            'tenant_id' => $this->tenant->id, 'move_id' => $move->id, 'account_id' => $debito->id,
            'debit' => $valor, 'credit' => 0, 'balance' => $valor,
        ]);

        MoveLine::create([
            'tenant_id' => $this->tenant->id, 'move_id' => $move->id, 'account_id' => $credito->id,
            'debit' => 0, 'credit' => $valor, 'balance' => -$valor,
        ]);
    }

    /** Um mês de movimento que toca as rubricas dos quatro mapas. */
    private function movimento(): void
    {
        $caixa = $this->conta('111', 'Caixa de teste', 'asset');
        $clientes = $this->conta('211', 'Clientes de teste', 'asset');
        $fornecedores = $this->conta('221', 'Fornecedores de teste', 'liability');
        $imobilizado = $this->conta('431', 'Equipamento de teste', 'asset');
        $capital = $this->conta('511', 'Capital de teste', 'equity');
        $cmvmc = $this->conta('611', 'Mercadorias vendidas de teste', 'expense', 'cogs');
        $fst = $this->conta('621', 'Serviços de teste', 'expense');
        $pessoal = $this->conta('631', 'Remunerações de teste', 'expense', 'payroll');
        $vendas = $this->conta('711', 'Vendas de teste', 'revenue', 'sales');

        $this->lancamento($caixa, $capital, 500000);
        $this->lancamento($clientes, $vendas, 200000);
        $this->lancamento($cmvmc, $fornecedores, 80000);
        $this->lancamento($fst, $caixa, 30000);
        $this->lancamento($pessoal, $caixa, 50000);
        $this->lancamento($imobilizado, $caixa, 100000);
    }

    private function descarregar(string $mapa)
    {
        return $this->get(route('accounting.reports.descarregar', [
            'mapa' => $mapa, 'formato' => 'pdf',
            'de' => now()->startOfMonth()->format('Y-m-d'),
            'ate' => now()->endOfMonth()->format('Y-m-d'),
        ]));
    }

    public function test_os_quatro_mapas_descarregam_em_pdf_com_movimento(): void
    {
        $this->movimento();

        foreach (self::MAPAS as $mapa) {
            $r = $this->descarregar($mapa);

            $r->assertOk();
            $this->assertStringContainsString('application/pdf', (string) $r->headers->get('content-type'), $mapa);
            $this->assertStringStartsWith('%PDF', (string) $r->getContent(), $mapa);
        }
    }

    /** Sem lançamento nenhum as vistas não rebentam: o PDF sai, com zeros. */
    public function test_os_quatro_mapas_descarregam_em_pdf_sem_movimento(): void
    {
        foreach (self::MAPAS as $mapa) {
            $r = $this->descarregar($mapa);

            $r->assertOk();
            $this->assertStringContainsString('application/pdf', (string) $r->headers->get('content-type'), $mapa);
        }
    }

    /**
     * O QUE VAI NO PAPEL é o que o serviço calculou: as contas, os totais e os
     * resultados. O PDF sai comprimido, por isso confere-se o HTML da vista.
     */
    public function test_as_vistas_desenham_as_contas_e_os_totais(): void
    {
        $this->movimento();

        $de = now()->startOfMonth()->format('Y-m-d');
        $ate = now()->endOfMonth()->format('Y-m-d');
        $empresa = ['name' => 'Empresa de Teste', 'nif' => '5000000000', 'address' => '', 'city' => 'Luanda', 'country' => 'Angola'];
        $tenantId = $this->tenant->id;

        $balanco = view('accounting.exports.pdf.balance-sheet', [
            'data' => app(BalanceSheetService::class)->generate($tenantId, $ate),
            'date' => $ate, 'company' => $empresa,
        ])->render();

        $this->assertStringContainsString('Caixa de teste', $balanco);
        // Caixa: 500.000 − 30.000 − 50.000 − 100.000.
        $this->assertStringContainsString('320.000,00', $balanco);
        $this->assertStringContainsString('Resultado Líquido do Período', $balanco);
        // Resultado: 200.000 − 80.000 − 30.000 − 50.000.
        $this->assertStringContainsString('40.000,00', $balanco);

        $natureza = view('accounting.exports.pdf.income-nature', [
            'data' => app(IncomeStatementNatureService::class)->generate($tenantId, $de, $ate),
            'dateFrom' => $de, 'dateTo' => $ate, 'company' => $empresa,
        ])->render();

        $this->assertStringContainsString('Vendas de teste', $natureza);
        $this->assertStringContainsString('200.000,00', $natureza);
        // Resultado bruto: 200.000 − 80.000.
        $this->assertStringContainsString('120.000,00', $natureza);
        $this->assertStringContainsString('Resultado operacional', $natureza);

        $funcoes = view('accounting.exports.pdf.income-function', [
            'data' => app(IncomeStatementFunctionService::class)->generate($tenantId, $de, $ate),
            'dateFrom' => $de, 'dateTo' => $ate, 'company' => $empresa,
        ])->render();

        $this->assertStringContainsString('Gastos administrativos', $funcoes);
        // As vendas chegam do serviço como texto decimal («200000.00») e saem
        // formatadas na mesma.
        $this->assertStringContainsString('200.000,00', $funcoes);
        $this->assertStringContainsString('40.000,00', $funcoes);
        $this->assertStringContainsString('Margem bruta', $funcoes);

        $fluxos = view('accounting.exports.pdf.cash-flow', [
            'data' => app(CashFlowService::class)->generate($tenantId, $de, $ate),
            'dateFrom' => $de, 'dateTo' => $ate, 'company' => $empresa,
        ])->render();

        $this->assertStringContainsString('Depreciações e amortizações', $fluxos);
        $this->assertStringContainsString('Activos fixos tangíveis', $fluxos);
        $this->assertStringContainsString('Caixa e equivalentes no fim do período', $fluxos);
        // O equipamento pago sai no investimento, com sinal negativo.
        $this->assertStringContainsString('-100.000,00', $fluxos);
    }
}
