<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
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
 *     voltava a correr, e os cinco gráficos ficavam em branco.
 *
 * O PAINEL É HOJE REACT (`resources/js/ecras/facturacao/Painel.tsx`) e os
 * números saem todos do serviço `PainelDaFacturacao`, pela API. A primeira
 * regra continua a ser a mesma e prova-se contra a API. A segunda deixou de
 * poder acontecer: o gráfico é SVG desenhado pelo próprio componente, que é
 * dono do seu ciclo — e é isso que se guarda aqui, para ninguém voltar a
 * pendurar o desenho num script da página.
 */
class PainelDaFacturacaoTest extends TenantTestCase
{
    private const ROTA = '/api/v1/invoicing/react/painel';

    protected function setUp(): void
    {
        parent::setUp();

        // Duas guardas à porta do painel: o módulo do grupo de rotas e a
        // permissão da própria rota. Sem qualquer uma delas vem 403 e não
        // há números para comparar.
        $this->comModulo('invoicing')->comPermissoes('invoicing.dashboard.view');
    }

    /** Os dados que o painel põe no ecrã. */
    private function painel(): array
    {
        return $this->getJson(self::ROTA)->assertOk()->json();
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

        // A página propriamente dita tem de abrir: foi assim que se viu que
        // uma factura sem data de vencimento a deitava abaixo com erro 500.
        $this->get('/invoicing/dashboard')->assertOk();

        $stats = $this->painel()['stats'];

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

        $stats = $this->painel()['stats'];

        $this->assertEqualsWithDelta(1500, (float) $stats['total_overdue'], 0.01,
            'vencido é o que passou da data e não está pago — 500 + 1000');
    }

    /** @test */
    public function a_lista_das_pendentes_usa_a_mesma_regra_do_cartao(): void
    {
        $this->factura('sent', 1000, 0, now()->addDays(5)->toDateString());

        $lista = $this->painel()['por_cobrar'];

        $this->assertCount(1, $lista,
            'se o cartão conta uma factura, a lista tem de a mostrar — senão o número e a lista não batem certo');
        $this->assertEqualsWithDelta(1000, (float) $lista[0]['saldo'], 0.01, 'a lista mostra o SALDO, não o total');
    }

    /**
     * O DESENHO DOS GRÁFICOS É DO PRÓPRIO ECRÃ.
     *
     * Em Blade o desenho teve de sair da página para um ficheiro carregado
     * pelo layout, com os ouvintes presos ao `document`: era a única forma de
     * sobreviver a uma navegação do Livewire, em que a página não volta a
     * correr os seus `<script>`. Em React o componente é dono do seu ciclo e o
     * problema não existe — desde que o desenho continue lá dentro, em SVG, e
     * ninguém volte a pendurá-lo num script global ou numa biblioteca de fora.
     *
     * @test
     */
    public function o_desenho_dos_graficos_vive_dentro_do_ecra(): void
    {
        $painel = file_get_contents(resource_path('js/ecras/facturacao/Painel.tsx'));

        $this->assertStringContainsString('GraficoDeBarras', $painel,
            'o gráfico é um componente do ecrã, não um script da página');
        $this->assertStringNotContainsString('new Chart(', $painel);
        $this->assertStringNotContainsString('addEventListener(', $painel,
            'nada de ouvintes globais: o ciclo é do componente');

        $grafico = file_get_contents(resource_path('js/ui/GraficoDeBarras.tsx'));

        $this->assertStringContainsString('role="img"', $grafico,
            'as barras são marcação da própria página, sem biblioteca nenhuma');
        $this->assertStringNotContainsString('getContext(', $grafico,
            'desenhar num canvas é imperativo: obriga a criar, destruir e apanhar o instante certo');

        // E OS DADOS VÊM DO SERVIDOR, não de números escritos no ecrã: trocar
        // de período tem de mudar mesmo a linha do gráfico.
        $this->assertStringContainsString("from '@/api/painel'", $painel);
        $this->assertStringContainsString('por_mes', $painel);
    }

