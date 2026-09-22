<?php

namespace Tests\Feature;

use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use Tests\TenantTestCase;

/**
 * STOCK EM FALTA E ABAIXO DO MÍNIMO (22/09/2026).
 *
 * O mapa tem de contar os mesmos artigos que o ecrã de Stock — em falta é
 * existência <= 0, abaixo do mínimo é existência <= `stock_min` — e dizer
 * quanto encomendar sem inventar números: até ao máximo, e sem máximo até
 * cobrir o que saiu em 30 dias (ou o mínimo, se for maior).
 */
class StockEmFaltaReportTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/relatorios/stock-levels';

    protected function setUp(): void
    {
        parent::setUp();
        $this->comPermissoes('invoicing.reports.view', 'invoicing.stock.view')->comModulo('invoicing');
    }

    private function mapa(array $filtros = []): array
    {
        return $this->getJson(self::RAIZ . ($filtros ? '?' . http_build_query($filtros) : ''))->assertOk()->json('dados');
    }

    /** As linhas do mapa, por nome do artigo. */
    private function linhas(array $filtros = []): array
    {
        return collect($this->mapa($filtros)['artigos'])->keyBy('nome')->all();
    }

    private function artigo(float $existencia, array $campos = []): Product
    {
        $p = $this->produtoComStock($existencia, 2000);
        if ($campos) {
            $p->forceFill($campos)->save();
        }

        return $p->fresh();
    }

    /** Uma saída que não mexe na existência — só para o consumo dos 30 dias. */
    private function saida(Product $p, float $quantidade, int $diasAtras = 3): void
    {
        $m = StockMovement::semAplicarStock(fn () => StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->armazem->id,
            'product_id' => $p->id,
            'type' => 'out',
            'quantity' => $quantidade,
            'unit_cost' => 1000,
            'user_id' => $this->user->id,
        ]));
        $m->forceFill(['created_at' => now()->subDays($diasAtras)])->save();
    }

    public function test_o_ecra_abre_e_esta_na_porta_dos_relatorios(): void
    {
        $this->get(route('invoicing.reports.stock-levels'))->assertOk();
        $this->getJson(self::RAIZ)->assertOk()
            ->assertJsonPath('esquema.slug', 'stock-levels')
            ->assertJsonPath('esquema.titulo', 'Stock em Falta e Abaixo do Mínimo');

        $slugs = collect($this->getJson('/api/v1/invoicing/react/relatorios')->assertOk()->json('seccoes'))
            ->flatMap(fn ($s) => collect($s['relatorios'])->pluck('slug'));
        $this->assertContains('stock-levels', $slugs->all());
    }

    public function test_separa_o_que_falta_do_que_esta_abaixo_do_minimo_e_deixa_o_resto_de_fora(): void
    {
        $falta = $this->artigo(0);
        $baixo = $this->artigo(3, ['stock_min' => 5]);
        $folgado = $this->artigo(20, ['stock_min' => 5]);
        $servico = $this->artigo(0, ['manage_stock' => false]);
        $inactivo = $this->artigo(0, ['is_active' => false]);

        $linhas = $this->linhas();

        $this->assertSame('Em falta', $linhas[$falta->name]['situacao']);
        $this->assertSame('Abaixo do mínimo', $linhas[$baixo->name]['situacao']);
        $this->assertArrayNotHasKey($folgado->name, $linhas, 'acima do mínimo não é para repor');
        $this->assertArrayNotHasKey($servico->name, $linhas, 'um serviço não falta em armazém nenhum');
        $this->assertArrayNotHasKey($inactivo->name, $linhas, 'um artigo inactivo não se repõe');

        // O que falta vem primeiro.
        $this->assertSame($falta->name, array_key_first($linhas));
    }

    public function test_um_artigo_sem_linha_de_stock_tambem_esta_em_falta(): void
    {
        // Nunca teve entrada: não há linha em invoicing_stocks — e é o mais em falta de todos.
        $nunca = $this->artigo(0);

        $this->assertSame('Em falta', $this->linhas()[$nunca->name]['situacao'] ?? null);
        $this->assertSame(0.0, (float) $this->linhas()[$nunca->name]['existencia']);
    }

    public function test_quanto_encomendar_e_quantos_dias_aguenta(): void
    {
        // Com máximo: encomenda-se até ao máximo.
        $comMaximo = $this->artigo(3, ['stock_min' => 5, 'stock_max' => 12]);
        // Sem máximo nem mínimo, mas com saídas: cobre-se o que saiu em 30 dias.
        $comSaidas = $this->artigo(0);
        $this->saida($comSaidas, 6);
        $this->saida($comSaidas, 50, 45); // fora da janela: não conta
        // Abaixo do mínimo e a vender: 4 unidades, 12 saídas em 30 dias = 0,4/dia → 10 dias.
        $aVender = $this->artigo(4, ['stock_min' => 5]);
        $this->saida($aVender, 12);
        // Sem nada que sirva de base: não se inventa uma quantidade.
        $semBase = $this->artigo(0);

        $l = $this->linhas();

        $this->assertEqualsWithDelta(9, $l[$comMaximo->name]['a_encomendar'], 0.001);
        $this->assertEqualsWithDelta(9 * 1000, $l[$comMaximo->name]['valor_a_repor'], 0.01, 'custo do artigo = 1000 (metade do preço)');
        $this->assertNull($l[$comMaximo->name]['cobertura_dias'], 'sem saídas não se sabe quantos dias aguenta');

        $this->assertEqualsWithDelta(6, $l[$comSaidas->name]['saidas_30_dias'], 0.001);
        $this->assertEqualsWithDelta(6, $l[$comSaidas->name]['a_encomendar'], 0.001);
        $this->assertSame(0, $l[$comSaidas->name]['cobertura_dias']);

        $this->assertSame(10, $l[$aVender->name]['cobertura_dias']);
        $this->assertEqualsWithDelta(8, $l[$aVender->name]['a_encomendar'], 0.001, 'sem máximo: até às 12 que saíram em 30 dias');

        $this->assertNull($l[$semBase->name]['a_encomendar']);
        $this->assertSame(0.0, (float) $l[$semBase->name]['valor_a_repor']);
    }

    public function test_os_filtros_e_os_cartoes(): void
    {
        $falta = $this->artigo(0);
        $baixo = $this->artigo(3, ['stock_min' => 5]);
        $semMinimo = $this->artigo(8);

        $this->assertSame([$falta->name], array_keys($this->linhas(['situacao' => 'falta'])));
        $this->assertSame([$baixo->name], array_keys($this->linhas(['situacao' => 'baixo'])));
        $this->assertContains($semMinimo->name, array_keys($this->linhas(['situacao' => 'sem_minimo'])));
        $this->assertSame([$baixo->name], array_keys($this->linhas(['search' => $baixo->code])));

        $resumo = $this->mapa()['resumo'];
        $this->assertSame(1, $resumo['em_falta']);
        $this->assertSame(1, $resumo['abaixo_do_minimo']);
        $this->assertSame(2, $resumo['sem_minimo'], 'o que falta sem mínimo e o que tem 8 sem mínimo');
    }

    public function test_so_conta_os_artigos_da_empresa(): void
    {
        $meu = $this->artigo(0);
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra Empresa', 'slug' => 'outra-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999), 'email' => 'outra' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);
        Product::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'name' => 'Da Outra Empresa', 'code' => 'OUT' . uniqid(), 'type' => 'produto',
            'price' => 10, 'cost' => 5, 'unit' => 'UN', 'manage_stock' => true, 'is_active' => true,
        ]);

        $nomes = array_keys($this->linhas());
        $this->assertContains($meu->name, $nomes);
        $this->assertNotContains('Da Outra Empresa', $nomes);
    }

    public function test_quem_gere_stock_abre_mesmo_sem_ver_os_outros_mapas(): void
    {
        $this->comPermissoes('invoicing.stock.view');
        $this->user->syncPermissions([]);
        $this->comPermissoes('invoicing.stock.view');

        $this->getJson(self::RAIZ)->assertOk();
    }

    public function test_exporta_em_csv(): void
    {
        $falta = $this->artigo(0);

        $csv = $this->get(route('invoicing.relatorio.stock-levels.csv'))->assertOk()->streamedContent();

        $this->assertStringContainsString('Situação', $csv);
        $this->assertStringContainsString($falta->name, $csv);
    }
}
