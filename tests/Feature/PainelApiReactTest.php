<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\InvoicingDashboard;
use App\Models\Invoicing\SalesInvoice;
use App\Services\Invoicing\PainelDaFacturacao;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * O painel em React e o painel em Blade dizem O MESMO.
 *
 * É esta a razão de o `PainelDaFacturacao` existir. Enquanto as contas
 * estivessem escritas dentro do componente Livewire, o controlador da API
 * teria de as repetir — e a partir do primeiro ajuste os dois ecrãs davam
 * números diferentes sem ninguém saber qual acreditar. Este ensaio compara-os
 * lado a lado.
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
    public function os_dois_paineis_dizem_o_mesmo(): void
    {
        $this->comPermissoes('invoicing.dashboard.view');

        $this->factura(['total' => 5000, 'paid_amount' => 1000]);
        $this->factura(['total' => 3000, 'status' => 'paid', 'paid_amount' => 3000]);
        $this->factura(['total' => 2000, 'due_date' => now()->subDays(10)->toDateString()]);

        $daApi = $this->getJson(self::ROTA)->assertOk()->json();

        $doBlade = Livewire::test(InvoicingDashboard::class)->viewData('stats');

        foreach (['total_invoiced', 'total_pending', 'total_overdue', 'year_invoiced'] as $numero) {
            $this->assertEqualsWithDelta(
                (float) $doBlade[$numero],
                (float) $daApi['stats'][$numero],
                0.01,
                "o painel em React e o em Blade discordam em {$numero}"
            );
        }

        $this->assertSame(
            Livewire::test(InvoicingDashboard::class)->viewData('invoiceStatus'),
            $daApi['estado_das_facturas'],
            'as quatro caixas têm de bater certo nos dois'
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

    /** A assinatura fiscal não viaja nas listas do painel. @test */
    public function a_lista_do_painel_nao_publica_a_assinatura(): void
    {
        $this->comPermissoes('invoicing.dashboard.view');

        $this->factura()->forceFill(['jws_signature' => 'assinatura-secreta'])->saveQuietly();

        $corpo = $this->getJson(self::ROTA)->assertOk()->content();

        $this->assertStringNotContainsString('assinatura-secreta', $corpo);
        $this->assertStringNotContainsString('jws_signature', $corpo);
    }

    /** O serviço é o mesmo objecto para os dois — não há segunda cópia. @test */
    public function o_componente_ja_nao_tem_contas_dentro(): void
    {
        $fonte = file_get_contents(app_path('Livewire/Invoicing/InvoicingDashboard.php'));

        $this->assertStringContainsString(PainelDaFacturacao::class, $fonte,
            'o painel Livewire tem de beber do serviço');
        $this->assertStringNotContainsString('private const LIQUIDADAS', $fonte,
            'as contas mudaram-se para o serviço; uma segunda cópia aqui volta a divergir');
    }
}
