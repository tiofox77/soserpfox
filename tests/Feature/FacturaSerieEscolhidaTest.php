<?php

namespace Tests\Feature;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\SalesInvoice;
use Tests\TenantTestCase;

/**
 * A série da factura fica ESCOLHIDA no ecrã, não implícita.
 *
 * O selector abria em "Série AGT padrão" — um valor vazio. O documento saía na
 * série por omissão de qualquer maneira, mas o ecrã não dizia qual, e quem
 * emitia não tinha como saber em que série ia ficar sem ir procurar às
 * definições. Numa empresa com três séries de factura — e há — isso é uma
 * escolha fiscal feita às escuras.
 *
 * O ECRÃ É AGORA EM REACT, e a regra mudou de sítio sem mudar de conteúdo: as
 * opções (`/factura/opcoes`) trazem as séries com o `document_type` e a que é
 * padrão, para o ecrã abrir já com uma escolhida e nunca em branco; e a série
 * que se manda em `series_id` é a que NUMERA o documento — a interna
 * (`series_code`) e a da AGT (`agt_series_id`). É isso que estes ensaios
 * provam, do lado onde passou a ser decidido.
 */
class FacturaSerieEscolhidaTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/factura';

    private function serie(string $codigo, bool $porOmissao, string $tipo = 'invoice', ?string $agt = null): InvoicingSeries
    {
        return InvoicingSeries::updateOrCreate(
            [
                'tenant_id'     => $this->tenant->id,
                'document_type' => $tipo,
                'series_code'   => $codigo,
            ],
            [
                'name'            => 'Série ' . $codigo,
                'prefix'          => $tipo === 'pos' ? 'FR' : 'FT',
                'next_number'     => 1,
                'number_padding'  => 6,
                'agt_series_id'   => $agt,
                // O ambiente TEM de ser o activo da empresa: uma série
                // registada noutro ambiente nunca numera (e bem — a AGT de
                // produção desconhece os códigos da homologação).
                'agt_environment' => \App\Services\AGT\AGTKeyStore::ambiente($this->tenant->id),
                'is_active'       => true,
                'is_default'      => $porOmissao,
            ]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.sales.invoices.create');
        $this->comModulo('invoicing');

        // A empresa de teste já traz séries. Sem as neutralizar, a "por
        // omissão" do teste não é a única e a ordenação decide por código.
        InvoicingSeries::where('tenant_id', $this->tenant->id)->update(['is_default' => false]);
    }

    /** O corpo mínimo de uma factura de 1 linha × 1000. */
    private function corpo(array $por = []): array
    {
        return array_merge([
            'client_id'    => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(),
            'status'       => 'draft',
            'linhas'       => [['product_id' => $this->produtoComStock(10, 1000)->id, 'quantity' => 1, 'price' => 1000]],
        ], $por);
    }

    /** @test */
    public function as_opcoes_dizem_qual_e_a_serie_por_omissao(): void
    {
        $this->serie('SOSFTB', false);
        $porOmissao = $this->serie('SOSFT', true);

        $series = collect($this->getJson(self::RAIZ . '/opcoes')->assertOk()->json('series'));

        $marcadas = $series->where('document_type', 'invoice')->where('is_default', true);

        $this->assertCount(1, $marcadas, 'o ecrã não pode ter duas padrão para escolher');
        $this->assertSame($porOmissao->id, $marcadas->first()['id']);

        // E cada série diz a que tipo pertence: é por aí que o ecrã separa as
        // de FT das do POS (FR) quando se troca de tipo.
        $this->assertNotEmpty($series->firstWhere('id', $porOmissao->id)['series_code']);
    }

    /** @test */
    public function nenhum_tipo_fica_sem_serie_para_escolher(): void
    {
        // Sem série marcada por omissão o campo escolhe a primeira — nunca
        // pode ficar em branco a decidir sozinho.
        $this->serie('SOSFTC', false);

        $series = collect($this->getJson(self::RAIZ . '/opcoes')->assertOk()->json('series'));

        $this->assertGreaterThan(0, $series->where('document_type', 'invoice')->count());
        $this->assertGreaterThan(0, $series->where('document_type', 'pos')->count());
    }

    /**
     * A ESCOLHA DO UTILIZADOR É A QUE NUMERA.
     *
     * Não basta o ecrã mostrar a série: o documento tem de sair nela. Aqui
     * escolhe-se a que NÃO é a padrão, de propósito.
     *
     * @test
     */
    public function a_serie_escolhida_e_a_que_numera(): void
    {
        $this->serie('SOSFT', true);
        $outra = $this->serie('SOSFTB', false);

        $id = $this->postJson(self::RAIZ, $this->corpo(['series_id' => $outra->id]))
            ->assertCreated()->json('id');

        $f = SalesInvoice::findOrFail($id);

        $this->assertSame($outra->id, (int) $f->series_id, 'a factura ficou ligada à série escolhida');
        $this->assertStringContainsString('SOSFTB', $f->numeroInterno(),
            'o número interno tem de trazer o código da série escolhida');
    }

    /** Sem escolha nenhuma, numera a que está marcada por omissão. @test */
    public function sem_escolha_numera_a_serie_por_omissao(): void
    {
        $this->serie('SOSFTB', false);
        $porOmissao = $this->serie('SOSFT', true);

        $id = $this->postJson(self::RAIZ, $this->corpo(['series_id' => null]))->assertCreated()->json('id');

        $this->assertSame($porOmissao->id, (int) SalesInvoice::findOrFail($id)->series_id);
    }

    /**
     * OS DOIS NÚMEROS DA MESMA FACTURA.
     *
     * O `invoice_number` gravado leva o código da AGT (é o elo com o portal);
     * a série interna, aquela por que a empresa chama o documento, sai no
     * `numeroInterno()`. Escolher a série no ecrã tem de acertar nos dois.
     *
     * @test
     */
    public function a_serie_escolhida_manda_no_numero_interno_e_no_da_agt(): void
    {
        $this->serie('SOSFT', true);
        $registada = $this->serie('SOSFTB', false, 'invoice', 'FT4226S75324N');

        $id = $this->postJson(self::RAIZ, $this->corpo(['series_id' => $registada->id]))
            ->assertCreated()->json('id');

        $f = SalesInvoice::findOrFail($id);

        $this->assertStringContainsString('FT4226S75324N', (string) $f->invoice_number,
            'o número fiscal sai na série registada na AGT');
        $this->assertStringContainsString('FT4226S75324N', (string) $f->numeroAgt());
        $this->assertStringContainsString('SOSFTB', $f->numeroInterno(),
            'e o número interno continua a ser o da série da casa');
    }

    /**
     * FT e FR usam conjuntos de séries diferentes: a Fatura-Recibo numera-se
     * pela sequência do POS. A que estava escolhida deixa de servir e tem de
     * ser substituída, não esvaziada.
     *
     * @test
     */
    public function a_factura_recibo_numera_pela_serie_do_pos(): void
    {
        $this->serie('SOSFT', true);
        $fr = $this->serie('SOSFR', true, 'pos');

        $id = $this->postJson(self::RAIZ, $this->corpo([
            'invoice_type'   => 'FR',
            'series_id'      => $fr->id,
            'payment_method' => 'cash',
        ]))->assertCreated()->json('id');

        $f = SalesInvoice::findOrFail($id);

        $this->assertSame('FR', $f->invoice_type);
        $this->assertSame($fr->id, (int) $f->series_id);
        $this->assertStringContainsString('SOSFR', $f->numeroInterno());
    }
}
