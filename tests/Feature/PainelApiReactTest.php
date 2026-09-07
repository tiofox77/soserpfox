<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Services\Invoicing\PainelDaFacturacao;
use Tests\TenantTestCase;

/**
 * O painel em React diz o que o serviço diz — e não uma segunda versão.
 *
 * É esta a razão de o `PainelDaFacturacao` existir. Enquanto as contas
 * estivessem escritas dentro do componente do ecrã, quem servisse o outro
 * ecrã teria de as repetir — e a partir do primeiro ajuste os dois davam
 * números diferentes sem ninguém saber qual acreditar. Foi assim que o painel
 * conviveu, durante a migração, com o de Blade. O de Blade já não existe; a
 * fonte única fica, e é ela que estes ensaios comparam com a resposta da API.
 */
class PainelApiReactTest extends TenantTestCase
{
    private const ROTA = '/api/v1/invoicing/react/painel';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function factura(array $por = []): SalesInvoice
    {
        return SalesInvoice::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT TESTE/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'total' => 1000,
            'paid_amount' => 0,
            'created_by' => $this->user->id,
        ], $por));
    }

    /** @test */
    public function sem_permissao_nao_ha_painel(): void
    {
        $this->getJson(self::ROTA)->assertForbidden();
    }

    /** @test */
    public function a_api_diz_exactamente_o_que_o_servico_conta(): void
    {
        $this->comPermissoes('invoicing.dashboard.view');

        $this->factura(['total' => 5000, 'paid_amount' => 1000]);
        $this->factura(['total' => 3000, 'status' => 'paid', 'paid_amount' => 3000]);
        $this->factura(['total' => 2000, 'due_date' => now()->subDays(10)->toDateString()]);

        $daApi = $this->getJson(self::ROTA)->assertOk()->json();

        $doServico = app(PainelDaFacturacao::class)->numeros((int) $this->tenant->id);

        foreach (['total_invoiced', 'total_invoiced_previous', 'total_pending', 'total_overdue'] as $numero) {
            $this->assertEqualsWithDelta(
                (float) $doServico['stats'][$numero],
                (float) $daApi['stats'][$numero],
                0.01,
                "o painel em React e o serviço discordam em {$numero}"
            );
        }

        $this->assertSame(
            $doServico['invoiceStatus'],
            $daApi['estado_das_facturas'],
            'as quatro caixas são as do serviço, sem contas pelo caminho'
        );
    }

    /**
     * CONTA-SE PELO SALDO, NÃO PELO NOME DO ESTADO.
     *
     * Uma factura `sent` com saldo está por cobrar. Nomear os estados que
     * contam foi o que partiu o painel de origem.
     *
     * @test
     */
    public function uma_factura_sent_com_saldo_entra_no_por_cobrar(): void
    {
        $this->comPermissoes('invoicing.dashboard.view');

        $this->factura(['total' => 7500, 'status' => 'sent', 'paid_amount' => 0]);

        $d = $this->getJson(self::ROTA)->assertOk()->json();

        $this->assertEqualsWithDelta(7500, $d['stats']['total_pending'], 0.01);
    }

    /**
     * OS DOZE MESES ESTÃO SEMPRE LÁ.
     *
     * Um gráfico que salte de Março para Junho porque Abril não tem facturas
     * mente sobre a forma do ano.
     *
     * @test
     */
    public function o_ano_traz_sempre_doze_meses(): void
    {
        $this->comPermissoes('invoicing.dashboard.view');

        $d = $this->getJson(self::ROTA)->assertOk()->json();

        $this->assertCount(12, $d['por_mes']);
        $this->assertCount(12, $d['por_mes_ano_passado']);
        $this->assertArrayHasKey('rotulo', $d['por_mes'][0]);
    }

    /**
     * O PERÍODO ESCOLHIDO MANDA NOS NÚMEROS.
     *
     * O painel de sempre tinha um selector — semana, mês, ano — e a migração
     * para React perdeu-o: o ecrã mostrava sempre o mesmo. Um documento fora
     * do período não pode entrar nas contas do período, e o rótulo que o
     * título usa tem de ser o do mesmo período que os cartões contaram.
     *
     * @test
     */
    public function o_periodo_escolhido_manda_nos_cartoes_e_no_titulo(): void
    {
        $this->comPermissoes('invoicing.dashboard.view');

        // Um dia deste ano que não é deste mês. Em Janeiro salta-se para Junho,
        // que continua a ser este ano — um período fixo tornaria este ensaio
        // verdadeiro onze meses por ano.
        $foraDoMes = now()->month === 1
            ? now()->copy()->startOfYear()->addMonths(5)
            : now()->copy()->startOfYear();

        $this->factura(['total' => 1000]);
        $this->factura(['total' => 4000, 'invoice_date' => $foraDoMes->toDateString()]);

        $mes = $this->getJson(self::ROTA . '?periodo=month')->assertOk()->json();
        $ano = $this->getJson(self::ROTA . '?periodo=year')->assertOk()->json();

        $this->assertEqualsWithDelta(1000, $mes['stats']['total_invoiced'], 0.01,
            'a factura de outro mês não entra nas contas do mês');
        $this->assertEqualsWithDelta(5000, $ano['stats']['total_invoiced'], 0.01,
            'no ano entram as duas');

        // As contagens seguem o mesmo intervalo dos valores: os cartões e as
        // caixas do ecrã não podem estar a falar de períodos diferentes.
        $this->assertSame(1, $mes['documentos']['invoices']);
        $this->assertSame(2, $ano['documentos']['invoices']);

        // E o título sai do mesmo sítio: o rótulo vem já traduzido do servidor.
        $this->assertSame('month', $mes['periodo']['valor']);
        $this->assertSame(__('Este mês'), $mes['periodo']['rotulo']);
        $this->assertSame(__('Este ano'), $ano['periodo']['rotulo']);

        // A lista de atalhos é uma só, e vem de lá — o ecrã não guarda outra.
        $this->assertSame(
            \App\Services\Invoicing\PainelDaFacturacao::PERIODOS,
            array_column($mes['periodo']['opcoes'], 'valor')
        );
    }

    /**
     * O GRÁFICO SEGUE O PERÍODO — e agrupa-se de forma legível.
     *
     * Agrupar sempre por dia dava, numa empresa com movimento, uma linha com
     * um ponto por cada dia do ano: ilegível, e com os rótulos por cima uns
     * dos outros.
     *
     * @test
     */
    public function o_grafico_segue_o_periodo_pedido(): void
    {
        $this->comPermissoes('invoicing.dashboard.view');

        $this->assertCount(12, $this->getJson(self::ROTA . '?periodo=year')->assertOk()->json('serie'),
            'o ano são doze meses');

        $semana = $this->getJson(self::ROTA . '?periodo=week')->assertOk()->json('serie');

        $this->assertCount(7, $semana, 'a semana são sete dias, mesmo os que não tiveram nada');
        $this->assertStringContainsString('/', $semana[0]['rotulo'], 'por dia o rótulo é a data');
    }

    /** Um período que não existe cai no de omissão, e não em erro. @test */
    public function um_periodo_desconhecido_cai_no_de_omissao(): void
    {
        $this->comPermissoes('invoicing.dashboard.view');

        $this->getJson(self::ROTA . '?periodo=quando-me-apetecer')
            ->assertOk()
            ->assertJsonPath('periodo.valor', \App\Services\Invoicing\PainelDaFacturacao::PERIODO_OMISSAO);
    }

    /** A assinatura fiscal não viaja nas listas do painel. @test */
    public function a_lista_do_painel_nao_publica_a_assinatura(): void
    {
        $this->comPermissoes('invoicing.dashboard.view');

        $this->factura()->forceFill(['jws_signature' => 'assinatura-secreta'])->saveQuietly();

        $corpo = $this->getJson(self::ROTA)->assertOk()->content();

        $this->assertStringNotContainsString('assinatura-secreta', $corpo);
        $this->assertStringNotContainsString('jws_signature', $corpo);
    }

    /**
     * O CONTROLADOR NÃO TEM CONTAS DENTRO — só formato.
     *
     * Era o componente Livewire que não podia ter uma segunda cópia das
     * contas; hoje é o controlador da API que fica com a mesma proibição. Uma
     * soma escrita aqui divergiria do serviço ao primeiro ajuste, e os
     * relatórios que bebem do mesmo sítio passavam a discordar do painel.
     *
     * @test
     */
    public function o_controlador_bebe_do_servico_e_nao_faz_contas(): void
    {
        $fonte = file_get_contents(app_path('Http/Controllers/Api/Invoicing/PainelApiController.php'));

        $this->assertStringContainsString(PainelDaFacturacao::class, $fonte,
            'o painel tem de beber do serviço');

        foreach (['private const LIQUIDADAS', 'whereNotIn(', 'sum(', 'selectRaw('] as $conta) {
            $this->assertStringNotContainsString($conta, $fonte,
                "as contas vivem no serviço; «{$conta}» aqui é uma segunda cópia à espera de divergir");
        }
    }
}
