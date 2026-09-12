<?php

namespace Tests\Feature\Contabilidade;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankReconciliationItem;
use App\Models\Accounting\IntegrationMapping;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\Period;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TenantTestCase;

/**
 * A RECONCILIAÇÃO, OS RELATÓRIOS E AS DEFINIÇÕES em React.
 *
 * IMPORTAR UM EXTRACTO NUNCA FUNCIONOU: o serviço chama
 * `whereDoesntHave('bankReconciliationItem')` e essa relação não existia no
 * `MoveLine` — o auto-match corre no fim da importação, o Eloquent atirava «Call
 * to undefined relationship», e o ecrã mostrava-o como «Erro ao importar». O
 * botão «Ver» da lista não tinha clique nenhum, pelo que as linhas do extracto
 * não se viam nem se casavam.
 *
 * AS DEFINIÇÕES abriam com `settings.view` e TODAS as escritas eram livres:
 * correr os seeders, ligar a integração automática e reescrever os mapeamentos
 * das contas.
 *
 * E OS RELATÓRIOS calculavam o balancete inteiro em todas as visitas, além de
 * dois exports que chamavam métodos inexistentes.
 */
class ReconciliacaoRelatoriosEDefinicoesTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/contabilidade';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('contabilidade')->comPermissoes(
            'accounting.reconciliation.view', 'accounting.reconciliation.manage',
            'accounting.reports.view',
            'accounting.settings.view', 'accounting.settings.edit',
        );
    }

    private function conta(array $extra = []): Account
    {
        return Account::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'code' => (string) random_int(100000, 999999),
            'name' => 'Conta '.uniqid(),
            'type' => 'asset', 'nature' => 'debit', 'level' => 1,
            'is_view' => false, 'blocked' => false,
        ], $extra));
    }

    private function periodo(): Period
    {
        return Period::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'P-'.now()->format('Y-m')],
            [
                'name' => 'Período', 'state' => 'open',
                'date_start' => now()->startOfYear()->format('Y-m-d'),
                'date_end' => now()->endOfYear()->format('Y-m-d'),
            ]
        );
    }

    private function diario(): Journal
    {
        return Journal::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'DG'],
            ['name' => 'Geral', 'type' => 'general', 'sequence_prefix' => 'DG-', 'last_number' => 0, 'active' => true]
        );
    }

    /** Um lançamento confirmado com uma linha numa conta, num dia. */
    private function lancamento(Account $conta, float $debito, float $credito, string $dia): MoveLine
    {
        $move = Move::create([
            'tenant_id' => $this->tenant->id, 'journal_id' => $this->diario()->id,
            'period_id' => $this->periodo()->id, 'date' => $dia, 'ref' => 'DG-'.uniqid(),
            'state' => 'posted', 'total_debit' => $debito, 'total_credit' => $credito,
            'created_by' => $this->user->id,
        ]);

        $linha = MoveLine::create([
            'tenant_id' => $this->tenant->id, 'move_id' => $move->id, 'account_id' => $conta->id,
            'debit' => $debito, 'credit' => $credito, 'balance' => $debito - $credito,
            'name' => 'Transferência do cliente',
        ]);

        MoveLine::create([
            'tenant_id' => $this->tenant->id, 'move_id' => $move->id, 'account_id' => $this->conta()->id,
            'debit' => $credito, 'credit' => $debito, 'balance' => $credito - $debito,
        ]);

        return $linha;
    }

    /* ─── A reconciliação ─────────────────────────────────────────────── */

    public function test_os_tres_ecras_montam_as_ilhas_de_react(): void
    {
        $this->get(route('accounting.reconciliation'))->assertOk()->assertSee('contabilidade/reconciliacao', false);
        $this->get(route('accounting.reports'))->assertOk()->assertSee('contabilidade/relatorios', false);
        $this->get(route('accounting.settings'))->assertOk()->assertSee('contabilidade/definicoes', false);
    }

    /**
     * IMPORTAR UM EXTRACTO — o que nunca funcionou.
     *
     * A relação que faltava no `MoveLine` fazia o auto-match atirar «Call to
     * undefined relationship» no fim de cada importação.
     */
    public function test_importar_um_extracto_csv_cria_as_linhas(): void
    {
        $banco = $this->conta(['code' => '1102', 'name' => 'Depósitos à ordem']);

        $csv = "data,referencia,descricao,valor\n"
            ."2026-09-01,TRF001,Transferência do cliente,150000\n"
            ."2026-09-05,CHQ002,Pagamento ao fornecedor,-45000\n";

        $r = $this->postJson(self::RAIZ.'/reconciliacao/importar', [
            'account_id' => $banco->id,
            'file_type' => 'csv',
            'file' => UploadedFile::fake()->createWithContent('extracto.csv', $csv),
        ])->assertCreated();

        $reconciliacao = BankReconciliation::findOrFail($r->json('id'));

        $this->assertSame(2, $reconciliacao->items()->count());
        // A DATA DO EXTRACTO é a mais recente, não a da última linha do ficheiro.
        $this->assertSame('2026-09-05', $reconciliacao->statement_date instanceof \DateTimeInterface
            ? $reconciliacao->statement_date->format('Y-m-d')
            : (string) $reconciliacao->statement_date);

        $entrada = $reconciliacao->items()->where('type', 'credit')->firstOrFail();
        $saida = $reconciliacao->items()->where('type', 'debit')->firstOrFail();

        $this->assertSame(150000.0, (float) $entrada->amount);
        $this->assertSame(45000.0, (float) $saida->amount, 'o valor sai em absoluto; o sinal é o tipo');
    }

    /** Um CSV sem linhas legíveis não é uma importação: é um ficheiro por ler. */
    public function test_um_extracto_sem_linhas_e_recusado(): void
    {
        $banco = $this->conta();

        $this->postJson(self::RAIZ.'/reconciliacao/importar', [
            'account_id' => $banco->id,
            'file_type' => 'csv',
            'file' => UploadedFile::fake()->createWithContent('vazio.csv', "data,ref,desc,valor\n"),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->assertSame(0, BankReconciliation::where('tenant_id', $this->tenant->id)->count(),
            'não fica uma conciliação vazia a dizer-se «reconciliada»');
    }

    /** O extracto vai contra uma conta DESTA empresa. */
    public function test_o_extracto_nao_vai_contra_a_conta_de_outra_empresa(): void
    {
        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'r'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $alheia = Account::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => '97', 'name' => 'Alheia',
            'type' => 'asset', 'nature' => 'debit', 'level' => 1,
        ]);

        $this->postJson(self::RAIZ.'/reconciliacao/importar', [
            'account_id' => $alheia->id,
            'file_type' => 'csv',
            'file' => UploadedFile::fake()->createWithContent('e.csv', "a,b,c,d\n2026-09-01,X,Y,100\n"),
        ])->assertStatus(422)->assertJsonValidationErrors('account_id');
    }

    /**
     * O AUTO-MATCH ENCONTRA O LANÇAMENTO — e é aqui que se vê a janela corrigida.
     *
     * Procurava lançamentos pela data em que a LINHA FOI INSERIDA (`created_at`)
     * e não pela data do lançamento: um lançamento escrito hoje para uma
     * transacção de Setembro nunca aparecia.
     */
    public function test_o_auto_match_encontra_o_lancamento_pela_data_do_lancamento(): void
    {
        $banco = $this->conta(['code' => '1103', 'name' => 'Banco de teste']);

        // O lançamento é de Setembro; a linha é inserida HOJE.
        $this->lancamento($banco, 150000, 0, '2026-09-01');

        $csv = "data,referencia,descricao,valor\n2026-09-01,Transferência do cliente,Entrada,150000\n";

        $r = $this->postJson(self::RAIZ.'/reconciliacao/importar', [
            'account_id' => $banco->id, 'file_type' => 'csv',
            'file' => UploadedFile::fake()->createWithContent('e.csv', $csv),
        ])->assertCreated();

        $item = BankReconciliation::findOrFail($r->json('id'))->items()->firstOrFail();

        $this->assertSame('matched', $item->status, 'o valor e a data batem: casa sozinho');
        $this->assertNotNull($item->move_line_id);
    }

    /**
     * UMA REFERÊNCIA VAZIA NÃO DÁ PONTOS DE DESCRIÇÃO.
     *
     * Em PHP 8, `stripos($x, '')` devolve `0` — que não é `false` — e o `!== false`
     * passava: todas as linhas ganhavam os 20 pontos da descrição. Num extracto
     * sem referência (MT940 e OFX não a trazem) isso empurrava a linha errada
     * acima dos 90 e CONCILIAVA-A SOZINHO com o lançamento errado.
     */
    public function test_uma_referencia_vazia_nao_infla_a_confianca(): void
    {
        $banco = $this->conta();

        // Um lançamento com valor DIFERENTE (a 5 de diferença: 30 pontos) e na
        // mesma data (20 pontos) = 50. Com os 20 pontos falsos da descrição
        // passaria a 70 e entrava nas sugestões.
        $this->lancamento($banco, 1005, 0, '2026-09-01');

        $reconciliacao = BankReconciliation::create([
            'tenant_id' => $this->tenant->id, 'account_id' => $banco->id,
            'statement_date' => '2026-09-01', 'statement_balance' => 1000,
            'book_balance' => 0, 'difference' => 0, 'status' => 'draft',
        ]);

        BankReconciliationItem::create([
            'reconciliation_id' => $reconciliacao->id,
            'transaction_date' => '2026-09-01',
            // SEM REFERÊNCIA e sem descrição: é o caso do MT940.
            'reference' => '', 'description' => '',
            'amount' => 1000, 'type' => 'debit', 'status' => 'unmatched',
        ]);

        $sugestoes = $this->getJson(self::RAIZ.'/reconciliacao/'.$reconciliacao->id)
            ->assertOk()->json('data.linhas_do_extracto.0.sugestoes');

        $this->assertSame([], $sugestoes,
            'sem referência nem descrição, os 50 pontos de valor e data não chegam aos 50 exigidos');
    }

    /**
     * A FICHA ABRE AS LINHAS — o ecrã que não existia.
     *
     * O botão «Ver» da lista era um `<button>` sem clique nenhum.
     */
    public function test_a_ficha_traz_as_linhas_do_extracto_e_as_sugestoes(): void
    {
        $banco = $this->conta();
        $linha = $this->lancamento($banco, 0, 45000, '2026-09-05');

        $reconciliacao = BankReconciliation::create([
            'tenant_id' => $this->tenant->id, 'account_id' => $banco->id,
            'statement_date' => '2026-09-05', 'statement_balance' => -45000,
            'book_balance' => 0, 'difference' => 0, 'status' => 'draft',
        ]);

        // SAÍDA no extracto (tipo `debit`) casa com um CRÉDITO na conta do
        // banco: é o dinheiro a sair do activo.
        BankReconciliationItem::create([
            'reconciliation_id' => $reconciliacao->id,
            'transaction_date' => '2026-09-05', 'reference' => 'Transferência do cliente',
            'description' => 'Pagamento', 'amount' => 45000, 'type' => 'debit', 'status' => 'unmatched',
        ]);

        $ficha = $this->getJson(self::RAIZ.'/reconciliacao/'.$reconciliacao->id)->assertOk();

        $this->assertCount(1, $ficha->json('data.linhas_do_extracto'));

        $sugestoes = $ficha->json('data.linhas_do_extracto.0.sugestoes');

        $this->assertNotEmpty($sugestoes, 'o lançamento do mesmo valor e dia tem de aparecer');
        $this->assertSame($linha->id, $sugestoes[0]['linha_id']);
        $this->assertGreaterThanOrEqual(80, $sugestoes[0]['confianca']);
    }

    /** Casar à mão, e desfazer. */
    public function test_casar_a_mao_e_desfazer(): void
    {
        $banco = $this->conta();
        $linha = $this->lancamento($banco, 0, 45000, '2026-09-05');

        $reconciliacao = BankReconciliation::create([
            'tenant_id' => $this->tenant->id, 'account_id' => $banco->id,
            'statement_date' => '2026-09-05', 'statement_balance' => 0,
            'book_balance' => 0, 'difference' => 0, 'status' => 'draft',
        ]);

        $item = BankReconciliationItem::create([
            'reconciliation_id' => $reconciliacao->id,
            'transaction_date' => '2026-09-05', 'reference' => 'X', 'description' => 'Y',
            'amount' => 45000, 'type' => 'credit', 'status' => 'unmatched',
        ]);

        $this->postJson(self::RAIZ.'/reconciliacao/linhas/'.$item->id.'/casar', ['linha_id' => $linha->id])->assertOk();

        $item->refresh();

        $this->assertSame('matched', $item->status);
        $this->assertSame(100, (int) $item->match_confidence);
        // Com todas as linhas casadas, a conciliação fecha.
        $this->assertSame('reconciled', $reconciliacao->fresh()->status);

        $this->postJson(self::RAIZ.'/reconciliacao/linhas/'.$item->id.'/desfazer')->assertOk();

        $this->assertSame('unmatched', $item->fresh()->status);
        $this->assertSame('draft', $reconciliacao->fresh()->status);
    }

    /** A linha de lançamento tem de ser da MESMA conta do extracto. */
    public function test_nao_se_casa_com_um_lancamento_de_outra_conta(): void
    {
        $banco = $this->conta();
        $caixa = $this->conta();

        $daCaixa = $this->lancamento($caixa, 0, 500, '2026-09-05');

        $reconciliacao = BankReconciliation::create([
            'tenant_id' => $this->tenant->id, 'account_id' => $banco->id,
            'statement_date' => '2026-09-05', 'statement_balance' => 0,
            'book_balance' => 0, 'difference' => 0, 'status' => 'draft',
        ]);

        $item = BankReconciliationItem::create([
            'reconciliation_id' => $reconciliacao->id,
            'transaction_date' => '2026-09-05', 'reference' => 'X', 'description' => 'Y',
            'amount' => 500, 'type' => 'credit', 'status' => 'unmatched',
        ]);

        $this->postJson(self::RAIZ.'/reconciliacao/linhas/'.$item->id.'/casar', ['linha_id' => $daCaixa->id])
            ->assertNotFound();

        $this->assertSame('unmatched', $item->fresh()->status);
    }

    /** Aprovar só o que está conciliado por inteiro. */
    public function test_nao_se_aprova_uma_conciliacao_com_linhas_por_casar(): void
    {
        $banco = $this->conta();

        $reconciliacao = BankReconciliation::create([
            'tenant_id' => $this->tenant->id, 'account_id' => $banco->id,
            'statement_date' => '2026-09-05', 'statement_balance' => 0,
            'book_balance' => 0, 'difference' => 0, 'status' => 'draft',
        ]);

        BankReconciliationItem::create([
            'reconciliation_id' => $reconciliacao->id,
            'transaction_date' => '2026-09-05', 'reference' => 'X', 'description' => 'Y',
            'amount' => 100, 'type' => 'credit', 'status' => 'unmatched',
        ]);

        $this->postJson(self::RAIZ.'/reconciliacao/'.$reconciliacao->id.'/aprovar')
            ->assertStatus(422)->assertJsonValidationErrors('geral');
    }

    /** E uma aprovada não se apaga: é o registo de que as contas foram conferidas. */
    public function test_nao_se_apaga_uma_conciliacao_aprovada(): void
    {
        $reconciliacao = BankReconciliation::create([
            'tenant_id' => $this->tenant->id, 'account_id' => $this->conta()->id,
            'statement_date' => '2026-09-05', 'statement_balance' => 0,
            'book_balance' => 0, 'difference' => 0, 'status' => 'approved',
        ]);

        $this->deleteJson(self::RAIZ.'/reconciliacao/'.$reconciliacao->id)
            ->assertStatus(422)->assertJsonValidationErrors('geral');
    }

    /* ─── Os relatórios ───────────────────────────────────────────────── */

    public function test_os_relatorios_dizem_que_mapas_descarregam(): void
    {
        $o = $this->getJson(self::RAIZ.'/relatorios/opcoes')->assertOk();

        $this->assertCount(10, $o->json('mapas'));

        // O balancete e o mapa de IVA só têm folha de cálculo — e é lá que os
        // dois métodos inexistentes davam «Call to undefined method».
        $this->assertContains('trial_balance', $o->json('exportacoes.excel'));
        $this->assertNotContains('trial_balance', $o->json('exportacoes.pdf'));
        $this->assertContains('vat', $o->json('exportacoes.excel'));
    }

    /** O balancete sai de um `group by` e fecha. */
    public function test_o_balancete_soma_e_fecha(): void
    {
        $caixa = $this->conta(['name' => 'Caixa']);

        $this->lancamento($caixa, 3000, 0, now()->format('Y-m-d'));

        $d = $this->getJson(self::RAIZ.'/relatorios?'.http_build_query([
            'mapa' => 'trial_balance',
            'de' => now()->startOfMonth()->format('Y-m-d'),
            'ate' => now()->endOfMonth()->format('Y-m-d'),
        ]))->assertOk()->json('data');

        $this->assertSame(3000.0, (float) $d['totais']['debito']);
        $this->assertSame(3000.0, (float) $d['totais']['credito']);
        $this->assertTrue($d['totais']['fecha']);

        $linha = collect($d['contas'])->firstWhere('nome', 'Caixa');

        $this->assertSame(3000.0, (float) $linha['debito']);
    }

    /** As contas de agregação não entram no balancete: contariam a dobrar. */
    public function test_o_balancete_nao_traz_contas_de_agregacao(): void
    {
        $agregacao = $this->conta(['is_view' => true, 'name' => 'Agregação']);

        // Uma linha directa numa conta de agregação só se consegue à mão — e é
        // exactamente isso que o balancete não pode contar.
        $this->lancamento($agregacao, 500, 0, now()->format('Y-m-d'));

        $nomes = collect($this->getJson(self::RAIZ.'/relatorios?'.http_build_query([
            'mapa' => 'trial_balance',
            'de' => now()->startOfMonth()->format('Y-m-d'),
            'ate' => now()->endOfMonth()->format('Y-m-d'),
        ]))->assertOk()->json('data.contas'))->pluck('nome');

        $this->assertFalse($nomes->contains('Agregação'));
    }

    /** A razão sem conta escolhida não devolve lixo: devolve nada, e diz-se. */
    public function test_a_razao_sem_conta_escolhida_vem_vazia(): void
    {
        $d = $this->getJson(self::RAIZ.'/relatorios?mapa=ledger')->assertOk()->json('data');

        $this->assertNull($d['conta']);
        $this->assertSame([], $d['linhas']);
    }

    /** E com conta escolhida traz o extracto, com abertura. */
    public function test_a_razao_traz_o_extracto_da_conta(): void
    {
        $caixa = $this->conta();

        $this->lancamento($caixa, 1000, 0, now()->subMonths(2)->format('Y-m-d'));
        $this->lancamento($caixa, 250, 0, now()->format('Y-m-d'));

        $d = $this->getJson(self::RAIZ.'/relatorios?'.http_build_query([
            'mapa' => 'ledger', 'conta' => $caixa->id,
            'de' => now()->startOfMonth()->format('Y-m-d'),
            'ate' => now()->endOfMonth()->format('Y-m-d'),
        ]))->assertOk()->json('data');

        $this->assertSame(1000.0, (float) $d['abertura'], 'o mês anterior é a abertura');
        $this->assertCount(1, $d['linhas']);
        $this->assertSame(1250.0, (float) $d['totais']['saldo']);
    }

    /** Sem contas de IVA marcadas o mapa di-lo em vez de mostrar três zeros. */
    public function test_o_mapa_de_iva_diz_quando_nao_ha_contas_marcadas(): void
    {
        $d = $this->getJson(self::RAIZ.'/relatorios?mapa=vat')->assertOk()->json('data');

        $this->assertTrue($d['sem_contas']);
    }

    /** Um mapa desconhecido é recusado, não calculado. */
    public function test_um_mapa_desconhecido_e_recusado(): void
    {
        $this->getJson(self::RAIZ.'/relatorios?mapa=xpto')->assertStatus(422);
    }

    /** O balancete descarrega em folha de cálculo — o que dava «undefined method». */
    public function test_o_balancete_descarrega_em_folha_de_calculo(): void
    {
        $this->lancamento($this->conta(), 1000, 0, now()->format('Y-m-d'));

        $r = $this->get(route('accounting.reports.descarregar', [
            'mapa' => 'trial_balance', 'formato' => 'excel',
            'de' => now()->startOfMonth()->format('Y-m-d'),
            'ate' => now()->endOfMonth()->format('Y-m-d'),
        ]))->assertOk();

        $this->assertStringContainsString('spreadsheetml', $r->headers->get('content-type'));
    }

    /** E um formato que aquele mapa não tem é 404, não uma folha vazia. */
    public function test_o_balancete_nao_tem_pdf(): void
    {
        $this->get(route('accounting.reports.descarregar', [
            'mapa' => 'trial_balance', 'formato' => 'pdf',
        ]))->assertNotFound();
    }

    /* ─── As definições ──────────────────────────────────────────────── */

    /**
     * VER E MEXER SÃO DIREITOS DIFERENTES.
     *
     * A página abria com `settings.view` e todas as escritas eram livres: correr
     * os seeders, ligar a integração automática — que decide se cada factura gera
     * lançamentos — e reescrever os mapeamentos das contas.
     */
    public function test_quem_so_ve_definicoes_nao_mexe_em_nada(): void
    {
        $so = User::create([
            'name' => 'Só vê', 'email' => 'd'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);
        $so->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_active' => true]]);

        setPermissionsTeamId($this->tenant->id);
        $so->givePermissionTo('accounting.settings.view');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($so);
        session(['active_tenant_id' => $this->tenant->id]);

        $this->assertFalse($this->getJson(self::RAIZ.'/definicoes')->assertOk()->json('permissoes.editar'));

        $this->postJson(self::RAIZ.'/definicoes/sincronizar', ['peca' => 'diarios'])->assertForbidden();
        $this->postJson(self::RAIZ.'/definicoes/integracao', ['ligada' => true])->assertForbidden();
        $this->postJson(self::RAIZ.'/definicoes/mapeamento', ['event' => 'invoice'])->assertForbidden();
        $this->deleteJson(self::RAIZ.'/definicoes/tudo')->assertForbidden();
    }

    /** Sincronizar é incremental: correr duas vezes não cria nada de novo. */
    public function test_sincronizar_e_incremental(): void
    {
        $this->postJson(self::RAIZ.'/definicoes/sincronizar', ['peca' => 'periodos', 'ano' => 2026])->assertOk();

        $depois = Period::where('tenant_id', $this->tenant->id)->whereYear('date_start', 2026)->count();

        $this->postJson(self::RAIZ.'/definicoes/sincronizar', ['peca' => 'periodos', 'ano' => 2026])->assertOk();

        $this->assertSame($depois, Period::where('tenant_id', $this->tenant->id)
            ->whereYear('date_start', 2026)->count());
    }

    /** Ligar sem mapeamentos não produz lançamentos — e diz-se em vez de mentir. */
    public function test_ligar_a_integracao_sem_mapeamentos_avisa(): void
    {
        $r = $this->postJson(self::RAIZ.'/definicoes/integracao', ['ligada' => true])->assertOk();

        $this->assertTrue($r->json('aviso'));
        $this->assertTrue((bool) Tenant::find($this->tenant->id)->accounting_integration_enabled);

        $this->postJson(self::RAIZ.'/definicoes/integracao', ['ligada' => false])->assertOk();

        $this->assertFalse((bool) Tenant::find($this->tenant->id)->accounting_integration_enabled);
    }

    /** O mapeamento grava, e tudo o que aponta é desta empresa. */
    public function test_o_mapeamento_grava_e_esta_preso_a_empresa(): void
    {
        $diario = $this->diario();
        $debito = $this->conta();
        $credito = $this->conta();

        $this->postJson(self::RAIZ.'/definicoes/mapeamento', [
            'event' => 'invoice', 'journal_id' => $diario->id,
            'debit_account_id' => $debito->id, 'credit_account_id' => $credito->id,
            'auto_post' => true, 'active' => true,
        ])->assertOk();

        $m = IntegrationMapping::where('tenant_id', $this->tenant->id)->where('event', 'invoice')->firstOrFail();

        $this->assertSame($diario->id, $m->journal_id);
        $this->assertTrue((bool) $m->auto_post);

        // O diário de outra empresa é recusado.
        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'm'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $alheio = Journal::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => 'AL', 'name' => 'Alheio',
            'type' => 'general', 'sequence_prefix' => 'AL-', 'active' => true,
        ]);

        $this->postJson(self::RAIZ.'/definicoes/mapeamento', [
            'event' => 'invoice', 'journal_id' => $alheio->id,
            'debit_account_id' => $debito->id, 'credit_account_id' => $credito->id,
        ])->assertStatus(422)->assertJsonValidationErrors('journal_id');
    }

    /**
     * UMA CONTA DE AGREGAÇÃO NÃO ENTRA NO MAPEAMENTO.
     *
     * É o caso concreto que este ecrã veio resolver: a resolução automática
     * aterra no cabeçalho de classe («31 CLIENTES» em vez de «311 Clientes
     * correntes»), e lançar contra ele conta o valor duas vezes no balanço.
     */
    public function test_o_mapeamento_recusa_uma_conta_de_agregacao(): void
    {
        $this->postJson(self::RAIZ.'/definicoes/mapeamento', [
            'event' => 'invoice', 'journal_id' => $this->diario()->id,
            'debit_account_id' => $this->conta(['is_view' => true])->id,
            'credit_account_id' => $this->conta()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('debit_account_id');
    }

    /** Apagar os dados é recusado com lançamentos: deixaria contas órfãs. */
    public function test_nao_se_apagam_os_dados_com_lancamentos(): void
    {
        $this->lancamento($this->conta(), 100, 0, now()->format('Y-m-d'));

        $this->deleteJson(self::RAIZ.'/definicoes/tudo')
            ->assertStatus(422)->assertJsonValidationErrors('geral');

        $this->assertGreaterThan(0, Account::where('tenant_id', $this->tenant->id)->count());
    }
}
