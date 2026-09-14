<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Supplier;
use Tests\TenantTestCase;

/**
 * «IMPRIMIR AUTOMATICAMENTE AO GRAVAR», nos editores em React.
 *
 * O interruptor das definições da facturação gravava-se e nenhum ecrã em
 * React o lia — perdeu-se na migração do Livewire. Estes ensaios guardam as
 * duas metades de que o ecrã precisa para o voltar a cumprir:
 *
 * 1. AS OPÇÕES DE CADA EDITOR DIZEM SE ESTÁ LIGADO (`imprimir_ao_gravar`), e
 *    dizem-no conforme a definição DESTA empresa — não um valor fixo.
 * 2. A RESPOSTA DE GRAVAR TRAZ A MORADA DO PAPEL (`pdf`) e o estado com que o
 *    documento ficou: sem a morada não há o que abrir, e sem o estado o ecrã
 *    não distingue um rascunho (que não se imprime) de um emitido.
 */
class ImprimirAoGravarNosEditoresTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');
    }

    private function imprimirAoGravar(bool $ligado): void
    {
        InvoicingSettings::forTenant($this->tenant->id)->update(['auto_print_after_save' => $ligado]);
        // As definições ficam em memória no pedido; o ensaio mudou-as por fora.
        InvoicingSettings::esquecerMemoria($this->tenant->id);
    }

    private function artigo(bool $comIva = true): Product
    {
        $categoria = Category::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Geral'], ['is_active' => true]);
        $taxa = $comIva ? Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'], ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']) : null;

        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => 'produto', 'price' => 1000, 'cost' => 600,
            'unit' => 'un', 'category_id' => $categoria->id, 'tax_type' => $comIva ? 'iva' : 'isento',
            'tax_rate_id' => $taxa?->id, 'exemption_reason' => $comIva ? null : 'M99',
            'manage_stock' => true, 'stock_quantity' => 100, 'is_active' => true,
        ]);
    }

    /* ─── As opções ───────────────────────────────────────────────────── */

    /**
     * AS OPÇÕES DE TODOS OS EDITORES SEGUEM A DEFINIÇÃO DA EMPRESA.
     *
     * Desligado por omissão (é o que uma empresa nova tem); ligado, acende em
     * todos — propostas, factura, notas, adiantamento e compra.
     *
     * @test
     */
    public function as_opcoes_de_cada_editor_dizem_se_se_imprime_ao_gravar(): void
    {
        $this->comPermissoes(
            'invoicing.sales.proformas.view',
            'invoicing.sales.quotes.view',
            'invoicing.purchases.proformas.view',
            'invoicing.sales.invoices.create',
            'invoicing.credit-notes.view',
            'invoicing.debit-notes.view',
            'invoicing.advances.create',
            'invoicing.purchases.invoices.create',
        );

        $opcoes = [
            '/emissor/proformas-venda/opcoes',
            '/emissor/orcamentos/opcoes',
            '/emissor/proformas-compra/opcoes',
            '/factura/opcoes',
            '/notas/credito/opcoes',
            '/notas/debito/opcoes',
            '/adiantamentos/opcoes',
            '/compra/opcoes',
        ];

        $this->imprimirAoGravar(false);

        foreach ($opcoes as $rota) {
            $this->getJson(self::RAIZ . $rota)->assertOk()->assertJsonPath('imprimir_ao_gravar', false);
        }

        $this->imprimirAoGravar(true);

        foreach ($opcoes as $rota) {
            $this->assertTrue(
                $this->getJson(self::RAIZ . $rota)->assertOk()->json('imprimir_ao_gravar'),
                "{$rota} tem de dizer que a empresa imprime ao gravar"
            );
        }
    }

    /* ─── O que volta de gravar ───────────────────────────────────────── */

    /**
     * A PROPOSTA GRAVADA TRAZ O PDF E A PRÉ-VISUALIZAÇÃO — e o estado.
     *
     * O ecrã de sucesso da proposta não tinha botão de PDF porque a resposta
     * não trazia a morada. Rascunho e enviada distinguem-se pelo `estado`.
     *
     * @test
     */
    public function gravar_uma_proposta_devolve_as_moradas_do_papel(): void
    {
        $this->comPermissoes('invoicing.sales.quotes.create');

        $corpo = [
            'parte_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'data' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 1000]],
        ];

        $rascunho = $this->postJson(self::RAIZ . '/emissor/orcamentos', $corpo)->assertCreated();
        $id = $rascunho->json('id');

        $rascunho->assertJsonPath('estado', 'draft')
            ->assertJsonPath('pdf', "/invoicing/sales/quotes/{$id}/pdf")
            ->assertJsonPath('preview', "/invoicing/sales/quotes/{$id}/preview");

        $enviada = $this->postJson(self::RAIZ . '/emissor/orcamentos', $corpo + ['estado' => 'sent'])->assertCreated();

        $enviada->assertJsonPath('estado', 'sent')
            ->assertJsonPath('pdf', '/invoicing/sales/quotes/' . $enviada->json('id') . '/pdf');
    }

    /** A morada do papel segue a rota de CADA tipo de proposta. @test */
    public function a_morada_do_papel_e_a_do_tipo_de_proposta(): void
    {
        $this->comPermissoes('invoicing.purchases.proformas.create');

        $fornecedor = Supplier::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Fornecedor ' . uniqid(), 'nif' => '5000000000',
            'type' => 'pessoa_juridica', 'is_active' => true,
        ]);

        $r = $this->postJson(self::RAIZ . '/emissor/proformas-compra', [
            'parte_id' => $fornecedor->id,
            'warehouse_id' => $this->armazem->id,
            'data' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 600]],
        ])->assertCreated();

        $r->assertJsonPath('pdf', '/invoicing/purchases/proformas/' . $r->json('id') . '/pdf');
    }

    /**
     * A FACTURA DIZ SE FOI EMITIDA OU FICOU EM RASCUNHO.
     *
     * É o que decide se o PDF abre sozinho: um rascunho ainda não é factura.
     *
     * @test
     */
    public function gravar_uma_factura_diz_o_estado_e_traz_o_pdf(): void
    {
        $this->comPermissoes('invoicing.sales.invoices.create');

        $corpo = fn (array $por = []) => array_merge([
            'client_id' => $this->clienteEmpresa()->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 1, 'price' => 1000]],
        ], $por);

        $emitida = $this->postJson(self::RAIZ . '/factura', $corpo())->assertCreated();

        $this->assertNotSame('draft', $emitida->json('estado'));
        $emitida->assertJsonPath('pdf', '/invoicing/sales/invoices/' . $emitida->json('id') . '/pdf');

        $this->postJson(self::RAIZ . '/factura', $corpo(['status' => 'draft']))
            ->assertCreated()
            ->assertJsonPath('estado', 'draft');
    }

    /** A nota emitida traz a morada do seu PDF — de crédito e de débito. @test */
    public function emitir_uma_nota_traz_o_pdf(): void
    {
        $this->comPermissoes('invoicing.credit-notes.create', 'invoicing.debit-notes.create');

        foreach ([['NC', 'credit_note'], ['ND', 'debit_note']] as [$codigo, $tipo]) {
            InvoicingSeries::create([
                'tenant_id' => $this->tenant->id, 'series_code' => $codigo, 'name' => "{$codigo} (ensaio)",
                'document_type' => $tipo, 'agt_environment' => 'sandbox', 'is_default' => true, 'is_active' => true,
            ]);
        }

        $artigo = $this->artigo();

        $f = SalesInvoice::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT/' . random_int(1000, 9999), 'invoice_date' => now()->toDateString(),
            'status' => 'sent', 'subtotal' => 2000, 'tax_amount' => 280, 'total' => 2280, 'paid_amount' => 0,
            'created_by' => $this->user->id,
        ]);

        $linha = SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id, 'product_id' => $artigo->id, 'product_name' => $artigo->name,
            'description' => $artigo->name, 'quantity' => 2, 'unit_price' => 1000, 'subtotal' => 2000,
            'tax_rate' => 14, 'tax_amount' => 280, 'total' => 2280, 'tax_code' => 'NOR',
            'tax_country_region' => 'AO', 'order' => 1,
        ]);

        $credito = $this->postJson(self::RAIZ . '/notas/credito', [
            'client_id' => $f->client_id, 'invoice_id' => $f->id, 'issue_date' => now()->toDateString(),
            'reason' => 'return', 'type' => 'partial',
            'linhas' => [['origem_line_id' => $linha->id, 'quantity' => 1]],
        ])->assertCreated();

        $credito->assertJsonPath('pdf', '/invoicing/credit-notes/' . $credito->json('id') . '/pdf');

        $debito = $this->postJson(self::RAIZ . '/notas/debito', [
            'client_id' => $f->client_id, 'invoice_id' => $f->id, 'issue_date' => now()->toDateString(),
            'reason' => 'correction',
            'linhas' => [['origem_line_id' => $linha->id, 'quantity' => 1]],
        ])->assertCreated();

        $debito->assertJsonPath('pdf', '/invoicing/debit-notes/' . $debito->json('id') . '/pdf');
    }

    /** O adiantamento registado traz a morada do seu PDF. @test */
    public function registar_um_adiantamento_traz_o_pdf(): void
    {
        $this->comPermissoes('invoicing.advances.create');

        $r = $this->postJson(self::RAIZ . '/adiantamentos', [
            'client_id' => $this->clienteEmpresa()->id,
            'payment_date' => now()->toDateString(),
            'amount' => 25000,
            'payment_method' => 'transfer',
        ])->assertCreated();

        $r->assertJsonPath('data.pdf', '/invoicing/advances/' . $r->json('data.id') . '/pdf');
    }

    /** A compra diz se ficou registada ou em rascunho, e traz o PDF. @test */
    public function registar_uma_compra_diz_o_estado_e_traz_o_pdf(): void
    {
        $this->comPermissoes('invoicing.purchases.invoices.create');

        $corpo = fn (array $por = []) => array_merge([
            'supplier_id' => Supplier::create([
                'tenant_id' => $this->tenant->id, 'name' => 'Fornecedor ' . uniqid(), 'nif' => '5000000000',
                'type' => 'pessoa_juridica', 'is_active' => true,
            ])->id,
            'warehouse_id' => $this->armazem->id,
            'invoice_date' => now()->toDateString(),
            'linhas' => [['product_id' => $this->artigo()->id, 'quantity' => 3, 'price' => 800]],
        ], $por);

        $registada = $this->postJson(self::RAIZ . '/compra', $corpo())->assertCreated();

        $registada->assertJsonPath('estado', 'pending')
            ->assertJsonPath('pdf', '/invoicing/purchases/invoices/' . $registada->json('id') . '/pdf');

        $this->postJson(self::RAIZ . '/compra', $corpo(['status' => 'draft']))
            ->assertCreated()
            ->assertJsonPath('estado', 'draft');
    }
}