    /**
     * O QUADRO "ESTADO DAS FATURAS" É DO MÊS, E DESTE ANO.
     *
     * Contava com `whereMonth` sem `whereYear`: em Setembro de 2026 somava
     * também Setembro de 2025 e de todos os anos anteriores. E chamava
     * "pendente" só ao estado `pending`, deixando de fora as `sent`.
     *
     * @test
     */
    public function o_quadro_do_estado_conta_o_mes_deste_ano_e_pelo_saldo(): void
    {
        // Deste mês.
        $this->factura('sent', 1000, 0, now()->addDays(5)->toDateString());       // por cobrar
        $this->factura('sent', 800, 300, now()->addDays(5)->toDateString());      // parte paga
        $this->factura('overdue', 600, 0, now()->subDays(5)->toDateString());     // vencida
        $this->factura('paid', 400, 400, now()->subDays(5)->toDateString());      // paga

        // Mesmo mês, ANO PASSADO: não pode entrar em caixa nenhuma.
        $this->factura('sent', 9999, 0, now()->addDays(5)->toDateString())
            ->forceFill(['invoice_date' => now()->subYear()])->save();

        $estado = $this->painel()['estado_das_facturas'];

        $this->assertSame(1, $estado['pending'],
            'uma factura enviada e dentro do prazo é uma pendente — e a do ano passado não conta');
        $this->assertSame(1, $estado['partially_paid'], 'a que tem parte paga conta uma vez');
        $this->assertSame(1, $estado['overdue'], 'vencida é a que passou da data');
        $this->assertSame(1, $estado['paid'], 'paga é a que não tem saldo');
    }

    /**
     * O ANO É DOZE PONTOS, NUNCA TREZENTOS E SESSENTA E CINCO.
     *
     * A consulta agrupava sempre por dia, fosse qual fosse o período. Numa
     * empresa com movimento, "Este Ano" dava uma linha com uma marca por cada
     * dia do ano e os rótulos por cima uns dos outros. O painel em React não
     * tem sequer selector: o ano vem sempre mês a mês, do servidor.
     *
     * @test
     */
    public function o_grafico_do_ano_agrupa_por_mes(): void
    {
        // Três dias do mesmo mês: uma só coluna no gráfico do ano.
        foreach ([1, 2, 3] as $dia) {
            $this->factura('sent', 100)->forceFill([
                'invoice_date' => now()->startOfYear()->addMonths(2)->addDays($dia),
            ])->save();
        }

        $ano = $this->painel()['por_mes'];

        $this->assertCount(12, $ano, 'os doze meses estão sempre lá, mesmo os que não tiveram nada');

        $marco = $ano[2];

        $this->assertEqualsWithDelta(300, (float) $marco['valor'], 0.01,
            'o ponto do mês soma o que se facturou nesse mês');

        // O rótulo do eixo vem do servidor — é ele que sabe se a linha é um dia
        // ou um mês, e é assim que o painel escreve "Mar" sempre da mesma
        // maneira, esteja em que ecrã estiver.
        $this->assertArrayHasKey('rotulo', $marco);
        $this->assertStringNotContainsString('/', $marco['rotulo'],
            'por mês o rótulo é o nome do mês, não uma data com barras');
    }

    /**
     * OS GRÁFICOS SEGUEM A MESMA REGRA DOS CARTÕES.
     *
     * Os cartões já filtravam por autor; os gráficos da mesma página liam a
     * empresa inteira. Quem só vê o que emitiu tinha, lado a lado, um cartão
     * com os seus números e um gráfico com os de todos — ao mesmo tempo uma
     * fuga e a razão por que a página se contradizia a si própria.
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

        // Sem `invoicing.documents.all`, só vejo o que emiti.
        $meu = array_sum(array_column($this->painel()['por_mes'], 'valor'));

        $this->assertEqualsWithDelta(1000, $meu, 0.01,
            'o gráfico tem de contar o mesmo que o cartão: só o que este utilizador emitiu');

        // Com a permissão de ver tudo, o gráfico abre.
        $this->comPermissoes('invoicing.documents.all');

        $tudo = array_sum(array_column($this->painel()['por_mes'], 'valor'));

        $this->assertEqualsWithDelta(3000, $tudo, 0.01,
            'quem pode ver os documentos todos vê também o gráfico todo');
    }

    /** @test */
    public function as_bibliotecas_sao_da_casa_e_nao_de_um_cdn(): void
    {
        foreach ([
            resource_path('js/ecras/facturacao/Painel.tsx'),
            resource_path('js/ui/GraficoDeBarras.tsx'),
        ] as $f) {
            $fonte = file_get_contents($f);

            foreach (['cdnjs.cloudflare.com', 'cdn.jsdelivr.net', 'unpkg.com'] as $cdn) {
                $this->assertStringNotContainsString($cdn, $fonte,
                    basename($f) . ": um CDN bloqueado deixava o painel sem gráficos e sem aviso");
            }
        }
    }
}
