<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesProforma;
use App\Models\User;
use App\Services\Invoicing\TiposDeDocumento;
use Tests\TenantTestCase;

/**
 * Listas de documentos: cada um vê os que emitiu.
 *
 * Fecha a porta do lado do relatório do POS. As vendas do POS acabam em
 * facturas, e a lista de Facturas de Venda mostrava-as todas a qualquer
 * utilizador com acesso ao módulo — um vendedor via as vendas dos colegas
 * com dois cliques.
 *
 * A regra vive num sítio só (App\Traits\DocumentosPorAutor) e vale para as
 * nove listas: facturas e proformas de venda e de compra, orçamentos, notas
 * de crédito e de débito, recibos e adiantamentos.
 *
 * OS ECRÃS SÃO HOJE REACT, e o ecrã novo não pode ser uma porta nova: se a
 * API mostrar um documento que o Livewire escondia, a migração não trocou de
 * tecnologia — abriu um buraco. Por isso a regra prova-se agora contra a API
 * que serve as listas: `sales-invoices` para as facturas e `documentos/{tipo}`
 * para as outras oito.
 */
class DocumentosPorAutorTest extends TenantTestCase
{
    private const FACTURAS = '/api/v1/invoicing/react/sales-invoices';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function colega(): User
    {
        $colega = User::create([
            'name' => 'Colega '.uniqid(),
            'email' => uniqid().'@exemplo.ao',
            'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id,
        ]);

        $colega->tenants()->syncWithoutDetaching([$this->tenant->id]);

        return $colega;
    }

