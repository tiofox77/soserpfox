<?php

namespace Tests\Feature\Invoicing;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Invoicing\Analytics\GraficosDeFacturacao;
use Spatie\Permission\Models\Permission;
use Tests\TenantTestCase;

/**
 * Os números por trás dos gráficos de facturação.
 *
 * O que estes testes protegem, acima de tudo: uma factura anulada nunca pode
 * entrar num gráfico. Uma anulação existe para desfazer o documento, e somá-la
 * faria a linha de vendas subir com dinheiro que ninguém recebeu.
 */
class GraficosDeFacturacaoTest extends TenantTestCase
{
    /** O mapa em gráficos é o relatório `charts` do catálogo. */
    private const RAIZ = '/api/v1/invoicing/react/relatorios/charts';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user->givePermissionTo(
            Permission::findOrCreate('invoicing.reports.view', 'web')
        );
    }

    private function ligarModulo(): void
    {
        $modulo = \App\Models\Module::firstOrCreate(
            ['slug' => 'invoicing'],
            ['name' => 'Faturação', 'is_active' => true]
        );

        $this->tenant->modules()->syncWithoutDetaching([
            $modulo->id => ['is_active' => true, 'trial_ends_at' => null],
        ]);
    }

    private function venda(string $data, float $total, string $estado = 'paid', ?string $vencimento = null): SalesInvoice
    {
        return SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'invoice_number' => 'FT-' . uniqid(),
            'client_id'      => $this->cliente->id,
            'invoice_date'   => $data,
            'due_date'       => $vencimento,
            'status'         => $estado,
            'subtotal'       => $total,
            'tax_amount'     => round($total * 0.14, 2),
            'total'          => $total,
            'created_by'     => $this->user->id,
        ]);
    }

    private function compra(string $data, float $total, string $estado = 'pending'): PurchaseInvoice
    {
        $fornecedor = Supplier::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Fornecedor Teste'],
            ['is_active' => true]
        );

        return PurchaseInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'supplier_id'    => $fornecedor->id,
            'invoice_number' => 'FC-' . uniqid(),
            'invoice_date'   => $data,
            'status'         => $estado,
            'subtotal'       => $total,
            'tax_amount'     => round($total * 0.14, 2),
            'total'          => $total,
            'created_by'     => $this->user->id,
        ]);
    }

    private function analise(string $de = '2026-01-01', string $ate = '2026-12-31'): GraficosDeFacturacao
    {
        return GraficosDeFacturacao::para($this->tenant->id, $de, $ate);
    }

    // ── A regra que atravessa tudo ───────────────────────────────────────

    public function test_factura_anulada_nunca_entra_em_gratico_nenhum(): void
    {
        $this->venda('2026-03-10', 100000);
        $this->venda('2026-03-11', 999999, 'cancelled');

        $a = $this->analise();

        $this->assertEqualsWithDelta(100000, $a->resumo()['vendas'], 0.01);
        $this->assertEqualsWithDelta(100000, array_sum($a->evolucaoDeVendas()['valores']), 0.01);
        $this->assertEqualsWithDelta(100000, array_sum($a->vendasContraCompras()['vendas']), 0.01);
        $this->assertEqualsWithDelta(100000, array_sum($a->estadoDasFacturas()['valores']), 0.01);
    }

    public function test_documentos_de_outra_empresa_nao_entram(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Vizinha', 'slug' => 'viz-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        SalesInvoice::withoutEvents(fn () => SalesInvoice::create([
            'tenant_id' => $outra->id, 'invoice_number' => 'FT-VIZ-' . uniqid(),
            'client_id' => $this->cliente->id, 'invoice_date' => '2026-03-10',
            'status' => 'paid', 'subtotal' => 500000, 'tax_amount' => 70000,
            'total' => 500000, 'created_by' => $this->user->id,
        ]));

        $this->venda('2026-03-10', 100000);

        $this->assertEqualsWithDelta(100000, $this->analise()->resumo()['vendas'], 0.01);
    }

    // ── Evolução ─────────────────────────────────────────────────────────

    /**
     * Um período sem vendas tem de continuar a ser um ponto no eixo. Sem isso,
     * a linha ligava Janeiro a Março como se Fevereiro não tivesse existido.
     */
    public function test_periodos_sem_vendas_ficam_no_eixo_a_zero(): void
    {
        $this->venda('2026-01-15', 50000);
        $this->venda('2026-03-15', 70000);

        $e = $this->analise('2026-01-01', '2026-03-31')->evolucaoDeVendas();

        $this->assertTrue($e['porMes']);
        $this->assertSame(['01/2026', '02/2026', '03/2026'], $e['rotulos']);
        $this->assertSame([50000.0, 0.0, 70000.0], $e['valores']);
    }

    /** Períodos curtos vão por dia; longos vão por mês, senão é uma mancha. */
    public function test_periodo_curto_agrupa_por_dia(): void
    {
        $this->venda('2026-03-10', 50000);

        $e = $this->analise('2026-03-01', '2026-03-31')->evolucaoDeVendas();

        $this->assertFalse($e['porMes']);
        $this->assertCount(31, $e['rotulos']);
        $this->assertEqualsWithDelta(50000, array_sum($e['valores']), 0.01);
    }

    // ── Estados ──────────────────────────────────────────────────────────

    /**
     * "Vencida" não é um estado guardado: é uma pendente cuja data já passou.
     * Contá-la à parte é o que faz este gráfico servir para alguma coisa.
     */
    public function test_pendente_com_prazo_passado_conta_como_vencida(): void
    {
        $this->venda('2026-03-01', 30000, 'pending', now()->subDays(10)->format('Y-m-d'));
        $this->venda('2026-03-02', 20000, 'pending', now()->addDays(10)->format('Y-m-d'));
        $this->venda('2026-03-03', 10000, 'paid');

        $e = $this->analise()->estadoDasFacturas();
        $porRotulo = array_combine($e['rotulos'], $e['valores']);

        $this->assertEqualsWithDelta(30000, $porRotulo['Vencidas'], 0.01);
        $this->assertEqualsWithDelta(20000, $porRotulo['Por cobrar'], 0.01);
        $this->assertEqualsWithDelta(10000, $porRotulo['Pagas'], 0.01);
    }

    /** O enum tem `partial` e `partially_paid`: tratar só um perdia metade. */
    public function test_os_dois_estados_de_pagamento_parcial_contam(): void
    {
        $this->venda('2026-03-01', 40000, 'partial');
        $this->venda('2026-03-02', 60000, 'partially_paid');

        $e = $this->analise()->estadoDasFacturas();
        $porRotulo = array_combine($e['rotulos'], $e['valores']);

        $this->assertEqualsWithDelta(100000, $porRotulo['Parcialmente pagas'], 0.01);
    }

    // ── Rankings ─────────────────────────────────────────────────────────

    public function test_top_produtos_ordena_por_valor_vendido(): void
    {
        $factura = $this->venda('2026-03-10', 100000);

        foreach ([['Rato', 5000], ['Portátil', 80000], ['Teclado', 15000]] as [$nome, $valor]) {
            $artigo = Product::create([
                'tenant_id' => $this->tenant->id, 'code' => 'P-' . uniqid(),
                'name' => $nome, 'price' => $valor, 'is_active' => true,
            ]);

            SalesInvoiceItem::create([
                'sales_invoice_id' => $factura->id, 'product_id' => $artigo->id,
                'product_name' => $nome, 'quantity' => 1, 'unit_price' => $valor,
                'subtotal' => $valor, 'total' => $valor,
            ]);
        }

        $top = $this->analise()->topProdutos();

        $this->assertSame(['Portátil', 'Teclado', 'Rato'], $top['rotulos']);
        $this->assertSame([80000.0, 15000.0, 5000.0], $top['valores']);
    }

    /** Nomes longos são cortados: rebentavam a legenda e o cartão. */
    public function test_nomes_muito_longos_sao_cortados(): void
    {
        $factura = $this->venda('2026-03-10', 10000);
        $nome = 'Computador portátil de alto desempenho com garantia alargada';

        $artigo = Product::create([
            'tenant_id' => $this->tenant->id, 'code' => 'P-' . uniqid(),
            'name' => $nome, 'price' => 10000, 'is_active' => true,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $factura->id, 'product_id' => $artigo->id,
            'product_name' => $nome, 'quantity' => 1, 'unit_price' => 10000,
            'subtotal' => 10000, 'total' => 10000,
        ]);

        $rotulo = $this->analise()->topProdutos()['rotulos'][0];

        $this->assertLessThanOrEqual(28, mb_strlen($rotulo));
        $this->assertStringEndsWith('…', $rotulo);
    }

    // ── Cobrança ─────────────────────────────────────────────────────────

    public function test_facturado_contra_recebido_usa_a_data_do_recibo(): void
    {
        $this->venda('2026-03-10', 100000, 'partially_paid');

        Receipt::create([
            'tenant_id'      => $this->tenant->id,
            'receipt_number' => 'RC-' . uniqid(),
            'client_id'      => $this->cliente->id,
            'payment_date'   => '2026-04-05',   // recebido NOUTRO mês
            'payment_method' => 'transferencia',
            'amount_paid'    => 60000,
            'status'         => 'issued',
        ]);

        $c = $this->analise('2026-03-01', '2026-04-30')->facturadoContraRecebido();

        $this->assertSame(['03/2026', '04/2026'], $c['rotulos']);
        $this->assertSame([100000.0, 0.0], $c['facturado']);
        $this->assertSame([0.0, 60000.0], $c['recebido']);
    }

    // ── Compras e IVA ────────────────────────────────────────────────────

    public function test_vendas_contra_compras(): void
    {
        $this->venda('2026-03-10', 100000);
        $this->compra('2026-03-12', 40000);

        $vc = $this->analise()->vendasContraCompras();

        $this->assertEqualsWithDelta(100000, array_sum($vc['vendas']), 0.01);
        $this->assertEqualsWithDelta(40000, array_sum($vc['compras']), 0.01);
    }

    public function test_iva_liquidado_contra_suportado(): void
    {
        $this->venda('2026-03-10', 100000);   // 14 000 liquidado
        $this->compra('2026-03-12', 50000);   // 7 000 suportado

        $iva = $this->analise()->ivaLiquidadoContraSuportado();

        $this->assertEqualsWithDelta(14000, array_sum($iva['liquidado']), 0.01);
        $this->assertEqualsWithDelta(7000, array_sum($iva['suportado']), 0.01);
    }

    /** A semana sai de segunda a domingo, não de domingo a sábado. */
    public function test_dias_da_semana_comecam_na_segunda(): void
    {
        // 2026-03-09 é uma segunda-feira.
        $this->venda('2026-03-09', 25000);

        $s = $this->analise()->vendasPorDiaDaSemana();

        $this->assertSame('Segunda', $s['rotulos'][0]);
        $this->assertSame('Domingo', $s['rotulos'][6]);
        $this->assertEqualsWithDelta(25000, $s['valores'][0], 0.01);
    }

    public function test_ticket_medio_nao_divide_por_zero(): void
    {
        $r = $this->analise('2020-01-01', '2020-12-31')->resumo();

        $this->assertSame(0, $r['documentos']);
        $this->assertEqualsWithDelta(0, $r['ticket'], 0.01);
    }

    // ── O ecrã ───────────────────────────────────────────────────────────

    /**
     * O ecrã abre E os gráficos são da casa.
     *
     * Era o Chart.js servido de `/vendor/js/chart.min.js`, e o que este ensaio
     * guardava era o caminho do ficheiro: errado, dava quadrados brancos no
     * browser sem erro nenhum a dizer porquê. O ecrã em React desenha as
     * barras com a marcação da própria página e não carrega biblioteca
     * nenhuma — a mesma regra, sem o ficheiro: nada de CDN, nada de `<canvas>`
     * por preencher.
     */
    public function test_o_ecra_abre_por_http_e_os_graficos_sao_da_casa(): void
    {
        $this->ligarModulo();

        $this->get(route('invoicing.reports.charts'))->assertOk();

        foreach ([
            resource_path('js/ecras/facturacao/Graficos.tsx'),
            resource_path('js/ui/GraficoDeBarras.tsx'),
        ] as $ficheiro) {
            $fonte = file_get_contents($ficheiro);

            $this->assertStringNotContainsString('cdn.jsdelivr.net', $fonte, basename($ficheiro));
            $this->assertStringNotContainsString('cdnjs.cloudflare.com', $fonte, basename($ficheiro));
            $this->assertStringNotContainsString('new Chart(', $fonte, basename($ficheiro));
            $this->assertStringNotContainsString('getContext(', $fonte, basename($ficheiro));
        }

        $this->assertStringContainsString('role="img"', file_get_contents(resource_path('js/ui/GraficoDeBarras.tsx')),
            'o gráfico é do próprio ecrã, e quem não o vê tem o mesmo nomeado em texto');
    }

    /**
     * Os atalhos de período do ecrã — hoje um parâmetro da API, resolvido
     * pelo `Periodo` para todos os mapas de uma vez.
     */
    public function test_atalhos_de_periodo(): void
    {
        $this->ligarModulo();
        $hoje = now();

        $this->getJson(self::RAIZ . '?period=month')->assertOk()
            ->assertJsonPath('dados.intervalo.de', $hoje->copy()->startOfMonth()->format('Y-m-d'))
            ->assertJsonPath('dados.intervalo.ate', $hoje->copy()->endOfMonth()->format('Y-m-d'));

        $this->getJson(self::RAIZ . '?period=last_year')->assertOk()
            ->assertJsonPath('dados.intervalo.de', $hoje->copy()->subYear()->startOfYear()->format('Y-m-d'))
            ->assertJsonPath('dados.intervalo.ate', $hoje->copy()->subYear()->endOfYear()->format('Y-m-d'));

        // E o ecrã tem mesmo o botão: os atalhos viajam com o relatório.
        $atalhos = array_column($this->getJson(self::RAIZ)->assertOk()->json('atalhos'), 'valor');
        $this->assertContains('month', $atalhos);
        $this->assertContains('last_year', $atalhos);
    }

    /** Fim antes do início devolvia gráficos vazios e parecia avaria. */
    public function test_datas_ao_contrario_sao_trocadas(): void
    {
        $this->ligarModulo();

        $this->getJson(self::RAIZ . '?dateFrom=2026-12-31&dateTo=2026-01-01')->assertOk()
            ->assertJsonPath('dados.intervalo.de', '2026-01-01')
            ->assertJsonPath('dados.intervalo.ate', '2026-12-31');
    }
}
