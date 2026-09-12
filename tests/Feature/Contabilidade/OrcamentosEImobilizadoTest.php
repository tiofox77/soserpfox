<?php

namespace Tests\Feature\Contabilidade;

use App\Models\Accounting\Account;
use App\Models\Accounting\Budget;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetCategory;
use App\Models\Accounting\FixedAssetDepreciation;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\Period;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * OS ORÇAMENTOS E O IMOBILIZADO em React.
 *
 * OS ORÇAMENTOS não comparavam com o real — que é o ponto inteiro de um
 * orçamento. E não havia como apagar nem mudar o estado, o total vinha do
 * browser, e dois orçamentos podiam cobrir a mesma conta no mesmo ano.
 *
 * O IMOBILIZADO ERA UMA FACHADA. O `save()` do ecrã em Livewire era isto:
 *
 *     session()->flash('success', 'Ativo salvo com sucesso!
 *         (Funcionalidade completa será implementada em breve)');
 *
 * Não gravava nada. A lista era um paginador vazio construído à mão, os quatro
 * totais eram zeros literais, e «Calcular Depreciações» flashava outra promessa.
 * As três tabelas existiam desde 2025 e nunca receberam uma linha.
 */
class OrcamentosEImobilizadoTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/contabilidade';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('contabilidade')->comPermissoes(
            'accounting.budgets.view', 'accounting.budgets.manage',
            'accounting.fixed-assets.view', 'accounting.fixed-assets.manage',
            'accounting.moves.view', 'accounting.moves.manage',
        );
    }

    private function conta(array $extra = []): Account
    {
        return Account::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'code' => (string) random_int(100000, 999999),
            'name' => 'Conta '.uniqid(),
            'type' => 'expense', 'nature' => 'debit', 'level' => 1,
            'is_view' => false, 'blocked' => false,
        ], $extra));
    }

    /** Os doze meses do ano corrente, para as amortizações terem onde cair. */
    private function exercicio(?int $ano = null): void
    {
        (new \Database\Seeders\Accounting\PeriodSeeder())->runForTenant($this->tenant->id, $ano ?? (int) now()->year);
    }

    /* ─── Os orçamentos ───────────────────────────────────────────────── */

    public function test_o_ecra_dos_orcamentos_monta_a_ilha_de_react(): void
    {
        $this->get(route('accounting.budgets'))->assertOk()->assertSee('contabilidade/orcamentos', false);
    }

    /** O TOTAL SAI DA SOMA DOS MESES, e não do que o browser mandar. */
    public function test_o_total_do_orcamento_sai_da_soma_dos_meses(): void
    {
        $conta = $this->conta();

        $this->postJson(self::RAIZ.'/orcamentos', [
            'name' => 'Compras', 'year' => now()->year, 'account_id' => $conta->id,
            'january' => 1000, 'february' => 2000, 'march' => 500,
            // O browser podia mandar um total qualquer — e mandava.
            'total' => 999999,
        ])->assertCreated();

        $o = Budget::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame(3500.0, (float) $o->total, 'o total é a soma das parcelas');
    }

    /**
     * O REALIZADO — a comparação que faltava por completo.
     *
     * E o SINAL segue a natureza da conta: um gasto cresce a débito, um proveito
     * a crédito. Somar tudo igual daria realizados negativos nas receitas.
     */
    public function test_o_orcamento_traz_o_realizado_e_o_desvio(): void
    {
        $this->exercicio();

        $gasto = $this->conta(['type' => 'expense', 'nature' => 'debit']);
        $caixa = $this->conta(['type' => 'asset', 'nature' => 'debit']);

        $periodo = Period::where('tenant_id', $this->tenant->id)
            ->whereDate('date_start', '<=', now())->whereDate('date_end', '>=', now())->firstOrFail();

        $diario = Journal::create([
            'tenant_id' => $this->tenant->id, 'code' => 'DG', 'name' => 'Geral',
            'type' => 'general', 'sequence_prefix' => 'DG-', 'last_number' => 0, 'active' => true,
        ]);

        $move = Move::create([
            'tenant_id' => $this->tenant->id, 'journal_id' => $diario->id, 'period_id' => $periodo->id,
            'date' => now()->format('Y-m-d'), 'ref' => 'DG-00001', 'state' => 'posted',
            'total_debit' => 2500, 'total_credit' => 2500, 'created_by' => $this->user->id,
        ]);

        foreach ([[$gasto, 2500, 0], [$caixa, 0, 2500]] as [$c, $d, $cr]) {
            MoveLine::create([
                'tenant_id' => $this->tenant->id, 'move_id' => $move->id, 'account_id' => $c->id,
                'debit' => $d, 'credit' => $cr, 'balance' => $d - $cr,
            ]);
        }

        $mes = mb_strtolower(now()->locale('en')->translatedFormat('F'));

        $this->postJson(self::RAIZ.'/orcamentos', [
            'name' => 'Gastos do mês', 'year' => now()->year, 'account_id' => $gasto->id,
            $mes => 2000,
        ])->assertCreated();

        $linha = $this->getJson(self::RAIZ.'/orcamentos?ano='.now()->year)->assertOk()->json('data.0');

        $this->assertSame(2000.0, (float) $linha['previsto']);
        $this->assertSame(2500.0, (float) $linha['realizado'], 'o gasto cresce a débito');
        $this->assertSame(500.0, (float) $linha['desvio'], 'gastou-se 500 acima do previsto');
        $this->assertSame(125.0, (float) $linha['execucao']);
    }

    /** Um rascunho não é um facto: não entra no realizado. */
    public function test_o_realizado_nao_conta_rascunhos(): void
    {
        $this->exercicio();

        $gasto = $this->conta();
        $periodo = Period::where('tenant_id', $this->tenant->id)->firstOrFail();

        $diario = Journal::create([
            'tenant_id' => $this->tenant->id, 'code' => 'DR', 'name' => 'Geral',
            'type' => 'general', 'sequence_prefix' => 'DR-', 'last_number' => 0, 'active' => true,
        ]);

        $move = Move::create([
            'tenant_id' => $this->tenant->id, 'journal_id' => $diario->id, 'period_id' => $periodo->id,
            'date' => now()->format('Y-m-d'), 'ref' => 'DR-00001', 'state' => 'draft',
            'total_debit' => 900, 'total_credit' => 900, 'created_by' => $this->user->id,
        ]);

        MoveLine::create([
            'tenant_id' => $this->tenant->id, 'move_id' => $move->id, 'account_id' => $gasto->id,
            'debit' => 900, 'credit' => 0, 'balance' => 900,
        ]);

        $this->postJson(self::RAIZ.'/orcamentos', [
            'name' => 'Com rascunho', 'year' => now()->year, 'account_id' => $gasto->id, 'january' => 1000,
        ])->assertCreated();

        $this->assertSame(0.0, (float) $this->getJson(self::RAIZ.'/orcamentos?ano='.now()->year)
            ->assertOk()->json('data.0.realizado'));
    }

    /** Um orçamento por conta e por ano: dois é orçamentar a dobrar. */
    public function test_nao_ha_dois_orcamentos_da_mesma_conta_no_mesmo_ano(): void
    {
        $conta = $this->conta();

        $this->postJson(self::RAIZ.'/orcamentos', [
            'name' => 'Um', 'year' => 2026, 'account_id' => $conta->id, 'january' => 100,
        ])->assertCreated();

        $this->postJson(self::RAIZ.'/orcamentos', [
            'name' => 'Outro', 'year' => 2026, 'account_id' => $conta->id, 'january' => 200,
        ])->assertStatus(422)->assertJsonValidationErrors('account_id');

        // No ano seguinte é outro orçamento, e passa.
        $this->postJson(self::RAIZ.'/orcamentos', [
            'name' => 'Do ano seguinte', 'year' => 2027, 'account_id' => $conta->id, 'january' => 200,
        ])->assertCreated();
    }

    /** Orçamentar uma conta de agregação é orçamentar o que já está nas filhas. */
    public function test_nao_se_orcamenta_uma_conta_de_agregacao(): void
    {
        $agregacao = $this->conta(['is_view' => true]);

        $this->postJson(self::RAIZ.'/orcamentos', [
            'name' => 'Da agregação', 'year' => 2026, 'account_id' => $agregacao->id, 'january' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('account_id');
    }

    /** A conta e o centro de custo são desta empresa. */
    public function test_a_conta_do_orcamento_tem_de_ser_da_empresa(): void
    {
        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $alheia = Account::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => '99', 'name' => 'Alheia',
            'type' => 'expense', 'nature' => 'debit', 'level' => 1,
        ]);

        $this->postJson(self::RAIZ.'/orcamentos', [
            'name' => 'Com conta alheia', 'year' => 2026, 'account_id' => $alheia->id, 'january' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('account_id');
    }

    /**
     * O ESTADO RESPEITA-SE.
     *
     * O gravar antigo punha sempre `draft`: um orçamento aprovado voltava a
     * rascunho na edição seguinte.
     */
    public function test_o_estado_do_orcamento_nao_volta_a_rascunho_ao_editar(): void
    {
        $conta = $this->conta();

        $this->postJson(self::RAIZ.'/orcamentos', [
            'name' => 'Aprovável', 'year' => 2026, 'account_id' => $conta->id, 'january' => 100,
        ])->assertCreated();

        $o = Budget::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->postJson(self::RAIZ.'/orcamentos/'.$o->id.'/estado', ['estado' => 'approved'])->assertOk();
        $this->assertSame('approved', $o->fresh()->status);

        // Editar sem mandar estado mantém o que lá está.
        $this->putJson(self::RAIZ.'/orcamentos/'.$o->id, [
            'name' => 'Aprovável (corrigido)', 'year' => 2026, 'account_id' => $conta->id, 'february' => 300,
        ])->assertOk();

        $this->assertSame('approved', $o->fresh()->status, 'editar não pode desaprovar');
    }

    public function test_o_orcamento_apaga_se(): void
    {
        $conta = $this->conta();
        $centro = CostCenter::create([
            'tenant_id' => $this->tenant->id, 'code' => 'CC', 'name' => 'Centro', 'type' => 'cost', 'is_active' => true,
        ]);

        $this->postJson(self::RAIZ.'/orcamentos', [
            'name' => 'Descartável', 'year' => 2026, 'account_id' => $conta->id,
            'cost_center_id' => $centro->id, 'january' => 100,
        ])->assertCreated();

        $o = Budget::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->deleteJson(self::RAIZ.'/orcamentos/'.$o->id)->assertOk();
        $this->assertDatabaseMissing('budgets', ['id' => $o->id]);
    }

    /* ─── O imobilizado ───────────────────────────────────────────────── */

    public function test_o_ecra_do_imobilizado_monta_a_ilha_de_react(): void
    {
        $this->get(route('accounting.fixed-assets'))->assertOk()->assertSee('contabilidade/imobilizado', false);
        $this->get(route('accounting.fixed-asset-categories'))->assertOk()->assertSee('familias-do-imobilizado', false);
    }

    /** @return array{0: FixedAsset, 1: Account, 2: Account} */
    private function bem(array $extra = []): array
    {
        $doBem = $this->conta(['type' => 'asset', 'name' => 'Viaturas']);
        $gasto = $this->conta(['type' => 'expense', 'name' => 'Amortizações do exercício']);
        $acumulada = $this->conta(['type' => 'asset', 'nature' => 'credit', 'name' => 'Amortizações acumuladas']);

        $r = $this->postJson(self::RAIZ.'/imobilizado', array_merge([
            'code' => 'IMO-'.random_int(1000, 9999),
            'name' => 'Viatura de teste',
            'account_id' => $doBem->id,
            'depreciation_account_id' => $gasto->id,
            'accumulated_depreciation_account_id' => $acumulada->id,
            'acquisition_date' => now()->startOfYear()->format('Y-m-d'),
            'acquisition_value' => 1_200_000,
            'residual_value' => 0,
            'useful_life_years' => 5,
            'depreciation_method' => 'linear',
        ], $extra))->assertCreated();

        return [FixedAsset::findOrFail($r->json('id')), $gasto, $acumulada];
    }

    /**
     * O REGISTO GRAVA — que é o que o ecrã antigo NÃO fazia.
     *
     * O `save()` flashava «será implementada em breve» e a tabela nunca recebeu
     * uma linha.
     */
    public function test_o_bem_grava_de_verdade(): void
    {
        [$bem] = $this->bem(['code' => 'IMO-0001', 'name' => 'Toyota Hilux', 'location' => 'Viana', 'serial_number' => 'ABC123']);

        $this->assertDatabaseHas('fixed_assets', [
            'id' => $bem->id, 'code' => 'IMO-0001', 'name' => 'Toyota Hilux',
            'location' => 'Viana', 'serial_number' => 'ABC123',
        ]);

        $this->assertSame(1200000.0, (float) $bem->acquisition_value);
        $this->assertSame(1200000.0, (float) $bem->book_value, 'um bem novo vale tudo o que custou');
        $this->assertSame('active', $bem->status);
    }

    /** E os totais são contados, não zeros literais. */
    public function test_os_totais_do_imobilizado_sao_contados(): void
    {
        $this->bem(['code' => 'IMO-A']);
        $this->bem(['code' => 'IMO-B', 'acquisition_value' => 300_000]);

        $resumo = $this->getJson(self::RAIZ.'/imobilizado')->assertOk()->json('resumo');

        $this->assertSame(2, $resumo['bens']);
        $this->assertSame(1500000.0, (float) $resumo['aquisicao']);
        $this->assertSame(1500000.0, (float) $resumo['liquido']);
    }

    public function test_o_codigo_do_bem_e_unico_por_empresa(): void
    {
        $this->bem(['code' => 'IMO-REP']);

        $doBem = $this->conta(['type' => 'asset']);

        $this->postJson(self::RAIZ.'/imobilizado', [
            'code' => 'IMO-REP', 'name' => 'Outro', 'account_id' => $doBem->id,
            'depreciation_account_id' => $doBem->id, 'accumulated_depreciation_account_id' => $doBem->id,
            'acquisition_date' => now()->format('Y-m-d'), 'acquisition_value' => 100,
            'useful_life_years' => 3, 'depreciation_method' => 'linear',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /** O residual não pode valer mais do que o bem: não haveria o que amortizar. */
    public function test_o_residual_tem_de_ser_menor_do_que_a_aquisicao(): void
    {
        $conta = $this->conta(['type' => 'asset']);

        $this->postJson(self::RAIZ.'/imobilizado', [
            'code' => 'IMO-RES', 'name' => 'Residual grande', 'account_id' => $conta->id,
            'depreciation_account_id' => $conta->id, 'accumulated_depreciation_account_id' => $conta->id,
            'acquisition_date' => now()->format('Y-m-d'),
            'acquisition_value' => 1000, 'residual_value' => 1000,
            'useful_life_years' => 5, 'depreciation_method' => 'linear',
        ])->assertStatus(422)->assertJsonValidationErrors('residual_value');
    }

    /** As três contas são desta empresa e recebem movimento. */
    public function test_as_contas_do_bem_nao_podem_ser_de_agregacao(): void
    {
        $agregacao = $this->conta(['type' => 'asset', 'is_view' => true]);
        $boa = $this->conta(['type' => 'asset']);

        $this->postJson(self::RAIZ.'/imobilizado', [
            'code' => 'IMO-AGR', 'name' => 'Com agregação', 'account_id' => $agregacao->id,
            'depreciation_account_id' => $boa->id, 'accumulated_depreciation_account_id' => $boa->id,
            'acquisition_date' => now()->format('Y-m-d'), 'acquisition_value' => 1000,
            'useful_life_years' => 5, 'depreciation_method' => 'linear',
        ])->assertStatus(422)->assertJsonValidationErrors('account_id');
    }

    /**
     * O CÁLCULO DAS AMORTIZAÇÕES — o botão que só flashava uma promessa.
     *
     * 1.200.000 em 5 anos são 20.000 por mês. Doze meses do ano dão 240.000.
     */
    public function test_calcular_as_amortizacoes_cria_uma_linha_por_mes(): void
    {
        $this->exercicio();

        [$bem] = $this->bem();

        $ate = now()->endOfYear()->format('Y-m-d');

        $r = $this->postJson(self::RAIZ.'/imobilizado/calcular', ['ate' => $ate])->assertOk();

        $this->assertSame(12, $r->json('criadas'), 'um ano de aquisição dá doze meses');

        $linhas = FixedAssetDepreciation::where('fixed_asset_id', $bem->id)->orderBy('depreciation_date')->get();

        $this->assertCount(12, $linhas);
        $this->assertSame(20000.0, (float) $linhas->first()->depreciation_amount, '1.200.000 / 60 meses');
        $this->assertSame(240000.0, (float) $linhas->last()->accumulated_depreciation);
        $this->assertSame('draft', $linhas->first()->status, 'calcular não é lançar');

        // E o bem fica com o acumulado e o líquido das linhas.
        $bem->refresh();

        $this->assertSame(240000.0, (float) $bem->accumulated_depreciation);
        $this->assertSame(960000.0, (float) $bem->book_value);
    }

    /** Calcular outra vez não duplica: as linhas do mês já existem. */
    public function test_calcular_duas_vezes_nao_duplica(): void
    {
        $this->exercicio();
        [$bem] = $this->bem();

        $ate = now()->endOfYear()->format('Y-m-d');

        $this->postJson(self::RAIZ.'/imobilizado/calcular', ['ate' => $ate])->assertOk();
        $this->postJson(self::RAIZ.'/imobilizado/calcular', ['ate' => $ate])->assertOk()->assertJson(['criadas' => 0]);

        $this->assertSame(12, FixedAssetDepreciation::where('fixed_asset_id', $bem->id)->count());
    }

    /**
     * NUNCA ABAIXO DO RESIDUAL.
     *
     * A última prestação é o que falta, não a prestação inteira — senão o valor
     * líquido passa por baixo e o bem passa a valer menos do que a sucata.
     */
    public function test_a_amortizacao_nunca_passa_o_residual(): void
    {
        $this->exercicio();

        // 1.000 de base (1.200 − 200) num ano: 83,33 por mês, e o último mês
        // fica com o que falta.
        [$bem] = $this->bem([
            'acquisition_value' => 1200, 'residual_value' => 200, 'useful_life_years' => 1,
        ]);

        $this->postJson(self::RAIZ.'/imobilizado/calcular', [
            'ate' => now()->endOfYear()->format('Y-m-d'),
        ])->assertOk();

        $bem->refresh();

        $this->assertSame(1000.0, (float) $bem->accumulated_depreciation, 'amortiza a base, não o valor todo');
        $this->assertSame(200.0, (float) $bem->book_value, 'sobra o residual');
        $this->assertSame('fully_depreciated', $bem->status);
    }

    /** Não se amortiza antes de comprar. */
    public function test_nao_se_amortiza_antes_da_aquisicao(): void
    {
        $this->exercicio();

        [$bem] = $this->bem(['acquisition_date' => now()->month(11)->startOfMonth()->format('Y-m-d')]);

        $this->postJson(self::RAIZ.'/imobilizado/calcular', [
            'ate' => now()->endOfYear()->format('Y-m-d'),
        ])->assertOk();

        $this->assertSame(2, FixedAssetDepreciation::where('fixed_asset_id', $bem->id)->count(),
            'comprado em Novembro, só Novembro e Dezembro');
    }

    /**
     * LANÇAR A AMORTIZAÇÃO — débito no gasto, crédito nas acumuladas.
     *
     * Passa pela porta única dos lançamentos, pelo que herda as guardas todas.
     */
    public function test_lancar_a_amortizacao_cria_o_lancamento_simetrico(): void
    {
        $this->exercicio();
        [$bem, $gasto, $acumulada] = $this->bem();

        Journal::create([
            'tenant_id' => $this->tenant->id, 'code' => 'AJ', 'name' => 'Ajustes',
            'type' => 'adjustment', 'sequence_prefix' => 'AJ-', 'last_number' => 0, 'active' => true,
        ]);

        $this->postJson(self::RAIZ.'/imobilizado/calcular', ['ate' => now()->format('Y-m-d')])->assertOk();

        $linha = FixedAssetDepreciation::where('fixed_asset_id', $bem->id)->orderBy('depreciation_date')->firstOrFail();

        $this->postJson(self::RAIZ.'/imobilizado/amortizacoes/'.$linha->id.'/lancar')->assertCreated();

        $linha->refresh();

        $this->assertSame('posted', $linha->status);
        $this->assertNotNull($linha->move_id);

        $move = Move::find($linha->move_id);

        $this->assertSame('posted', $move->state, 'a amortização entra confirmada');
        $this->assertSame(20000.0, (float) $move->total_debit);

        $linhas = MoveLine::where('move_id' , $move->id)->get()->keyBy('account_id');

        $this->assertSame(20000.0, (float) $linhas[$gasto->id]->debit, 'o gasto é debitado');
        $this->assertSame(20000.0, (float) $linhas[$acumulada->id]->credit, 'as acumuladas são creditadas');
    }

    /** Lançar duas vezes a mesma linha não duplica o gasto. */
    public function test_nao_se_lanca_a_mesma_amortizacao_duas_vezes(): void
    {
        $this->exercicio();
        [$bem] = $this->bem();

        Journal::create([
            'tenant_id' => $this->tenant->id, 'code' => 'AJ2', 'name' => 'Ajustes',
            'type' => 'adjustment', 'sequence_prefix' => 'AJ2-', 'last_number' => 0, 'active' => true,
        ]);

        $this->postJson(self::RAIZ.'/imobilizado/calcular', ['ate' => now()->format('Y-m-d')])->assertOk();

        $linha = FixedAssetDepreciation::where('fixed_asset_id', $bem->id)->orderBy('depreciation_date')->firstOrFail();

        $this->postJson(self::RAIZ.'/imobilizado/amortizacoes/'.$linha->id.'/lancar')->assertCreated();

        $this->postJson(self::RAIZ.'/imobilizado/amortizacoes/'.$linha->id.'/lancar')
            ->assertStatus(422)->assertJsonValidationErrors('geral');

        $this->assertSame(1, Move::where('tenant_id', $this->tenant->id)->count());
    }

    /** Uma linha já LANÇADA nunca é tocada por um novo cálculo. */
    public function test_um_novo_calculo_nao_mexe_numa_amortizacao_lancada(): void
    {
        $this->exercicio();
        [$bem] = $this->bem();

        Journal::create([
            'tenant_id' => $this->tenant->id, 'code' => 'AJ3', 'name' => 'Ajustes',
            'type' => 'adjustment', 'sequence_prefix' => 'AJ3-', 'last_number' => 0, 'active' => true,
        ]);

        $this->postJson(self::RAIZ.'/imobilizado/calcular', ['ate' => now()->format('Y-m-d')])->assertOk();

        $linha = FixedAssetDepreciation::where('fixed_asset_id', $bem->id)->orderBy('depreciation_date')->firstOrFail();
        $this->postJson(self::RAIZ.'/imobilizado/amortizacoes/'.$linha->id.'/lancar')->assertCreated();

        // Muda-se a vida útil: as linhas em rascunho seguem, a lançada fica.
        $this->putJson(self::RAIZ.'/imobilizado/'.$bem->id, [
            'code' => $bem->code, 'name' => $bem->name,
            'account_id' => $bem->account_id,
            'depreciation_account_id' => $bem->depreciation_account_id,
            'accumulated_depreciation_account_id' => $bem->accumulated_depreciation_account_id,
            'acquisition_date' => $bem->acquisition_date->format('Y-m-d'),
            'acquisition_value' => 1_200_000, 'residual_value' => 0,
            'useful_life_years' => 10, 'depreciation_method' => 'linear',
        ])->assertOk();

        $this->postJson(self::RAIZ.'/imobilizado/calcular', ['ate' => now()->format('Y-m-d')])->assertOk();

        $this->assertSame(20000.0, (float) $linha->fresh()->depreciation_amount,
            'a lançada não muda: corrige-se por estorno');
    }

    /** Um bem com amortizações lançadas não se apaga. */
    public function test_nao_se_apaga_um_bem_com_amortizacoes_lancadas(): void
    {
        $this->exercicio();
        [$bem] = $this->bem();

        Journal::create([
            'tenant_id' => $this->tenant->id, 'code' => 'AJ4', 'name' => 'Ajustes',
            'type' => 'adjustment', 'sequence_prefix' => 'AJ4-', 'last_number' => 0, 'active' => true,
        ]);

        $this->postJson(self::RAIZ.'/imobilizado/calcular', ['ate' => now()->format('Y-m-d')])->assertOk();

        $linha = FixedAssetDepreciation::where('fixed_asset_id', $bem->id)->orderBy('depreciation_date')->firstOrFail();
        $this->postJson(self::RAIZ.'/imobilizado/amortizacoes/'.$linha->id.'/lancar')->assertCreated();

        $this->deleteJson(self::RAIZ.'/imobilizado/'.$bem->id)
            ->assertStatus(422)->assertJsonValidationErrors('geral');

        $this->assertDatabaseHas('fixed_assets', ['id' => $bem->id, 'deleted_at' => null]);
    }

    /** Um bem só com rascunhos apaga-se, e leva-os. */
    public function test_um_bem_sem_amortizacoes_lancadas_apaga_se(): void
    {
        $this->exercicio();
        [$bem] = $this->bem();

        $this->postJson(self::RAIZ.'/imobilizado/calcular', ['ate' => now()->format('Y-m-d')])->assertOk();

        $this->deleteJson(self::RAIZ.'/imobilizado/'.$bem->id)->assertOk();

        $this->assertNotNull(FixedAsset::withTrashed()->find($bem->id)->deleted_at);
        $this->assertSame(0, FixedAssetDepreciation::where('fixed_asset_id', $bem->id)->count());
    }

    /** O bem de outra empresa não se vê nem se mexe. */
    public function test_o_bem_de_outra_empresa_da_404(): void
    {
        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'b'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $contaAlheia = Account::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => '98', 'name' => 'Alheia',
            'type' => 'asset', 'nature' => 'debit', 'level' => 1,
        ]);

        $alheio = FixedAsset::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => 'IMO-ALHEIO', 'name' => 'Alheio',
            'account_id' => $contaAlheia->id,
            'depreciation_account_id' => $contaAlheia->id,
            'accumulated_depreciation_account_id' => $contaAlheia->id,
            'acquisition_date' => now()->format('Y-m-d'), 'acquisition_value' => 500,
            'residual_value' => 0, 'useful_life_years' => 5, 'depreciation_method' => 'linear',
            'accumulated_depreciation' => 0, 'book_value' => 500, 'status' => 'active',
        ]);

        $this->getJson(self::RAIZ.'/imobilizado/'.$alheio->id)->assertNotFound();
        $this->deleteJson(self::RAIZ.'/imobilizado/'.$alheio->id)->assertNotFound();
    }

    /** A família traz as suas omissões para o formulário. */
    public function test_a_familia_traz_as_omissoes_dela(): void
    {
        FixedAssetCategory::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Viaturas',
            'default_useful_life' => 4, 'default_depreciation_method' => 'declining_balance',
            'default_depreciation_rate' => 25,
        ]);

        $familia = $this->getJson(self::RAIZ.'/imobilizado/opcoes')->assertOk()->json('categorias.0');

        $this->assertSame('Viaturas', $familia['rotulo']);
        $this->assertSame(4, $familia['vida_util']);
        $this->assertSame('declining_balance', $familia['metodo']);
        $this->assertSame(25.0, (float) $familia['taxa']);
    }

    /** As quotas degressivas amortizam mais no princípio. */
    public function test_as_quotas_degressivas_amortizam_mais_no_principio(): void
    {
        $this->exercicio();

        [$bem] = $this->bem([
            'acquisition_value' => 100_000, 'useful_life_years' => 5,
            'depreciation_method' => 'declining_balance', 'depreciation_rate' => 40,
        ]);

        $this->postJson(self::RAIZ.'/imobilizado/calcular', [
            'ate' => now()->endOfYear()->format('Y-m-d'),
        ])->assertOk();

        $linhas = FixedAssetDepreciation::where('fixed_asset_id', $bem->id)->orderBy('depreciation_date')->get();

        $this->assertGreaterThan(
            (float) $linhas->last()->depreciation_amount,
            (float) $linhas->first()->depreciation_amount,
            'o primeiro mês amortiza mais do que o último',
        );
    }
}
