<?php

namespace Tests\Feature;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\DebitNote;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesProforma;
use App\Traits\NumeracaoInternaEAgt;
use Tests\TenantTestCase;

/**
 * A série INTERNA primeiro, a da AGT logo abaixo.
 *
 * O documento leva gravado o número da série da AGT — NC NC4226S46906N/000002.
 * Ninguém procura um documento por aquilo. A série da casa (SOSNC) vivia na
 * série ligada e não aparecia em lado nenhum.
 *
 * As listas em Blade deram lugar aos ecrãs em React, e por isso os dois
 * números têm de VIAJAR NA RESPOSTA: a lista de facturas e a lista genérica
 * que serve os outros oito documentos mandam `numero` (o interno) e
 * `numero_agt`. E a procura tem de encontrar pelos dois — quem escreve SOSNC
 * quer o documento, e quem vem do portal com o código da AGT também.
 */
class NumeroInternoNasListasTest extends TenantTestCase
{
    /** Os ecrãs em React que desenham as listas de documentos. */
    private const ECRAS = [
        'js/ecras/facturacao/vendas/ListaDeFacturas.tsx',
        'js/ecras/facturacao/ListaDeDocumentos.tsx',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    /**
     * Uma série da casa (SOSxx) já registada na AGT com o seu código críptico.
     *
     * O ambiente é o ACTIVO da empresa: uma série registada no outro ambiente
     * não emite nada, e o documento nem chega a nascer.
     */
    private function serie(string $tipo, string $prefixo, string $interna, string $agt): InvoicingSeries
    {
        return InvoicingSeries::create([
            'tenant_id' => $this->tenant->id,
            'series_code' => $interna,
            'name' => $interna . ' (ensaio)',
            'prefix' => $prefixo,
            'document_type' => $tipo,
            'agt_series_id' => $agt,
            'agt_environment' => \App\Services\AGT\AGTKeyStore::ambiente($this->tenant->id),
            'is_default' => false,
            'is_active' => true,
        ]);
    }

    /**
     * UMA SÓ IMPLEMENTAÇÃO DA NUMERAÇÃO.
     *
     * Isto nasceu dentro do modelo das facturas. Copiá-lo para os outros quatro
     * documentos daria cinco cópias da mesma fórmula, e a numeração é a última
     * coisa onde se quer que duas cópias divirjam.
     *
     * @test
     */
    public function a_numeracao_vive_num_sitio_so(): void
    {
        foreach ([SalesInvoice::class, CreditNote::class, DebitNote::class, Receipt::class, SalesProforma::class] as $modelo) {
            $this->assertContains(NumeracaoInternaEAgt::class, class_uses_recursive($modelo),
                class_basename($modelo) . ': não usa a numeração partilhada');
        }

        // E o modelo das facturas já não tem a sua cópia inline.
        $fonte = file_get_contents(app_path('Models/Invoicing/SalesInvoice.php'));
        $this->assertStringNotContainsString('public function numeroInterno(): string', $fonte,
            'a cópia dentro do modelo tem de sair — quem manda é o trait');
    }

    /** @test */
    public function o_numero_interno_sai_da_serie_ligada(): void
    {
        $nota = new CreditNote(['credit_note_number' => 'NC NC4226S46906N/000002']);

        // Sem série ligada, fica o que está gravado.
        $this->assertSame('NC NC4226S46906N/000002', $nota->numeroInterno());
        $this->assertNull($nota->numeroAgt());

        // A composição é pura e é a mesma para todos os documentos.
        $this->assertSame(
            'NC SOSNC/000002',
            CreditNote::comporNumeroInterno('NC', 'SOSNC', 'NC NC4226S46906N/000002')
        );
    }

    /**
     * A LISTA DAS FACTURAS MANDA OS DOIS NÚMEROS.
     *
     * @test
     */
    public function a_lista_de_facturas_manda_a_serie_interna_e_a_da_agt(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $serie = $this->serie('invoice', 'FT', 'SOSFT', 'FT4226S75324N');

        $f = SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'series_id' => $serie->id,
            'invoice_number' => 'FT FT4226S75324N/000002',
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'total' => 1000,
            'created_by' => $this->user->id,
        ]);

        $linha = collect($this->getJson('/api/v1/invoicing/react/sales-invoices')->assertOk()->json('data'))
            ->firstWhere('id', $f->id);

