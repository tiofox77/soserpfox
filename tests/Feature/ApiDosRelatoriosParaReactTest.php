<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Invoicing\Relatorios\Catalogo;
use Tests\TenantTestCase;

/**
 * OS RELATÓRIOS, para o ecrã genérico em React.
 *
 * O que estes ensaios guardam: todos os mapas do catálogo abrem com um
 * esquema coerente com os dados; a permissão dos relatórios manda (e o de
 * validades também abre a quem gere stock); o CSV é a primeira tabela; o
 * extracto de conta encontra a entidade; e um mapa faz contas certas.
 */
class ApiDosRelatoriosParaReactTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/relatorios';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    /** @test */
    public function todos_os_mapas_abrem_com_esquema_coerente_com_os_dados(): void
    {
        $this->comPermissoes('invoicing.reports.view');

        foreach (array_keys(Catalogo::RELATORIOS) as $slug) {
            $r = $this->getJson(self::RAIZ . '/' . $slug)->assertOk();
            $esquema = $r->json('esquema');
            $dados = $r->json('dados');

            $this->assertSame($slug, $esquema['slug'], $slug);
            $this->assertNotEmpty($esquema['titulo'], $slug);
            $this->assertIsArray($esquema['filtros'], $slug);
            $this->assertIsArray($esquema['cartoes'], $slug);
            $this->assertIsArray($esquema['tabelas'], $slug);

            foreach ($esquema['tabelas'] as $tabela) {
                $this->assertArrayHasKey($tabela['chave'], $dados, "{$slug}: a tabela «{$tabela['chave']}» não tem dados");
                $this->assertNotEmpty($tabela['colunas'], $slug);
            }
            foreach ($esquema['filtros'] as $filtro) {
                if ($filtro['tipo'] === 'select') {
                    $this->assertIsArray($filtro['opcoes'], "{$slug}: o filtro «{$filtro['nome']}» ficou sem opções resolvidas");
                }
            }
            if ($esquema['periodo']) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dados['intervalo']['de'], $slug);
            }
            $this->assertSame($esquema['csv'], $r->json('csv') !== null, $slug);
        }
    }

    /** @test */
    public function a_permissao_dos_relatorios_manda(): void
    {
        $this->getJson(self::RAIZ)->assertForbidden();
        $this->getJson(self::RAIZ . '/sales')->assertForbidden();

        // Validades também serve quem gere stock.
        $this->comPermissoes('invoicing.stock.view');
        $this->getJson(self::RAIZ . '/expiry-report')->assertOk();
        $this->getJson(self::RAIZ . '/sales')->assertForbidden();

        $this->getJson(self::RAIZ . '/nao-existe')->assertNotFound();
    }

    /** @test */
    public function a_porta_lista_as_seccoes_com_os_caminhos(): void
    {
        $this->comPermissoes('invoicing.reports.view');

        $r = $this->getJson(self::RAIZ)->assertOk();
        $seccoes = $r->json('seccoes');

        $this->assertCount(7, $seccoes);
        $todos = collect($seccoes)->flatMap(fn ($s) => $s['relatorios']);
        $this->assertCount(count(Catalogo::RELATORIOS), $todos, 'cada mapa do catálogo tem lugar na porta');
        $this->assertSame('/invoicing/reports/sales/novo-ecra', $todos->firstWhere('slug', 'sales')['caminho']);
        $this->assertSame('/invoicing/expiry-report/novo-ecra', $todos->firstWhere('slug', 'expiry-report')['caminho']);
    }

    /** @test */
    public function o_periodo_segue_o_atalho_ou_as_datas(): void
    {
        $this->comPermissoes('invoicing.reports.view');

        $this->getJson(self::RAIZ . '/sales?period=year')->assertOk()
            ->assertJsonPath('dados.intervalo.de', now()->startOfYear()->toDateString());

        $this->getJson(self::RAIZ . '/sales?dateFrom=2026-03-10&dateTo=2026-03-01')->assertOk()
            ->assertJsonPath('dados.intervalo.de', '2026-03-01')
            ->assertJsonPath('dados.intervalo.ate', '2026-03-10');
    }

    /** @test */
    public function a_tabela_de_precos_faz_as_contas_certas(): void
    {
        $this->comPermissoes('invoicing.reports.view');

        Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo de margem', 'type' => 'produto', 'price' => 100, 'cost' => 60,
            'unit' => 'un', 'tax_type' => 'isento', 'exemption_reason' => 'M99', 'manage_stock' => false, 'is_active' => true,
        ]);

        $linha = collect($this->getJson(self::RAIZ . '/price-list?search=margem')->assertOk()->json('dados.products'))->firstWhere('name', 'Artigo de margem');

        $this->assertNotNull($linha);
        $this->assertEqualsWithDelta(40, $linha['profit'], 0.001);
        $this->assertEqualsWithDelta(40, $linha['margin'], 0.001);
        $this->assertEqualsWithDelta(66.667, $linha['markup'], 0.01);
        $this->assertSame('active', $linha['estado']);
    }

    /** @test */
    public function o_extracto_encontra_a_entidade_e_abre_a_conta(): void
    {
        $this->comPermissoes('invoicing.reports.view');
        $cliente = $this->clienteEmpresa();

        $achados = $this->getJson(self::RAIZ . '/account-statement/entidades?entidade=cliente&q=' . urlencode(mb_substr($cliente->name, 0, 6)))->assertOk()->json('data');
        $this->assertContains($cliente->id, array_column($achados, 'id'));

        $r = $this->getJson(self::RAIZ . '/account-statement?entidade=cliente&entidadeId=' . $cliente->id)->assertOk();
        $this->assertNotNull($r->json('dados.resumo'));
        $this->assertSame($cliente->id, $r->json('dados.entidade_escolhida.id'));

        // Sem entidade: sem resumo, e a procura noutro mapa é 404.
        $this->assertNull($this->getJson(self::RAIZ . '/account-statement')->assertOk()->json('dados.resumo'));
        $this->getJson(self::RAIZ . '/sales/entidades?q=x')->assertNotFound();
    }

    /** @test */
    public function o_csv_e_a_primeira_tabela_com_o_que_esta_no_ecra(): void
    {
        $this->comPermissoes('invoicing.reports.view');

        $r = $this->get('/invoicing/reports/sales/novo-ecra/csv?period=month');

        $r->assertOk();
        $this->assertStringContainsString('text/csv', (string) $r->headers->get('content-type'));
        $conteudo = $r->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $conteudo, 'o BOM para o Excel em português');
        $this->assertStringContainsString('Nº;Data;Cliente;Subtotal;IVA;Total;Pago;Estado', $conteudo);

        $this->get('/invoicing/reports/charts/novo-ecra/csv')->assertNotFound();
    }
}