    private function cliente(): Client
    {
        return Client::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente '.uniqid(),
            'email' => uniqid().'@cliente.ao',
        ]);
    }

    private function factura(int $autor, string $numero): SalesInvoice
    {
        return SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente()->id,
            'invoice_number' => $numero,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'pending',
            'subtotal' => 1000,
            'total' => 1000,
            'created_by' => $autor,
        ]);
    }

    private function proforma(int $autor, string $numero): SalesProforma
    {
        return SalesProforma::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente()->id,
            'proforma_number' => $numero,
            'proforma_date' => now(),
            'status' => 'draft',
            'subtotal' => 500,
            'total' => 500,
            'created_by' => $autor,
        ]);
    }

    /** Os ids que a lista de facturas devolve a quem está autenticado. */
    private function listaDeFacturas(string $cauda = ''): \Illuminate\Support\Collection
    {
        return collect($this->getJson(self::FACTURAS . $cauda)->assertOk()->json('data'))->pluck('id');
    }

    /**
     * O vendedor vê as suas — e a conta do topo conta o mesmo que a lista
     * mostra, senão via uma lista de 1 e um total de 200.
     *
     * @test
     */
    public function um_vendedor_so_ve_as_facturas_que_emitiu(): void
    {
        $colega = $this->colega();
        $minha = $this->factura($this->user->id, 'FT MINHA/001');
        $dele = $this->factura($colega->id, 'FT DELE/002');
        $this->factura($colega->id, 'FT DELE/003');

        $this->comPermissoes('invoicing.sales.invoices.view');
        $this->actingAs($this->user);

        $resposta = $this->getJson(self::FACTURAS)->assertOk();
        $vistas = collect($resposta->json('data'))->pluck('id');

        $this->assertTrue($vistas->contains($minha->id));
        $this->assertFalse($vistas->contains($dele->id), 'a factura do colega apareceu na lista');
        $this->assertStringNotContainsString('FT DELE/002', $resposta->content());

        $this->assertSame(1, $resposta->json('meta.total'),
            'a conta do topo segue a mesma regra da lista');
    }

    /**
     * OS CARTÕES DE TOTAIS AINDA NÃO EXISTEM DO LADO DA API.
     *
     * O ecrã Livewire tinha, por cima da tabela, os contadores do período —
     * quantas facturas e quanto somam — e seguiam a mesma regra do escopo. A
     * lista em React ainda não os tem: a API devolve a CONTAGEM (`meta.total`,
     * provada acima) mas não a soma dos valores.
     *
     * @test
     */
    public function os_totais_do_topo_seguem_a_mesma_regra(): void
    {
        $colega = $this->colega();
        $this->factura($this->user->id, 'FT MINHA/010');
        $this->factura($colega->id, 'FT DELE/011');

        $this->comPermissoes('invoicing.sales.invoices.view');
        $this->actingAs($this->user);

        $resposta = $this->getJson(self::FACTURAS)->assertOk();

        $this->assertSame(1, $resposta->json('meta.total'));

        /*
         * E OS CARTÕES DE TOPO CONTAM O MESMO.
         *
         * De nada servia esconder a linha do colega na tabela se a soma por
         * cima dela continuasse a incluí-la: quem não pode ver o que os outros
         * facturaram, deduzia-o da diferença.
         */
        $this->assertSame(
            round((float) SalesInvoice::where('created_by', $this->user->id)->sum('total'), 2),
            round((float) $resposta->json('meta.somas.facturado'), 2),
            'a soma do cartão conta só as do próprio'
        );
    }

    /** @test */
    public function com_a_permissao_ve_as_de_todos(): void
    {
        $colega = $this->colega();
        $minha = $this->factura($this->user->id, 'FT MINHA/020');
        $dele = $this->factura($colega->id, 'FT DELE/021');

        $this->comPermissoes('invoicing.sales.invoices.view', 'invoicing.documents.all');
        $this->actingAs($this->user);

        $vistas = $this->listaDeFacturas();

        $this->assertTrue($vistas->contains($minha->id));
        $this->assertTrue($vistas->contains($dele->id));
    }

    /**
     * Com a permissão, o filtro escolhe mesmo o autor.
     *
     * (Que o filtro NÃO dá a volta à permissão de quem só vê as suas está
     * provado em ApiDasFacturasParaReactTest::o_filtro_por_autor_nao_e_uma_porta_lateral.)
     */
    public function test_com_a_permissao_o_filtro_funciona(): void
    {
        $colega = $this->colega();
        $dele = $this->factura($colega->id, 'FT DELE/040');
        $minha = $this->factura($this->user->id, 'FT MINHA/041');

        $this->comPermissoes('invoicing.sales.invoices.view', 'invoicing.documents.all');
        $this->actingAs($this->user);

        $vistas = $this->listaDeFacturas('?autor=' . $colega->id);

        $this->assertTrue($vistas->contains($dele->id));
        $this->assertFalse($vistas->contains($minha->id));
    }

    /**
     * Pelo id também não: os botões da linha recebem um número e abriam
     * qualquer documento da empresa. A API que abre o documento aplica o mesmo
     * escopo e responde 404.
     */
    public function test_abrir_a_factura_de_um_colega_pelo_id_nao_mostra_nada(): void
    {
        $colega = $this->colega();
        $dele = $this->factura($colega->id, 'FT DELE/050');
        $minha = $this->factura($this->user->id, 'FT MINHA/051');

        $this->comPermissoes('invoicing.sales.invoices.view');
        $this->actingAs($this->user);

        $this->getJson('/api/v1/invoicing/react/factura/' . $dele->id)->assertNotFound();
        $this->getJson('/api/v1/invoicing/react/factura/' . $minha->id)->assertOk();
    }

    /** A regra não é só das facturas: as proformas seguem-na na mesma. */
    public function test_as_proformas_seguem_a_mesma_regra(): void
    {
        $colega = $this->colega();
        $minha = $this->proforma($this->user->id, 'PF MINHA/001');
        $dela = $this->proforma($colega->id, 'PF DELE/002');

        $this->comPermissoes('invoicing.sales.proformas.view');
        $this->actingAs($this->user);

        $vistas = collect(
            $this->getJson('/api/v1/invoicing/react/documentos/proformas-venda')->assertOk()->json('data')
        )->pluck('id');

        $this->assertTrue($vistas->contains($minha->id));
        $this->assertFalse($vistas->contains($dela->id));
    }

    /**
     * Um documento SEM autor é de ninguém, não é de um colega.
     *
     * As facturas e proformas de venda têm autor obrigatório na base, mas as
     * compras, notas, recibos e adiantamentos aceitam nulo — importações,
     * sincronização offline e dados antigos entram por aí. Escondê-los de
     * toda a gente tornava-os inalcançáveis, que é pior do que mostrá-los.
     */
    public function test_um_documento_sem_autor_continua_a_ver_se(): void
    {
        $fornecedor = \App\Models\Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor '.uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'is_active' => true,
        ]);

        $orfa = \App\Models\Invoicing\PurchaseInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $fornecedor->id,
            'invoice_number' => 'FC ORFA/060',
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'subtotal' => 700,
            'total' => 700,
            'created_by' => null,
        ]);

        $this->comPermissoes('invoicing.purchases.invoices.view');
        $this->actingAs($this->user);

        $vistas = collect(
            $this->getJson('/api/v1/invoicing/react/documentos/facturas-compra')->assertOk()->json('data')
        )->pluck('id');

        $this->assertTrue($vistas->contains($orfa->id), 'um documento sem autor não pode ficar inalcançável');
    }

    /**
     * As nove listas usam mesmo a regra — nenhuma ficou de fora.
     *
     * Eram nove componentes Livewire; são hoje dois controladores: o das
     * facturas de venda, que tem ecrã próprio, e o genérico que serve os
     * outros oito documentos. Ambos usam o MESMO trait — se a regra mudar,
     * muda nos dois ao mesmo tempo.
     */
    public function test_todas_as_listas_de_documentos_usam_a_regra(): void
    {
        $controladores = [
            \App\Http\Controllers\Api\Invoicing\SalesInvoiceApiController::class,
            \App\Http\Controllers\Api\Invoicing\DocumentosApiController::class,
        ];

        foreach ($controladores as $controlador) {
            $this->assertContains(
                \App\Traits\DocumentosPorAutor::class,
                class_uses_recursive($controlador),
                "{$controlador} não segue a regra de quem vê que documentos"
            );
        }

        // E as outras oito listas estão mesmo servidas pelo controlador
        // genérico: uma que ficasse de fora do registo ficava sem escopo.
        $tipos = array_keys(TiposDeDocumento::todos());

        foreach ([
            'proformas-venda', 'orcamentos', 'notas-credito', 'notas-debito',
            'recibos', 'adiantamentos', 'facturas-compra', 'proformas-compra',
        ] as $tipo) {
            $this->assertContains($tipo, $tipos, "a lista {$tipo} ficou fora do registo");
        }
    }
}