        $this->assertSame('FT SOSFT/000002', $linha['numero'],
            'a série interna é a que aparece — é por ela que se procura');
        $this->assertSame('FT FT4226S75324N/000002', $linha['numero_agt'],
            'e a da AGT vai a seguir');
    }

    /**
     * E A LISTA GENÉRICA, QUE SERVE OS OUTROS OITO DOCUMENTOS, TAMBÉM.
     *
     * Mandava só o número gravado — o da AGT — e a série da casa não aparecia
     * em lado nenhum destes oito ecrãs.
     *
     * @test
     */
    public function a_lista_generica_manda_os_dois_numeros(): void
    {
        $this->comPermissoes('invoicing.credit-notes.view');

        $serie = $this->serie('credit_note', 'NC', 'SOSNC', 'NC4226S46906N');

        $nota = CreditNote::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'series_id' => $serie->id,
            'credit_note_number' => 'NC NC4226S46906N/000002',
            'issue_date' => now()->toDateString(),
            'status' => 'issued',
            'reason' => 'return',
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'type' => 'total',
            'created_by' => $this->user->id,
        ]);

        $linha = collect(
            $this->getJson('/api/v1/invoicing/react/documentos/notas-credito')->assertOk()->json('data')
        )->firstWhere('id', $nota->id);

        $this->assertSame('NC SOSNC/000002', $linha['numero']);
        $this->assertSame('NC NC4226S46906N/000002', $linha['numero_agt']);
    }

    /**
     * A PROCURA ENCONTRA PELAS DUAS SÉRIES.
     *
     * @test
     */
    public function a_procura_encontra_pela_serie_interna_e_pela_da_agt(): void
    {
        $this->comPermissoes('invoicing.credit-notes.view', 'invoicing.sales.invoices.view');

        $serieNota = $this->serie('credit_note', 'NC', 'SOSNC', 'NC4226S46906N');

        $nota = CreditNote::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'series_id' => $serieNota->id,
            'credit_note_number' => 'NC NC4226S46906N/000002',
            'issue_date' => now()->toDateString(),
            'status' => 'issued',
            'reason' => 'return',
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'type' => 'total',
            'created_by' => $this->user->id,
        ]);

        $serieFactura = $this->serie('invoice', 'FT', 'SOSFT', 'FT4226S75324N');

        $factura = SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'series_id' => $serieFactura->id,
            'invoice_number' => 'FT FT4226S75324N/000002',
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'total' => 1000,
            'created_by' => $this->user->id,
        ]);

        // Quem escreve SOSNC quer a nota; quem vem do portal com o código da
        // AGT quer a mesma nota.
        foreach (['SOSNC', 'NC4226S46906N'] as $termo) {
            $ids = collect(
                $this->getJson('/api/v1/invoicing/react/documentos/notas-credito?procura=' . $termo)->json('data')
            )->pluck('id');

            $this->assertTrue($ids->contains($nota->id), "a procura por «{$termo}» tinha de encontrar a nota");
        }

        foreach (['SOSFT', 'FT4226S75324N'] as $termo) {
            $ids = collect(
                $this->getJson('/api/v1/invoicing/react/sales-invoices?procura=' . $termo)->json('data')
            )->pluck('id');

            $this->assertTrue($ids->contains($factura->id), "a procura por «{$termo}» tinha de encontrar a factura");
        }
    }

    /**
     * A TABELA TEM DE CABER NO ECRÃ.
     *
     * `whitespace-nowrap` no título obriga «FATURA ORIGEM» e «PORTAL AGT» a uma
     * linha só, e isso fixa uma largura mínima que a coluna nunca larga. Nove
     * colunas assim passavam a largura da página e nascia a barra lateral —
     * medido: a coluna do Portal AGT caiu de 120 para 84 pixéis ao deixar o
     * título partir, e a tabela voltou a caber. `px-6 py-4` nas células fazia
     * o mesmo pelo outro lado.
     *
     * @test
     */
    public function nenhuma_lista_fixa_a_largura_das_colunas(): void
    {
        foreach (self::ECRAS as $ficheiro) {
            $fonte = file_get_contents(resource_path($ficheiro));

            $this->assertStringNotContainsString('whitespace-nowrap', $fonte,
                "{$ficheiro}: um título que não parte fixa a largura e traz de volta a barra lateral");

            $this->assertStringNotContainsString('px-6 py-4', $fonte,
                "{$ficheiro}: px-6 é o espaçamento largo que empurrava a tabela para fora");

            // E a tabela rola dentro da sua caixa: sem isto é a PÁGINA que
            // rola de lado, e o menu foge com ela.
            $this->assertStringContainsString('overflow-x-auto', $fonte);
        }
    }

    /** Os dois números aparecem, e o interno primeiro. @test */
    public function as_listas_mostram_a_serie_interna_primeiro(): void
    {
        foreach (self::ECRAS as $ficheiro) {
            $fonte = file_get_contents(resource_path($ficheiro));

            $interno = strpos($fonte, '.numero}');
            $agt = strpos($fonte, '.numero_agt');

            $this->assertNotFalse($interno, "{$ficheiro}: a série interna tem de ser a que aparece");
            $this->assertNotFalse($agt, "{$ficheiro}: a série da AGT vai a seguir");
            $this->assertLessThan($agt, $interno,
                "{$ficheiro}: a interna vem PRIMEIRO, é por ela que se procura");
        }
    }
}
