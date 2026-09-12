<?php

namespace Tests\Feature\Empresa;

use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * O ECRÃ DOS DADOS DA EMPRESA EM REACT.
 *
 * O que a permissão decide está em `DadosDaEmpresaPermissaoTest`, e a morada em
 * `GeografiaDaMoradaTest`. Aqui fica o resto: o RESUMO FISCAL — que é o que dá
 * sentido à escolha do regime — e o logótipo.
 *
 * O resumo existe por uma razão prática: um regime não se escolhe no abstracto.
 * Escolhe-se a olhar para o imposto que vai passar a sair nos documentos e para
 * quantos produtos vão mudar de mão. E o número que mais interessa é o dos
 * ISENTOS SEM MOTIVO: a AGT recusa um documento isento sem código de isenção, e
 * cada produto assim é uma factura que vai voltar rejeitada.
 */
class EcraDaEmpresaEmReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/empresa';

    protected function setUp(): void
    {
        parent::setUp();
        $this->comPermissoes('settings.view', 'settings.edit');
        Storage::fake('public');
    }

    private function produto(array $extra = []): Product
    {
        return Product::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Artigo '.uniqid(),
            'code' => 'A'.strtoupper(substr(uniqid(), -8)),
            'sku' => 'S'.strtoupper(substr(uniqid(), -8)),
            'price' => 1000,
            'tax_type' => 'iva',
        ], $extra));
    }

    /** O ecrã monta-se e traz o catálogo de regimes. */
    public function test_o_ecra_abre_com_os_tres_regimes(): void
    {
        $this->get(route('company.profile'))->assertOk()->assertSee('empresa/dados', false);

        $resposta = $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk();

        $this->assertCount(count(Tenant::REGIMES), $resposta->json('regimes'));

        $naoSujeicao = collect($resposta->json('regimes'))
            ->firstWhere('valor', Tenant::REGIME_NAO_SUJEICAO);

        $this->assertTrue($naoSujeicao['isento']);
        $this->assertSame('M04', $naoSujeicao['codigo_de_isencao'],
            'a não sujeição sai com o motivo obrigatório, e o ecrã tem de o dizer');
    }

    /**
     * OS ISENTOS SEM MOTIVO são o número que interessa.
     *
     * A AGT recusa um documento isento sem código de isenção. Cada produto
     * assim é uma factura que vai voltar rejeitada — e sem este número ninguém
     * sabe quantas são antes de as emitir.
     */
    public function test_o_resumo_conta_os_isentos_sem_motivo(): void
    {
        $this->produto(['tax_type' => 'iva']);
        $this->produto(['tax_type' => 'isento', 'exemption_reason' => 'M04']);
        $this->produto(['tax_type' => 'isento', 'exemption_reason' => null]);
        $this->produto(['tax_type' => 'isento', 'exemption_reason' => '']);

        $resumo = $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk()->json('resumo');

        $this->assertSame(4, $resumo['produtos']['total']);
        $this->assertSame(1, $resumo['produtos']['com_iva']);
        $this->assertSame(3, $resumo['produtos']['isentos']);
        $this->assertSame(2, $resumo['produtos']['isentos_sem_motivo'],
            'o nulo e a cadeia vazia contam os dois — a AGT recusa ambos');
    }

    /** Sem imposto por omissão, o ecrã diz que falta configurá-lo. */
    public function test_o_resumo_avisa_quando_nao_ha_imposto_por_omissao(): void
    {
        Tax::where('tenant_id', $this->tenant->id)->update(['is_default' => false]);

        $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk()
            ->assertJsonPath('resumo.falta_configurar_imposto', true)
            ->assertJsonPath('resumo.imposto', null);

        Tax::create([
            'tenant_id' => $this->tenant->id, 'code' => 'IVA14-'.uniqid(),
            'name' => 'IVA 14%', 'rate' => 14, 'type' => 'iva',
            'is_active' => true, 'is_default' => true,
        ]);

        $this->actingAs($this->user)->getJson(self::RAIZ)->assertOk()
            ->assertJsonPath('resumo.falta_configurar_imposto', false)
            ->assertJsonPath('resumo.imposto.nome', 'IVA 14%');
    }

    /* ─── O logótipo ──────────────────────────────────────────────────── */

    public function test_o_logotipo_grava_se_e_apaga_se(): void
    {
        $this->actingAs($this->user)->postJson(self::RAIZ.'/logotipo', [
            'logo' => UploadedFile::fake()->image('marca.png'),
        ])->assertOk();

        $caminho = $this->tenant->fresh()->logo;

        $this->assertNotNull($caminho);
        Storage::disk('public')->assertExists($caminho);

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/logotipo')->assertOk();

        $this->assertNull($this->tenant->fresh()->logo);
        Storage::disk('public')->assertMissing($caminho);
    }

    /** Um logótipo pesado demais é recusado — sai em todas as facturas. */
    public function test_o_logotipo_pesado_e_recusado(): void
    {
        $this->actingAs($this->user)->postJson(self::RAIZ.'/logotipo', [
            'logo' => UploadedFile::fake()->image('enorme.png')->size(3000),
        ])->assertStatus(422)->assertJsonValidationErrors('logo');

        $this->assertNull($this->tenant->fresh()->logo);
    }

    /** E o ficheiro que não é imagem também. */
    public function test_o_que_nao_e_imagem_nao_e_logotipo(): void
    {
        $this->actingAs($this->user)->postJson(self::RAIZ.'/logotipo', [
            'logo' => UploadedFile::fake()->create('folha.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('logo');
    }

    /**
     * TROCAR DE LOGÓTIPO não deixa o anterior para trás.
     *
     * Quando a extensão muda, o ficheiro novo tem outro nome e o antigo ficava
     * no disco para sempre — a acumular a cada troca.
     */
    public function test_trocar_de_logotipo_apaga_o_anterior(): void
    {
        $this->actingAs($this->user)->postJson(self::RAIZ.'/logotipo', [
            'logo' => UploadedFile::fake()->image('marca.png'),
        ])->assertOk();

        $antigo = $this->tenant->fresh()->logo;

        $this->actingAs($this->user)->postJson(self::RAIZ.'/logotipo', [
            'logo' => UploadedFile::fake()->image('marca.jpg'),
        ])->assertOk();

        $novo = $this->tenant->fresh()->logo;

        $this->assertNotSame($antigo, $novo);
        Storage::disk('public')->assertMissing($antigo);
        Storage::disk('public')->assertExists($novo);
    }

    /** Apagar um logótipo que não existe diz-se, não rebenta. */
    public function test_apagar_um_logotipo_que_nao_ha_recusa_se_por_escrito(): void
    {
        $this->tenant->update(['logo' => null]);

        $this->actingAs($this->user)->deleteJson(self::RAIZ.'/logotipo')->assertStatus(422);
    }

    /**
     * O NIF DA EMPRESA não é o bilhete de identidade de ninguém.
     *
     * O campo antigo aceitava `[A-Za-z0-9]{5,20}` — passava o número do BI, com
     * letras e tudo, no próprio ecrã onde se vem corrigir isso.
     */
    public function test_o_nif_tem_de_ser_de_empresa(): void
    {
        $antes = $this->tenant->nif;

        $this->actingAs($this->user)->putJson(self::RAIZ, [
            'name' => $this->tenant->name,
            'nif' => '00123',
            'country' => 'AO',
            'regime' => Tenant::canonicalRegime($this->tenant->regime),
        ])->assertStatus(422)->assertJsonValidationErrors('nif');

        $this->assertSame($antes, $this->tenant->fresh()->nif);
    }
}
