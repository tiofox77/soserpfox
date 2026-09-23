<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\SalesInvoice;
use App\Services\POS\PosSalesReportQuery;
use Tests\TenantTestCase;

/**
 * O relatório do POS mostra as DUAS numerações.
 *
 * Depois de a série ser registada na AGT, o `invoice_number` passa a levar o
 * código da AGT (`FR FR4226S61319N/000399`). O relatório mostrava só esse — e
 * a numeração que a casa reconhece, procura e arquiva (`FR SOSFR/000399`)
 * desaparecia do mapa de vendas.
 *
 * A lista de facturas já mostrava as duas desde 22/08; o relatório do POS
 * ficou para trás. O número FISCAL continua a ser o da AGT — isto é
 * apresentação, não muda documento nenhum.
 */
class RelatorioPosNumeracaoTest extends TenantTestCase
{
    private function serie(array $over = []): InvoicingSeries
    {
        return InvoicingSeries::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Série do POS',
            'document_type' => 'invoice',
            'prefix' => 'FR',
            'series_code' => 'SOSFR',
            'next_number' => 1,
            'is_active' => true,
            // Uma série REGISTADA só serve no ambiente em que foi registada.
            // Carimba-se o da empresa em vez de o presumir.
            'agt_environment' => \App\Models\Invoicing\InvoicingSettings::forTenant($this->tenant->id)->agt_environment,
        ], $over));
    }

    private function factura(InvoicingSeries $serie, string $numero): SalesInvoice
    {
        return SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'series_id' => $serie->id,
            'invoice_number' => $numero,
            'invoice_date' => now()->toDateString(),
            'status' => 'paid',
            'subtotal' => 1000,
            'tax_amount' => 0,
            'total' => 1000,
            'created_by' => $this->user->id,
        ]);
    }

    private function linhas(): \Illuminate\Support\Collection
    {
        return collect((new PosSalesReportQuery(
            $this->tenant->id,
            ['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()],
        ))->listagem()->paginate(50)->items());
    }

    /**
     * A INTERNA VIAJA COM A LINHA.
     *
     * @test
     */
    public function o_relatorio_traz_a_serie_interna(): void
    {
        $serie = $this->serie(['agt_series_id' => 'FR4226S61319N']);
        $this->factura($serie, 'FR FR4226S61319N/000399');

        $linha = $this->linhas()->firstWhere('numero', 'FR FR4226S61319N/000399');

        $this->assertNotNull($linha, 'a factura tem de aparecer no relatório');
        $this->assertSame('FR', $linha->serie_prefixo);
        $this->assertSame('SOSFR', $linha->serie_interna);

        // E compõe-se pelo MESMO método do modelo — não por uma segunda regra.
        $this->assertSame(
            'FR SOSFR/000399',
            SalesInvoice::comporNumeroInterno($linha->serie_prefixo, $linha->serie_interna, $linha->numero)
        );
    }

    /** Sem registo na AGT, as duas são a mesma — e não se mostra duas vezes. */
    public function test_sem_registo_agt_a_interna_e_o_proprio_numero(): void
    {
        $serie = $this->serie(['series_code' => 'SOSFR2', 'agt_series_id' => null]);
        $this->factura($serie, 'FR SOSFR2/000001');

        $linha = $this->linhas()->firstWhere('numero', 'FR SOSFR2/000001');

        $this->assertSame(
            'FR SOSFR2/000001',
            SalesInvoice::comporNumeroInterno($linha->serie_prefixo, $linha->serie_interna, $linha->numero)
        );
    }

    /**
     * O UNION não pode partir.
     *
     * As facturas ganharam duas colunas; as notas de crédito têm de as ter
     * também, senão o relatório inteiro deixa de abrir — e o relatório é o que
     * a casa usa para fechar o dia.
     *
     * @test
     */
    public function as_notas_de_credito_continuam_a_aparecer(): void
    {
        $serie = $this->serie(['series_code' => 'SOSFR3']);
        $factura = $this->factura($serie, 'FR SOSFR3/000001');

        \App\Models\Invoicing\CreditNote::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'invoice_id' => $factura->id,
            'credit_note_number' => 'NC SOSNC/000001',
            'issue_date' => now()->toDateString(),
            'status' => 'issued',
            'subtotal' => 500,
            'tax_amount' => 0,
            'total' => 500,
            'reason_text' => 'Devolução de teste',
            'created_by' => $this->user->id,
        ]);

        $linhas = $this->linhas();

        $this->assertNotNull($linhas->firstWhere('numero', 'NC SOSNC/000001'),
            'a nota de crédito tem de sobreviver às colunas novas');
        $this->assertNotNull($linhas->firstWhere('numero', 'FR SOSFR3/000001'));
    }

    /**
     * O QUE O ECRÃ RECEBE, e não só a consulta.
     *
     * A consulta trazia os pedaços desde agosto, mas a API lia um
     * `numero_interno` que não existia e mandava o número da AGT nos dois
     * campos: o mapa continuava a mostrar só FR FR4226S61319N/… (23/09).
     */
    public function test_a_api_do_relatorio_manda_a_interna_e_a_da_agt(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.pos.reports');
        $serie = $this->serie(['agt_series_id' => 'FR4226S61319N']);
        $this->factura($serie, 'FR FR4226S61319N/003256');

        $r = $this->getJson('/api/v1/invoicing/react/pos/relatorio?' . http_build_query([
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
        ]))->assertOk();

        $linha = collect($r->json('data'))->firstWhere('numero', 'FR FR4226S61319N/003256');
        $this->assertNotNull($linha);
        $this->assertSame('FR SOSFR/003256', $linha['numero_interno']);

        // E procura-se pela numeração da casa.
        $achadas = $this->getJson('/api/v1/invoicing/react/pos/relatorio?' . http_build_query([
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(), 'search' => 'SOSFR',
        ]))->assertOk()->json('data');
        $this->assertContains('FR SOSFR/003256', array_column($achadas, 'numero_interno'));
    }

    /** As vendas do turno: a interna em `numero`, a fiscal em `numero_agt`. */
    public function test_as_vendas_do_turno_mostram_as_duas(): void
    {
        $serie = $this->serie(['agt_series_id' => 'FR4226S61319N']);
        $factura = $this->factura($serie, 'FR FR4226S61319N/003257');

        $turno = \App\Models\Invoicing\PosShift::createSafely([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'status' => 'open', 'opened_at' => now(), 'opening_balance' => 0,
        ], $this->tenant->id);
        $turno->addTransaction([
            'type' => 'invoice', 'reference_type' => SalesInvoice::class, 'reference_id' => $factura->id,
            'reference_number' => $factura->invoice_number, 'payment_method' => 'cash', 'amount' => 1000,
            'description' => 'Venda POS',
        ]);

        $doc = \App\Services\POS\ProdutosDoTurno::de($turno->fresh())['documentos'][0];

        $this->assertSame('FR SOSFR/003257', $doc['numero']);
        $this->assertSame('FR FR4226S61319N/003257', $doc['numero_agt']);
    }
}
