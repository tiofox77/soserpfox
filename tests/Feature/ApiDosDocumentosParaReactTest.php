<?php

namespace Tests\Feature;

use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\SalesQuote;
use App\Models\User;
use App\Services\Invoicing\TiposDeDocumento;
use Tests\TenantTestCase;

/**
 * A lista genérica que serve CINCO documentos.
 *
 * O risco de um controlador genérico é ser genérico também nas permissões: uma
 * rota só que serve cinco tabelas é uma rota só que as pode abrir todas. Estes
 * ensaios provam que não — cada tipo exige a SUA permissão, e o escopo por
 * autor continua a valer em todos.
 */
class ApiDosDocumentosParaReactTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function rota(string $tipo): string
    {
        return "/api/v1/invoicing/react/documentos/{$tipo}";
    }

    private function orcamento(array $por = []): SalesQuote
    {
        return SalesQuote::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'quote_number' => 'ORC/' . random_int(1000, 9999),
            'quote_date' => now()->toDateString(),
            'status' => 'draft',
            'total' => 100,
            'created_by' => $this->user->id,
        ], $por));
    }

    private function fornecedor(): \App\Models\Supplier
    {
        return \App\Models\Supplier::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Fornecedor de Ensaio'],
            ['nif' => (string) random_int(500000000, 599999999), 'is_active' => true]
        );
    }

    private function compra(array $por = []): PurchaseInvoice
    {
        return PurchaseInvoice::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->fornecedor()->id,
            'invoice_number' => 'FC/' . random_int(1000, 9999),
            'invoice_date' => now()->toDateString(),
            'status' => 'pending',
            'total' => 5000,
            'paid_amount' => 0,
            'created_by' => $this->user->id,
        ], $por));
    }

    /** @test */
    public function um_tipo_inventado_da_404(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $this->getJson($this->rota('facturas-secretas'))->assertNotFound();
    }

    /**
     * CADA TIPO EXIGE A SUA PERMISSÃO.
     *
     * Quem pode ver proformas de venda não passa a ver facturas de compra por
     * a rota ser a mesma.
     *
     * @test
     */
    public function a_permissao_e_por_tipo_e_nao_pela_rota(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view');

        $this->getJson($this->rota('proformas-venda'))->assertOk();

        foreach (['orcamentos', 'facturas-compra', 'proformas-compra', 'recibos'] as $outro) {
            $this->getJson($this->rota($outro))->assertForbidden();
            $this->getJson($this->rota($outro) . '/opcoes')->assertForbidden();
        }
    }

    /** Os cinco tipos do registo respondem, cada um com a sua permissão. @test */
    public function os_cinco_tipos_respondem(): void
    {
        foreach (TiposDeDocumento::todos() as $slug => $def) {
            $this->comPermissoes($def['permissao']);

            $this->getJson($this->rota($slug))
                ->assertOk()
                ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);

            $this->getJson($this->rota($slug) . '/opcoes')
                ->assertOk()
                ->assertJsonPath('parte', $def['parte'])
                ->assertJsonPath('tem_saldo', $def['tem_saldo']);
        }
    }

    /** O escopo por autor vale aqui também. @test */
    public function quem_so_ve_os_seus_nao_ve_os_do_colega(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $colega = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $meu = $this->orcamento();
        $dele = $this->orcamento(['created_by' => $colega->id]);

        $ids = collect($this->getJson($this->rota('orcamentos'))->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($meu->id));
        $this->assertFalse($ids->contains($dele->id), 'o orçamento do colega não pode aparecer');
    }

    /** A factura de compra é a única com saldo, e mostra o que FALTA. @test */
    public function so_a_factura_de_compra_traz_saldo(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view', 'invoicing.sales.proformas.view');

        $f = $this->compra(['total' => 5000, 'paid_amount' => 2000]);

        $linha = collect($this->getJson($this->rota('facturas-compra'))->json('data'))
            ->firstWhere('id', $f->id);

        $this->assertEqualsWithDelta(3000, $linha['saldo'], 0.01, 'mostra o que falta, não o total');

        SalesProforma::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'proforma_number' => 'PRF/' . random_int(1000, 9999),
            'proforma_date' => now()->toDateString(),
            'status' => 'draft',
            'total' => 100,
            'created_by' => $this->user->id,
        ]);

        $daProforma = collect($this->getJson($this->rota('proformas-venda'))->json('data'))->first();

        $this->assertArrayNotHasKey('saldo', $daProforma, 'uma proforma não tem pagamentos');
    }

    /** Uma compra paga não fica a dever, seja qual for o `paid_amount`. @test */
    public function uma_compra_paga_nao_aparece_a_dever(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view');

        $f = $this->compra(['status' => 'paid', 'total' => 5000, 'paid_amount' => 0]);

        $linha = collect($this->getJson($this->rota('facturas-compra'))->json('data'))
            ->firstWhere('id', $f->id);

        $this->assertSame(0, (int) $linha['saldo']);
    }

    /** Os estados oferecidos no filtro são os que a tabela TEM. @test */
    public function os_estados_do_filtro_saem_dos_dados(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $this->orcamento(['status' => 'accepted']);

        $estados = collect($this->getJson($this->rota('orcamentos') . '/opcoes')->json('estados'))
            ->pluck('valor');

        $this->assertTrue($estados->contains('accepted'));
        $this->assertFalse($estados->contains('overdue'), 'um orçamento não vence');
    }

    /** A procura encontra pelo número e pelo nome da outra parte. @test */
    public function a_procura_apanha_o_numero_e_a_outra_parte(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $o = $this->orcamento(['quote_number' => 'ORC PROCURA/0001']);

        $ids = collect($this->getJson($this->rota('orcamentos') . '?procura=PROCURA')->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($o->id));

        $porCliente = collect(
            $this->getJson($this->rota('orcamentos') . '?procura=' . urlencode($o->client->name))->json('data')
        )->pluck('id');

        $this->assertTrue($porCliente->contains($o->id));
    }

    /* ─── O que as listas em Blade tinham e faltava ───────────────────── */

    /**
     * A COLUNA DO PRAZO: validade nas propostas, vencimento nas compras.
     *
     * A lista em Blade tinha-a, e não é decoração — uma proposta caducada não
     * se converte, e uma compra vencida é dinheiro em atraso. Quem decide que
     * o prazo passou é o SERVIDOR, com o relógio dele.
     *
     * @test
     */
    public function o_prazo_vem_na_linha_e_diz_se_ja_passou(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view', 'invoicing.purchases.invoices.view');

        $caducado = $this->orcamento(['valid_until' => now()->subWeek()->toDateString()]);
        $valido = $this->orcamento(['valid_until' => now()->addWeek()->toDateString()]);

        $this->getJson($this->rota('orcamentos') . '/opcoes')->assertOk()
            ->assertJsonPath('prazo', 'Validade');

        $linhas = collect($this->getJson($this->rota('orcamentos'))->assertOk()->json('data'))
            ->keyBy('id');

        $this->assertTrue($linhas[$caducado->id]['expirado'], 'a validade passada tem de aparecer expirada');
        $this->assertFalse($linhas[$valido->id]['expirado']);

        // Na compra o mesmo campo chama-se «Vencimento» — é o esquema que sabe.
        $this->getJson($this->rota('facturas-compra') . '/opcoes')->assertOk()
            ->assertJsonPath('prazo', 'Vencimento');
    }

    /**
     * AS NOTAS TRAZEM A FACTURA DE ORIGEM E O MOTIVO.
     *
     * A lista em Blade tinha-os em coluna própria: uma nota de crédito sem
     * saber de que factura é não se lê, e uma de devolução conta uma história
     * diferente de uma de correcção.
     *
     * @test
     */
    public function as_notas_trazem_a_factura_de_origem_e_o_motivo(): void
    {
        $this->comPermissoes('invoicing.credit-notes.view');

        $factura = \App\Models\Invoicing\SalesInvoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT ORIGEM/0001',
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'total' => 5000,
            'created_by' => $this->user->id,
        ]);

        $nota = \App\Models\Invoicing\CreditNote::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $factura->client_id,
            'invoice_id' => $factura->id,
            'credit_note_number' => 'NC/0001',
            'issue_date' => now()->toDateString(),
            'status' => 'issued',
            'reason' => 'return',
            'total' => 1000,
            'created_by' => $this->user->id,
        ]);

        $opcoes = $this->getJson($this->rota('notas-credito') . '/opcoes')->assertOk();

        $this->assertSame('Fatura Origem', $opcoes->json('origem'));
        $this->assertContains('return', collect($opcoes->json('motivos'))->pluck('valor')->all());

        $linha = collect($this->getJson($this->rota('notas-credito'))->assertOk()->json('data'))
            ->firstWhere('id', $nota->id);

        $this->assertSame($factura->id, $linha['origem']['id']);
        $this->assertSame('Devolução', $linha['motivo_rotulo']);

        // E o FILTRO do motivo separa-as.
        $ids = collect($this->getJson($this->rota('notas-credito') . '?motivo=return')->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($nota->id));

        $ids = collect($this->getJson($this->rota('notas-credito') . '?motivo=discount')->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($nota->id));
    }

    /**
     * O FILTRO DO MOTIVO SÓ EXISTE ONDE HÁ MOTIVOS.
     *
     * Num orçamento é recusado em vez de ignorado: ignorado em silêncio,
     * devolvia a lista toda e fazia acreditar que filtrava.
     *
     * @test
     */
    public function o_motivo_nao_se_aceita_onde_nao_existe(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $this->getJson($this->rota('orcamentos') . '?motivo=return')
            ->assertStatus(422)->assertJsonValidationErrors('motivo');

        $this->getJson($this->rota('orcamentos') . '/opcoes')->assertOk()
            ->assertJsonPath('motivos', [])
            ->assertJsonPath('origem', null);
    }

    /**
     * CONVERTER UMA PROPOSTA EM FACTURA — o botão que a lista em Blade tinha e
     * a migração não trouxe, nem no ecrã nem na API.
     *
     * A factura nasce em RASCUNHO: converter não é emitir.
     *
     * @test
     */
    public function converter_um_orcamento_faz_nascer_uma_factura_em_rascunho(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $o = $this->orcamento();

        // SEM A PERMISSÃO DE FACTURAR, não converte: nasce uma factura, e quem
        // não pode facturar não a faz nascer por este atalho.
        $this->postJson($this->rota('orcamentos') . '/' . $o->id . '/converter')->assertForbidden();

        $this->comPermissoes('invoicing.sales.invoices.create');

        $r = $this->postJson($this->rota('orcamentos') . '/' . $o->id . '/converter')->assertOk();

        $id = $r->json('factura.id');

        $this->assertDatabaseHas('invoicing_sales_invoices', [
            'id' => $id,
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
        ]);

        // E o histórico passa a mostrá-la.
        $h = $this->getJson($this->rota('orcamentos') . '/' . $o->id . '/historico')->assertOk();

        $this->assertCount(1, $h->json('facturas'));
        $this->assertSame($id, $h->json('facturas.0.id'));
    }

    /** Um documento que não se converte responde 404, e não uma factura vazia. @test */
    public function um_documento_que_nao_se_converte_recusa(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view', 'invoicing.sales.invoices.create');

        $c = $this->compra();

        $this->postJson($this->rota('facturas-compra') . '/' . $c->id . '/converter')->assertNotFound();
        $this->getJson($this->rota('facturas-compra') . '/' . $c->id . '/historico')->assertNotFound();
    }

    /**
     * ELIMINAR — e não o que já foi convertido.
     *
     * Uma proposta convertida em factura não se elimina: a factura aponta para
     * ela e ficaria a referir um documento que já não existe.
     *
     * @test
     */
    public function eliminar_respeita_a_permissao_e_o_que_ja_foi_convertido(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $o = $this->orcamento();

        // Sem a permissão de apagar, o botão nem devia aparecer — e a porta
        // recusa na mesma, que é a guarda que conta.
        $this->getJson($this->rota('orcamentos') . '/opcoes')->assertOk()
            ->assertJsonPath('pode_apagar', false);

        $this->deleteJson($this->rota('orcamentos') . '/' . $o->id)->assertForbidden();

        $this->comPermissoes('invoicing.sales.quotes.delete');

        $this->getJson($this->rota('orcamentos') . '/opcoes')->assertOk()
            ->assertJsonPath('pode_apagar', true);

        // Um já convertido não se apaga, e diz porquê.
        $convertido = $this->orcamento(['status' => 'converted']);

        $this->deleteJson($this->rota('orcamentos') . '/' . $convertido->id)->assertStatus(422);
        $this->assertNotNull(SalesQuote::find($convertido->id));

        // O que ainda dá, apaga-se.
        $this->deleteJson($this->rota('orcamentos') . '/' . $o->id)->assertOk();
        $this->assertNull(SalesQuote::find($o->id));
    }

    /**
     * A FICHA DO DOCUMENTO — o modal de ver que a lista em Blade tinha.
     *
     * Quem só quer conferir não abre o editor (onde se estraga um documento
     * por engano) nem a pré-visualização, que é uma página inteira feita para
     * imprimir.
     *
     * @test
     */
    public function a_ficha_do_documento_traz_o_cabecalho_as_linhas_e_os_totais(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $o = $this->orcamento([
            'valid_until' => now()->addWeek()->toDateString(),
            'subtotal' => 1000,
            'tax_amount' => 140,
            'total' => 1140,
            'notes' => 'Entrega em Viana.',
        ]);

        \App\Models\Invoicing\SalesQuoteItem::create([
            'sales_quote_id' => $o->id,
            'product_id' => $this->produtoComStock()->id,
            'product_name' => 'Artigo da ficha',
            'description' => 'Artigo da ficha',
            'quantity' => 2,
            'unit' => 'UN',
            'unit_price' => 500,
            'tax_rate' => 14,
            'subtotal' => 1000,
            'total' => 1140,
        ]);

        $f = $this->getJson($this->rota('orcamentos') . '/' . $o->id)->assertOk()->json();

        $this->assertSame($o->client->name, $f['parte']['nome']);
        $this->assertSame($o->client->nif, $f['parte']['nif']);
        $this->assertSame('Validade', $f['prazo_rotulo']);
        $this->assertSame('Entrega em Viana.', $f['notas']);

        $this->assertTrue($f['tem_linhas']);
        $this->assertCount(1, $f['linhas']);
        $this->assertSame('Artigo da ficha', $f['linhas'][0]['descricao']);
        $this->assertEqualsWithDelta(2, $f['linhas'][0]['quantidade'], 0.001);
        $this->assertEqualsWithDelta(14, $f['linhas'][0]['taxa'], 0.01);

        $this->assertEqualsWithDelta(1000, $f['totais']['subtotal'], 0.01);
        $this->assertEqualsWithDelta(140, $f['totais']['imposto'], 0.01);
        $this->assertEqualsWithDelta(1140, $f['totais']['total'], 0.01);
    }

    /**
     * UM RECIBO NÃO TEM LINHAS — é dinheiro, não mercadoria.
     *
     * A ficha di-lo (`tem_linhas` a falso) para o ecrã não desenhar uma tabela
     * vazia, que faria acreditar que as linhas se perderam.
     *
     * @test
     */
    public function a_ficha_de_um_recibo_diz_que_nao_tem_linhas(): void
    {
        $this->comPermissoes('invoicing.receipts.view');

        $recibo = \App\Models\Invoicing\Receipt::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->clienteEmpresa()->id,
            'receipt_number' => 'RC FICHA/0001',
            'payment_date' => now()->toDateString(),
            'status' => 'issued',
            'amount_paid' => 2500,
            'created_by' => $this->user->id,
        ]);

        $f = $this->getJson($this->rota('recibos') . '/' . $recibo->id)->assertOk()->json();

        $this->assertFalse($f['tem_linhas']);
        $this->assertSame([], $f['linhas']);
        $this->assertEqualsWithDelta(2500, $f['totais']['total'], 0.01);
    }

    /** A ficha segue a permissão do tipo e o escopo da empresa. @test */
    public function a_ficha_segue_a_permissao_e_a_empresa(): void
    {
        $o = $this->orcamento();

        $this->getJson($this->rota('orcamentos') . '/' . $o->id)->assertForbidden();

        $this->comPermissoes('invoicing.sales.quotes.view');

        $this->getJson($this->rota('orcamentos') . '/999999')->assertNotFound();
    }

    /** Uma factura de compra não se elimina por aqui: anula-se. @test */
    public function a_factura_de_compra_nao_se_elimina_por_aqui(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view', 'invoicing.purchases.invoices.delete');

        $c = $this->compra();

        $this->deleteJson($this->rota('facturas-compra') . '/' . $c->id)->assertNotFound();
        $this->assertNotNull(PurchaseInvoice::find($c->id));
    }

    /*
     * ─── O RECIBO TEM DOIS LADOS ───────────────────────────────────────────
     *
     * Um recibo de venda recebe de um CLIENTE; um de compra paga a um
     * FORNECEDOR, e nesse o `client_id` fica vazio. A lista lia sempre o
     * cliente — e metade dos recibos aparecia sem nome nenhum.
     */

    private function recibo(string $lado, array $por = []): \App\Models\Invoicing\Receipt
    {
        $de = $lado === 'purchase'
            ? ['supplier_id' => $this->fornecedor()->id, 'client_id' => null]
            : ['client_id' => $this->clienteEmpresa()->id, 'supplier_id' => null];

        return \App\Models\Invoicing\Receipt::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'type' => $lado,
            'receipt_number' => 'RC/' . random_int(1000, 9999),
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount_paid' => 2500,
            'status' => 'issued',
            'created_by' => $this->user->id,
        ], $de, $por));
    }

    /** @test */
    public function o_recibo_de_compra_mostra_o_fornecedor_e_nao_fica_sem_nome(): void
    {
        $this->comPermissoes('invoicing.receipts.view');

        $this->recibo('purchase');

        $linha = $this->getJson($this->rota('recibos'))->assertOk()->json('data.0');

        $this->assertSame('Fornecedor de Ensaio', $linha['parte'], 'a parte de um recibo de compra é o fornecedor');
        $this->assertSame('purchase', $linha['lado']['valor']);
        $this->assertSame('Compra', $linha['lado']['rotulo']);
    }

    /** @test */
    public function o_recibo_de_venda_continua_a_mostrar_o_cliente(): void
    {
        $this->comPermissoes('invoicing.receipts.view');

        $this->recibo('sale');

        $linha = $this->getJson($this->rota('recibos'))->assertOk()->json('data.0');

        $this->assertSame($this->clienteEmpresa()->name, $linha['parte']);
        $this->assertSame('sale', $linha['lado']['valor']);
        $this->assertSame('Venda', $linha['lado']['rotulo']);
    }

    /** @test */
    public function o_filtro_do_lado_separa_o_que_entrou_do_que_saiu(): void
    {
        $this->comPermissoes('invoicing.receipts.view');

        $this->recibo('sale');
        $this->recibo('purchase');
        $this->recibo('purchase');

        $this->assertCount(3, $this->getJson($this->rota('recibos'))->json('data'));
        $this->assertCount(1, $this->getJson($this->rota('recibos') . '?lado=sale')->json('data'));
        $this->assertCount(2, $this->getJson($this->rota('recibos') . '?lado=purchase')->json('data'));
    }

    /** A procura tem de passar pelo fornecedor, senão não acha o de compra. @test */
    public function procurar_pelo_nome_encontra_tambem_o_recibo_de_compra(): void
    {
        $this->comPermissoes('invoicing.receipts.view');

        $this->recibo('purchase');

        $achados = $this->getJson($this->rota('recibos') . '?procura=Fornecedor de Ensaio')->assertOk()->json('data');

        $this->assertCount(1, $achados);
    }

    /** Onde o documento tem um lado só, o filtro é RECUSADO e não ignorado. @test */
    public function o_filtro_do_lado_e_recusado_onde_o_documento_nao_tem_dois(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view');

        $this->orcamento();

        $this->getJson($this->rota('orcamentos') . '?lado=sale')
            ->assertStatus(422)
            ->assertJsonValidationErrors('lado');
    }

    /** @test */
    public function as_opcoes_dos_recibos_trazem_os_lados_e_o_cabecalho_da_parte(): void
    {
        $this->comPermissoes('invoicing.receipts.view');

        $o = $this->getJson($this->rota('recibos') . '/opcoes')->assertOk();

        $this->assertSame('Tipo', $o->json('lados.rotulo'));
        $this->assertSame(['sale', 'purchase'], array_column($o->json('lados.opcoes'), 'valor'));
        $this->assertSame('Cliente/Fornecedor', $o->json('parte_rotulo'));

        // E nos outros não há lados nenhuns — o ecrã não desenha a coluna.
        $this->comPermissoes('invoicing.sales.quotes.view');
        $this->assertNull($this->getJson($this->rota('orcamentos') . '/opcoes')->json('lados'));
        $this->assertNull($this->getJson($this->rota('orcamentos') . '/opcoes')->json('parte_rotulo'));
    }

    /** A ficha do recibo de compra também mostra o fornecedor. @test */
    public function a_ficha_do_recibo_de_compra_mostra_o_fornecedor(): void
    {
        $this->comPermissoes('invoicing.receipts.view');

        $r = $this->recibo('purchase');

        $this->getJson($this->rota('recibos') . '/' . $r->id)
            ->assertOk()
            ->assertJsonPath('parte.nome', 'Fornecedor de Ensaio');
    }

    /*
     * ─── O USADO E O DISPONÍVEL DOS ADIANTAMENTOS ──────────────────────────
     *
     * Um adiantamento não é um documento de valor fixo: é um saldo que se vai
     * gastando à medida que as facturas o consomem. A lista de sempre tinha as
     * duas colunas, e sem elas não se sabe o que ainda lá está.
     */

    private function adiantamento(array $por = []): \App\Models\Invoicing\Advance
    {
        return \App\Models\Invoicing\Advance::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'type' => 'sale',
            'client_id' => $this->clienteEmpresa()->id,
            'advance_number' => 'ADT/' . random_int(1000, 9999),
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => 10000,
            'used_amount' => 4000,
            'remaining_amount' => 6000,
            'status' => 'available',
            'created_by' => $this->user->id,
        ], $por));
    }

    /** @test */
    public function o_adiantamento_traz_o_usado_e_o_disponivel(): void
    {
        $this->comPermissoes('invoicing.advances.view');

        $this->adiantamento();

        $linha = $this->getJson($this->rota('adiantamentos'))->assertOk()->json('data.0');

        $this->assertEquals(10000, $linha['valor']);
        $this->assertEquals(4000, $linha['montantes']['used_amount']);
        $this->assertEquals(6000, $linha['montantes']['remaining_amount']);
    }

    /** @test */
    public function as_opcoes_dizem_que_colunas_de_valor_este_documento_tem(): void
    {
        $this->comPermissoes('invoicing.advances.view');

        $o = $this->getJson($this->rota('adiantamentos') . '/opcoes')->assertOk();

        $this->assertSame(
            ['used_amount', 'remaining_amount'],
            array_column($o->json('montantes'), 'chave')
        );
        $this->assertSame(['Usado', 'Disponível'], array_column($o->json('montantes'), 'rotulo'));

        // E os outros documentos não têm nenhuma — a coluna não aparece.
        $this->comPermissoes('invoicing.sales.quotes.view');
        $this->assertSame([], $this->getJson($this->rota('orcamentos') . '/opcoes')->json('montantes'));
    }
}
