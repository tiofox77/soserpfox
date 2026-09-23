<?php

namespace Tests\Feature\Tesouraria;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Transaction;
use App\Services\Treasury\RelatoriosDeTesouraria;
use Tests\TenantTestCase;

/**
 * O QUE O PAINEL DA TESOURARIA MOSTRAVA MAL, e como se resolve depressa
 * (23/09/2026):
 *
 *  · 38 movimentos «por arrumar» e uma ligação para a lista inteira — agora
 *    arrumam-se de uma vez, com o destino sugerido;
 *  · «Já cobrado» acima do facturado — o POS gravava o troco como pago;
 *  · «Saídas 5.500» num fluxo de caixa sem gasto nenhum — era uma
 *    transferência interna contada como entrada e saída.
 */
class ArrumarTrocoETransferenciasTest extends TenantTestCase
{
    private const RAIZ = '/api/v1/invoicing/react';

    private CashRegister $gaveta;

    private Account $banco;

    private PaymentMethod $dinheiro;

    private PaymentMethod $tpa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('treasury')->comPermissoes('treasury.transactions.view', 'treasury.transactions.edit', 'treasury.transfers.create');

        $this->gaveta = CashRegister::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'name' => 'Caixa do Cleiton', 'code' => 'CX' . random_int(1000, 9999),
            'is_active' => true, 'is_default' => false, 'status' => 'open', 'opening_balance' => 0, 'current_balance' => 0, 'expected_balance' => 0,
        ]);

        $bfa = \App\Models\Treasury\Bank::firstOrCreate(['code' => 'BFA'], ['name' => 'Banco de Fomento Angola', 'country' => 'AO', 'is_active' => true]);
        $this->banco = Account::create([
            'tenant_id' => $this->tenant->id, 'bank_id' => $bfa->id, 'account_name' => 'Conta TPA', 'account_number' => (string) random_int(100000, 999999),
            'currency' => 'AOA', 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'is_default' => false,
        ]);

        $this->dinheiro = PaymentMethod::updateOrCreate(['tenant_id' => $this->tenant->id, 'code' => 'CASH'], ['name' => 'Dinheiro', 'type' => 'cash', 'is_active' => true]);
        $this->tpa = PaymentMethod::create(['tenant_id' => $this->tenant->id, 'code' => 'TPA' . random_int(10, 99), 'name' => 'TPA', 'type' => 'card', 'is_active' => true, 'default_account_id' => $this->banco->id]);
    }

    /** Um movimento que não caiu em lado nenhum — como os da produção. */
    private function semDestino(PaymentMethod $forma, string $tipo, float $valor): Transaction
    {
        return app(\App\Services\Treasury\TreasuryMovementService::class)->post([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => $tipo, 'category' => $forma->type,
            'amount' => $valor, 'currency' => 'AOA', 'transaction_date' => now()->subDays(3), 'payment_method_id' => $forma->id,
            'account_id' => null, 'cash_register_id' => null, 'description' => 'Venda antiga', 'status' => 'completed',
        ]);
    }

    private function saldo($modelo): float
    {
        return round((float) $modelo->fresh()->current_balance, 2);
    }

    /* ─── Arrumar ─────────────────────────────────────────────────────── */

    public function test_arrumar_de_uma_vez_com_o_destino_sugerido(): void
    {
        $this->semDestino($this->dinheiro, 'income', 1000);
        $this->semDestino($this->dinheiro, 'income', 500);
        $this->semDestino($this->dinheiro, 'expense', 300);
        $this->semDestino($this->tpa, 'income', 2000);

        $r = $this->getJson(self::RAIZ . '/tesouraria/movimentos/por-arrumar')->assertOk();
        $grupos = collect($r->json('grupos'))->keyBy('forma');

        $this->assertSame(4, $r->json('total.movimentos'));
        $this->assertSame('cash:' . $this->gaveta->id, $grupos['Dinheiro']['sugestao'], 'o numerário vai para a caixa de quem o registou');
        $this->assertSame(3, $grupos['Dinheiro']['movimentos']);
        $this->assertSame('account:' . $this->banco->id, $grupos['TPA']['sugestao'], 'o TPA vai para a conta da forma');

        $this->postJson(self::RAIZ . '/tesouraria/movimentos/arrumar', [
            'atribuicoes' => collect($r->json('grupos'))->map(fn ($g) => ['ids' => $g['ids'], 'destino' => $g['sugestao']])->all(),
        ])->assertOk()->assertJsonPath('arrumados', 4);

        $this->assertEqualsWithDelta(1200, $this->saldo($this->gaveta), 0.01, '1000 + 500 − 300');
        $this->assertEqualsWithDelta(2000, $this->saldo($this->banco), 0.01);
        $this->assertSame(0, Transaction::where('tenant_id', $this->tenant->id)->whereNull('account_id')->whereNull('cash_register_id')->count());

        // Arrumar outra vez não mexe em nada.
        $this->postJson(self::RAIZ . '/tesouraria/movimentos/arrumar', [
            'atribuicoes' => collect($r->json('grupos'))->map(fn ($g) => ['ids' => $g['ids'], 'destino' => $g['sugestao']])->all(),
        ])->assertOk()->assertJsonPath('arrumados', 0);
        $this->assertEqualsWithDelta(1200, $this->saldo($this->gaveta), 0.01);
    }

    public function test_nao_se_arruma_para_a_caixa_de_outra_empresa(): void
    {
        $m = $this->semDestino($this->dinheiro, 'income', 1000);

        $outra = \App\Models\Tenant::create(['name' => 'Outra', 'slug' => 'outra-' . uniqid(), 'nif' => (string) random_int(500000000, 599999999), 'email' => 'o' . uniqid() . '@x.ao', 'is_active' => true]);
        $alheia = CashRegister::withoutGlobalScopes()->create([
            'tenant_id' => $outra->id, 'user_id' => $this->user->id, 'name' => 'Alheia', 'code' => 'AL' . random_int(1000, 9999),
            'is_active' => true, 'status' => 'open', 'opening_balance' => 0, 'current_balance' => 0, 'expected_balance' => 0,
        ]);

        $this->postJson(self::RAIZ . '/tesouraria/movimentos/arrumar', ['atribuicoes' => [['ids' => [$m->id], 'destino' => 'cash:' . $alheia->id]]])
            ->assertStatus(422);

        $this->assertNull($m->fresh()->cash_register_id);
        $this->assertEqualsWithDelta(0, (float) $alheia->fresh()->current_balance, 0.01);
    }

    public function test_arrumar_pede_permissao_de_editar_movimentos(): void
    {
        $this->user->syncPermissions([]);
        $this->comPermissoes('treasury.transactions.view');

        $this->getJson(self::RAIZ . '/tesouraria/movimentos/por-arrumar')->assertForbidden();
    }

    /* ─── Transferências internas ─────────────────────────────────────── */

    public function test_a_transferencia_interna_nao_conta_como_entrada_nem_saida(): void
    {
        $this->postJson(self::RAIZ . '/tesouraria/transferencias', [
            'de' => 'account:' . $this->banco->id, 'para' => 'cash:' . $this->gaveta->id,
            'amount' => 5500, 'transfer_date' => now()->toDateString(),
        ])->assertCreated();

        $fluxo = (new RelatoriosDeTesouraria($this->tenant->id, now()->startOfMonth()->toDateString(), now()->toDateString()))->fluxoDeCaixa();
        $this->assertEqualsWithDelta(0, (float) $fluxo['totalIncome'], 0.01);
        $this->assertEqualsWithDelta(0, (float) $fluxo['totalExpense'], 0.01, 'a empresa não gastou nada');

        $painel = $this->getJson(self::RAIZ . '/tesouraria/painel?periodo=today')->assertOk();
        $this->assertEqualsWithDelta(0, (float) $painel->json('movimento.entradas'), 0.01);
        $this->assertEqualsWithDelta(0, (float) $painel->json('movimento.saidas'), 0.01);

        // O dinheiro mudou de sítio, e isso continua a ver-se nos saldos.
        $this->assertEqualsWithDelta(5500, $this->saldo($this->gaveta), 0.01);
        $this->assertEqualsWithDelta(-5500, $this->saldo($this->banco), 0.01);
    }

    /* ─── O troco do POS ──────────────────────────────────────────────── */

    public function test_uma_venda_com_troco_fica_paga_pelo_total(): void
    {
        $this->comModulo('invoicing')->comPermissoes('invoicing.pos.access', 'invoicing.pos.sell');
        \App\Models\Invoicing\PosShift::createSafely(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'status' => 'open', 'opened_at' => now(), 'opening_balance' => 0], $this->tenant->id);

        $taxa = \App\Models\Invoicing\Tax::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'IVA 14%'], ['rate' => 14, 'is_active' => true, 'saft_code' => 'NOR']);
        $artigo = \App\Models\Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Pão ' . uniqid(), 'type' => 'produto', 'price' => 1000, 'cost' => 400, 'unit' => 'UN',
            'tax_type' => 'iva', 'tax_rate_id' => $taxa->id, 'manage_stock' => false, 'stock_quantity' => 0, 'is_active' => true,
        ]);

        // 1.140 com IVA, pago com uma nota de 2.000.
        $this->postJson(self::RAIZ . '/pos/vender', [
            'local_uuid' => (string) \Illuminate\Support\Str::uuid(), 'payment_method' => 'cash', 'amount_received' => 2000,
            'items' => [['product_id' => $artigo->id, 'product_name' => $artigo->name, 'quantity' => 1, 'unit_price' => 1000, 'is_service' => false, 'unit' => 'UN']],
        ])->assertSuccessful();

        $venda = SalesInvoice::where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(1140, (float) $venda->paid_amount, 0.01, 'pago é o total da venda');
        $this->assertEqualsWithDelta(2000, (float) $venda->amount_received, 0.01, 'o entregue fica para o talão (troco 860)');
        $this->assertEqualsWithDelta(0, (float) $venda->balance, 0.01);
    }

    public function test_o_troco_ja_nao_conta_como_pago_nas_vendas_antigas(): void
    {
        $venda = new SalesInvoice([
            'client_id' => $this->clienteEmpresa()->id, 'invoice_number' => 'FR POS/' . random_int(1000, 9999), 'invoice_date' => now()->toDateString(),
            'invoice_type' => 'FR', 'status' => 'paid', 'subtotal' => 12700, 'total' => 12700, 'paid_amount' => 14000,
            'source_billing' => 'P', 'invoice_status' => 'F', 'created_by' => $this->user->id,
        ]);
        $venda->tenant_id = $this->tenant->id;
        $venda->save();

        (require base_path('database/migrations/2026_09_23_100000_o_valor_entregue_na_venda_do_pos.php'))->up();

        $venda->refresh();
        $this->assertEqualsWithDelta(12700, (float) $venda->paid_amount, 0.01, 'pago é o total');
        $this->assertEqualsWithDelta(14000, (float) $venda->amount_received, 0.01, 'o entregue fica guardado para o talão');
        $this->assertEqualsWithDelta(0, (float) $venda->balance, 0.01);
    }
}
