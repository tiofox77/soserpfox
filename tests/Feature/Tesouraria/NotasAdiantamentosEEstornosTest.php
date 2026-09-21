<?php

namespace Tests\Feature\Tesouraria;

use App\Models\Category;
use App\Models\Invoicing\Advance;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\Transaction;
use App\Services\Invoicing\SomasDasFacturas;
use Tests\TenantTestCase;

/**
 * O DINHEIRO DAS NOTAS, DOS ADIANTAMENTOS E DOS ESTORNOS (20/09/2026).
 *
 * Auditoria ao caminho do dinheiro. Quatro sítios onde ele se mexia no mundo
 * real e não nos livros:
 *
 *  · o ADIANTAMENTO — o cliente entrega o dinheiro e ele não entrava na
 *    tesouraria nem na gaveta;
 *  · a NOTA DE CRÉDITO — baixava a gaveta do turno (movimento negativo) e
 *    nunca a tesouraria: o turno dizia que tinha saído dinheiro e o saldo da
 *    caixa não descia;
 *  · a NOTA DE CRÉDITO PARCIAL — não reduzia a dívida da factura. Creditar
 *    metade de uma factura deixava-a por receber por inteiro;
 *  · a NOTA DE DÉBITO — não fazia rigorosamente nada. O
 *    `DebitNote::updateInvoiceBalance()` somava as notas e deitava fora o
 *    resultado.
 *
 * E ainda: APAGAR UM RECIBO desfazia o pagamento na factura e deixava o
 * dinheiro na tesouraria.
 */
class NotasAdiantamentosEEstornosTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        foreach ([['NC', 'credit_note'], ['ND', 'debit_note'], ['RC', 'receipt']] as [$codigo, $tipo]) {
            InvoicingSeries::create([
                'tenant_id' => $this->tenant->id, 'series_code' => $codigo, 'name' => "{$codigo} (ensaio)",
                'document_type' => $tipo, 'agt_environment' => 'sandbox', 'is_default' => true, 'is_active' => true,
            ]);
        }
    }

    /* ─── As peças ────────────────────────────────────────────────────── */

    private function caixaAberta(): CashRegister
    {
        return CashRegister::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Balcão', 'code' => 'CX' . random_int(1000, 9999),
            'user_id' => $this->user->id, 'is_active' => true, 'is_default' => true, 'status' => 'open',
            'opening_balance' => 0, 'current_balance' => 0, 'expected_balance' => 0,
        ]);
    }

    /** Uma factura com uma linha de 2 × 1000 a 14% — total 2280. */
    private function factura(float $pago = 0, ?string $forma = null): SalesInvoice
    {
        $categoria = Category::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Geral'], ['is_active' => true]);
        $taxa = Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'], ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']);

        $artigo = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Artigo ' . uniqid(), 'type' => 'produto', 'price' => 1000,
            'unit' => 'un', 'category_id' => $categoria->id, 'tax_type' => 'iva', 'tax_rate_id' => $taxa->id, 'is_active' => true,
        ]);

        $f = SalesInvoice::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT/' . random_int(10000, 99999), 'invoice_date' => now()->toDateString(),
            'invoice_type' => 'FT', 'status' => 'sent', 'payment_method' => $forma,
            'subtotal' => 2000, 'tax_amount' => 280, 'total' => 2280, 'paid_amount' => $pago,
            'created_by' => $this->user->id,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id, 'product_id' => $artigo->id, 'product_name' => $artigo->name,
            'description' => $artigo->name, 'quantity' => 2, 'unit_price' => 1000, 'subtotal' => 2000,
            'tax_rate' => 14, 'tax_amount' => 280, 'total' => 2280, 'tax_code' => 'NOR',
            'tax_country_region' => 'AO-CAB', 'order' => 1,
        ]);

        return $f->fresh(['items']);
    }

    private function creditar(SalesInvoice $f, float $quantidade, string $tipo = 'partial')
    {
        return $this->postJson('/api/v1/invoicing/react/notas/credito', [
            'client_id' => $f->client_id, 'invoice_id' => $f->id, 'issue_date' => now()->toDateString(),
            'reason' => 'return', 'type' => $tipo,
            'linhas' => [['origem_line_id' => $f->items->first()->id, 'quantity' => $quantidade]],
        ]);
    }

    private function movimentoDe($origem): ?Transaction
    {
        return Transaction::withoutGlobalScopes()
            ->where('related_type', $origem::class)->where('related_id', $origem->getKey())->first();
    }

    private function porReceber(): float
    {
        return (float) (new SomasDasFacturas())
            ->de(SalesInvoice::where('tenant_id', $this->tenant->id))['por_receber'];
    }

    /* ─── O adiantamento ──────────────────────────────────────────────── */

    public function test_o_adiantamento_entra_na_tesouraria(): void
    {
        $this->comPermissoes('invoicing.advances.view', 'invoicing.advances.create');
        $caixa = $this->caixaAberta();

        $this->postJson('/api/v1/invoicing/react/adiantamentos', [
            'client_id' => $this->clienteEmpresa()->id,
            'payment_date' => now()->toDateString(),
            'amount' => 15000,
            'payment_method' => 'cash',
            'purpose' => 'Sinal',
        ])->assertCreated();

        $adiantamento = Advance::withoutGlobalScopes()->latest('id')->firstOrFail();
        $movimento = $this->movimentoDe($adiantamento);

        $this->assertNotNull($movimento, 'o dinheiro do adiantamento tem de aparecer na tesouraria');
        $this->assertSame('income', $movimento->type);
        $this->assertEqualsWithDelta(15000, (float) $movimento->amount, 0.01);
        $this->assertSame($caixa->id, (int) $movimento->cash_register_id, 'em numerário, vai para a gaveta');
    }

    public function test_o_adiantamento_conta_no_fecho_de_caixa(): void
    {
        $this->comPermissoes('invoicing.advances.view', 'invoicing.advances.create');
        $this->caixaAberta();

        $turno = PosShift::createSafely([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'status' => 'open', 'opened_at' => now(), 'opening_balance' => 0,
        ], $this->tenant->id);

        $this->postJson('/api/v1/invoicing/react/adiantamentos', [
            'client_id' => $this->clienteEmpresa()->id,
            'payment_date' => now()->toDateString(),
            'amount' => 8000, 'payment_method' => 'cash',
        ])->assertCreated();

        $turno->refresh();

        $this->assertEqualsWithDelta(8000, (float) $turno->cash_sales, 0.01,
            'o sinal recebido ao balcão faz subir o esperado na gaveta');
    }

    /* ─── As notas de crédito ─────────────────────────────────────────── */

    public function test_a_nota_de_credito_de_uma_factura_paga_devolve_o_dinheiro(): void
    {
        $this->comPermissoes('invoicing.credit-notes.create');
        $caixa = $this->caixaAberta();

        // A factura foi paga por inteiro — o dinheiro está na gaveta.
        $factura = $this->factura(pago: 2280, forma: 'cash');

        $this->creditar($factura, 2, 'total')->assertCreated();

        $nota = CreditNote::withoutGlobalScopes()->latest('id')->firstOrFail();
        $movimento = $this->movimentoDe($nota);

        $this->assertNotNull($movimento, 'devolver dinheiro é dinheiro que sai da tesouraria');
        $this->assertSame('expense', $movimento->type);
        $this->assertEqualsWithDelta((float) $nota->total, (float) $movimento->amount, 0.01);
        $this->assertSame($caixa->id, (int) $movimento->cash_register_id);
    }

    public function test_a_nota_de_credito_de_uma_factura_por_pagar_nao_tira_dinheiro(): void
    {
        $this->comPermissoes('invoicing.credit-notes.create');
        $this->caixaAberta();

        // Nunca ninguém pagou: anular não devolve dinheiro nenhum.
        $factura = $this->factura(pago: 0);

        $this->creditar($factura, 2, 'total')->assertCreated();

        $nota = CreditNote::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertNull($this->movimentoDe($nota),
            'não se devolve dinheiro que nunca entrou');
    }

    public function test_a_nota_de_credito_parcial_reduz_a_divida(): void
    {
        $this->comPermissoes('invoicing.credit-notes.create');

        $factura = $this->factura();

        $this->assertEqualsWithDelta(2280, $this->porReceber(), 0.01);

        // Credita-se metade: 1 das 2 unidades = 1140.
        $this->creditar($factura, 1)->assertCreated();

        $this->assertEqualsWithDelta(1140, $this->porReceber(), 0.01,
            'creditar metade tem de deixar só a outra metade por receber');
    }

    /* ─── A nota de débito ────────────────────────────────────────────── */

    public function test_a_nota_de_debito_aumenta_a_divida(): void
    {
        $this->comPermissoes('invoicing.debit-notes.create');

        $factura = $this->factura();

        $this->postJson('/api/v1/invoicing/react/notas/debito', [
            'client_id' => $factura->client_id, 'invoice_id' => $factura->id,
            'issue_date' => now()->toDateString(), 'reason' => 'correction',
            'linhas' => [['descricao' => 'Acerto de preço', 'quantity' => 1, 'price' => 1000, 'tax_rate' => 14]],
        ])->assertCreated();

        $this->assertEqualsWithDelta(2280 + 1140, $this->porReceber(), 0.01,
            'uma nota de débito é dívida a mais — tem de aparecer no que falta receber');
    }

    /* ─── O estorno ───────────────────────────────────────────────────── */

    public function test_apagar_um_recibo_tira_o_dinheiro_da_tesouraria(): void
    {
        $this->comPermissoes('invoicing.receipts.view', 'invoicing.receipts.create');
        $caixa = $this->caixaAberta();

        $factura = $this->factura();

        $this->postJson('/api/v1/invoicing/react/recibos', [
            'type' => 'sale', 'client_id' => $factura->client_id, 'invoice_id' => $factura->id,
            'payment_date' => now()->toDateString(), 'payment_method' => 'cash', 'amount_paid' => 2000,
        ])->assertCreated();

        $recibo = Receipt::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertNotNull($this->movimentoDe($recibo));
        $this->assertEqualsWithDelta(2000, (float) $caixa->fresh()->current_balance, 0.01);

        $recibo->delete();

        $this->assertNull($this->movimentoDe($recibo),
            'o dinheiro de um recibo apagado não pode ficar na tesouraria');
        $this->assertEqualsWithDelta(0, (float) $caixa->fresh()->current_balance, 0.01,
            'e o saldo da gaveta tem de voltar ao que era');
    }
}
