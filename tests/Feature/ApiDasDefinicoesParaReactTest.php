<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Warehouse;
use App\Models\Tenant;
use App\Services\Invoicing\GestorDeSeries;
use Tests\TenantTestCase;

/**
 * A API DAS DEFINIÇÕES DA FACTURAÇÃO, para o ecrã em React.
 *
 * O que ela promete e estes ensaios guardam: ver é uma permissão e editar é
 * outra; guardar passa pelas mesmas regras do Livewire (o armazém escolhido
 * fica mesmo padrão, o de outra empresa é recusado com aviso, o menu do PWA
 * leva sempre o Início); e as séries nascem com o prefixo do catálogo, não
 * se renomeiam noutra empresa, e escolher a padrão de uma série por estrear
 * pede confirmação com os números à frente dos olhos.
 */
class ApiDasDefinicoesParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/definicoes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function definicoes(): InvoicingSettings
    {
        return InvoicingSettings::forTenant($this->tenant->id);
    }

    private function corpo(array $por = []): array
    {
        return array_merge($this->getJson(self::RAIZ)->json('definicoes'), $por);
    }

    /** @test */
    public function sem_permissao_nao_ha_nada(): void
    {
        $this->getJson(self::RAIZ)->assertForbidden();
        $this->putJson(self::RAIZ, [])->assertForbidden();
    }

    /** Ver é uma permissão; editar é outra. @test */
    public function quem_so_ve_nao_edita(): void
    {
        $this->comPermissoes('invoicing.settings.view');

        $r = $this->getJson(self::RAIZ)->assertOk();

        $this->assertFalse($r->json('permissoes.pode_editar'));
        $this->assertSame('AOA', $r->json('definicoes.default_currency'));
        $this->assertNotEmpty($r->json('series.tipos'));

        $this->putJson(self::RAIZ, $this->corpo())->assertForbidden();
        $this->postJson(self::RAIZ . '/series', ['tipo' => 'invoice', 'codigo' => 'B'])->assertForbidden();
    }

    /** @test */
    public function guardar_passa_pelas_mesmas_regras_do_livewire(): void
    {
        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $novo = Warehouse::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Novo', 'code' => 'ARM-N-' . uniqid(),
            'is_active' => true, 'is_default' => false,
        ]);

        $this->putJson(self::RAIZ, $this->corpo([
            'default_warehouse_id' => $novo->id,
            'profile_pharmacy' => true,
            'pos_formato_impressao' => 'a4',
            'pwa_menu' => ['pos'],
        ]))->assertOk()->assertJsonPath('aviso', null);

        $d = $this->definicoes()->fresh();

        $this->assertTrue((bool) $d->profile_pharmacy);
        $this->assertSame('a4', $d->pos_formato_impressao);
        $this->assertTrue($novo->fresh()->is_default, 'o armazém escolhido fica mesmo padrão');
        $this->assertContains('inicio', $d->pwa_menu, 'o Início vai sempre');
        $this->assertContains('pos', $d->pwa_menu);
    }

    /** O armazém de outra empresa é recusado, e a definição fica limpa. @test */
    public function o_armazem_de_outra_empresa_e_recusado_com_aviso(): void
    {
        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $outra = Tenant::create([
            'name' => 'Vizinha', 'slug' => 'viz-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $alheio = Warehouse::withoutEvents(fn () => Warehouse::create([
            'tenant_id' => $outra->id, 'name' => 'Alheio',
            'code' => 'ARM-VIZ-' . uniqid(), 'is_active' => true, 'is_default' => true,
        ]));

        $r = $this->putJson(self::RAIZ, $this->corpo(['default_warehouse_id' => $alheio->id]))->assertOk();

        $this->assertNotNull($r->json('aviso'));
        $this->assertNull($this->definicoes()->fresh()->default_warehouse_id);
        $this->assertTrue($alheio->fresh()->is_default, 'o armazém da outra empresa não muda');
    }

    /** @test */
    public function o_que_vem_do_navegador_nao_manda_no_cabecalho_dos_documentos(): void
    {
        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $this->putJson(self::RAIZ, $this->corpo(['nome_nos_documentos' => 'alcunha']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nome_nos_documentos');

        $this->putJson(self::RAIZ, $this->corpo(['pos_formato_impressao' => 'xpto']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('pos_formato_impressao');
    }

    /** A série nasce com o prefixo do catálogo, não com o do pedido. @test */
    public function a_serie_nasce_com_o_prefixo_do_catalogo(): void
    {
        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $r = $this->postJson(self::RAIZ . '/series', ['tipo' => 'invoice', 'codigo' => 'B', 'prefixo' => 'XX'])
            ->assertCreated();

        $s = InvoicingSeries::find($r->json('serie.id'));

        $this->assertSame('FT', $s->prefix);
        $this->assertSame('B', $s->series_code);
        $this->assertFalse((bool) $s->is_default, 'criar nunca é escolher a padrão');

        $this->postJson(self::RAIZ . '/series', ['tipo' => 'xpto', 'codigo' => 'C'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipo');
    }

    /** @test */
    public function nao_se_renomeia_a_serie_de_outra_empresa(): void
    {
        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $outra = Tenant::create([
            'name' => 'Vizinha', 'slug' => 'viz-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $dela = app(GestorDeSeries::class)->criar($outra->id, 'invoice', 'Z', null, null);

        $this->putJson(self::RAIZ . '/series/' . $dela->id, ['codigo' => 'ROUBADA'])->assertNotFound();

        $this->assertSame('Z', $dela->fresh()->series_code);
    }

    /**
     * Passar o padrão para uma série por estrear enquanto outra já vai
     * adiantada pede confirmação — e só com ela muda.
     *
     * @test
     */
    public function escolher_a_padrao_de_uma_serie_por_estrear_pede_confirmacao(): void
    {
        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $gestor = app(GestorDeSeries::class);

        $emUso = InvoicingSeries::where('tenant_id', $this->tenant->id)
            ->where('document_type', 'invoice')->where('is_default', true)->first()
            ?? tap($gestor->criar($this->tenant->id, 'invoice', 'A', null, null), fn ($s) => $gestor->tornarPadrao($s, true));

        $emUso->update(['next_number' => 12]);

        $nova = $gestor->criar($this->tenant->id, 'invoice', 'N', null, null);

        $r = $this->postJson(self::RAIZ . '/series/' . $nova->id . '/padrao', ['confirmado' => false])->assertOk();

        $this->assertSame('N', $r->json('aviso.nova'));
        $this->assertSame(12, $r->json('aviso.em_uso_proximo'));
        $this->assertFalse((bool) $nova->fresh()->is_default, 'sem confirmação não muda nada');

        $this->postJson(self::RAIZ . '/series/' . $nova->id . '/padrao', ['confirmado' => true])
            ->assertOk()
            ->assertJsonPath('aviso', null);

        $this->assertTrue((bool) $nova->fresh()->is_default);
        $this->assertFalse((bool) $emUso->fresh()->is_default, 'só há uma padrão por tipo');
    }

    /**
     * A TAXA DO IVA NASCE DO IMPOSTO ESCOLHIDO — nunca do que se escreve.
     *
     * Eram duas verdades a competir: `default_tax_id` (o imposto do catálogo)
     * e `default_tax_rate` (uma percentagem à parte). Nada as obrigava a
     * concordar, e o número sozinho não sabe nada do regime da empresa: em
     * regime de isenção dá 0% e a linha segue sem código de isenção — a AGT
     * recusa uma linha sem imposto e sem motivo. O `SalonPOS` já tinha
     * deixado de o usar por isso mesmo.
     *
     * A RETENÇÃO (IRT) é o contrário e continua a escrever-se: não vem de
     * imposto nenhum do catálogo, é uma percentagem retida na fonte.
     *
     * @test
     */
    public function a_taxa_do_iva_vem_do_imposto_e_nao_do_que_se_escreve(): void
    {
        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $imposto = \App\Models\Invoicing\Tax::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'IVA7E',
            'name' => 'IVA 7% de ensaio',
            'rate' => 7,
            'is_active' => true,
            'saft_code' => 'RED',
        ]);

        // O pedido MENTE: escolhe o imposto de 7% e diz que a taxa é 14%.
        $this->putJson(self::RAIZ, $this->corpo([
            'default_tax_id' => $imposto->id,
            'default_tax_rate' => 14,
            'default_irt_rate' => 6.5,
        ]))->assertOk();

        $d = InvoicingSettings::where('tenant_id', $this->tenant->id)->first();

        $this->assertEqualsWithDelta(7, (float) $d->default_tax_rate, 0.01,
            'a taxa tem de vir do imposto escolhido, não do número do pedido');

        // A retenção é escrita, e fica como veio.
        $this->assertEqualsWithDelta(6.5, (float) $d->default_irt_rate, 0.01,
            'o IRT não vem de imposto nenhum: continua a escrever-se');
    }

    /**
     * A RETENÇÃO SEGUE A MESMA REGRA, quando há um IRT no catálogo.
     *
     * Há doze «IRT 6,5% (Retenção)» na base, e escrever 6,5 numa caixa ao lado
     * deles eram duas verdades a competir — exactamente como no IVA.
     *
     * Quem NÃO tenha IRT nenhum no catálogo continua a escrever o número: o
     * ensaio acima é esse caso, e tirar-lhes a caixa era tirar-lhes a retenção.
     *
     * @test
     */
    public function a_retencao_vem_do_imposto_de_irt_quando_ha_um(): void
    {
        $this->comPermissoes('invoicing.settings.view', 'invoicing.settings.edit');

        $irt = \App\Models\Invoicing\Tax::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'IRT10E',
            'name' => 'IRT 10% de ensaio',
            'rate' => 10,
            'type' => 'irt',
            'is_active' => true,
            'saft_code' => 'OUT',
        ]);

        // O pedido MENTE: escolhe o IRT de 10% e diz que a taxa é 6,5%.
        $this->putJson(self::RAIZ, $this->corpo([
            'default_irt_tax_id' => $irt->id,
            'default_irt_rate' => 6.5,
        ]))->assertOk();

        $d = InvoicingSettings::where('tenant_id', $this->tenant->id)->first();

        $this->assertEqualsWithDelta(10, (float) $d->default_irt_rate, 0.01,
            'com IRT escolhido, a taxa vem dele e não do número do pedido');
        $this->assertSame($irt->id, (int) $d->default_irt_tax_id);
    }
}
