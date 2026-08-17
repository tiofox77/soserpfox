<?php

namespace Tests\Feature;

use App\Livewire\Invoicing\Reports\AccountStatementReport;
use App\Models\Invoicing\CreditNote;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Services\Invoicing\ContaCorrenteQuery;
use Livewire\Livewire;
use Tests\TenantTestCase;

/**
 * Extracto de conta corrente.
 *
 * Não existia nenhum: havia o mapa de contas a receber (quanto está por pagar,
 * hoje) e o aging (há quanto tempo), mas nada respondia à pergunta que um
 * cliente faz ao telefone — "o que é que eu devo, e porquê?".
 */
class ExtractoContaCorrenteTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comPermissoes('invoicing.reports.view')->comModulo('invoicing');
    }

    private function factura(float $total, string $data, string $estado = 'pending'): SalesInvoice
    {
        return SalesInvoice::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'invoice_number' => 'FT ' . strtoupper(substr(uniqid(), -8)),
            'invoice_date'   => $data,
            'status'         => $estado,
            'subtotal'       => $total,
            'tax_amount'     => 0,
            'total'          => $total,
            'created_by'     => $this->user->id,
        ]);
    }

    private function recibo(SalesInvoice $f, float $valor, string $data): Receipt
    {
        return Receipt::create([
            'tenant_id'      => $this->tenant->id,
            'client_id'      => $this->cliente->id,
            'invoice_id'     => $f->id,
            'receipt_number' => 'RC ' . strtoupper(substr(uniqid(), -8)),
            'payment_date'   => $data,
            'amount_paid'    => $valor,
            'type'           => 'sale',
            'status'         => 'issued',
            'created_by'     => $this->user->id,
        ]);
    }

    private function extracto(?string $de = null, ?string $ate = null): ContaCorrenteQuery
    {
        return new ContaCorrenteQuery(
            $this->tenant->id,
            ContaCorrenteQuery::CLIENTE,
            $this->cliente->id,
            $de ?? now()->subYears(5)->toDateString(),
            $ate ?? now()->addYear()->toDateString(),
        );
    }

    public function test_a_factura_debita_e_o_recibo_credita(): void
    {
        $f = $this->factura(10000, now()->subDays(10)->toDateString());
        $this->recibo($f, 4000, now()->subDays(5)->toDateString());

        $r = $this->extracto()->resumo();

        $this->assertEquals(10000, $r['debito']);
        $this->assertEquals(4000, $r['credito']);
        $this->assertEquals(6000, $r['saldo_final']);
    }

    public function test_o_saldo_acumula_pela_ordem_dos_acontecimentos(): void
    {
        $f1 = $this->factura(1000, now()->subDays(30)->toDateString());
        $this->factura(500, now()->subDays(20)->toDateString());
        $this->recibo($f1, 300, now()->subDays(10)->toDateString());

        $saldos = $this->extracto()->movimentos()->pluck('saldo')->all();

        $this->assertSame([1000.0, 1500.0, 1200.0], array_map('floatval', $saldos));
    }

    public function test_uma_nota_de_credito_credita_a_conta(): void
    {
        $f = $this->factura(10000, now()->subDays(10)->toDateString());

        CreditNote::create([
            'tenant_id'          => $this->tenant->id,
            'client_id'          => $this->cliente->id,
            'invoice_id'         => $f->id,
            'credit_note_number' => 'NC ' . strtoupper(substr(uniqid(), -8)),
            'issue_date'         => now()->subDays(3)->toDateString(),
            'status'             => 'issued',
            'reason'             => 'return',
            'subtotal'           => 2500, 'tax_amount' => 0, 'total' => 2500,
            'type'               => 'partial',
        ]);

        $r = $this->extracto()->resumo();

        $this->assertEquals(2500, $r['credito']);
        $this->assertEquals(7500, $r['saldo_final']);
    }

    public function test_o_saldo_anterior_transita_para_o_periodo(): void
    {
        // Sem saldo transportado, um extracto de período parcial começa do zero
        // e mente: a dívida antiga desaparecia e o saldo final ficava errado
        // pelo mesmo valor.
        $this->factura(8000, now()->subMonths(6)->toDateString());
        $this->factura(2000, now()->subDays(3)->toDateString());

        $q = $this->extracto(now()->subMonth()->toDateString(), now()->addDay()->toDateString());
        $r = $q->resumo();

        $this->assertEquals(8000, $r['saldo_anterior'], 'a dívida antiga tem de transitar');
        $this->assertEquals(2000, $r['debito'], 'só o período conta no débito');
        $this->assertEquals(10000, $r['saldo_final']);
        $this->assertCount(1, $q->movimentos());
    }

    public function test_um_documento_anulado_nao_move_a_conta(): void
    {
        $this->factura(10000, now()->subDays(10)->toDateString());
        $this->factura(5000, now()->subDays(9)->toDateString(), 'cancelled');

        $this->assertEquals(10000, $this->extracto()->resumo()['saldo_final']);
    }

    public function test_um_pagamento_sem_recibo_credita_a_conta(): void
    {
        // O caso que fazia o extracto mentir por milhões: só o modal de
        // pagamentos emite recibo. O POS e as facturas-recibo escrevem
        // `paid_amount` directamente — 17,5 milhões pagos contra 13.420 Kz em
        // recibos, nesta base. Sem contar isto, o extracto mostrava a dívida
        // inteira de quem ja tinha pago tudo.
        $f = $this->factura(10000, now()->subDays(10)->toDateString());
        $f->update(['paid_amount' => 10000, 'status' => 'paid']);

        $r = $this->extracto()->resumo();

        $this->assertEquals(10000, $r['credito'], 'o que a factura diz estar pago tem de creditar');
        $this->assertEquals(0, $r['saldo_final'], 'quem pagou tudo não deve nada');
    }

    public function test_um_pagamento_com_recibo_nao_conta_duas_vezes(): void
    {
        // Onde HÁ recibo é o recibo que aparece; o resto entra como pagamento.
        // Somar os dois duplicava o crédito e punha a conta a favor do cliente.
        $f = $this->factura(10000, now()->subDays(10)->toDateString());
        $this->recibo($f, 4000, now()->subDays(5)->toDateString());
        $f->update(['paid_amount' => 10000, 'status' => 'paid']);

        $r = $this->extracto()->resumo();

        $this->assertEquals(10000, $r['credito'], '4.000 pelo recibo + 6.000 por documentar');
        $this->assertEquals(0, $r['saldo_final']);
    }

    public function test_guias_e_notas_dentro_da_tabela_das_facturas_ficam_de_fora(): void
    {
        // Há guias de transporte e notas de crédito/débito gravadas dentro de
        // invoicing_sales_invoices, de antes de terem tabela própria. A guia não
        // é dívida, e as notas já entram pelas suas tabelas — deixá-las aqui era
        // contá-las duas vezes e cobrar transportes ao cliente.
        $this->factura(10000, now()->subDays(10)->toDateString());

        $guia = $this->factura(3000, now()->subDays(9)->toDateString());
        $guia->update(['invoice_type' => 'GT']);

        $nota = $this->factura(1000, now()->subDays(8)->toDateString());
        $nota->update(['invoice_type' => 'NC']);

        $this->assertEquals(10000, $this->extracto()->resumo()['saldo_final']);
    }

    public function test_o_saldo_bate_com_o_que_esta_por_pagar(): void
    {
        $f1 = $this->factura(10000, now()->subDays(20)->toDateString());
        $this->factura(4000, now()->subDays(10)->toDateString());
        $this->recibo($f1, 6000, now()->subDays(5)->toDateString());

        $f1->update(['paid_amount' => 6000]);

        $porPagar = (float) \Illuminate\Support\Facades\DB::table('invoicing_sales_invoices')
            ->where('tenant_id', $this->tenant->id)
            ->where('client_id', $this->cliente->id)
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('COALESCE(SUM(total - COALESCE(paid_amount, 0)), 0) s')
            ->value('s');

        $this->assertEquals($porPagar, $this->extracto()->resumo()['saldo_final']);
    }

    public function test_a_conta_de_outra_empresa_nao_se_mistura(): void
    {
        $this->factura(10000, now()->subDays(5)->toDateString());

        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => '5' . random_int(100000000, 999999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        SalesInvoice::create([
            'tenant_id' => $outra->id, 'client_id' => $this->cliente->id,
            'invoice_number' => 'FT ALHEIA ' . uniqid(),
            'invoice_date' => now()->subDays(4)->toDateString(),
            'status' => 'pending', 'subtotal' => 99999, 'tax_amount' => 0, 'total' => 99999,
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals(10000, $this->extracto()->resumo()['saldo_final']);
    }

    public function test_no_fornecedor_um_saldo_positivo_quer_dizer_que_devemos(): void
    {
        // Nas contas, a conta de um fornecedor é credora — mostrar isso em bruto
        // dava um saldo negativo a quem só queria saber quanto tem a pagar.
        $fornecedor = \App\Models\Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor ' . uniqid(),
            'nif' => (string) random_int(500000000, 599999999),
            'is_active' => true,
        ]);

        \App\Models\Invoicing\PurchaseInvoice::create([
            'tenant_id' => $this->tenant->id, 'supplier_id' => $fornecedor->id,
            'invoice_number' => 'FC ' . strtoupper(substr(uniqid(), -8)),
            'invoice_date' => now()->subDays(10)->toDateString(),
            'status' => 'pending', 'subtotal' => 50000, 'total' => 50000,
        ]);

        $r = (new ContaCorrenteQuery(
            $this->tenant->id,
            ContaCorrenteQuery::FORNECEDOR,
            $fornecedor->id,
            now()->subYear()->toDateString(),
            now()->addDay()->toDateString(),
        ))->resumo();

        $this->assertEquals(50000, $r['saldo_final'], 'positivo = devemos ao fornecedor');
    }

    public function test_o_ecra_abre_e_mostra_o_extracto(): void
    {
        $f = $this->factura(10000, now()->subDays(10)->toDateString());

        Livewire::test(AccountStatementReport::class)
            ->set('dateFrom', now()->subYear()->toDateString())
            ->set('dateTo', now()->addDay()->toDateString())
            ->call('selecionar', $this->cliente->id)
            ->assertSee($f->invoice_number)
            ->assertSee('Saldo transportado');
    }

    public function test_sem_conta_escolhida_o_ecra_pede_uma(): void
    {
        Livewire::test(AccountStatementReport::class)
            ->assertSee('Escolha um cliente para ver o extracto');
    }

    public function test_trocar_de_tipo_de_conta_limpa_a_escolha(): void
    {
        // Um id de cliente não serve para procurar um fornecedor: sem limpar,
        // o extracto passava a mostrar a conta do fornecedor com aquele id.
        Livewire::test(AccountStatementReport::class)
            ->call('selecionar', $this->cliente->id)
            ->assertSet('entidadeId', $this->cliente->id)
            ->set('entidade', 'fornecedor')
            ->assertSet('entidadeId', null);
    }

    public function test_o_pdf_do_extracto_abre(): void
    {
        $this->factura(10000, now()->subDays(10)->toDateString());

        $resposta = $this->get(route('invoicing.reports.account-statement.pdf', [
            'entidade' => 'cliente',
            'id'       => $this->cliente->id,
            'de'       => now()->subYear()->toDateString(),
            'ate'      => now()->addDay()->toDateString(),
        ]));

        $resposta->assertOk();
        $this->assertStringStartsWith('%PDF-', $resposta->getContent());
    }

    public function test_o_pdf_de_uma_conta_de_outra_empresa_da_404(): void
    {
        $outra = \App\Models\Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(),
            'nif' => '5' . random_int(100000000, 999999999),
            'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true,
        ]);

        $alheio = \App\Models\Client::create([
            'tenant_id' => $outra->id, 'name' => 'Alheio',
            'nif' => (string) random_int(300000000, 399999999),
            'type' => 'pessoa_fisica', 'is_active' => true,
        ]);

        $this->get(route('invoicing.reports.account-statement.pdf', [
            'entidade' => 'cliente', 'id' => $alheio->id,
        ]))->assertNotFound();
    }
}
