<?php

namespace Tests\Feature\Contabilidade;

use App\Models\Accounting\Account;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\Period;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * OS ECRÃS DA CONTABILIDADE EM REACT.
 *
 * O que aqui se prova é sobretudo o que o ecrã em Livewire deixava passar. Os
 * sete testes que existiam sobre a contabilidade não tocavam nenhuma destas
 * regras — porque nenhuma delas estava escrita em lado nenhum:
 *
 *  · um lançamento todo a zeros «equilibrava» (zero é igual a zero);
 *  · uma linha podia ser débito E crédito ao mesmo tempo;
 *  · lançava-se numa conta de AGREGAÇÃO, e o valor contava duas vezes;
 *  · a data podia cair fora do período a que o lançamento dizia pertencer;
 *  · um rascunho de Janeiro confirmava-se em Março, num período já fechado;
 *  · um lançamento CONFIRMADO apagava-se, e a contabilidade mudava sem rasto;
 *  · duas contas podiam ter o mesmo código no mesmo plano;
 *  · e uma conta apagava-se por cima do movimento que já tinha.
 */
class EcrasDaContabilidadeEmReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/contabilidade';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('contabilidade')->comPermissoes(
            'accounting.dashboard.view',
            'accounting.accounts.view',
            'accounting.accounts.manage',
            'accounting.moves.view',
            'accounting.moves.manage',
            'accounting.reports.view',
        );
    }

    /* ─── A montagem ──────────────────────────────────────────────────── */

    private function conta(array $extra = []): Account
    {
        return Account::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'code' => (string) random_int(100000, 999999),
            'name' => 'Conta '.uniqid(),
            'type' => 'asset',
            'nature' => 'debit',
            'level' => 1,
            'is_view' => false,
            'blocked' => false,
        ], $extra));
    }

    private function diario(array $extra = []): Journal
    {
        return Journal::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'code' => 'DG'.random_int(100, 999),
            'name' => 'Diário Geral',
            'type' => 'general',
            'sequence_prefix' => 'DG-',
            'last_number' => 0,
            'active' => true,
        ], $extra));
    }

    private function periodo(array $extra = []): Period
    {
        return Period::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'code' => 'P'.random_int(1000, 9999),
            'name' => 'Período de teste',
            'date_start' => now()->startOfYear()->format('Y-m-d'),
            'date_end' => now()->endOfYear()->format('Y-m-d'),
            'state' => 'open',
        ], $extra));
    }

    /** Um lançamento confirmado, pronto a somar. */
    private function lancamentoConfirmado(Account $debito, Account $credito, float $valor, ?string $dia = null): Move
    {
        $move = Move::create([
            'tenant_id' => $this->tenant->id,
            'journal_id' => $this->diario()->id,
            'period_id' => $this->periodo()->id,
            'date' => $dia ?? now()->format('Y-m-d'),
            'ref' => 'MAN-'.uniqid(),
            'state' => 'posted',
            'total_debit' => $valor,
            'total_credit' => $valor,
            'created_by' => $this->user->id,
        ]);

        foreach ([[$debito, $valor, 0], [$credito, 0, $valor]] as [$conta, $d, $c]) {
            MoveLine::create([
                'tenant_id' => $this->tenant->id,
                'move_id' => $move->id,
                'account_id' => $conta->id,
                'debit' => $d,
                'credit' => $c,
                'balance' => $d - $c,
            ]);
        }

        return $move;
    }

    /** O corpo de um lançamento válido, para os testes o estragarem à vez. */
    private function pedido(array $extra = [], ?array $linhas = null): array
    {
        $periodo = $extra['period_id'] ?? $this->periodo()->id;

        return array_merge([
            'journal_id' => $this->diario()->id,
            'period_id' => $periodo,
            'date' => now()->format('Y-m-d'),
            'lines' => $linhas ?? [
                ['account_id' => $this->conta()->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $this->conta()->id, 'debit' => 0, 'credit' => 1000],
            ],
        ], $extra);
    }

    /* ─── As páginas ──────────────────────────────────────────────────── */

    public function test_as_tres_paginas_montam_as_ilhas_de_react(): void
    {
        $this->get(route('accounting.dashboard'))->assertOk()->assertSee('contabilidade/painel', false);
        $this->get(route('accounting.accounts'))->assertOk()->assertSee('contabilidade/contas', false);
        $this->get(route('accounting.moves'))->assertOk()->assertSee('contabilidade/lancamentos', false);
    }

    public function test_sem_permissao_a_api_recusa(): void
    {
        $outro = User::create([
            'name' => 'Sem nada', 'email' => 'n'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);
        $outro->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_active' => true]]);

        $this->actingAs($outro);
        session(['active_tenant_id' => $this->tenant->id]);

        $this->getJson(self::RAIZ.'/painel')->assertForbidden();
        $this->getJson(self::RAIZ.'/contas')->assertForbidden();
        $this->getJson(self::RAIZ.'/lancamentos')->assertForbidden();
    }

    /* ─── O painel ────────────────────────────────────────────────────── */

    /**
     * A NATUREZA DECIDE O SINAL.
     *
     * Activo e gasto crescem a débito; passivo, capital e proveito crescem a
     * crédito. Somar tudo da mesma maneira dava proveitos negativos — e um
     * painel que mostra a receita com sinal de menos não se usa duas vezes.
     */
    public function test_o_painel_da_o_sinal_certo_a_cada_natureza(): void
    {
        $caixa = $this->conta(['type' => 'asset', 'nature' => 'debit']);
        $vendas = $this->conta(['type' => 'revenue', 'nature' => 'credit']);

        $this->lancamentoConfirmado($caixa, $vendas, 50_000);

        $saldos = $this->getJson(self::RAIZ.'/painel')->assertOk()->json('saldos');

        $this->assertSame(50000.0, (float) $saldos['activo'], 'o activo cresce a débito');
        $this->assertSame(50000.0, (float) $saldos['proveitos'],
            'o proveito cresce a CRÉDITO — somado como o activo sairia a -50.000');
        $this->assertSame(50000.0, (float) $saldos['resultado'], 'proveitos menos gastos');
    }

    /** Um rascunho é uma intenção: não entra em saldo nenhum. */
    public function test_o_painel_nao_soma_rascunhos(): void
    {
        $caixa = $this->conta(['type' => 'asset']);
        $vendas = $this->conta(['type' => 'revenue', 'nature' => 'credit']);

        $this->lancamentoConfirmado($caixa, $vendas, 10_000)->update(['state' => 'draft']);

        $painel = $this->getJson(self::RAIZ.'/painel')->assertOk();

        $this->assertSame(0.0, (float) $painel->json('saldos.activo'));
        $this->assertSame(1, $painel->json('lancamentos.rascunhos'));
        $this->assertSame(0, $painel->json('lancamentos.confirmados'));
    }

    /**
     * QUEM NÃO PODE VER RELATÓRIOS NÃO VÊ VALORES — e eles não viajam.
     *
     * O painel em Blade passava cada saldo por `valorProtegido(...)`. Mandar o
     * número e esconder no ecrã era guardar o segredo na resposta HTTP.
     */
    public function test_os_saldos_nao_saem_do_servidor_a_quem_nao_ve_relatorios(): void
    {
        $caixa = $this->conta(['type' => 'asset']);
        $vendas = $this->conta(['type' => 'revenue', 'nature' => 'credit']);
        $this->lancamentoConfirmado($caixa, $vendas, 90_000);

        $semRelatorios = User::create([
            'name' => 'Só o painel', 'email' => 'p'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);
        $semRelatorios->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_active' => true]]);

        setPermissionsTeamId($this->tenant->id);
        $semRelatorios->givePermissionTo('accounting.dashboard.view');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($semRelatorios);
        session(['active_tenant_id' => $this->tenant->id]);

        $painel = $this->getJson(self::RAIZ.'/painel')->assertOk();

        $this->assertFalse($painel->json('ve_valores'));
        $this->assertNull($painel->json('saldos.activo'));
        $this->assertNull($painel->json('saldos.resultado'));
        $this->assertNull($painel->json('recentes.0.total'));
        $this->assertSame([], $painel->json('mensal.valores'));

        // A contagem de lançamentos não é dinheiro: essa fica.
        $this->assertSame(1, $painel->json('lancamentos.confirmados'));
    }

    /** Doze meses, e os vazios a zero: um gráfico que salta meses mente. */
    public function test_o_grafico_mensal_traz_doze_meses_com_os_vazios_a_zero(): void
    {
        $mensal = $this->getJson(self::RAIZ.'/painel')->assertOk()->json('mensal');

        $this->assertCount(12, $mensal['etiquetas']);
        $this->assertCount(12, $mensal['valores']);
    }

    /* ─── O plano de contas ───────────────────────────────────────────── */

    /** Duas contas «11» no mesmo plano, e nenhum relatório a saber de qual fala. */
    public function test_o_codigo_da_conta_e_unico_na_empresa(): void
    {
        $this->conta(['code' => '11']);

        $this->postJson(self::RAIZ.'/contas', [
            'code' => '11', 'name' => 'Outra', 'type' => 'asset', 'nature' => 'debit',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /** Mas o mesmo código noutra empresa é outro plano, e passa. */
    public function test_o_codigo_repete_se_entre_empresas(): void
    {
        $outra = Tenant::create([
            'name' => 'Outra Empresa', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        Account::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => '11', 'name' => 'Caixa da outra',
            'type' => 'asset', 'nature' => 'debit', 'level' => 1,
        ]);

        $this->postJson(self::RAIZ.'/contas', [
            'code' => '11', 'name' => 'Caixa', 'type' => 'asset', 'nature' => 'debit',
        ])->assertCreated();
    }

    /**
     * A MÃE TEM DE SER DE AGREGAÇÃO.
     *
     * Pendurar uma conta numa conta de movimento faz o total da mãe somar o
     * movimento dela mais o das filhas: o mesmo valor conta duas vezes.
     */
    public function test_a_conta_mae_tem_de_ser_de_agregacao(): void
    {
        $movimento = $this->conta(['is_view' => false]);

        $this->postJson(self::RAIZ.'/contas', [
            'code' => '111', 'name' => 'Filha', 'type' => 'asset', 'nature' => 'debit',
            'parent_id' => $movimento->id,
        ])->assertStatus(422)->assertJsonValidationErrors('parent_id');
    }

    /** O NÍVEL SAI DA MÃE, não da mão de quem escreve. */
    public function test_o_nivel_sai_da_conta_mae(): void
    {
        $raiz = $this->conta(['is_view' => true, 'level' => 1]);

        $filha = $this->postJson(self::RAIZ.'/contas', [
            'code' => '1101', 'name' => 'Filha', 'type' => 'asset', 'nature' => 'debit',
            'parent_id' => $raiz->id,
            // Mesmo que alguém mande um nível errado, manda a mãe.
            'level' => 9,
        ])->assertCreated()->json('data');

        $this->assertSame(2, $filha['nivel']);

        $semMae = $this->postJson(self::RAIZ.'/contas', [
            'code' => '12', 'name' => 'Raiz', 'type' => 'asset', 'nature' => 'debit',
        ])->assertCreated()->json('data');

        $this->assertSame(1, $semMae['nivel'], 'sem mãe é raiz');
    }

    /** Uma conta com movimento não passa a somar as filhas. */
    public function test_uma_conta_com_movimento_nao_passa_a_ser_de_agregacao(): void
    {
        $caixa = $this->conta();
        $this->lancamentoConfirmado($caixa, $this->conta(), 5_000);

        $this->putJson(self::RAIZ.'/contas/'.$caixa->id, [
            'code' => $caixa->code, 'name' => $caixa->name, 'type' => 'asset',
            'nature' => 'debit', 'is_view' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('is_view');
    }

    /**
     * APAGAR UMA CONTA COM MOVIMENTO deixa a razão dela órfã e o balanço com um
     * buraco. Quando não se pode apagar, BLOQUEIA-SE.
     */
    public function test_nao_se_apaga_uma_conta_com_movimento(): void
    {
        $caixa = $this->conta();
        $this->lancamentoConfirmado($caixa, $this->conta(), 3_000);

        $this->deleteJson(self::RAIZ.'/contas/'.$caixa->id)
            ->assertStatus(422)->assertJsonValidationErrors('geral');

        $this->assertDatabaseHas('accounting_accounts', ['id' => $caixa->id]);

        // Bloquear é o caminho, e funciona.
        $this->postJson(self::RAIZ.'/contas/'.$caixa->id.'/estado')
            ->assertOk()->assertJson(['bloqueada' => true]);
    }

    /** Apagar uma mãe arranca o meio da árvore e deixa as folhas soltas. */
    public function test_nao_se_apaga_uma_conta_com_filhas(): void
    {
        $mae = $this->conta(['is_view' => true]);
        $this->conta(['parent_id' => $mae->id, 'level' => 2]);

        $this->deleteJson(self::RAIZ.'/contas/'.$mae->id)
            ->assertStatus(422)->assertJsonValidationErrors('geral');
    }

    /** E nem uma conta que outra usa como reflexão. */
    public function test_nao_se_apaga_uma_conta_usada_como_reflexao(): void
    {
        $reflexo = $this->conta();
        $this->conta(['debit_reflection_account_id' => $reflexo->id]);

        $this->deleteJson(self::RAIZ.'/contas/'.$reflexo->id)
            ->assertStatus(422)->assertJsonValidationErrors('geral');
    }

    /** Uma conta sem nada em cima apaga-se, e é para isso que serve. */
    public function test_uma_conta_livre_apaga_se(): void
    {
        $conta = $this->conta();

        $this->deleteJson(self::RAIZ.'/contas/'.$conta->id)->assertOk();

        $this->assertDatabaseMissing('accounting_accounts', ['id' => $conta->id]);
    }

    /** As contas de outra empresa não se vêem nem se mexem. */
    public function test_a_conta_de_outra_empresa_da_404(): void
    {
        $outra = Tenant::create([
            'name' => 'Terceira', 'slug' => 'terceira-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 't'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $alheia = Account::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => '99', 'name' => 'Alheia',
            'type' => 'asset', 'nature' => 'debit', 'level' => 1,
        ]);

        $this->getJson(self::RAIZ.'/contas/'.$alheia->id)->assertNotFound();
        $this->deleteJson(self::RAIZ.'/contas/'.$alheia->id)->assertNotFound();
    }

    /**
     * A RAZÃO DA CONTA COMEÇA NO SALDO DE ABERTURA.
     *
     * Uma razão que começa a zero no primeiro dia do filtro é um pedaço da
     * conta, não a conta: sem a abertura, o saldo final não bate com nada.
     */
    public function test_a_razao_traz_o_saldo_de_abertura_e_o_acumulado(): void
    {
        $caixa = $this->conta(['type' => 'asset', 'nature' => 'debit']);
        $vendas = $this->conta(['type' => 'revenue', 'nature' => 'credit']);

        // Um lançamento ANTES do filtro: é o saldo de abertura.
        $this->lancamentoConfirmado($caixa, $vendas, 20_000, now()->subMonths(3)->format('Y-m-d'));
        // E dois dentro.
        $this->lancamentoConfirmado($caixa, $vendas, 5_000, now()->format('Y-m-d'));
        $this->lancamentoConfirmado($vendas, $caixa, 2_000, now()->format('Y-m-d'));

        $razao = $this->getJson(self::RAIZ.'/contas/'.$caixa->id.'/razao?'.http_build_query([
            'de' => now()->startOfMonth()->format('Y-m-d'),
            'ate' => now()->endOfMonth()->format('Y-m-d'),
        ]))->assertOk();

        $this->assertSame(20000.0, (float) $razao->json('abertura'));
        $this->assertCount(2, $razao->json('data'));
        $this->assertTrue($razao->json('conta.cresce_a_debito'));
        // 20.000 de abertura + 5.000 a débito − 2.000 a crédito.
        $this->assertSame(23000.0, (float) $razao->json('totais.saldo'));
        $this->assertSame(23000.0, (float) $razao->json('data.1.acumulado'));
    }

    /* ─── Os lançamentos ──────────────────────────────────────────────── */

    /** Zero é igual a zero: um lançamento vazio passava como se fosse bom. */
    public function test_um_lancamento_todo_a_zeros_nao_equilibra(): void
    {
        $this->postJson(self::RAIZ.'/lancamentos', $this->pedido([], [
            ['account_id' => $this->conta()->id, 'debit' => 0, 'credit' => 0],
            ['account_id' => $this->conta()->id, 'debit' => 0, 'credit' => 0],
        ]))->assertStatus(422)->assertJsonValidationErrors('lines.0.debit');
    }

    /** Uma linha com os dois aparece duas vezes na razão da conta. */
    public function test_uma_linha_nao_pode_ser_debito_e_credito(): void
    {
        $this->postJson(self::RAIZ.'/lancamentos', $this->pedido([], [
            ['account_id' => $this->conta()->id, 'debit' => 1000, 'credit' => 1000],
            ['account_id' => $this->conta()->id, 'debit' => 0, 'credit' => 1000],
        ]))->assertStatus(422)->assertJsonValidationErrors('lines.0.debit');
    }

    /** Uma conta de agregação existe para somar as filhas, não para receber. */
    public function test_nao_se_lanca_numa_conta_de_agregacao(): void
    {
        $agregacao = $this->conta(['is_view' => true]);

        $this->postJson(self::RAIZ.'/lancamentos', $this->pedido([], [
            ['account_id' => $agregacao->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->conta()->id, 'debit' => 0, 'credit' => 1000],
        ]))->assertStatus(422)->assertJsonValidationErrors('lines.0.account_id');
    }

    /** E uma bloqueada também não recebe. */
    public function test_nao_se_lanca_numa_conta_bloqueada(): void
    {
        $bloqueada = $this->conta(['blocked' => true]);

        $this->postJson(self::RAIZ.'/lancamentos', $this->pedido([], [
            ['account_id' => $bloqueada->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->conta()->id, 'debit' => 0, 'credit' => 1000],
        ]))->assertStatus(422)->assertJsonValidationErrors('lines.0.account_id');
    }

    /** Um lançamento tem pelo menos duas linhas: de onde sai e para onde vai. */
    public function test_um_lancamento_com_uma_linha_nao_passa(): void
    {
        $this->postJson(self::RAIZ.'/lancamentos', $this->pedido([], [
            ['account_id' => $this->conta()->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => '', 'debit' => 0, 'credit' => 0],
        ]))->assertStatus(422)->assertJsonValidationErrors('lines');
    }

    public function test_o_debito_e_o_credito_tem_de_bater_certo(): void
    {
        $this->postJson(self::RAIZ.'/lancamentos', $this->pedido([], [
            ['account_id' => $this->conta()->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $this->conta()->id, 'debit' => 0, 'credit' => 700],
        ]))->assertStatus(422)->assertJsonValidationErrors('lines');
    }

    /** Um lançamento fora do seu período é um lançamento que ninguém encontra. */
    public function test_a_data_tem_de_cair_dentro_do_periodo(): void
    {
        $periodo = $this->periodo([
            'date_start' => now()->startOfMonth()->format('Y-m-d'),
            'date_end' => now()->endOfMonth()->format('Y-m-d'),
        ]);

        $this->postJson(self::RAIZ.'/lancamentos', $this->pedido([
            'period_id' => $periodo->id,
            'date' => now()->addMonths(2)->format('Y-m-d'),
        ]))->assertStatus(422)->assertJsonValidationErrors('date');
    }

    public function test_um_periodo_fechado_nao_recebe_lancamentos(): void
    {
        $fechado = $this->periodo(['state' => 'closed']);

        $this->postJson(self::RAIZ.'/lancamentos', $this->pedido(['period_id' => $fechado->id]))
            ->assertStatus(422)->assertJsonValidationErrors('period_id');
    }

    /** Um lançamento bom cria-se em rascunho, e a referência sai do diário. */
    public function test_um_lancamento_valido_nasce_em_rascunho_com_referencia_do_diario(): void
    {
        $diario = $this->diario(['sequence_prefix' => 'DG-', 'last_number' => 7]);

        $criado = $this->postJson(self::RAIZ.'/lancamentos', $this->pedido(['journal_id' => $diario->id]))
            ->assertCreated()->json('data');

        $this->assertSame('draft', $criado['estado']);
        $this->assertSame('DG-00008', $criado['ref']);
        $this->assertSame(1000.0, (float) $criado['debito']);
        $this->assertSame(2, $criado['linhas']);
        $this->assertTrue($criado['pode_confirmar']);
        $this->assertTrue($criado['pode_apagar']);
        $this->assertFalse($criado['pode_estornar']);
    }

    /**
     * DUAS REFERÊNCIAS IGUAIS.
     *
     * A antiga saía ao escolher o diário e só se incrementava ao gravar: dois a
     * lançar ao mesmo tempo levavam a mesma. Agora sai sob tranca, ao gravar.
     */
    public function test_duas_criacoes_seguidas_levam_referencias_diferentes(): void
    {
        $diario = $this->diario(['sequence_prefix' => 'DG-', 'last_number' => 0]);
        $periodo = $this->periodo();

        $refs = [];

        for ($i = 0; $i < 3; $i++) {
            $refs[] = $this->postJson(self::RAIZ.'/lancamentos', $this->pedido([
                'journal_id' => $diario->id, 'period_id' => $periodo->id,
            ]))->assertCreated()->json('data.ref');
        }

        $this->assertSame(['DG-00001', 'DG-00002', 'DG-00003'], $refs);
    }

    /**
     * DOIS DIÁRIOS COM O MESMO PREFIXO.
     *
     * O prefixo é texto livre e o contador é POR DIÁRIO, mas a referência é
     * única POR EMPRESA. Dois diários «DG-» — que a lista de diários deixa
     * criar — batiam no índice único com um 500, e o lançamento perdia-se.
     */
    public function test_dois_diarios_com_o_mesmo_prefixo_nao_colidem(): void
    {
        $periodo = $this->periodo();
        $primeiro = $this->diario(['sequence_prefix' => 'DG-', 'last_number' => 0]);
        $segundo = $this->diario(['sequence_prefix' => 'DG-', 'last_number' => 0]);

        $a = $this->postJson(self::RAIZ.'/lancamentos', $this->pedido([
            'journal_id' => $primeiro->id, 'period_id' => $periodo->id,
        ]))->assertCreated()->json('data.ref');

        $b = $this->postJson(self::RAIZ.'/lancamentos', $this->pedido([
            'journal_id' => $segundo->id, 'period_id' => $periodo->id,
        ]))->assertCreated()->json('data.ref');

        $this->assertSame('DG-00001', $a);
        $this->assertSame('DG-00002', $b, 'salta-se a que já está tomada em vez de estoirar');
    }

    public function test_confirmar_poe_o_lancamento_a_contar(): void
    {
        $criado = $this->postJson(self::RAIZ.'/lancamentos', $this->pedido())
            ->assertCreated()->json('data');

        $confirmado = $this->postJson(self::RAIZ.'/lancamentos/'.$criado['id'].'/confirmar')
            ->assertOk()->json('data');

        $this->assertSame('posted', $confirmado['estado']);
        $this->assertFalse($confirmado['pode_apagar']);
        $this->assertTrue($confirmado['pode_estornar']);
        $this->assertNotNull($confirmado['confirmado_em']);
    }

    /**
     * UM RASCUNHO DE JANEIRO NÃO SE CONFIRMA EM MARÇO num período já fechado.
     *
     * Era verificado ao criar e NÃO ao confirmar: entre uma coisa e a outra
     * pode ter passado um mês e o período pode ter sido encerrado.
     */
    public function test_nao_se_confirma_para_dentro_de_um_periodo_fechado(): void
    {
        $periodo = $this->periodo();

        $criado = $this->postJson(self::RAIZ.'/lancamentos', $this->pedido(['period_id' => $periodo->id]))
            ->assertCreated()->json('data');

        // O contabilista fecha o mês depois de o rascunho ter nascido.
        $periodo->update(['state' => 'closed']);

        $this->postJson(self::RAIZ.'/lancamentos/'.$criado['id'].'/confirmar')
            ->assertStatus(422)->assertJsonValidationErrors('period_id');

        $this->assertDatabaseHas('accounting_moves', ['id' => $criado['id'], 'state' => 'draft']);
    }

    /** Um confirmado não se apaga: reescrever a contabilidade sem rasto. */
    public function test_nao_se_apaga_um_lancamento_confirmado(): void
    {
        $criado = $this->postJson(self::RAIZ.'/lancamentos', $this->pedido())->assertCreated()->json('data');
        $this->postJson(self::RAIZ.'/lancamentos/'.$criado['id'].'/confirmar')->assertOk();

        $this->deleteJson(self::RAIZ.'/lancamentos/'.$criado['id'])
            ->assertStatus(422)->assertJsonValidationErrors('geral');

        $this->assertDatabaseHas('accounting_moves', ['id' => $criado['id']]);
    }

    /** Um rascunho apaga-se com as suas linhas — nunca mexeu saldo nenhum. */
    public function test_um_rascunho_apaga_se_com_as_linhas(): void
    {
        $criado = $this->postJson(self::RAIZ.'/lancamentos', $this->pedido())->assertCreated()->json('data');

        $this->deleteJson(self::RAIZ.'/lancamentos/'.$criado['id'])->assertOk();

        $this->assertDatabaseMissing('accounting_moves', ['id' => $criado['id']]);
        $this->assertDatabaseMissing('accounting_move_lines', ['move_id' => $criado['id']]);
    }

    /**
     * O ESTORNO é simétrico, nasce confirmado, e o ORIGINAL FICA.
     *
     * É essa a diferença entre corrigir e fazer de conta que nunca aconteceu.
     */
    public function test_o_estorno_e_simetrico_nasce_confirmado_e_o_original_fica(): void
    {
        $debito = $this->conta();
        $credito = $this->conta();

        $criado = $this->postJson(self::RAIZ.'/lancamentos', $this->pedido([], [
            ['account_id' => $debito->id, 'debit' => 4000, 'credit' => 0],
            ['account_id' => $credito->id, 'debit' => 0, 'credit' => 4000],
        ]))->assertCreated()->json('data');

        $this->postJson(self::RAIZ.'/lancamentos/'.$criado['id'].'/confirmar')->assertOk();

        $estorno = $this->postJson(self::RAIZ.'/lancamentos/'.$criado['id'].'/estornar')
            ->assertCreated()->json('data');

        $this->assertSame('posted', $estorno['estado'], 'um estorno nasce confirmado');
        $this->assertNotSame($criado['id'], $estorno['id']);

        // O original fica de pé.
        $this->assertDatabaseHas('accounting_moves', ['id' => $criado['id'], 'state' => 'posted']);

        // E as linhas vêm trocadas.
        $linhas = $this->getJson(self::RAIZ.'/lancamentos/'.$estorno['id'])
            ->assertOk()->json('data.linhas_do_lancamento');

        $doDebito = collect($linhas)->firstWhere('conta_id', $debito->id);
        $doCredito = collect($linhas)->firstWhere('conta_id', $credito->id);

        $this->assertSame(4000.0, (float) $doDebito['credito'], 'o que era débito passa a crédito');
        $this->assertSame(4000.0, (float) $doCredito['debito'], 'e ao contrário');
    }

    public function test_so_se_estorna_um_confirmado(): void
    {
        $criado = $this->postJson(self::RAIZ.'/lancamentos', $this->pedido())->assertCreated()->json('data');

        $this->postJson(self::RAIZ.'/lancamentos/'.$criado['id'].'/estornar')
            ->assertStatus(422)->assertJsonValidationErrors('geral');
    }

    /**
     * OS RASCUNHOS PRESOS.
     *
     * Rascunhos em períodos já fechados: já não se podem confirmar e ficam ali
     * para sempre a parecer trabalho por acabar. Contá-los é a única forma de
     * alguém dar por eles.
     */
    public function test_a_lista_conta_os_rascunhos_presos_em_periodos_fechados(): void
    {
        $periodo = $this->periodo();

        $preso = $this->postJson(self::RAIZ.'/lancamentos', $this->pedido(['period_id' => $periodo->id]))
            ->assertCreated()->json('data');

        $this->postJson(self::RAIZ.'/lancamentos', $this->pedido(['period_id' => $this->periodo()->id]))
            ->assertCreated();

        $periodo->update(['state' => 'closed']);

        $resumo = $this->getJson(self::RAIZ.'/lancamentos')->assertOk()->json('resumo');

        $this->assertSame(2, $resumo['rascunhos']);
        $this->assertSame(1, $resumo['presos']);

        $linha = collect($this->getJson(self::RAIZ.'/lancamentos')->json('data'))
            ->firstWhere('id', $preso['id']);

        $this->assertFalse($linha['periodo_aberto']);
        $this->assertFalse($linha['pode_confirmar'], 'o ecrã não pode oferecer o que o servidor recusa');
    }

    /* ─── As opções ───────────────────────────────────────────────────── */

    /**
     * SÓ OS PERÍODOS ABERTOS, e só as contas que recebem movimento.
     *
     * Oferecer um período fechado era deixar preencher o formulário todo para
     * levar com a recusa no fim. E a lista antiga de contas só excluía as
     * bloqueadas — as de agregação apareciam.
     */
    public function test_as_opcoes_so_oferecem_o_que_o_servidor_aceita(): void
    {
        $aberto = $this->periodo(['name' => 'Aberto']);
        $this->periodo(['name' => 'Fechado', 'state' => 'closed']);

        $boa = $this->conta(['name' => 'Recebe movimento']);
        $agregacao = $this->conta(['name' => 'Agregação', 'is_view' => true]);
        $bloqueada = $this->conta(['name' => 'Bloqueada', 'blocked' => true]);

        $o = $this->getJson(self::RAIZ.'/lancamentos/opcoes')->assertOk();

        $periodos = collect($o->json('periodos'))->pluck('valor');
        $this->assertTrue($periodos->contains((string) $aberto->id));
        $this->assertCount(1, $periodos->filter(fn ($v) => $v === (string) $aberto->id));
        $this->assertFalse(collect($o->json('periodos'))->pluck('rotulo')->contains('Fechado'));

        $contas = collect($o->json('contas'))->pluck('valor');
        $this->assertTrue($contas->contains((string) $boa->id));
        $this->assertFalse($contas->contains((string) $agregacao->id), 'agregação não recebe movimento');
        $this->assertFalse($contas->contains((string) $bloqueada->id));

        $this->assertTrue($o->json('permissoes.gerir'));
    }

    /** As contas-mãe oferecidas são só as de agregação. */
    public function test_as_maes_oferecidas_sao_so_as_de_agregacao(): void
    {
        $agregacao = $this->conta(['is_view' => true]);
        $movimento = $this->conta(['is_view' => false]);

        $maes = collect($this->getJson(self::RAIZ.'/contas/opcoes')->assertOk()->json('maes'))->pluck('valor');

        $this->assertTrue($maes->contains((string) $agregacao->id));
        $this->assertFalse($maes->contains((string) $movimento->id));
    }
}
