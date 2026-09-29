<?php

namespace Tests\Feature\Contabilidade;

use App\Models\Accounting\Account;
use App\Models\Accounting\IntegrationMapping;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\Period;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\Accounting\IntegrationService;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * A CONTA-CORRENTE DE TERCEIROS — saldos, extrato e antiguidade de clientes e
 * fornecedores.
 *
 * Não existia, e não PODIA existir: nenhuma integração gravava o terceiro nas
 * linhas de lançamento (`partner_id` estava sempre vazio). Além disso as
 * faturas de compra nunca chegavam à contabilidade, e um recibo de compra
 * (pagamento a um fornecedor) era lançado como recebimento de um cliente.
 */
class ContaCorrenteDeTerceirosTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react/contabilidade/terceiros';

    private Account $clientes;
    private Account $fornecedores;
    private Account $vendas;
    private Account $compras;
    private Account $iva;
    private Account $caixa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('contabilidade')->comPermissoes('accounting.partners.view');

        // As âncoras de terceiros (conta-mãe com integration_key) e as folhas.
        $this->conta('31', 'Clientes', 'asset', 'debit', ['is_view' => true, 'integration_key' => 'receivables']);
        $this->clientes = $this->conta('3111', 'Clientes Gerais', 'asset', 'debit');
        $this->conta('32', 'Fornecedores', 'liability', 'credit', ['is_view' => true, 'integration_key' => 'payables']);
        $this->fornecedores = $this->conta('3211', 'Fornecedores Gerais', 'liability', 'credit');
        $this->vendas = $this->conta('711', 'Vendas', 'revenue', 'credit');
        $this->compras = $this->conta('211', 'Compras', 'expense', 'debit');
        $this->iva = $this->conta('3451', 'IVA', 'liability', 'credit');
        $this->caixa = $this->conta('111', 'Caixa', 'asset', 'debit');
    }

    /* ─── Montagem ─────────────────────────────────────────────────────── */

    private function conta(string $codigo, string $nome, string $tipo, string $natureza, array $extra = []): Account
    {
        return Account::create(array_merge([
            'tenant_id' => $this->tenant->id, 'code' => $codigo, 'name' => $nome,
            'type' => $tipo, 'nature' => $natureza, 'level' => strlen($codigo),
            'is_view' => false, 'blocked' => false,
        ], $extra));
    }

    private function diario(): Journal
    {
        return Journal::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'DG'],
            ['name' => 'Geral', 'type' => 'general', 'sequence_prefix' => 'DG-', 'last_number' => 0, 'active' => true]
        );
    }

    private function periodo(): Period
    {
        return Period::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => 'P-TESTE'],
            [
                'name' => 'Período', 'state' => 'open',
                'date_start' => now()->subYear()->startOfYear()->toDateString(),
                'date_end' => now()->endOfYear()->toDateString(),
            ]
        );
    }

    private function integracao(string $evento, Account $debito, Account $credito, ?Account $iva = null): void
    {
        Tenant::whereKey($this->tenant->id)->update(['accounting_integration_enabled' => true]);
        $this->periodo();

        IntegrationMapping::create([
            'tenant_id' => $this->tenant->id, 'event' => $evento, 'journal_id' => $this->diario()->id,
            'debit_account_id' => $debito->id, 'credit_account_id' => $credito->id,
            'vat_account_id' => $iva?->id, 'auto_post' => true, 'active' => true,
        ]);
    }

    private function fornecedor(string $nome = 'Fornecedor Lda'): Supplier
    {
        return Supplier::create([
            'tenant_id' => $this->tenant->id, 'name' => $nome,
            'nif' => (string) random_int(500000000, 599999999),
        ]);
    }

    /**
     * Um lançamento com a linha do terceiro (e a contrapartida), num dia.
     * `$estado` permite provar que os rascunhos não contam.
     */
    private function movimento(string $tipo, int $terceiro, float $debito, float $credito, int $diasAtras, string $estado = 'posted', ?int $tenantId = null): void
    {
        $tenantId ??= $this->tenant->id;
        $conta = $tipo === 'client' ? $this->clientes : $this->fornecedores;

        $move = Move::create([
            'tenant_id' => $tenantId, 'journal_id' => $this->diario()->id, 'period_id' => $this->periodo()->id,
            'date' => now()->subDays($diasAtras)->toDateString(), 'ref' => 'T-' . uniqid(),
            'state' => $estado, 'total_debit' => $debito ?: $credito, 'total_credit' => $debito ?: $credito,
            'created_by' => $this->user->id,
        ]);

        MoveLine::create([
            'tenant_id' => $tenantId, 'move_id' => $move->id, 'account_id' => $conta->id,
            'partner_id' => $terceiro, 'partner_type' => $tipo,
            'debit' => $debito, 'credit' => $credito, 'name' => 'Linha do terceiro',
        ]);

        MoveLine::create([
            'tenant_id' => $tenantId, 'move_id' => $move->id, 'account_id' => $this->caixa->id,
            'debit' => $credito, 'credit' => $debito, 'name' => 'Contrapartida',
        ]);
    }

    /** A linha de um lançamento numa conta. */
    private function linhaNa(Move $move, Account $conta): ?MoveLine
    {
        return MoveLine::withoutGlobalScopes()->where('move_id', $move->id)->where('account_id', $conta->id)->first();
    }

    /* ─── As integrações passam a gravar o terceiro ────────────────────── */

    public function test_a_fatura_de_venda_leva_o_cliente_na_linha_de_clientes(): void
    {
        $this->integracao('invoice', $this->clientes, $this->vendas, $this->iva);

        $fatura = (new SalesInvoice())->forceFill([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->cliente->id,
            'invoice_number' => 'FT TST/' . uniqid(), 'invoice_date' => now()->toDateString(),
            'subtotal' => 1000, 'tax_amount' => 140, 'total' => 1140, 'created_by' => $this->user->id,
        ])->setRelation('client', $this->cliente);

        $move = app(IntegrationService::class)->createMoveFromInvoice($fatura);

        $this->assertNotNull($move);
        $linha = $this->linhaNa($move, $this->clientes);
        $this->assertSame($this->cliente->id, (int) $linha->partner_id);
        $this->assertSame('client', $linha->partner_type);
        $this->assertSame($this->tenant->id, (int) $linha->tenant_id);
        $this->assertEquals(1140, (float) $linha->debit);
        // As linhas de vendas e IVA não são de terceiros.
        $this->assertNull($this->linhaNa($move, $this->vendas)->partner_id);
    }

    public function test_o_recibo_de_venda_leva_o_cliente_na_linha_de_clientes(): void
    {
        $this->integracao('receipt_cash', $this->caixa, $this->clientes);

        $recibo = (new Receipt())->forceFill([
            'tenant_id' => $this->tenant->id, 'type' => 'sale', 'client_id' => $this->cliente->id,
            'receipt_number' => 'RC TST/' . uniqid(), 'payment_date' => now()->toDateString(),
            'payment_method' => 'cash', 'amount_paid' => 500, 'created_by' => $this->user->id,
        ]);

        $move = app(IntegrationService::class)->createMoveFromReceipt($recibo);

        $linha = $this->linhaNa($move, $this->clientes);
        $this->assertEquals(500, (float) $linha->credit);
        $this->assertSame($this->cliente->id, (int) $linha->partner_id);
        $this->assertSame('client', $linha->partner_type);
    }

    /**
     * PAGAR A UM FORNECEDOR NÃO É RECEBER DE UM CLIENTE.
     *
     * O recibo de compra usava o mapeamento `receipt_*` (Dr Caixa, Cr Clientes):
     * o dinheiro «entrava» na caixa e a dívida de um cliente baixava.
     */
    public function test_o_recibo_de_compra_paga_ao_fornecedor_e_nao_mexe_em_clientes(): void
    {
        $this->integracao('receipt_cash', $this->caixa, $this->clientes);
        $this->integracao('payment_cash', $this->fornecedores, $this->caixa);
        $fornecedor = $this->fornecedor();

        $recibo = (new Receipt())->forceFill([
            'tenant_id' => $this->tenant->id, 'type' => 'purchase', 'supplier_id' => $fornecedor->id,
            'receipt_number' => 'RP TST/' . uniqid(), 'payment_date' => now()->toDateString(),
            'payment_method' => 'cash', 'amount_paid' => 300, 'created_by' => $this->user->id,
        ]);

        $move = app(IntegrationService::class)->createMoveFromReceipt($recibo);

        $this->assertNull($this->linhaNa($move, $this->clientes), 'Um pagamento a fornecedor não pode tocar em Clientes.');

        $fornecedores = $this->linhaNa($move, $this->fornecedores);
        $this->assertEquals(300, (float) $fornecedores->debit);
        $this->assertSame($fornecedor->id, (int) $fornecedores->partner_id);
        $this->assertSame('supplier', $fornecedores->partner_type);

        $this->assertEquals(300, (float) $this->linhaNa($move, $this->caixa)->credit, 'O dinheiro SAI da caixa.');
    }

    /**
     * A FATURA DE COMPRA CHEGA À CONTABILIDADE quando deixa de ser rascunho, e
     * uma só vez.
     */
    public function test_a_fatura_de_compra_definitiva_e_lancada_uma_vez(): void
    {
        $this->integracao('purchase', $this->compras, $this->fornecedores, $this->iva);
        $fornecedor = $this->fornecedor();

        $fatura = PurchaseInvoice::create([
            'tenant_id' => $this->tenant->id, 'supplier_id' => $fornecedor->id,
            'warehouse_id' => $this->armazem->id, 'invoice_date' => now()->toDateString(),
            'status' => 'draft', 'subtotal' => 2000, 'tax_amount' => 280, 'total' => 2280,
            'created_by' => $this->user->id,
        ]);

        $this->assertFalse(Move::withoutGlobalScopes()->where('ref', $fatura->invoice_number)->exists(), 'Rascunho não se lança.');

        $fatura->update(['status' => 'pending']);
        $fatura->update(['status' => 'paid']);

        $moves = Move::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('ref', $fatura->invoice_number)->get();
        $this->assertCount(1, $moves);

        $move = $moves->first();
        $this->assertSame('posted', $move->state);
        $this->assertEquals(2000, (float) $this->linhaNa($move, $this->compras)->debit);
        $this->assertEquals(280, (float) $this->linhaNa($move, $this->iva)->debit);

        $linha = $this->linhaNa($move, $this->fornecedores);
        $this->assertEquals(2280, (float) $linha->credit);
        $this->assertSame($fornecedor->id, (int) $linha->partner_id);
        $this->assertSame('supplier', $linha->partner_type);
    }

    /* ─── Saldos, extrato e antiguidade ────────────────────────────────── */

    public function test_os_saldos_contam_so_os_lancamentos_confirmados_desta_empresa(): void
    {
        $this->movimento('client', $this->cliente->id, 1000, 0, 100);
        $this->movimento('client', $this->cliente->id, 500, 0, 10);
        $this->movimento('client', $this->cliente->id, 0, 600, 5);
        $this->movimento('client', $this->cliente->id, 9999, 0, 3, 'draft');     // rascunho: não conta

        $outra = Tenant::create([
            'name' => 'Outra', 'slug' => 'outra-' . uniqid(), 'nif' => (string) random_int(500000000, 599999999),
            'email' => 'o' . uniqid() . '@exemplo.ao', 'is_active' => true,
        ]);
        $this->movimento('client', $this->cliente->id, 7777, 0, 3, 'posted', $outra->id); // outra empresa: não conta

        $r = $this->getJson(self::RAIZ . '?tipo=client')->assertOk();

        $this->assertCount(1, $r->json('linhas'));
        $linha = $r->json('linhas.0');
        $this->assertSame($this->cliente->id, $linha['id']);
        $this->assertSame($this->cliente->name, $linha['nome']);
        $this->assertEquals(1500, $linha['debito']);
        $this->assertEquals(600, $linha['credito']);
        $this->assertEquals(900, $linha['saldo'], 'Cliente: débito − crédito = a receber.');
        $this->assertEquals(900, $r->json('totais.saldo'));
    }

    public function test_o_saldo_do_fornecedor_le_se_no_sentido_da_divida(): void
    {
        $fornecedor = $this->fornecedor();
        $this->movimento('supplier', $fornecedor->id, 0, 800, 40);   // fatura
        $this->movimento('supplier', $fornecedor->id, 300, 0, 20);   // pagamento

        $linha = $this->getJson(self::RAIZ . '?tipo=supplier')->assertOk()->json('linhas.0');

        $this->assertEquals(500, $linha['saldo'], 'Fornecedor: crédito − débito = a pagar.');
    }

    public function test_so_com_saldo_esconde_os_terceiros_saldados(): void
    {
        $saldado = $this->clienteEmpresa();
        $this->movimento('client', $saldado->id, 400, 0, 30);
        $this->movimento('client', $saldado->id, 0, 400, 20);
        $this->movimento('client', $this->cliente->id, 250, 0, 10);

        $this->assertCount(2, $this->getJson(self::RAIZ . '?tipo=client')->json('linhas'));

        $so = $this->getJson(self::RAIZ . '?tipo=client&com_saldo=1')->assertOk()->json('linhas');
        $this->assertCount(1, $so);
        $this->assertSame($this->cliente->id, $so[0]['id']);
    }

    public function test_o_extrato_traz_o_saldo_anterior_e_o_acumulado(): void
    {
        $this->movimento('client', $this->cliente->id, 1000, 0, 100);
        $this->movimento('client', $this->cliente->id, 500, 0, 10);
        $this->movimento('client', $this->cliente->id, 0, 600, 5);

        $de = now()->subDays(20)->toDateString();
        $r = $this->getJson(self::RAIZ . "/client/{$this->cliente->id}/extrato?de={$de}")->assertOk();

        $this->assertEquals(1000, $r->json('saldo_anterior'));
        $this->assertSame([1500.0, 900.0], array_map('floatval', array_column($r->json('movimentos'), 'saldo')));
        $this->assertEquals(900, $r->json('totais.saldo_final'));
        $this->assertSame($this->cliente->name, $r->json('terceiro.nome'));
    }

    /**
     * FIFO: os 600 pagos abatem à dívida mais antiga (1000, há 100 dias), que
     * fica com 400 no escalão 91–180; os 500 de há 10 dias ficam em 0–30.
     */
    public function test_a_antiguidade_abate_os_pagamentos_as_dividas_mais_antigas(): void
    {
        $this->movimento('client', $this->cliente->id, 1000, 0, 100);
        $this->movimento('client', $this->cliente->id, 500, 0, 10);
        $this->movimento('client', $this->cliente->id, 0, 600, 5);

        $r = $this->getJson(self::RAIZ . '/antiguidade?tipo=client')->assertOk();

        $linha = $r->json('linhas.0');
        $this->assertEquals(500, $linha['escaloes']['ate_30']);
        $this->assertEquals(0, $linha['escaloes']['de_61_90']);
        $this->assertEquals(400, $linha['escaloes']['de_91_180']);
        $this->assertEquals(900, $linha['total']);
        $this->assertEquals(0, $linha['a_favor']);
        $this->assertEquals(900, $r->json('totais.total'));
    }

    public function test_pagar_a_mais_fica_a_favor_sem_idade(): void
    {
        $fornecedor = $this->fornecedor();
        $this->movimento('supplier', $fornecedor->id, 0, 300, 50);  // fatura de 300
        $this->movimento('supplier', $fornecedor->id, 400, 0, 10);  // pagámos 400

        $linha = $this->getJson(self::RAIZ . '/antiguidade?tipo=supplier')->assertOk()->json('linhas.0');

        $this->assertEquals(0, $linha['total']);
        $this->assertEquals(100, $linha['a_favor']);
    }

    /* ─── Acesso ───────────────────────────────────────────────────────── */

    public function test_o_ecra_monta_a_ilha_de_react(): void
    {
        $this->get(route('accounting.partners'))->assertOk()->assertSee('contabilidade/terceiros', false);
    }

    public function test_sem_permissao_nao_se_ve_a_conta_corrente(): void
    {
        $this->user->revokePermissionTo('accounting.partners.view');
        $this->user->forgetCachedPermissions();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->getJson(self::RAIZ . '?tipo=client')->assertForbidden();
        $this->getJson(self::RAIZ . "/client/{$this->cliente->id}/extrato")->assertForbidden();
        $this->getJson(self::RAIZ . '/antiguidade?tipo=client')->assertForbidden();
    }

    public function test_um_tipo_de_terceiro_desconhecido_e_recusado(): void
    {
        $this->getJson(self::RAIZ . '?tipo=funcionario')->assertUnprocessable();
    }

    /* ─── O histórico ──────────────────────────────────────────────────── */

    public function test_o_comando_preenche_o_terceiro_nos_lancamentos_antigos(): void
    {
        $numero = 'RC ANT/' . uniqid();
        DB::table('invoicing_receipts')->insert([
            'tenant_id' => $this->tenant->id, 'receipt_number' => $numero, 'type' => 'sale',
            'client_id' => $this->cliente->id, 'payment_date' => now()->toDateString(),
            'payment_method' => 'cash', 'amount_paid' => 450, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Um lançamento antigo, como as integrações o gravavam: sem terceiro.
        $move = Move::create([
            'tenant_id' => $this->tenant->id, 'journal_id' => $this->diario()->id, 'period_id' => $this->periodo()->id,
            'date' => now()->toDateString(), 'ref' => $numero, 'state' => 'posted',
            'total_debit' => 450, 'total_credit' => 450, 'created_by' => $this->user->id,
        ]);
        MoveLine::create(['tenant_id' => $this->tenant->id, 'move_id' => $move->id, 'account_id' => $this->caixa->id, 'debit' => 450, 'credit' => 0]);
        $linha = MoveLine::create(['tenant_id' => $this->tenant->id, 'move_id' => $move->id, 'account_id' => $this->clientes->id, 'debit' => 0, 'credit' => 450]);

        $this->artisan('accounting:preencher-terceiros', ['--tenant' => $this->tenant->id, '--simular' => true])->assertSuccessful();
        $this->assertNull($linha->fresh()->partner_id, '--simular não grava.');

        $this->artisan('accounting:preencher-terceiros', ['--tenant' => $this->tenant->id])->assertSuccessful();

        $linha->refresh();
        $this->assertSame($this->cliente->id, (int) $linha->partner_id);
        $this->assertSame('client', $linha->partner_type);
        $this->assertSame($numero, $linha->document_ref);
        $this->assertNull(MoveLine::withoutGlobalScopes()->where('move_id', $move->id)->where('account_id', $this->caixa->id)->value('partner_id'),
            'Só a linha da conta de terceiros leva o terceiro.');
    }
}
