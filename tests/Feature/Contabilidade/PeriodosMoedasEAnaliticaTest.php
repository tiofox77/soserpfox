<?php

namespace Tests\Feature\Contabilidade;

use App\Models\Accounting\AnalyticDimension;
use App\Models\Accounting\AnalyticTag;
use App\Models\Accounting\Currency;
use App\Models\Accounting\ExchangeRate;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\Period;
use App\Models\Accounting\Account;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * OS PERÍODOS, AS MOEDAS E A ANALÍTICA em React.
 *
 * O que aqui se prova é o que os três ecrãs em Livewire não faziam:
 *
 *  · CRIAR UM PERÍODO. Não havia como: nasciam de um seeder corrido à mão, e
 *    uma empresa nova lia «não há períodos abertos» nos lançamentos sem ter por
 *    onde resolver. E dois períodos podiam cobrir o mesmo dia.
 *  · O QUE SEGURA O FECHO, dito ANTES do clique — rascunhos e desequilíbrio.
 *  · DESACTIVAR UMA MOEDA e corrigir-lhe as casas decimais: só se escreviam ao
 *    criar. E apagar, que não existia.
 *  · EDITAR UMA ETIQUETA. O ecrã carregava-a e gravava sempre uma NOVA: corrigir
 *    um nome deixava duas etiquetas iguais.
 *  · E O ESCOPO DE EMPRESA da analítica, que não existia em sítio nenhum.
 */
class PeriodosMoedasEAnaliticaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/contabilidade';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('contabilidade')->comPermissoes(
            'accounting.periods.view', 'accounting.periods.manage',
            'accounting.currencies.view', 'accounting.currencies.manage',
            'accounting.analytics.view', 'accounting.cost-centers.manage',
        );

        // As moedas e os câmbios são da plataforma: quem os escreve é o dono dela.
        $this->user->forceFill(['is_super_admin' => true])->save();
    }

    private function periodo(array $extra = []): Period
    {
        return Period::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'code' => 'P'.random_int(10000, 99999),
            'name' => 'Período de teste',
            'date_start' => now()->startOfMonth()->format('Y-m-d'),
            'date_end' => now()->endOfMonth()->format('Y-m-d'),
            'state' => 'open',
        ], $extra));
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

    /** Um lançamento num período, com as linhas que se pedirem. */
    private function lancamento(Period $periodo, string $estado, float $debito, float $credito): Move
    {
        $diario = Journal::create([
            'tenant_id' => $this->tenant->id, 'code' => 'D'.random_int(100, 999),
            'name' => 'Diário', 'type' => 'general', 'sequence_prefix' => 'D-',
            'last_number' => 0, 'active' => true,
        ]);

        $move = Move::create([
            'tenant_id' => $this->tenant->id, 'journal_id' => $diario->id, 'period_id' => $periodo->id,
            'date' => $periodo->date_start->format('Y-m-d'), 'ref' => 'M-'.uniqid(),
            'state' => $estado, 'total_debit' => $debito, 'total_credit' => $credito,
            'created_by' => $this->user->id,
        ]);

        MoveLine::create([
            'tenant_id' => $this->tenant->id, 'move_id' => $move->id,
            'account_id' => $this->conta()->id, 'debit' => $debito, 'credit' => 0, 'balance' => $debito,
        ]);

        MoveLine::create([
            'tenant_id' => $this->tenant->id, 'move_id' => $move->id,
            'account_id' => $this->conta()->id, 'debit' => 0, 'credit' => $credito, 'balance' => -$credito,
        ]);

        return $move;
    }

    /* ─── Os períodos ─────────────────────────────────────────────────── */

    public function test_o_ecra_dos_periodos_monta_a_ilha_de_react(): void
    {
        $this->get(route('accounting.periods'))->assertOk()->assertSee('contabilidade/periodos', false);
    }

    /**
     * GERAR O EXERCÍCIO — o que faltava por completo.
     *
     * É INCREMENTAL: correr outra vez não cria nada nem toca num período
     * existente, que pode estar fechado.
     */
    public function test_gerar_o_exercicio_cria_os_doze_meses_e_repete_se_sem_estragar(): void
    {
        $ano = (int) now()->year;

        $this->postJson(self::RAIZ.'/periodos/gerar', ['ano' => $ano])
            ->assertCreated()->assertJson(['criados' => 12]);

        $this->assertSame(12, Period::where('tenant_id', $this->tenant->id)
            ->whereYear('date_start', $ano)->count());

        // Um deles fecha-se, e gerar outra vez não o reabre.
        $janeiro = Period::where('tenant_id', $this->tenant->id)
            ->whereYear('date_start', $ano)->orderBy('date_start')->first();

        $janeiro->update(['state' => 'closed']);

        $this->postJson(self::RAIZ.'/periodos/gerar', ['ano' => $ano])
            ->assertOk()->assertJson(['criados' => 0]);

        $this->assertSame('closed', $janeiro->fresh()->state,
            'um período fechado nunca é reaberto por aqui');
    }

    /** Dois períodos a cobrir o mesmo dia fazem um lançamento cair num por sorte. */
    public function test_um_periodo_novo_nao_se_sobrepoe_a_outro(): void
    {
        $this->periodo([
            'code' => 'JAN', 'name' => 'Janeiro',
            'date_start' => '2026-01-01', 'date_end' => '2026-01-31',
        ]);

        $this->postJson(self::RAIZ.'/periodos', [
            'code' => 'MEIO', 'name' => 'A meio de Janeiro',
            'date_start' => '2026-01-15', 'date_end' => '2026-02-15',
        ])->assertStatus(422)->assertJsonValidationErrors('date_start');

        // Um intervalo que não toca em nada passa.
        $this->postJson(self::RAIZ.'/periodos', [
            'code' => 'FEV', 'name' => 'Fevereiro',
            'date_start' => '2026-02-01', 'date_end' => '2026-02-28',
        ])->assertCreated();
    }

    public function test_o_codigo_do_periodo_e_unico_e_o_fim_nao_vem_antes_do_inicio(): void
    {
        $this->periodo(['code' => 'REP', 'date_start' => '2026-03-01', 'date_end' => '2026-03-31']);

        $this->postJson(self::RAIZ.'/periodos', [
            'code' => 'REP', 'name' => 'Repetido',
            'date_start' => '2026-04-01', 'date_end' => '2026-04-30',
        ])->assertStatus(422)->assertJsonValidationErrors('code');

        $this->postJson(self::RAIZ.'/periodos', [
            'code' => 'AVESSO', 'name' => 'Ao avesso',
            'date_start' => '2026-05-30', 'date_end' => '2026-05-01',
        ])->assertStatus(422)->assertJsonValidationErrors('date_end');
    }

    /**
     * O QUE SEGURA O FECHO, DITO ANTES DO CLIQUE.
     *
     * O ecrã antigo não dizia nem quantos rascunhos havia nem de quanto era a
     * diferença: carregava-se em Fechar para descobrir.
     */
    public function test_a_lista_diz_porque_e_que_um_periodo_ainda_nao_fecha(): void
    {
        $comRascunho = $this->periodo([
            'code' => 'RAS', 'name' => 'Com rascunho',
            'date_start' => now()->startOfMonth()->format('Y-m-d'),
            'date_end' => now()->endOfMonth()->format('Y-m-d'),
        ]);

        $this->lancamento($comRascunho, 'draft', 1000, 1000);

        $linha = collect($this->getJson(self::RAIZ.'/periodos?ano='.now()->year)->assertOk()->json('data'))
            ->firstWhere('id', $comRascunho->id);

        $this->assertFalse($linha['pode_fechar']);
        $this->assertStringContainsString('rascunho', $linha['porque_nao_fecha']);
        $this->assertSame(1, $linha['rascunhos']);
        $this->assertTrue($linha['e_o_de_hoje'], 'hoje cai neste período');
    }

    /** E o balancete de cada período vem na linha, sem N+1. */
    public function test_a_linha_traz_o_balancete_do_periodo(): void
    {
        $periodo = $this->periodo([
            'date_start' => now()->startOfMonth()->format('Y-m-d'),
            'date_end' => now()->endOfMonth()->format('Y-m-d'),
        ]);

        $this->lancamento($periodo, 'posted', 7500, 7500);

        $linha = collect($this->getJson(self::RAIZ.'/periodos?ano='.now()->year)->assertOk()->json('data'))
            ->firstWhere('id', $periodo->id);

        $this->assertSame(7500.0, (float) $linha['debito']);
        $this->assertSame(7500.0, (float) $linha['credito']);
        $this->assertTrue($linha['equilibrado']);
        $this->assertTrue($linha['pode_fechar']);
    }

    /** O serviço recusa por excepção; o ecrã precisa de um 422, não de um 500. */
    public function test_fechar_um_periodo_com_rascunhos_devolve_422_com_a_razao(): void
    {
        $periodo = $this->periodo();
        $this->lancamento($periodo, 'draft', 500, 500);

        $this->postJson(self::RAIZ.'/periodos/'.$periodo->id.'/fechar')
            ->assertStatus(422)->assertJsonValidationErrors('geral');

        $this->assertSame('open', $periodo->fresh()->state);
    }

    public function test_um_periodo_limpo_fecha_e_reabre(): void
    {
        $periodo = $this->periodo();
        $this->lancamento($periodo, 'posted', 200, 200);

        $this->postJson(self::RAIZ.'/periodos/'.$periodo->id.'/fechar')->assertOk();
        $this->assertSame('closed', $periodo->fresh()->state);

        $this->postJson(self::RAIZ.'/periodos/'.$periodo->id.'/reabrir')->assertOk();
        $this->assertSame('open', $periodo->fresh()->state);
    }

    /** Reabre-se de trás para a frente: um posterior fechado prende o anterior. */
    public function test_nao_se_reabre_um_periodo_com_posteriores_fechados(): void
    {
        $antigo = $this->periodo([
            'code' => 'A1', 'date_start' => '2026-01-01', 'date_end' => '2026-01-31', 'state' => 'closed',
        ]);

        $this->periodo([
            'code' => 'A2', 'date_start' => '2026-02-01', 'date_end' => '2026-02-28', 'state' => 'closed',
        ]);

        $this->postJson(self::RAIZ.'/periodos/'.$antigo->id.'/reabrir')
            ->assertStatus(422)->assertJsonValidationErrors('geral');

        $this->assertSame('closed', $antigo->fresh()->state);
    }

    /** O período de outra empresa não se fecha pelo id. */
    public function test_o_periodo_de_outra_empresa_da_404(): void
    {
        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $alheio = Period::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => 'AL', 'name' => 'Alheio',
            'date_start' => '2026-06-01', 'date_end' => '2026-06-30', 'state' => 'open',
        ]);

        $this->postJson(self::RAIZ.'/periodos/'.$alheio->id.'/fechar')->assertNotFound();
    }

    /* ─── As moedas ───────────────────────────────────────────────────── */

    public function test_o_ecra_das_moedas_monta_a_ilha_de_react(): void
    {
        $this->get(route('accounting.currencies'))->assertOk()->assertSee('contabilidade/moedas', false);
    }

    /**
     * DESACTIVAR E CORRIGIR AS CASAS DECIMAIS.
     *
     * `is_active` e `decimal_places` só se escreviam ao CRIAR: uma moeda não se
     * podia desactivar nem corrigir.
     */
    public function test_a_moeda_desactiva_se_e_corrige_as_casas_decimais(): void
    {
        $r = $this->postJson(self::RAIZ.'/moedas', [
            'code' => 'xaf', 'name' => 'Franco CFA', 'symbol' => 'FCFA',
        ])->assertCreated();

        $moeda = Currency::where('code', 'XAF')->firstOrFail();

        $this->assertSame('XAF', $moeda->code, 'o código é sempre em maiúsculas');
        $this->assertSame(2, (int) $moeda->decimal_places);
        $this->assertTrue((bool) $moeda->is_active);
        $this->assertNotEmpty($r->json('message'));

        $this->putJson(self::RAIZ.'/moedas/'.$moeda->id, [
            'code' => 'XAF', 'name' => 'Franco CFA', 'symbol' => 'FCFA',
            'decimal_places' => 0, 'is_active' => false,
        ])->assertOk();

        $moeda->refresh();

        $this->assertSame(0, (int) $moeda->decimal_places);
        $this->assertFalse((bool) $moeda->is_active);
    }

    public function test_o_codigo_da_moeda_tem_tres_letras_e_e_unico(): void
    {
        $this->postJson(self::RAIZ.'/moedas', ['code' => 'ZZZ', 'name' => 'Teste', 'symbol' => 'Z'])->assertCreated();

        $this->postJson(self::RAIZ.'/moedas', ['code' => 'ZZZ', 'name' => 'Outra', 'symbol' => 'Y'])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $this->postJson(self::RAIZ.'/moedas', ['code' => 'ZZ', 'name' => 'Curta', 'symbol' => 'Y'])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $this->postJson(self::RAIZ.'/moedas', ['code' => 'Z1Z', 'name' => 'Com número', 'symbol' => 'Y'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /** Uma moeda com câmbios não desaparece: as taxas ficariam órfãs. */
    public function test_nao_se_apaga_uma_moeda_com_cambios(): void
    {
        $de = Currency::create(['code' => 'AA'.chr(random_int(65, 90)), 'name' => 'De', 'symbol' => 'a', 'decimal_places' => 2, 'is_active' => true]);
        $para = Currency::create(['code' => 'BB'.chr(random_int(65, 90)), 'name' => 'Para', 'symbol' => 'b', 'decimal_places' => 2, 'is_active' => true]);

        ExchangeRate::create([
            'currency_from_id' => $de->id, 'currency_to_id' => $para->id,
            'date' => now()->format('Y-m-d'), 'rate' => 2, 'source' => 'manual',
        ]);

        $this->deleteJson(self::RAIZ.'/moedas/'.$de->id)
            ->assertStatus(422)->assertJsonValidationErrors('geral');

        $this->assertDatabaseHas('currencies', ['id' => $de->id]);

        // Uma sem câmbios apaga-se.
        $livre = Currency::create(['code' => 'CC'.chr(random_int(65, 90)), 'name' => 'Livre', 'symbol' => 'c', 'decimal_places' => 2, 'is_active' => true]);

        $this->deleteJson(self::RAIZ.'/moedas/'.$livre->id)->assertOk();
        $this->assertDatabaseMissing('currencies', ['id' => $livre->id]);
    }

    /** UMA TAXA ZERO faz toda a conversão dar zero: não é um câmbio. */
    public function test_o_cambio_recusa_taxa_zero_e_a_mesma_moeda_dos_dois_lados(): void
    {
        $de = Currency::create(['code' => 'DD'.chr(random_int(65, 90)), 'name' => 'De', 'symbol' => 'd', 'decimal_places' => 2, 'is_active' => true]);
        $para = Currency::create(['code' => 'EE'.chr(random_int(65, 90)), 'name' => 'Para', 'symbol' => 'e', 'decimal_places' => 2, 'is_active' => true]);

        $this->postJson(self::RAIZ.'/moedas/cambios', [
            'currency_from_id' => $de->id, 'currency_to_id' => $para->id,
            'date' => now()->format('Y-m-d'), 'rate' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('rate');

        $this->postJson(self::RAIZ.'/moedas/cambios', [
            'currency_from_id' => $de->id, 'currency_to_id' => $de->id,
            'date' => now()->format('Y-m-d'), 'rate' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('currency_to_id');
    }

    /**
     * O PAR MAIS A DATA SÃO A CHAVE: gravar outra vez CORRIGE.
     *
     * Duas taxas do mesmo dia fariam a conversão depender da ordem da consulta.
     */
    public function test_gravar_o_mesmo_par_no_mesmo_dia_corrige_em_vez_de_duplicar(): void
    {
        $de = Currency::create(['code' => 'FF'.chr(random_int(65, 90)), 'name' => 'De', 'symbol' => 'f', 'decimal_places' => 2, 'is_active' => true]);
        $para = Currency::create(['code' => 'GG'.chr(random_int(65, 90)), 'name' => 'Para', 'symbol' => 'g', 'decimal_places' => 2, 'is_active' => true]);

        $dia = now()->format('Y-m-d');

        foreach ([900, 925.5] as $taxa) {
            $this->postJson(self::RAIZ.'/moedas/cambios', [
                'currency_from_id' => $de->id, 'currency_to_id' => $para->id,
                'date' => $dia, 'rate' => $taxa,
            ])->assertCreated();
        }

        $taxas = ExchangeRate::where('currency_from_id', $de->id)->where('currency_to_id', $para->id)->get();

        $this->assertCount(1, $taxas, 'uma taxa por par e por dia');
        $this->assertSame(925.5, (float) $taxas->first()->rate);
    }

    /** Escrever nas moedas é da plataforma: pede a permissão de gerir. */
    public function test_quem_so_ve_moedas_nao_escreve(): void
    {
        $so = \App\Models\User::create([
            'name' => 'Só vê', 'email' => 'm'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);
        $so->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_active' => true]]);

        setPermissionsTeamId($this->tenant->id);
        $so->givePermissionTo('accounting.currencies.view');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($so);
        session(['active_tenant_id' => $this->tenant->id]);

        $this->assertFalse($this->getJson(self::RAIZ.'/moedas')->assertOk()->json('permissoes.gerir'));

        $this->postJson(self::RAIZ.'/moedas', ['code' => 'QQQ', 'name' => 'Sem direito', 'symbol' => 'q'])
            ->assertForbidden();
    }

    /* ─── A analítica ─────────────────────────────────────────────────── */

    public function test_o_ecra_da_analitica_monta_a_ilha_de_react(): void
    {
        $this->get(route('accounting.analytics'))->assertOk()->assertSee('contabilidade/analitica', false);
    }

    /**
     * EDITAR UMA ETIQUETA EDITA — não cria outra.
     *
     * Era o defeito mais caro: o ecrã carregava a etiqueta no formulário e
     * gravava sempre uma nova, pelo que corrigir um nome deixava duas etiquetas
     * iguais e os lançamentos repartidos entre elas.
     */
    public function test_editar_uma_etiqueta_nao_cria_uma_segunda(): void
    {
        $dimensao = AnalyticDimension::create([
            'tenant_id' => $this->tenant->id, 'code' => 'PROJ', 'name' => 'Projecto', 'is_mandatory' => false,
        ]);

        $this->postJson(self::RAIZ.'/analitica/etiquetas', [
            'dimension_id' => $dimensao->id, 'code' => 'OBRA1', 'name' => 'Obra do Kilamba',
        ])->assertCreated();

        $etiqueta = AnalyticTag::where('dimension_id', $dimensao->id)->firstOrFail();

        $this->putJson(self::RAIZ.'/analitica/etiquetas/'.$etiqueta->id, [
            'dimension_id' => $dimensao->id, 'code' => 'OBRA1', 'name' => 'Obra do Kilamba (fase 2)',
        ])->assertOk();

        $this->assertSame(1, AnalyticTag::where('dimension_id', $dimensao->id)->count(),
            'corrigir um nome não pode deixar duas etiquetas iguais');
        $this->assertSame('Obra do Kilamba (fase 2)', $etiqueta->fresh()->name);
    }

    /** O código da etiqueta é único DENTRO da dimensão. */
    public function test_o_codigo_da_etiqueta_e_unico_na_dimensao(): void
    {
        $a = AnalyticDimension::create([
            'tenant_id' => $this->tenant->id, 'code' => 'D1', 'name' => 'Uma', 'is_mandatory' => false,
        ]);
        $b = AnalyticDimension::create([
            'tenant_id' => $this->tenant->id, 'code' => 'D2', 'name' => 'Outra', 'is_mandatory' => false,
        ]);

        $this->postJson(self::RAIZ.'/analitica/etiquetas', [
            'dimension_id' => $a->id, 'code' => 'X', 'name' => 'Xis',
        ])->assertCreated();

        $this->postJson(self::RAIZ.'/analitica/etiquetas', [
            'dimension_id' => $a->id, 'code' => 'X', 'name' => 'Outro xis',
        ])->assertStatus(422)->assertJsonValidationErrors('code');

        // O mesmo código noutra dimensão é outra pergunta, e passa.
        $this->postJson(self::RAIZ.'/analitica/etiquetas', [
            'dimension_id' => $b->id, 'code' => 'X', 'name' => 'Xis da outra',
        ])->assertCreated();
    }

    /**
     * O ESCOPO DE EMPRESA, que não existia em sítio nenhum: a tabela das
     * etiquetas não tem `tenant_id` e o `findOrFail` bastava para lá chegar.
     */
    public function test_a_etiqueta_e_a_dimensao_de_outra_empresa_dao_404(): void
    {
        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'a'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);

        $dimensaoAlheia = AnalyticDimension::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => 'AL', 'name' => 'Alheia', 'is_mandatory' => false,
        ]);

        $etiquetaAlheia = AnalyticTag::create([
            'dimension_id' => $dimensaoAlheia->id, 'code' => 'AL1', 'name' => 'Alheia', 'is_active' => true,
        ]);

        $this->putJson(self::RAIZ.'/analitica/dimensoes/'.$dimensaoAlheia->id, [
            'code' => 'AL', 'name' => 'Mexida',
        ])->assertNotFound();

        $this->deleteJson(self::RAIZ.'/analitica/etiquetas/'.$etiquetaAlheia->id)->assertNotFound();

        // E pendurar uma etiqueta na dimensão alheia também não.
        $this->postJson(self::RAIZ.'/analitica/etiquetas', [
            'dimension_id' => $dimensaoAlheia->id, 'code' => 'INTRUSA', 'name' => 'Intrusa',
        ])->assertNotFound();
    }

    /** A dimensão passou a editar-se e a apagar-se — não havia nem uma coisa nem outra. */
    public function test_a_dimensao_edita_se_e_so_se_apaga_sem_etiquetas(): void
    {
        $r = $this->postJson(self::RAIZ.'/analitica/dimensoes', [
            'code' => 'LOJA', 'name' => 'Loja', 'is_mandatory' => true,
        ])->assertCreated();

        $id = $r->json('id');

        $this->putJson(self::RAIZ.'/analitica/dimensoes/'.$id, [
            'code' => 'LOJA', 'name' => 'Estabelecimento', 'is_mandatory' => false,
        ])->assertOk();

        $dimensao = AnalyticDimension::find($id);

        $this->assertSame('Estabelecimento', $dimensao->name);
        $this->assertFalse((bool) $dimensao->is_mandatory);

        AnalyticTag::create(['dimension_id' => $id, 'code' => 'L1', 'name' => 'Loja 1', 'is_active' => true]);

        $this->deleteJson(self::RAIZ.'/analitica/dimensoes/'.$id)
            ->assertStatus(422)->assertJsonValidationErrors('geral');

        AnalyticTag::where('dimension_id', $id)->delete();

        $this->deleteJson(self::RAIZ.'/analitica/dimensoes/'.$id)->assertOk();
    }

    public function test_o_codigo_da_dimensao_e_unico_por_empresa(): void
    {
        $this->postJson(self::RAIZ.'/analitica/dimensoes', ['code' => 'UNI', 'name' => 'Uma'])->assertCreated();

        $this->postJson(self::RAIZ.'/analitica/dimensoes', ['code' => 'UNI', 'name' => 'Outra'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /**
     * A LISTA ABRE NA PRIMEIRA DIMENSÃO.
     *
     * A do Livewire abria com as etiquetas vazias até se carregar numa
     * dimensão, e nada dizia que era preciso — parecia não haver etiquetas.
     */
    public function test_a_lista_abre_na_primeira_dimensao_com_as_etiquetas_dela(): void
    {
        $dimensao = AnalyticDimension::create([
            'tenant_id' => $this->tenant->id, 'code' => 'AAA', 'name' => 'A primeira', 'is_mandatory' => false,
        ]);

        AnalyticTag::create(['dimension_id' => $dimensao->id, 'code' => 'T1', 'name' => 'Uma', 'is_active' => true]);
        AnalyticTag::create(['dimension_id' => $dimensao->id, 'code' => 'T2', 'name' => 'Outra', 'is_active' => false]);

        $r = $this->getJson(self::RAIZ.'/analitica')->assertOk();

        $this->assertSame($dimensao->id, $r->json('escolhida'));
        $this->assertCount(2, $r->json('etiquetas'));
        $this->assertSame(2, $r->json('dimensoes.0.etiquetas'));
        $this->assertSame(1, $r->json('dimensoes.0.etiquetas_activas'));

        // E o filtro de estado funciona.
        $this->assertCount(1, $this->getJson(self::RAIZ.'/analitica?estado=activas')->assertOk()->json('etiquetas'));
    }
}
