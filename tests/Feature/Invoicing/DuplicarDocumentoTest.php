<?php

namespace Tests\Feature\Invoicing;

use App\Models\Client;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\SalesProforma;
use App\Models\Invoicing\SalesProformaItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * Duplicar um documento: aproveitar o trabalho, não a identidade.
 *
 * O ganho é óbvio — quem factura o mesmo cliente todos os meses deixa de
 * reescrever doze linhas. O RISCO é que não é óbvio, e é caro: se o duplicado
 * herdar o número, a série ou o hash do original, nascem dois documentos com a
 * mesma identidade fiscal. Duas realidades para a mesma venda.
 *
 * ─── COMO ISTO ESTÁ FEITO ─────────────────────────────────────────────────
 *
 * `GET .../duplicar` NÃO GRAVA NADA. Devolve o CONTEÚDO COMERCIAL do original
 * — cliente, armazém, linhas, preços, descontos, notas — e é o editor que o
 * abre como documento novo, em branco. Ninguém fica com um rascunho fantasma
 * por ter carregado no botão errado, e o número só é pedido à série quando a
 * pessoa gravar. O que viaja e o que fica decide-se no `DuplicaDocumento`.
 *
 * Estes ensaios guardam as duas metades: o que TEM de vir (senão duplicar não
 * serve para nada) e o que NUNCA pode vir (senão duplicar é um defeito).
 */
class DuplicarDocumentoTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    private Product $artigo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing')->comPermissoes(
            'invoicing.sales.invoices.view',
            'invoicing.sales.invoices.create',
        );

        $this->artigo = Product::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Artigo a duplicar',
            'code'         => 'ART-DUP',
            'price'        => 2500,
            'type'         => 'produto',
            'manage_stock' => false,
            'is_active'    => true,
        ]);
    }

    private function facturaEmitida(): SalesInvoice
    {
        $factura = SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'warehouse_id'   => $this->armazem->id,
            'created_by'     => $this->user->id,
            'invoice_number' => 'FT A/000042',
            'invoice_type'   => 'FT',
            'invoice_date'   => now()->subMonths(3),
            'due_date'       => now()->subMonths(3)->addDays(30),
            'status'         => 'paid',
            'notes'          => 'Avença mensal',
            'terms'          => 'Pagamento a 30 dias',
            'subtotal'       => 5000,
            'tax_amount'     => 700,
            'total'          => 5700,
            'paid_amount'    => 5700,
            'discount_commercial' => 250,
            // A identidade fiscal, que é o que não pode viajar.
            'saft_hash'      => 'HASH-DO-ORIGINAL',
            'hash'           => 'HASH-DO-ORIGINAL',
            'hash_previous'  => 'HASH-ANTERIOR',
            'atcud'          => 'ABC123-42',
            'hash_control'   => '1',
        ]);

        SalesInvoiceItem::create([
            'tenant_id'         => $this->tenant->id,
            'sales_invoice_id'  => $factura->id,
            'product_id'        => $this->artigo->id,
            'product_name'      => $this->artigo->name,
            'quantity'          => 2,
            'unit_price'        => 2500,
            'tax_rate'          => 14,
            'discount_percent'  => 0,
            'subtotal'          => 5000,
            'tax_amount'        => 700,
            'total'             => 5700,
        ]);

        return $factura->fresh();
    }

    /* ─── O editor que ABRE ────────────────────────────────────────────── */

    /**
     * O CONTEÚDO COMERCIAL É O QUE SE APROVEITA — e é o que a API entrega.
     *
     * Cliente, armazém, tipo e linhas: tudo o que uma duplicação copia já vem
     * por aqui, e é assim que ela o vai buscar.
     *
     * @test
     */
    public function abrir_uma_factura_entrega_o_conteudo_comercial(): void
    {
        $origem = $this->facturaEmitida();

        $r = $this->getJson(self::RAIZ . '/factura/' . $origem->id)->assertOk();

        $this->assertSame($origem->client_id, $r->json('documento.client_id'));
        $this->assertSame($origem->warehouse_id, $r->json('documento.warehouse_id'));
        $this->assertSame('FT', $r->json('documento.invoice_type'));
        $this->assertCount(1, $r->json('linhas'));
        $this->assertEqualsWithDelta(2, $r->json('linhas.0.quantity'), 0.001);
        $this->assertEqualsWithDelta(2500, $r->json('linhas.0.price'), 0.01);
    }

    /**
     * O ENSAIO QUE IMPORTA.
     *
     * Nenhum campo que dê identidade fiscal ao original pode chegar ao editor
     * como coisa editável. Não é uma questão de arrumação: dois documentos com
     * o mesmo número são duas verdades para a mesma venda, e a AGT vê as duas.
     *
     * Varre-se a resposta INTEIRA e não uma lista escolhida a dedo — um campo
     * novo que um dia passe a trazer o hash do original faz isto falhar sem
     * ninguém se lembrar de o acrescentar aqui.
     *
     * @test
     */
    public function a_identidade_fiscal_nao_viaja_para_o_editor(): void
    {
        $origem = $this->facturaEmitida();

        $r = $this->getJson(self::RAIZ . '/factura/' . $origem->id)->assertOk();

        foreach (['HASH-DO-ORIGINAL', 'HASH-ANTERIOR', 'ABC123-42'] as $marca) {
            $this->assertStringNotContainsString($marca, $r->content(),
                "A resposta do editor trouxe `{$marca}` — identidade fiscal do documento original.");
        }

        // O número aparece, mas como ETIQUETA do documento aberto, e não
        // dentro de nada que se volte a gravar.
        $this->assertSame('FT A/000042', $r->json('documento.numero'));

        $editaveis = $r->json('documento');
        unset($editaveis['numero'], $editaveis['pdf']);

        $this->assertStringNotContainsString('FT A/000042', json_encode($editaveis),
            'o número do original não pode estar em nenhum campo editável');
    }

    /**
     * E O TRAVÃO POR TRÁS DE TUDO: uma factura emitida não se volta a gravar.
     *
     * Mesmo que um duplicado errado herdasse o id, o servidor recusa escrever
     * por cima de um documento já assinado — o original não desaparece sem uma
     * palavra, como desapareceria se o `isEdit` viesse ligado.
     *
     * @test
     */
    public function uma_factura_emitida_nao_se_grava_por_cima(): void
    {
        $origem = $this->facturaEmitida();

        $this->assertFalse(
            $this->getJson(self::RAIZ . '/factura/' . $origem->id)->json('documento.pode_editar'),
            'emitida, só se lê'
        );

        $this->putJson(self::RAIZ . '/factura/' . $origem->id, [
            'client_id'    => $origem->client_id,
            'warehouse_id' => $this->armazem->id,
            'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(),
            'linhas'       => [['product_id' => $this->artigo->id, 'quantity' => 9, 'price' => 2500]],
        ])->assertStatus(422);

        $this->assertSame('FT A/000042', $origem->fresh()->invoice_number);
        $this->assertEqualsWithDelta(5700, (float) $origem->fresh()->total, 0.01);
    }

    /* ─── DUPLICAR, propriamente dito ──────────────────────────────────── */

    /**
     * O QUE SE APROVEITA: cliente, armazém, tipo, descontos, notas e linhas.
     *
     * É todo o ganho da funcionalidade. Sem isto, «duplicar» abria um
     * formulário vazio e quem o carregasse voltava a escrever tudo.
     *
     * @test
     */
    public function duplicar_traz_o_conteudo_comercial_da_factura(): void
    {
        $origem = $this->facturaEmitida();

        $r = $this->getJson(self::RAIZ . '/factura/' . $origem->id . '/duplicar')->assertOk();

        $this->assertSame($origem->client_id, $r->json('documento.client_id'));
        $this->assertSame($origem->warehouse_id, $r->json('documento.warehouse_id'));
        $this->assertSame('FT', $r->json('documento.invoice_type'));
        $this->assertSame('Avença mensal', $r->json('documento.notes'));
        $this->assertEqualsWithDelta(250, $r->json('documento.discount_commercial'), 0.01);

        $this->assertCount(1, $r->json('linhas'));
        $this->assertSame($this->artigo->id, $r->json('linhas.0.product_id'));
        $this->assertEqualsWithDelta(2, $r->json('linhas.0.quantity'), 0.001);
        $this->assertEqualsWithDelta(2500, $r->json('linhas.0.price'), 0.01);

        // E o ecrã sabe de onde isto veio, para o poder dizer a quem duplicou.
        $this->assertSame($origem->id, $r->json('origem.id'));
        $this->assertSame('FT A/000042', $r->json('origem.numero'));
    }

    /**
     * A IDENTIDADE FISCAL NÃO VIAJA — e varre-se a resposta inteira.
     *
     * O `documento` que o editor vai gravar não pode conter o número, o hash,
     * a cadeia nem o ATCUD do original em campo nenhum, chame-se ele como se
     * chamar. Um campo novo que um dia os traga faz isto falhar.
     *
     * @test
     */
    public function duplicar_nunca_traz_numero_hash_serie_nem_atcud(): void
    {
        $origem = $this->facturaEmitida();

        $r = $this->getJson(self::RAIZ . '/factura/' . $origem->id . '/duplicar')->assertOk();

        $paraGravar = json_encode($r->json('documento'));

        foreach (['FT A/000042', 'HASH-DO-ORIGINAL', 'HASH-ANTERIOR', 'ABC123-42'] as $marca) {
            $this->assertStringNotContainsString($marca, $paraGravar,
                "O duplicado traz `{$marca}` — identidade fiscal do original.");
        }

        // E nem sequer há campos por onde isso pudesse passar.
        foreach (['id', 'numero', 'estado', 'series_id', 'pdf', 'hash', 'saft_hash', 'atcud', 'paid_amount'] as $campo) {
            $this->assertArrayNotHasKey($campo, $r->json('documento'),
                "O duplicado devolveu o campo `{$campo}`, que dá identidade ao original.");
        }
    }

    /**
     * O DUPLICADO É DE HOJE.
     *
     * Herdar a data de emissão de um documento de há três meses punha-o num
     * período fiscal que já fechou — e o vencimento sai outra vez da condição
     * de pagamento do cliente, não do prazo que a factura antiga tinha.
     *
     * @test
     */
    public function o_duplicado_nasce_com_a_data_de_hoje(): void
    {
        $origem = $this->facturaEmitida();

        $r = $this->getJson(self::RAIZ . '/factura/' . $origem->id . '/duplicar')->assertOk();

        $this->assertSame(now()->toDateString(), $r->json('documento.invoice_date'));
        $this->assertNotSame(
            $origem->invoice_date->toDateString(),
            $r->json('documento.invoice_date'),
            'a data do original não pode viajar'
        );
        $this->assertNull($r->json('documento.delivery_date'));
    }

    /**
     * DUPLICAR NÃO GRAVA NADA.
     *
     * É a decisão que faz esta porta ser segura: quem carrega no botão e muda
     * de ideias não deixa um rascunho fantasma para trás, e a série não é
     * consumida por um documento que ninguém quis.
     *
     * @test
     */
    public function duplicar_nao_cria_documento_nenhum(): void
    {
        $origem = $this->facturaEmitida();
        $antes = SalesInvoice::where('tenant_id', $this->tenant->id)->count();

        $this->getJson(self::RAIZ . '/factura/' . $origem->id . '/duplicar')->assertOk();

        $this->assertSame($antes, SalesInvoice::where('tenant_id', $this->tenant->id)->count(),
            'duplicar devolve conteúdo, não cria documentos');
    }

    /**
     * O CONTEÚDO DUPLICADO GRAVA-SE COMO DOCUMENTO NOVO, com número seu.
     *
     * A prova do circuito inteiro: o que o `duplicar` devolveu volta pela
     * porta de gravar e nasce uma factura diferente — o original fica onde
     * estava, com o número que sempre teve.
     *
     * @test
     */
    public function o_conteudo_duplicado_grava_uma_factura_nova(): void
    {
        $origem = $this->facturaEmitida();

        $copia = $this->getJson(self::RAIZ . '/factura/' . $origem->id . '/duplicar')->assertOk();

        $nova = $this->postJson(self::RAIZ . '/factura', array_merge($copia->json('documento'), [
            'linhas' => $copia->json('linhas'),
            'status' => 'draft',
        ]))->assertCreated();

        $this->assertNotSame($origem->id, $nova->json('id'));
        $this->assertNotSame('FT A/000042', $nova->json('numero'));

        $criada = SalesInvoice::findOrFail($nova->json('id'));
        $this->assertSame($origem->client_id, $criada->client_id);
        $this->assertSame(1, $criada->items()->count());
        $this->assertNotSame($origem->saft_hash, $criada->saft_hash);
        $this->assertEqualsWithDelta(0, (float) ($criada->paid_amount ?? 0), 0.01,
            'o duplicado de uma factura paga não nasce pago');

        // E o original continua exactamente como estava.
        $this->assertSame('FT A/000042', $origem->fresh()->invoice_number);
        $this->assertSame('paid', $origem->fresh()->status);
    }

    /** A factura de OUTRA EMPRESA não se duplica: nem se sabe que existe. @test */
    public function a_factura_de_outra_empresa_da_404(): void
    {
        $alheia = $this->facturaDeOutraEmpresa();

        $this->getJson(self::RAIZ . '/factura/' . $alheia->id . '/duplicar')->assertNotFound();
    }

    /** Duplicar é começar um documento novo: exige a permissão de CRIAR. @test */
    public function sem_permissao_de_criar_nao_se_duplica(): void
    {
        $origem = $this->facturaEmitida();

        $this->semPermissao('invoicing.sales.invoices.create');

        $this->getJson(self::RAIZ . '/factura/' . $origem->id . '/duplicar')->assertForbidden();

        // Ver continua a poder ver-se: o que se tirou foi o direito de criar.
        $this->getJson(self::RAIZ . '/factura/' . $origem->id)->assertOk();
    }

    /** Tira um direito ao utilizador do ensaio, venha ele de onde vier. */
    private function semPermissao(string $nome): void
    {
        setPermissionsTeamId($this->tenant->id);

        $p = \Spatie\Permission\Models\Permission::findOrCreate($nome, 'web');

        $this->user->revokePermissionTo($p);

        foreach ($this->user->roles as $papel) {
            $papel->revokePermissionTo($p);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->forgetCachedPermissions();
    }

    /* ─── Os outros documentos que a lista deixa duplicar ──────────────── */

    /** Uma proforma duplica-se pela mesma regra — e não herda o seu número. @test */
    public function duplicar_uma_proforma_traz_as_linhas_e_nao_o_numero(): void
    {
        $this->comPermissoes('invoicing.sales.proformas.view', 'invoicing.sales.proformas.create');

        $proforma = SalesProforma::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'created_by' => $this->user->id,
            'proforma_date' => now()->subMonths(2),
            'valid_until' => now()->subMonth(),
            'status' => 'sent',
            'notes' => 'Proposta anual',
            'subtotal' => 5000,
            'tax_amount' => 700,
            'total' => 5700,
        ]);

        SalesProformaItem::create([
            'sales_proforma_id' => $proforma->id,
            'product_id' => $this->artigo->id,
            'product_name' => $this->artigo->name,
            'quantity' => 2,
            'unit_price' => 2500,
            'tax_rate' => 14,
            'subtotal' => 5000,
            'tax_amount' => 700,
            'total' => 5700,
            'order' => 1,
        ]);

        $numero = $proforma->fresh()->proforma_number;

        $r = $this->getJson(self::RAIZ . '/emissor/proformas-venda/' . $proforma->id . '/duplicar')->assertOk();

        $this->assertSame($this->cliente->id, $r->json('documento.parte_id'));
        $this->assertSame('Proposta anual', $r->json('documento.notas'));
        $this->assertCount(1, $r->json('linhas'));
        $this->assertSame(now()->toDateString(), $r->json('documento.data'));
        $this->assertNull($r->json('documento.valido_ate'), 'a validade recomeça: duplicar não nasce expirado');
        $this->assertArrayNotHasKey('numero', $r->json('documento'));

        if ($numero) {
            $this->assertStringNotContainsString($numero, json_encode($r->json('documento')));
        }
    }

    /**
     * UM ORÇAMENTO NÃO SE DUPLICA POR AQUI — nem se duplicava em Blade.
     *
     * O tipo é editável e a porta é a mesma dos outros; o que a fecha é o
     * `TiposDeDocumento::duplicaveis`, para que a lista e a API digam o mesmo.
     *
     * @test
     */
    public function um_tipo_que_a_lista_nao_duplica_da_404(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.view', 'invoicing.sales.quotes.create');

        $orcamento = \App\Models\Invoicing\SalesQuote::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'created_by' => $this->user->id,
            'quote_date' => now(),
            'status' => 'draft',
            'subtotal' => 100,
            'tax_amount' => 14,
            'total' => 114,
        ]);

        $this->getJson(self::RAIZ . '/emissor/orcamentos/' . $orcamento->id . '/duplicar')->assertNotFound();
    }

    /**
     * A FACTURA DE COMPRA duplica-se sem que entre stock nenhum.
     *
     * É a garantia que separa duplicar de registar: o conteúdo copia-se, o
     * stock só se mexe quando o duplicado for mesmo gravado.
     *
     * @test
     */
    public function duplicar_uma_compra_nao_mexe_no_stock(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.view', 'invoicing.purchases.invoices.create');

        $artigo = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Artigo de compra',
            'type' => 'produto',
            'price' => 900,
            'cost' => 0,
            'unit' => 'UN',
            'tax_rate_id' => $this->imposto->id,
            'manage_stock' => true,
            'is_active' => true,
        ]);

        $id = $this->postJson(self::RAIZ . '/compra', [
            'supplier_id' => Supplier::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Fornecedor ' . uniqid(),
                'is_active' => true,
            ])->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_date' => now()->subMonth()->toDateString(),
            'status' => 'pending',
            'linhas' => [['product_id' => $artigo->id, 'quantity' => 6, 'price' => 500, 'batch_number' => 'L-1']],
        ])->assertCreated()->json('id');

        $stockAntes = (float) $artigo->fresh()->stock_quantity;
        $original = PurchaseInvoice::findOrFail($id);

        $r = $this->getJson(self::RAIZ . '/compra/' . $id . '/duplicar')->assertOk();

        $this->assertSame($original->supplier_id, $r->json('documento.supplier_id'));
        $this->assertSame(now()->toDateString(), $r->json('documento.invoice_date'));
        $this->assertCount(1, $r->json('linhas'));
        $this->assertSame('L-1', $r->json('linhas.0.batch_number'));
        $this->assertArrayNotHasKey('numero', $r->json('documento'));
        $this->assertStringNotContainsString((string) $original->invoice_number, json_encode($r->json('documento')));

        $this->assertEqualsWithDelta($stockAntes, (float) $artigo->fresh()->stock_quantity, 0.001,
            'duplicar não dá entrada de nada — o stock só se mexe ao gravar');
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function facturaDeOutraEmpresa(): SalesInvoice
    {
        $outra = Tenant::create([
            'name' => 'Vizinha',
            'slug' => 'viz-' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'email' => 'v' . uniqid() . '@x.ao',
            'is_active' => true,
        ]);

        $cliente = Client::create([
            'tenant_id' => $outra->id,
            'name' => 'Cliente da vizinha',
            'nif' => (string) random_int(100000000, 199999999),
            'is_active' => true,
        ]);

        return SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id,
            'client_id' => $cliente->id,
            'created_by' => $this->user->id,
            'invoice_number' => 'FT B/000001',
            'invoice_type' => 'FT',
            'invoice_date' => now(),
            'status' => 'sent',
            'subtotal' => 100,
            'tax_amount' => 14,
            'total' => 114,
        ]);
    }
}
