<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Um utilizador sem empresa nenhuma a abrir o ecra de definicoes.
 *
 * O activeTenantId() devolve null quando quem esta autenticado nao esta ligado
 * a empresa nenhuma — e o dono da plataforma costuma estar nesse estado. O
 * forTenant(null) descia ate um firstOrCreate(['tenant_id' => null]) e tentava
 * INSERIR uma linha de definicoes sem empresa: a coluna e NOT NULL e o ecra
 * dava 500 em producao.
 *
 * Duas promessas a guardar: nunca se escreve uma linha sem empresa, e o ecra
 * diz o que se passa em vez de estoirar. O ecra passou a React e quem
 * responde e a API `/api/v1/invoicing/react/definicoes` — a promessa e a
 * mesma, so muda quem a cumpre.
 */
class DefinicoesSemEmpresaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/definicoes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function utilizadorSemEmpresa(): User
    {
        $u = User::create([
            'name'      => 'Sem Empresa',
            'email'     => 'sem-empresa-' . uniqid() . '@exemplo.ao',
            'password'  => bcrypt('irrelevante'),
            'tenant_id' => null,
        ]);

        // Sem sessao de empresa activa: e este o estado que provoca o 500.
        session()->forget('active_tenant_id');

        return $u;
    }

    public function test_sem_empresa_nao_estoira(): void
    {
        $this->actingAs($this->utilizadorSemEmpresa());

        $definicoes = InvoicingSettings::forTenant(activeTenantId());

        $this->assertNotNull($definicoes, 'devia devolver algo com que se possa ler um valor por omissao');
    }

    /** E, sobretudo, nao pode deixar lixo gravado. */
    public function test_sem_empresa_nao_grava_linha_nenhuma(): void
    {
        $this->actingAs($this->utilizadorSemEmpresa());

        $antes = DB::table('invoicing_settings')->count();

        InvoicingSettings::forTenant(activeTenantId());

        $this->assertSame($antes, DB::table('invoicing_settings')->count(),
            'gravou uma linha de definicoes sem empresa');
        $this->assertSame(0, DB::table('invoicing_settings')->whereNull('tenant_id')->count());
    }

    /** Os valores por omissao continuam a poder ser lidos por quem so quer ler. */
    public function test_os_valores_por_omissao_continuam_legiveis(): void
    {
        $this->actingAs($this->utilizadorSemEmpresa());

        $definicoes = InvoicingSettings::forTenant(activeTenantId());

        $this->assertSame('AOA', $definicoes->default_currency);
        $this->assertFalse($definicoes->exists, 'nao pode estar gravado na base');
    }

    /**
     * A API recusa-se a servir o ecra, e DIZ PORQUE.
     *
     * Nao e um 500 nem um corpo vazio: e uma recusa com um codigo que o ecra
     * sabe ler para pedir a escolha da empresa. Um ecra em branco lia-se como
     * avaria; isto le-se como o que e.
     */
    public function test_o_ecra_diz_o_que_se_passa(): void
    {
        $this->actingAs($this->utilizadorSemEmpresa());

        $r = $this->getJson(self::RAIZ)->assertStatus(403);

        $this->assertSame('no_active_tenant', $r->json('code'));
        $this->assertStringContainsString('empresa', mb_strtolower((string) $r->json('error')));
    }

    /**
     * E NAO manda a ficha. Deixar o formulario desenhar-se contra os valores
     * por omissao de uma linha que nao existe era pior do que o erro: quem o
     * preenchesse estaria a escrever para o vazio.
     */
    public function test_o_ecra_nao_mostra_a_ficha_para_preencher(): void
    {
        $this->actingAs($this->utilizadorSemEmpresa());

        $r = $this->getJson(self::RAIZ);

        $this->assertNull($r->json('definicoes'), 'a ficha esta la para ser preenchida');
        $this->assertNull($r->json('series'));
        $this->assertNull($r->json('permissoes.pode_editar'));
    }

    /** E gravar tambem nao passa: sem empresa nao ha onde escrever. */
    public function test_sem_empresa_nao_se_grava_pela_api(): void
    {
        $this->actingAs($this->utilizadorSemEmpresa());

        $this->putJson(self::RAIZ, ['default_currency' => 'USD'])->assertStatus(403);

        $this->assertSame(0, DB::table('invoicing_settings')->whereNull('tenant_id')->count());
    }

    /** Com empresa, a ficha aparece na mesma. */
    public function test_com_empresa_a_ficha_continua_a_aparecer(): void
    {
        $this->comPermissoes('invoicing.settings.view');

        $r = $this->getJson(self::RAIZ)->assertOk();

        $this->assertSame('AOA', $r->json('definicoes.default_currency'));
        $this->assertNotEmpty($r->json('series.tipos'));
    }

    /** Com empresa continua tudo como estava: le, cria uma vez, e reaproveita. */
    public function test_com_empresa_continua_a_criar_e_a_reaproveitar(): void
    {
        InvoicingSettings::esquecerMemoria();
        DB::table('invoicing_settings')->where('tenant_id', $this->tenant->id)->delete();

        $primeira = InvoicingSettings::forTenant($this->tenant->id);

        $this->assertTrue($primeira->exists);
        $this->assertSame($this->tenant->id, $primeira->tenant_id);

        InvoicingSettings::esquecerMemoria();
        $segunda = InvoicingSettings::forTenant($this->tenant->id);

        $this->assertSame($primeira->id, $segunda->id, 'criou uma segunda linha para a mesma empresa');
    }
}
