<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Livewire\Invoicing\InvoicingDashboard;
use App\Models\Invoicing\SalesInvoice;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O painel da facturação: os cartões e os gráficos.
 *
 * A queixa foi «o card do dashboard e gráficos do módulo facturação parece que
 * tem bugs». Eram dois defeitos independentes, e os dois se confirmaram com
 * medições nesta base:
 *
 *   · os cartões de PENDENTES e VENCIDAS nomeavam os estados que contam
 *     (`pending`, `partially_paid`) em vez dos que NÃO contam. Uma factura
 *     `sent` ou `overdue` desaparecia: o painel dizia 76 mil por cobrar quando
 *     havia 15 milhões, e dizia ZERO vencidas quando havia 14,5 milhões;
 *
 *   · os gráficos viviam num `<script>` da própria página. Chegando ao painel
 *     pela barra lateral — `wire:navigate`, sem recarregar — esse script não
 *     volta a correr, e os cinco gráficos ficavam em branco.
 */
class PainelDaFacturacaoTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Duas guardas à porta do painel: o módulo do grupo de rotas e a
        // permissão da própria rota. Sem qualquer uma delas vem 403 e não
        // há números para comparar.
        $this->comModulo('invoicing')->comPermissoes('invoicing.dashboard.view');
    }

    /** Os dados que o painel poe no ecra. */
    private function painel(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(InvoicingDashboard::class);
    }

    private function factura(string $estado, float $total, float $pago = 0, ?string $vencimento = null): SalesInvoice
    {
        $cliente = Client::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'nif' => '5000000' . random_int(100, 999)],
            ['name' => 'Cliente de Ensaio']
        );

        $f = new SalesInvoice([
            'client_id'      => $cliente->id,
            'invoice_number' => 'FT TESTE/' . random_int(100000, 999999),
            'invoice_date'   => now(),
            'due_date'       => $vencimento,
            'status'         => $estado,
            'subtotal'       => $total,
            'total'          => $total,
            'paid_amount'    => $pago,
            'created_by'     => $this->user->id,
        ]);

        $f->tenant_id = $this->tenant->id;
        $f->save();

        return $f;
    }

    /**
     * UMA FACTURA ENVIADA CONTINUA POR COBRAR.
     *
     * Era o buraco: o cartão só conhecia `pending` e `partially_paid`.
     *
     * @test
     */
    public function o_cartao_de_pendentes_conta_tudo_o_que_nao_esta_liquidado(): void
    {
        $this->factura('sent', 1000);
        $this->factura('overdue', 500);
        $this->factura('pending', 300);
        $this->factura('partially_paid', 400, 100);

        // Estas não são dívida.
        $this->factura('paid', 9999, 9999);
        $this->factura('cancelled', 8888);
        $this->factura('credited', 7777);

        // A pagina propriamente dita tem de abrir: foi assim que se viu que
        // uma factura sem data de vencimento a deitava abaixo com erro 500.
        $this->get('/invoicing/dashboard')->assertOk();

        $stats = $this->painel()->viewData('stats');

        // 1000 + 500 + 300 + (400 − 100) = 2100
        $this->assertEqualsWithDelta(2100, (float) $stats['total_pending'], 0.01,
            'uma factura enviada ou vencida continua por cobrar, e a parcial entra pelo saldo');
    }

    /**
     * O QUE ESTÁ VENCIDO É O QUE PASSOU DA DATA, seja qual for o estado.
     *
     * O cartão exigia `status = pending`, e as facturas marcadas `overdue` —
     * literalmente vencidas — não entravam.
     *
     * @test
     */
    public function o_cartao_de_vencidas_conta_pela_data_e_nao_pelo_nome_do_estado(): void
    {
        $this->factura('overdue', 500, 0, now()->subDays(30)->toDateString());
        $this->factura('sent', 1000, 0, now()->subDays(10)->toDateString());
        $this->factura('sent', 2000, 0, now()->addDays(10)->toDateString());
        $this->factura('paid', 9999, 9999, now()->subDays(60)->toDateString());

        $stats = $this->painel()->viewData('stats');

        $this->assertEqualsWithDelta(1500, (float) $stats['total_overdue'], 0.01,
            'vencido é o que passou da data e não está pago — 500 + 1000');
    }

    /** @test */
    public function a_lista_das_pendentes_usa_a_mesma_regra_do_cartao(): void
    {
        $this->factura('sent', 1000, 0, now()->addDays(5)->toDateString());

        $lista = $this->painel()->viewData('pendingInvoices');

        $this->assertCount(1, $lista,
            'se o cartão conta uma factura, a lista tem de a mostrar — senão o número e a lista não batem certo');
    }

    /**
     * OS GRÁFICOS DESENHAM-SE MESMO QUANDO SE CHEGA PELA BARRA LATERAL.
     *
     * O desenho saiu do Blade para um ficheiro carregado pelo layout, com os
     * ouvintes presos ao `document`: é a única forma que sobrevive a uma
     * navegação do Livewire. Os dados viajam em nós JSON que o Livewire volta a
     * escrever, para trocar de período mudar mesmo a linha do gráfico.
     *
     * @test
     */
    public function o_desenho_dos_graficos_sobrevive_a_navegacao(): void
    {
        $modulo = public_path('js/painel-facturacao.js');
        $this->assertFileExists($modulo);

        $js = file_get_contents($modulo);

        $this->assertStringContainsString("addEventListener('livewire:navigated'", $js,
            'pela barra lateral não há recarregamento: é este o momento de desenhar');
        $this->assertStringContainsString("morph.updated", $js,
            'trocar de período troca o HTML: redesenhar a seguir');
        $this->assertStringContainsString('dadosVendas', $js,
            'os dados vêm do DOM, senão ficam presos ao primeiro desenho');

        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString('/js/painel-facturacao.js', $layout,
            'tem de ser carregado pelo layout: um script na página não volta a correr');

        // E o Blade deixa de ter o desenho lá dentro.
        $painel = file_get_contents(resource_path('views/livewire/invoicing/invoicing-dashboard.blade.php'));
        $this->assertStringNotContainsString('new Chart(', $painel,
            'o desenho vive no ficheiro, não na página');
        $this->assertStringContainsString('id="dadosVendas"', $painel);
        $this->assertStringContainsString('id="dadosPainel"', $painel);
        $this->assertStringContainsString('id="textosPainel"', $painel);
    }

    /**
     * O QUADRO "ESTADO DAS FATURAS" E DO MES, E DESTE ANO.
     *
     * Contava com `whereMonth` sem `whereYear`: em Setembro de 2026 somava
     * tambem Setembro de 2025 e de todos os anos anteriores. E chamava
     * "pendente" so ao estado `pending`, deixando de fora as `sent`.
     *
     * @test
     */
    public function o_quadro_do_estado_conta_o_mes_deste_ano_e_pelo_saldo(): void
    {
        // Deste mes.
        $this->factura('sent', 1000, 0, now()->addDays(5)->toDateString());       // por cobrar
        $this->factura('sent', 800, 300, now()->addDays(5)->toDateString());      // parte paga
        $this->factura('overdue', 600, 0, now()->subDays(5)->toDateString());     // vencida
        $this->factura('paid', 400, 400, now()->subDays(5)->toDateString());      // paga

        // Mesmo mes, ANO PASSADO: nao pode entrar em caixa nenhuma.
        $this->factura('sent', 9999, 0, now()->addDays(5)->toDateString())
            ->forceFill(['invoice_date' => now()->subYear()])->save();

        $estado = $this->painel()->viewData('invoiceStatus');

        $this->assertSame(1, $estado['pending'],
            'uma factura enviada e dentro do prazo e uma pendente — e a do ano passado nao conta');
        $this->assertSame(1, $estado['partially_paid'], 'a que tem parte paga conta uma vez');
        $this->assertSame(1, $estado['overdue'], 'vencida e a que passou da data');
        $this->assertSame(1, $estado['paid'], 'paga e a que nao tem saldo');
    }

    /**
     * ESCOLHER O ANO NAO PODE DAR 365 PONTOS.
     *
     * A consulta agrupava sempre por dia, fosse qual fosse o periodo. Numa
     * empresa com movimento, "Este Ano" dava uma linha com uma marca por cada
     * dia do ano e os rotulos por cima uns dos outros.
     *
     * @test
     */
    public function o_grafico_do_ano_agrupa_por_mes(): void
    {
        // Tres dias do mesmo mes: uma so coluna no grafico do ano.
        foreach ([1, 2, 3] as $dia) {
            $this->factura('sent', 100)->forceFill([
                'invoice_date' => now()->startOfYear()->addMonths(2)->addDays($dia),
            ])->save();
        }

        $this->factura('sent', 500)->forceFill([
            'invoice_date' => now()->startOfYear()->addMonths(5),
        ])->save();

        $ano = $this->painel()->set('selectedPeriod', 'year')->get('chartData');

        $this->assertCount(2, $ano,
            'tres facturas do mesmo mes sao um ponto so quando se olha para o ano');

        $this->assertEqualsWithDelta(300, (float) $ano[0]['total'], 0.01,
            'o ponto do mes soma o que se facturou nesse mes');

        $this->assertArrayHasKey('rotulo', $ano[0],
            'o rotulo do eixo vem do servidor: e ele que sabe se a linha e um dia ou um mes');

        $this->assertStringNotContainsString('/', $ano[0]['rotulo'],
            'por mes o rotulo e o nome do mes, nao uma data com barras');

        // E por mes continua a ser dia a dia.
        $mes = $this->painel()->set('selectedPeriod', 'month')->get('chartData');

        foreach ($mes as $ponto) {
            $this->assertMatchesRegularExpression('#^\d{2}/\d{2}$#', $ponto['rotulo'],
                'dentro do mes cada ponto e um dia');
        }
    }
    /**
     * OS GRAFICOS SEGUEM A MESMA REGRA DOS CARTOES.
     *
     * Os cartoes ja filtravam por autor; os graficos da mesma pagina liam a
     * empresa inteira. Quem so ve o que emitiu tinha, lado a lado, um cartao
     * com os seus numeros e um grafico com os de todos — ao mesmo tempo uma
     * fuga e a razao por que a pagina se contradizia a si propria.
     *
     * @test
     */
    public function os_graficos_nao_mostram_o_que_o_cartao_esconde(): void
    {
        // Um colega da mesma empresa emite o dobro.
        $colega = \App\Models\User::create([
            'name'      => 'Colega',
            'email'     => uniqid() . '@exemplo.ao',
            'password'  => bcrypt('x'),
            'tenant_id' => $this->tenant->id,
        ]);
        $colega->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $this->factura('sent', 1000);                       // minha
        $this->factura('sent', 2000)->forceFill(['created_by' => $colega->id])->save();

        // Sem `invoicing.documents.all`, so vejo o que emiti.
        $painel = $this->painel()->get('graficos');
        $meu = array_sum($painel['estados']['valores'] ?? []);

        $this->assertEqualsWithDelta(1000, $meu, 0.01,
            'o grafico tem de contar o mesmo que o cartao: so o que este utilizador emitiu');

        // Com a permissao de ver tudo, o grafico abre.
        $this->comPermissoes('invoicing.documents.all');

        $tudo = array_sum($this->painel()->get('graficos')['estados']['valores'] ?? []);

        $this->assertEqualsWithDelta(3000, $tudo, 0.01,
            'quem pode ver os documentos todos ve tambem o grafico todo');
    }

    /** @test */
    public function as_bibliotecas_sao_da_casa_e_nao_de_um_cdn(): void
    {
        foreach ([
            public_path('js/painel-facturacao.js'),
            resource_path('views/livewire/invoicing/invoicing-dashboard.blade.php'),
        ] as $f) {
            $this->assertStringNotContainsString('cdnjs.cloudflare.com', file_get_contents($f),
                basename($f) . ': um CDN bloqueado deixava o painel sem gráficos e sem aviso');
        }

        $this->assertFileExists(public_path('vendor/js/chart.min.js'));
    }
}
