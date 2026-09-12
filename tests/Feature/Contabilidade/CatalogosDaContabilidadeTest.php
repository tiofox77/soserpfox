<?php

namespace Tests\Feature\Contabilidade;

use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\DocumentType;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Move;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * OS TRÊS CATÁLOGOS DA CONTABILIDADE no ecrã genérico.
 *
 * Passar um Livewire para os catálogos perde guardas em silêncio, e por isso as
 * do esquema verificam-se uma a uma. As que faltavam ao Livewire e aqui existem:
 *
 *  · o escopo de empresa: `Journal::find($id)` no editar e no apagar não olhava
 *    à companhia, e o diário alheio editava-se pelo id;
 *  · o código único, que a base exige e ninguém declarava (1062 cru);
 *  · a guarda de apagar: um diário com LANÇAMENTOS em cima deixava-os órfãos;
 *  · as contas por omissão do diário podiam ser de outra empresa;
 *  · e o centro de custo não se podia desactivar — o gravar forçava
 *    `is_active => true` em toda a edição.
 */
class CatalogosDaContabilidadeTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/catalogos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('contabilidade')->comPermissoes(
            'accounting.journals.view', 'accounting.journals.manage',
            'accounting.document-types.view', 'accounting.document-types.manage',
            'accounting.cost-centers.view', 'accounting.cost-centers.manage',
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

    private function outraEmpresa(): Tenant
    {
        return Tenant::create([
            'name' => 'Outra '.uniqid(), 'slug' => 'outra-'.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o'.uniqid().'@exemplo.ao', 'is_active' => true,
        ]);
    }

    /* ─── Os três abrem ───────────────────────────────────────────────── */

    public function test_os_tres_catalogos_abrem_com_os_seus_campos(): void
    {
        foreach ([
            'diarios' => 'Diários',
            'tipos-de-documento' => 'Tipos de Documento',
            'centros-de-custo' => 'Centros de Custo',
        ] as $slug => $titulo) {
            $o = $this->getJson(self::RAIZ.'/'.$slug.'/opcoes')->assertOk();

            $this->assertSame($titulo, $o->json('titulo'));
            $this->assertNotEmpty($o->json('campos'), $slug.': o formulário não pode vir vazio');
            $this->assertNotEmpty($o->json('colunas'), $slug.': a tabela não pode vir vazia');
            $this->assertTrue($o->json('permissoes.pode_escrever'));
        }
    }

    /** Ver é uma permissão; gerir é outra. */
    public function test_quem_so_ve_nao_escreve(): void
    {
        $outro = \App\Models\User::create([
            'name' => 'Só vê', 'email' => 'v'.uniqid().'@exemplo.ao',
            'password' => bcrypt('secret'), 'tenant_id' => $this->tenant->id,
        ]);
        $outro->tenants()->syncWithoutDetaching([$this->tenant->id => ['is_active' => true]]);

        setPermissionsTeamId($this->tenant->id);
        $outro->givePermissionTo('accounting.journals.view');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($outro);
        session(['active_tenant_id' => $this->tenant->id]);

        $this->assertFalse($this->getJson(self::RAIZ.'/diarios/opcoes')->assertOk()->json('permissoes.pode_escrever'));

        $this->postJson(self::RAIZ.'/diarios', [
            'code' => 'X', 'name' => 'Sem direito', 'type' => 'general', 'sequence_prefix' => 'X-',
        ])->assertForbidden();
    }

    /* ─── Os diários ──────────────────────────────────────────────────── */

    public function test_o_diario_grava_com_o_contador_e_as_contas_por_omissao(): void
    {
        $debito = $this->conta();
        $credito = $this->conta();

        $r = $this->postJson(self::RAIZ.'/diarios', [
            'code' => 'VD', 'name' => 'Diário de Vendas', 'type' => 'sale',
            'sequence_prefix' => 'VD-', 'last_number' => 42,
            'default_debit_account_id' => $debito->id,
            'default_credit_account_id' => $credito->id,
            'active' => true,
        ])->assertCreated();

        $diario = Journal::find($r->json('data.id'));

        $this->assertSame('VD-', $diario->sequence_prefix);
        $this->assertSame(42, (int) $diario->last_number, 'o contador vem de quem traz numeração de outro sistema');
        $this->assertSame($debito->id, $diario->default_debit_account_id);
    }

    /** O código é único por empresa: a base exige-o e ninguém o declarava. */
    public function test_o_codigo_do_diario_e_unico(): void
    {
        $this->postJson(self::RAIZ.'/diarios', [
            'code' => 'DG', 'name' => 'Geral', 'type' => 'general', 'sequence_prefix' => 'DG-',
        ])->assertCreated();

        $this->postJson(self::RAIZ.'/diarios', [
            'code' => 'DG', 'name' => 'Outro geral', 'type' => 'general', 'sequence_prefix' => 'DG2-',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /** `general` está no enum e faltava na lista: abrir e gravar dava erro. */
    public function test_o_tipo_operacoes_diversas_e_aceite(): void
    {
        $tipos = collect($this->getJson(self::RAIZ.'/diarios/opcoes')->assertOk()->json('campos'))
            ->firstWhere('chave', 'type')['opcoes'];

        $this->assertContains('general', collect($tipos)->pluck('valor')->all(),
            'o diário de operações diversas existe na base e tem de se poder escolher');
    }

    /** A conta por omissão é desta empresa: `nullable|integer` aceitava a alheia. */
    public function test_a_conta_por_omissao_do_diario_tem_de_ser_da_empresa(): void
    {
        $outra = $this->outraEmpresa();

        $alheia = Account::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => '99', 'name' => 'Alheia',
            'type' => 'asset', 'nature' => 'debit', 'level' => 1,
        ]);

        $this->postJson(self::RAIZ.'/diarios', [
            'code' => 'AL', 'name' => 'Com conta alheia', 'type' => 'general',
            'sequence_prefix' => 'AL-', 'default_debit_account_id' => $alheia->id,
        ])->assertStatus(422)->assertJsonValidationErrors('default_debit_account_id');
    }

    /** O diário de outra empresa não se edita nem se apaga pelo id. */
    public function test_o_diario_de_outra_empresa_da_404(): void
    {
        $outra = $this->outraEmpresa();

        $alheio = Journal::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => 'AL', 'name' => 'Alheio',
            'type' => 'general', 'sequence_prefix' => 'AL-', 'active' => true,
        ]);

        $this->putJson(self::RAIZ.'/diarios/'.$alheio->id, [
            'code' => 'AL', 'name' => 'Mexido', 'type' => 'general', 'sequence_prefix' => 'AL-',
        ])->assertNotFound();

        $this->deleteJson(self::RAIZ.'/diarios/'.$alheio->id)->assertNotFound();
    }

    /**
     * UM DIÁRIO COM LANÇAMENTOS NÃO SE APAGA: os lançamentos ficariam a
     * apontar para um id que já não existe.
     */
    public function test_nao_se_apaga_um_diario_com_lancamentos(): void
    {
        $r = $this->postJson(self::RAIZ.'/diarios', [
            'code' => 'CL', 'name' => 'Com lançamentos', 'type' => 'general', 'sequence_prefix' => 'CL-',
        ])->assertCreated();

        $id = $r->json('data.id');
        $this->assertTrue($r->json('data.pode_apagar'), 'vazio, apaga-se');

        $periodo = \App\Models\Accounting\Period::create([
            'tenant_id' => $this->tenant->id, 'code' => 'P'.random_int(1000, 9999),
            'name' => 'Período', 'state' => 'open',
            'date_start' => now()->startOfYear()->format('Y-m-d'),
            'date_end' => now()->endOfYear()->format('Y-m-d'),
        ]);

        Move::create([
            'tenant_id' => $this->tenant->id, 'journal_id' => $id, 'period_id' => $periodo->id,
            'date' => now()->format('Y-m-d'), 'ref' => 'CL-00001', 'state' => 'draft',
            'total_debit' => 0, 'total_credit' => 0, 'created_by' => $this->user->id,
        ]);

        $this->deleteJson(self::RAIZ.'/diarios/'.$id)->assertStatus(422);
        $this->assertDatabaseHas('accounting_journals', ['id' => $id]);

        // E a lista já diz que não se pode, antes de alguém tentar.
        $linha = collect($this->getJson(self::RAIZ.'/diarios')->assertOk()->json('data'))
            ->firstWhere('id', $id);

        $this->assertFalse($linha['pode_apagar']);
    }

    /* ─── Os tipos de documento ───────────────────────────────────────── */

    public function test_o_tipo_de_documento_grava_as_bandeiras_dos_mapas(): void
    {
        $r = $this->postJson(self::RAIZ.'/tipos-de-documento', [
            'code' => 'FT', 'description' => 'Factura', 'recapitulativos' => true,
            'retencao_fonte' => true, 'bal_financeira' => true, 'bal_analitica' => false,
            'rec_informacao' => 2, 'tipo_doc_imo' => 1, 'calculo_fluxo_caixa' => 3,
            'display_order' => 5, 'is_active' => true,
        ])->assertCreated();

        $tipo = DocumentType::find($r->json('data.id'));

        $this->assertTrue((bool) $tipo->recapitulativos);
        $this->assertTrue((bool) $tipo->retencao_fonte);
        $this->assertSame(2, (int) $tipo->rec_informacao);
        $this->assertSame(3, (int) $tipo->calculo_fluxo_caixa);
        $this->assertSame(5, (int) $tipo->display_order);
    }

    /** O diário do tipo é desta empresa: a regra antiga não olhava à companhia. */
    public function test_o_diario_do_tipo_de_documento_tem_de_ser_da_empresa(): void
    {
        $outra = $this->outraEmpresa();

        $alheio = Journal::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'code' => 'AL2', 'name' => 'Alheio',
            'type' => 'general', 'sequence_prefix' => 'AL2-', 'active' => true,
        ]);

        $this->postJson(self::RAIZ.'/tipos-de-documento', [
            'code' => 'ND', 'description' => 'Com diário alheio', 'journal_id' => $alheio->id,
        ])->assertStatus(422)->assertJsonValidationErrors('journal_id');
    }

    public function test_o_codigo_do_tipo_de_documento_e_unico(): void
    {
        $this->postJson(self::RAIZ.'/tipos-de-documento', ['code' => 'RC', 'description' => 'Recibo'])->assertCreated();

        $this->postJson(self::RAIZ.'/tipos-de-documento', ['code' => 'RC', 'description' => 'Outro recibo'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    /* ─── Os centros de custo ─────────────────────────────────────────── */

    /**
     * DESACTIVAR UM CENTRO passou a ser possível: o gravar antigo forçava
     * `is_active => true` em toda a edição, pelo que um centro desactivado
     * voltava a ficar activo sem ninguém pedir.
     */
    public function test_o_centro_de_custo_desactiva_se(): void
    {
        $r = $this->postJson(self::RAIZ.'/centros-de-custo', [
            'code' => 'CC1', 'name' => 'Loja do Centro', 'type' => 'cost', 'is_active' => true,
        ])->assertCreated();

        $id = $r->json('data.id');

        $this->putJson(self::RAIZ.'/centros-de-custo/'.$id, [
            'code' => 'CC1', 'name' => 'Loja do Centro', 'type' => 'cost', 'is_active' => false,
        ])->assertOk();

        $this->assertFalse((bool) CostCenter::find($id)->is_active, 'desactivar tem de pegar');
    }

    public function test_um_centro_de_custo_nao_se_pendura_em_si_proprio(): void
    {
        $r = $this->postJson(self::RAIZ.'/centros-de-custo', [
            'code' => 'CC2', 'name' => 'Sozinho', 'type' => 'cost',
        ])->assertCreated();

        $id = $r->json('data.id');

        $this->putJson(self::RAIZ.'/centros-de-custo/'.$id, [
            'code' => 'CC2', 'name' => 'Sozinho', 'type' => 'cost', 'parent_id' => $id,
        ])->assertStatus(422)->assertJsonValidationErrors('parent_id');
    }

    /** Um centro com filhos não desaparece: a árvore partia-se ao meio. */
    public function test_nao_se_apaga_um_centro_com_filhos(): void
    {
        $mae = CostCenter::create([
            'tenant_id' => $this->tenant->id, 'code' => 'CCM', 'name' => 'Mãe',
            'type' => 'cost', 'is_active' => true,
        ]);

        CostCenter::create([
            'tenant_id' => $this->tenant->id, 'code' => 'CCF', 'name' => 'Filho',
            'type' => 'cost', 'parent_id' => $mae->id, 'is_active' => true,
        ]);

        $this->deleteJson(self::RAIZ.'/centros-de-custo/'.$mae->id)->assertStatus(422);
        $this->assertDatabaseHas('cost_centers', ['id' => $mae->id]);
    }

    /** E um centro que uma conta usa por omissão também não. */
    public function test_nao_se_apaga_um_centro_usado_por_uma_conta(): void
    {
        $centro = CostCenter::create([
            'tenant_id' => $this->tenant->id, 'code' => 'CCU', 'name' => 'Usado',
            'type' => 'cost', 'is_active' => true,
        ]);

        $this->conta(['default_cost_center_id' => $centro->id]);

        $this->deleteJson(self::RAIZ.'/centros-de-custo/'.$centro->id)->assertStatus(422);
    }

    /**
     * A LISTA MOSTRA A ÁRVORE TODA.
     *
     * A do Livewire só trazia os centros de raiz (`whereNull('parent_id')`): um
     * centro pendurado noutro não aparecia em lado nenhum, e não havia como o
     * editar.
     */
    public function test_a_lista_mostra_tambem_os_centros_pendurados(): void
    {
        $mae = CostCenter::create([
            'tenant_id' => $this->tenant->id, 'code' => 'AR1', 'name' => 'Raiz',
            'type' => 'cost', 'is_active' => true,
        ]);

        $filho = CostCenter::create([
            'tenant_id' => $this->tenant->id, 'code' => 'AR2', 'name' => 'Pendurado',
            'type' => 'cost', 'parent_id' => $mae->id, 'is_active' => true,
        ]);

        $ids = collect($this->getJson(self::RAIZ.'/centros-de-custo')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mae->id));
        $this->assertTrue($ids->contains($filho->id), 'o pendurado tem de se ver e poder editar');
    }
}
