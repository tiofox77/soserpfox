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
        $this->get('/invoicing/sales/invoices/novo-ecra')->assertForbidden();

        $this->comPermissoes('invoicing.sales.invoices.view');

        $this->get('/invoicing/sales/invoices/novo-ecra')
            ->assertOk()
            ->assertSee('data-ecra="facturacao/lista-de-facturas"', false);
    }
}
