<?php

namespace Tests\Feature;

use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\SalesInvoice;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * A API que serve o ecrã de facturas em React.
 *
 * O QUE ISTO GUARDA. Um ecrã novo não pode ser uma porta nova: se a API
 * mostrar um documento que o ecrã Livewire escondia, a migração não trocou de
 * tecnologia — abriu um buraco. Estes ensaios comparam a API com a regra que
 * já existia, não com o que seria cómodo.
 */
class ApiDasFacturasParaReactTest extends TenantTestCase
{
    private const LISTA = '/api/v1/invoicing/react/sales-invoices';
    private const OPCOES = '/api/v1/invoicing/react/sales-invoices/opcoes';

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
            'created_by' => $this->user->id,
        ], $por));
    }

    /** @test */
    public function sem_a_permissao_nao_se_ve_nada(): void
    {
        $this->factura();

        $this->getJson(self::LISTA)->assertForbidden();
        $this->getJson(self::OPCOES)->assertForbidden();
    }

    /** @test */
    public function com_a_permissao_a_lista_vem_com_a_forma_combinada(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $f = $this->factura(['total' => 2595187]);

        $resposta = $this->getJson(self::LISTA)
            ->assertOk()
            ->assertJsonPath('data.0.id', $f->id)
            ->assertJsonPath('data.0.cliente.nome', $f->client->name)
            ->assertJsonStructure([
                'data' => [['id', 'numero', 'numero_agt', 'tipo', 'cliente' => ['id', 'nome', 'nif'],
                    'data', 'vencimento', 'estado', 'estado_rotulo', 'estado_cor', 'total', 'pago',
                    'saldo', 'agt' => ['comunicada', 'rotulo'], 'pode_creditar', 'pode_receber']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        // O valor compara-se por número e não por tipo: o JSON de 2595187.00
        // volta como inteiro, e um assertJsonPath estrito falharia por causa
        // do ".0" sem que nada estivesse errado.
        $this->assertEqualsWithDelta(2595187, $resposta->json('data.0.total'), 0.01);
    }

    /**
     * O QUE NÃO PODE SAIR DAQUI.
     *
     * A assinatura e os hashes fiscais são internos. Um `SalesInvoice` cru
     * numa resposta publicava-os, e a partir daí qualquer coluna nova ficava
     * publicada sem ninguém decidir isso.
     *
     * @test
     */
    public function a_assinatura_e_os_hashes_nao_viajam(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->factura()->forceFill([
            'jws_signature' => 'assinatura-secreta',
            'hash' => 'hash-secreto',
        ])->saveQuietly();

        $corpo = $this->getJson(self::LISTA)->assertOk()->content();

        $this->assertStringNotContainsString('assinatura-secreta', $corpo);
        $this->assertStringNotContainsString('hash-secreto', $corpo);
        $this->assertStringNotContainsString('jws_signature', $corpo);
    }

    /**
     * A MESMA REGRA DO ECRÃ: cada um vê os documentos que emitiu.
     *
     * @test
     */
    public function quem_so_ve_os_seus_nao_ve_os_dos_colegas_pela_api(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $minha = $this->factura();

        $colega = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $dele = $this->factura(['created_by' => $colega->id]);

        $resposta = $this->getJson(self::LISTA)->assertOk();

        $ids = collect($resposta->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($minha->id), 'a minha tem de aparecer');
        $this->assertFalse($ids->contains($dele->id), 'a do colega não');
    }

    /**
     * E O FILTRO POR AUTOR NÃO DÁ A VOLTA À PERMISSÃO.
     *
     * Escolher o nome de um colega no parâmetro não pode mostrar as facturas
     * dele a quem só vê as suas.
     *
     * @test
     */
    public function o_filtro_por_autor_nao_e_uma_porta_lateral(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $colega = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $dele = $this->factura(['created_by' => $colega->id]);

        $ids = collect($this->getJson(self::LISTA . '?autor=' . $colega->id)->assertOk()->json('data'))
            ->pluck('id');

        $this->assertFalse($ids->contains($dele->id),
            'pedir pelo id do colega não pode revelar as facturas dele');
    }

    /** E os nomes dos colegas também não vêm nas opções. @test */
    public function as_opcoes_nao_entregam_os_nomes_dos_colegas(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Colega Escondido']);

        $this->getJson(self::OPCOES)
            ->assertOk()
            ->assertJsonPath('permissoes.ve_de_todos', false)
            ->assertJsonPath('autores', []);
    }

    /**
     * A DECISÃO VEM DECIDIDA. O ecrã não recebe o estado para concluir se pode
     * creditar — recebe a resposta.
     *
     * @test
     */
    public function pode_creditar_vem_resolvido_do_servidor(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $aberta = $this->factura(['total' => 1000]);
        $fechada = $this->factura(['total' => 1000]);

        CreditNote::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $fechada->client_id,
            'invoice_id' => $fechada->id,
            'credit_note_number' => 'NC ' . strtoupper(substr(uniqid(), -8)),
            'issue_date' => now()->toDateString(),
            'status' => 'issued',
            'reason' => 'return',
            'subtotal' => 1000,
            'tax_amount' => 0,
            'total' => 1000,
            'type' => 'total',
            'created_by' => $this->user->id,
        ]);

        $porId = collect($this->getJson(self::LISTA)->assertOk()->json('data'))->keyBy('id');

        $this->assertTrue($porId[$aberta->id]['pode_creditar']);
        $this->assertFalse($porId[$fechada->id]['pode_creditar'],
            'uma factura já inteiramente anulada não se credita outra vez');
    }

    /**
     * UMA FACTURA-RECIBO NÃO TEM NADA A RECEBER.
     *
     * A FR é paga no acto da venda e nunca tem recibo, por isso o
     * `paid_amount` fica em zero para sempre. Uma conta ingénua
     * (`total - paid_amount`) punha-a a dizer «Pago» e «falta 570,00» ao lado
     * — foi o que se viu no ecrã à primeira vez que ele abriu.
     *
     * @test
     */
    public function uma_factura_recibo_nao_aparece_a_dever(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $fr = $this->factura(['invoice_type' => 'FR', 'status' => 'paid', 'total' => 570, 'paid_amount' => 0]);
        $ft = $this->factura(['invoice_type' => 'FT', 'status' => 'pending', 'total' => 570, 'paid_amount' => 0]);

        $porId = collect($this->getJson(self::LISTA)->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(0, (int) $porId[$fr->id]['saldo'], 'uma FR está paga por definição');
        $this->assertFalse($porId[$fr->id]['pode_receber'], 'e não se recebe outra vez');

        $this->assertEqualsWithDelta(570, $porId[$ft->id]['saldo'], 0.01);
        $this->assertTrue($porId[$ft->id]['pode_receber']);
    }

    /** E o que está pago, cancelado ou creditado também não deve nada. @test */
    public function o_que_ja_esta_liquidado_nao_aparece_a_dever(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        foreach (['paid', 'cancelled', 'credited'] as $estado) {
            $f = $this->factura(['status' => $estado, 'total' => 1000, 'paid_amount' => 0]);

            $linha = collect($this->getJson(self::LISTA)->json('data'))->firstWhere('id', $f->id);

            $this->assertSame(0, (int) $linha['saldo'], "estado {$estado} não deve nada");
            $this->assertFalse($linha['pode_receber']);
        }
    }

    /**
     * OS CARTÕES DO TOPO SOMAM VALORES, E SOMAM O QUE ESTÁ FILTRADO.
     *
     * O ecrã em Blade tinha-os; ao passar para React ficou só a contagem
     * (`meta.total`) e os valores desapareceram. E não se soma a PÁGINA: um
     * número que mudasse ao carregar em «Seguinte» não queria dizer nada.
     *
     * @test
     */
    public function os_cartoes_somam_os_valores_do_que_esta_filtrado(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        // Por receber inteira, por receber em parte, paga e anulada.
        $this->factura(['status' => 'sent', 'total' => 1000, 'paid_amount' => 0]);
        $this->factura(['status' => 'partially_paid', 'total' => 1000, 'paid_amount' => 400]);
        $this->factura(['status' => 'paid', 'total' => 1000, 'paid_amount' => 1000]);
        $this->factura(['status' => 'cancelled', 'total' => 5000, 'paid_amount' => 0]);

        $somas = $this->getJson(self::LISTA)->assertOk()->json('meta.somas');

        // O anulado não se facturou: 1000 + 1000 + 1000.
        $this->assertEqualsWithDelta(3000, $somas['facturado'], 0.01);
        // Por receber: 1000 da primeira + 600 da segunda. Nem a paga nem a
        // anulada devem nada.
        $this->assertEqualsWithDelta(1600, $somas['por_receber'], 0.01);

        // E com um filtro posto, somam só o que o filtro deixou passar.
        $comFiltro = $this->getJson(self::LISTA . '?estado=sent')->assertOk();

        $this->assertSame(1, $comFiltro->json('meta.total'));
        $this->assertEqualsWithDelta(1000, $comFiltro->json('meta.somas.facturado'), 0.01);
        $this->assertEqualsWithDelta(1000, $comFiltro->json('meta.somas.por_receber'), 0.01);
    }

    /**
     * O CARTÃO E AS LINHAS TÊM DE DIZER O MESMO.
     *
     * A conta do cartão é SQL (`SomasDasFacturas`) e a de cada linha é PHP
     * (`SalesInvoiceResource::porReceber`). São duas escritas da mesma regra, e
     * é este ensaio que as prende uma à outra: uma FR está paga por definição,
     * e o que está pago, anulado ou creditado não deve nada.
     *
     * @test
     */
    public function a_soma_do_cartao_bate_certo_com_os_saldos_das_linhas(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->factura(['invoice_type' => 'FR', 'status' => 'paid', 'total' => 570, 'paid_amount' => 0]);
        $this->factura(['invoice_type' => 'FT', 'status' => 'sent', 'total' => 900, 'paid_amount' => 250]);
        $this->factura(['invoice_type' => 'FT', 'status' => 'overdue', 'total' => 300, 'paid_amount' => 0]);
        $this->factura(['invoice_type' => 'FT', 'status' => 'credited', 'total' => 800, 'paid_amount' => 0]);

        $resposta = $this->getJson(self::LISTA)->assertOk();

        $dasLinhas = collect($resposta->json('data'))->sum('saldo');

        $this->assertEqualsWithDelta(950, $dasLinhas, 0.01, 'as linhas: 650 + 300');
        $this->assertEqualsWithDelta(
            $dasLinhas,
            $resposta->json('meta.somas.por_receber'),
            0.01,
            'o cartão não pode dizer um número que não sai de nenhuma linha à vista'
        );
    }

    /** Vencido é o que está por receber e passou do prazo. @test */
    public function o_cartao_do_vencido_conta_o_que_passou_do_prazo(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->factura(['status' => 'sent', 'total' => 400, 'due_date' => now()->subDays(10)->toDateString()]);
        $this->factura(['status' => 'sent', 'total' => 700, 'due_date' => now()->addDays(10)->toDateString()]);
        // Já paga: passou do prazo mas não há nada a receber.
        $this->factura(['status' => 'paid', 'total' => 900, 'paid_amount' => 900, 'due_date' => now()->subDays(30)->toDateString()]);

        $somas = $this->getJson(self::LISTA)->assertOk()->json('meta.somas');

        $this->assertEqualsWithDelta(1100, $somas['por_receber'], 0.01);
        $this->assertEqualsWithDelta(400, $somas['vencido'], 0.01);
    }

    /**
     * OS CARTÕES OBEDECEM AO MESMO ESCOPO DA LISTA.
     *
     * Esconder a linha do colega e depois somá-la no cartão do topo era contar
     * pela porta do lado o que a tabela recusa mostrar.
     *
     * @test
     */
    public function quem_so_ve_os_seus_nao_soma_as_facturas_do_colega(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->factura(['status' => 'sent', 'total' => 1000, 'paid_amount' => 0]);

        $colega = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->factura(['created_by' => $colega->id, 'status' => 'sent', 'total' => 7777, 'paid_amount' => 0]);

        $somas = $this->getJson(self::LISTA)->assertOk()->json('meta.somas');

        $this->assertEqualsWithDelta(1000, $somas['facturado'], 0.01, 'a do colega não entra');
        $this->assertEqualsWithDelta(1000, $somas['por_receber'], 0.01);

        // Com a permissão de ver os documentos de todos, a soma abre-se.
        $this->comPermissoes('invoicing.documents.all');

        $this->assertEqualsWithDelta(
            8777,
            $this->getJson(self::LISTA)->assertOk()->json('meta.somas.facturado'),
            0.01
        );
    }

    /** Os filtros filtram, e a paginação pagina. @test */
    public function os_filtros_e_a_paginacao_funcionam(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->factura(['status' => 'paid']);

        for ($i = 0; $i < 5; $i++) {
            $this->factura(['status' => 'draft']);
        }

        $this->getJson(self::LISTA . '?estado=draft')
            ->assertOk()
            ->assertJsonCount(5, 'data');

        // O mínimo por página são 5 — pedir 1 é pedir ao servidor uma consulta
        // por linha, e a validação recusa-o.
        $this->getJson(self::LISTA . '?por_pagina=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 6)
            ->assertJsonPath('meta.last_page', 2);
    }

    /** Um parâmetro inventado não passa em silêncio. @test */
    public function os_parametros_sao_validados(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->getJson(self::LISTA . '?tipo=XX')->assertStatus(422);
        $this->getJson(self::LISTA . '?por_pagina=9999')->assertStatus(422);
    }

    /**
     * A PÁGINA QUE SERVE O ECRÃ EXISTE E PEDE A MESMA PERMISSÃO.
     *
     * @test
     */
    public function a_pagina_do_ecra_novo_pede_a_mesma_permissao(): void
    {
        $this->get('/invoicing/sales/invoices')->assertForbidden();

        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->get('/invoicing/sales/invoices')
            ->assertOk()
            ->assertSee('data-ecra="facturacao/lista-de-facturas"', false);
    }

    /*
     * ─── MARCAR COMO PAGA, o segundo botão verde da lista de sempre ────────
     *
     * Não se confunde com o recibo: este NÃO lança dinheiro, apenas fecha a
     * conta de uma factura já paga por fora. As recusas são as do ecrã
     * Livewire, à letra — e cada uma tem aqui o seu teste, porque uma porta
     * HTTP não tem botões para esconder.
     */

    private function pagar(int $id): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(self::LISTA . '/' . $id . '/pagar');
    }

    /** @test */
    public function sem_permissao_de_editar_nao_se_marca_como_paga(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $f = $this->factura();

        $this->pagar($f->id)->assertForbidden();
        $this->assertSame('sent', $f->fresh()->status);
    }

    /** @test */
    public function marcar_como_paga_fecha_a_conta_da_factura(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.edit');

        $f = $this->factura();

        $this->pagar($f->id)->assertOk()->assertJsonPath('estado', 'paid');

        $this->assertSame('paid', $f->fresh()->status);
    }

    /** A FR já é paga no acto da venda: marcá-la duplicaria o recebimento. @test */
    public function a_factura_recibo_nao_se_marca_como_paga(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.edit');

        $f = $this->factura(['invoice_type' => 'FR']);

        $this->pagar($f->id)->assertStatus(422);
        $this->assertSame('sent', $f->fresh()->status);
    }

    /** @test */
    public function o_que_ja_esta_pago_ou_anulado_nao_se_marca_outra_vez(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.edit');

        $paga = $this->factura(['status' => 'paid']);
        $anulada = $this->factura(['status' => 'cancelled']);

        $this->pagar($paga->id)->assertStatus(422);
        $this->pagar($anulada->id)->assertStatus(422);

        $this->assertSame('paid', $paga->fresh()->status);
        $this->assertSame('cancelled', $anulada->fresh()->status);
    }

    /** O escopo por autor manda: quem só vê as suas não fecha a dos outros. @test */
    public function quem_so_ve_as_suas_nao_marca_a_dos_outros_como_paga(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.edit');

        $outro = User::factory()->create();
        $doOutro = $this->factura(['created_by' => $outro->id]);

        $this->pagar($doOutro->id)->assertNotFound();
        $this->assertSame('sent', $doOutro->fresh()->status);
    }

    /**
     * UM RASCUNHO NÃO DEVE NADA — ainda não foi emitido a ninguém.
     *
     * A lista oferecia-lhe «Receber», e o ecrã do recibo não conseguia sequer
     * escolher a factura: a API dos recibos recusa rascunhos. Era um botão que
     * levava a lado nenhum, e o rascunho contava como dinheiro a haver.
     *
     * @test
     */
    public function um_rascunho_nao_conta_como_dinheiro_a_receber(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->factura(['status' => 'draft', 'total' => 5000, 'paid_amount' => 0]);

        $r = $this->getJson(self::LISTA)->assertOk();

        $this->assertEquals(0, $r->json('data.0.saldo'), 'um rascunho não deve nada');
        $this->assertFalse($r->json('data.0.pode_receber'), 'e não se recebe contra um rascunho');
        $this->assertEquals(0, $r->json('meta.somas.por_receber'), 'nem entra na soma do cartão');
    }
}
