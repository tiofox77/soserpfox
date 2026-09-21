<?php

namespace Tests\Feature\Tesouraria;

use App\Models\Invoicing\Advance;
use App\Models\Invoicing\PosShift;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Treasury\CashRegister;
use App\Models\Treasury\Transaction;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * A REPARAÇÃO DO PASSADO — `contas:verificar` (20/09/2026).
 *
 * Os defeitos da auditoria estão corrigidos daqui para a frente. O que já
 * estava gravado continua como estava, e é este comando que o mostra e, com
 * `--aplicar`, o arruma.
 *
 * O QUE ELE NÃO PODE FAZER, e é o que estes ensaios guardam:
 *
 *  · NUNCA ESCREVER EM SIMULAÇÃO. Correr sem `--aplicar` conta e não toca.
 *  · NUNCA MEXER NO TURNO DE HOJE. O dinheiro reposto é de outro dia e de
 *    outra pessoa: metê-lo na gaveta de quem está agora ao balcão fazia essa
 *    pessoa responder por uma falta que não é dela.
 *  · NUNCA LANÇAR DUAS VEZES. Correr o comando outra vez não repete nada.
 */
class ReparacaoDasContasTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->comModulo('invoicing');

        \App\Models\Invoicing\InvoicingSeries::create([
            'tenant_id' => $this->tenant->id, 'series_code' => 'RC', 'name' => 'RC (ensaio)',
            'document_type' => 'receipt', 'agt_environment' => 'sandbox',
            'is_default' => true, 'is_active' => true,
        ]);

        CashRegister::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Balcão', 'code' => 'CXR' . random_int(100, 999),
            'user_id' => $this->user->id, 'is_active' => true, 'is_default' => true, 'status' => 'open',
            'opening_balance' => 0, 'current_balance' => 0, 'expected_balance' => 0,
        ]);
    }

    /** Uma factura como o hotel e o restaurante as deixavam: paga e a dever tudo. */
    private function facturaPagaSemValor(): SalesInvoice
    {
        $f = SalesInvoice::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $this->clienteEmpresa()->id,
            'invoice_number' => 'FT/' . random_int(10000, 99999), 'invoice_date' => now()->subMonth(),
            'invoice_type' => 'FT', 'status' => 'paid', 'subtotal' => 5000, 'total' => 5000,
            'paid_amount' => 0, 'created_by' => $this->user->id,
        ]);

        return $f;
    }

    /** Um adiantamento como os de antes: sem movimento de tesouraria nenhum. */
    private function adiantamentoSemMovimento(): Advance
    {
        return Advance::create([
            'tenant_id' => $this->tenant->id, 'type' => 'sale',
            'client_id' => $this->clienteEmpresa()->id,
            'payment_date' => now()->subMonth()->toDateString(),
            'amount' => 12000, 'remaining_amount' => 12000, 'used_amount' => 0,
            'payment_method' => 'cash', 'status' => 'available', 'created_by' => $this->user->id,
        ]);
    }

    private function correr(bool $aplicar = false): void
    {
        $opcoes = ['--tenant' => $this->tenant->id];

        if ($aplicar) {
            $opcoes['--aplicar'] = true;
        }

        $this->artisan('contas:verificar', $opcoes)->assertSuccessful();
    }

    public function test_em_simulacao_nao_escreve_nada(): void
    {
        $factura = $this->facturaPagaSemValor();
        $adiantamento = $this->adiantamentoSemMovimento();

        $this->correr(aplicar: false);

        $this->assertEqualsWithDelta(0, (float) $factura->fresh()->paid_amount, 0.01,
            'sem --aplicar não se grava um cêntimo');
        $this->assertSame(0, Transaction::withoutGlobalScopes()
            ->where('related_type', Advance::class)->where('related_id', $adiantamento->id)->count());
    }

    public function test_com_aplicar_arruma_o_que_encontra(): void
    {
        $factura = $this->facturaPagaSemValor();
        $adiantamento = $this->adiantamentoSemMovimento();

        $this->correr(aplicar: true);

        $this->assertEqualsWithDelta(5000, (float) $factura->fresh()->paid_amount, 0.01,
            'uma factura marcada como paga passa a dizer quanto foi pago');

        $movimento = Transaction::withoutGlobalScopes()
            ->where('related_type', Advance::class)->where('related_id', $adiantamento->id)->first();

        $this->assertNotNull($movimento, 'o adiantamento antigo entra na tesouraria');
        $this->assertEqualsWithDelta(12000, (float) $movimento->amount, 0.01);
        // A DATA É A DO DOCUMENTO, não a de hoje: senão o dinheiro de um mês
        // aparece todo no dia em que se correu o comando.
        $this->assertSame(
            now()->subMonth()->toDateString(),
            $movimento->transaction_date->toDateString(),
        );
    }

    public function test_o_dinheiro_reposto_nao_entra_no_turno_de_hoje(): void
    {
        $turno = PosShift::createSafely([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'status' => 'open', 'opened_at' => now(), 'opening_balance' => 0,
        ], $this->tenant->id);

        $this->adiantamentoSemMovimento();

        $this->correr(aplicar: true);

        $turno->refresh();

        $this->assertEqualsWithDelta(0, (float) $turno->cash_sales, 0.01,
            'quem está ao balcão hoje não responde por dinheiro do mês passado');
        $this->assertSame(0, $turno->transactions()->count());
    }

    public function test_correr_duas_vezes_nao_lanca_a_dobrar(): void
    {
        $adiantamento = $this->adiantamentoSemMovimento();

        $this->correr(aplicar: true);
        $this->correr(aplicar: true);

        $this->assertSame(1, Transaction::withoutGlobalScopes()
            ->where('related_type', Advance::class)->where('related_id', $adiantamento->id)->count());
    }

    public function test_um_movimento_de_recibo_apagado_devolve_o_saldo_a_gaveta(): void
    {
        $this->comPermissoes('invoicing.receipts.view', 'invoicing.receipts.create');

        $caixa = CashRegister::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $factura = $this->facturaPagaSemValor();

        $recibo = \App\Models\Invoicing\Receipt::create([
            'tenant_id' => $this->tenant->id, 'type' => 'sale',
            'client_id' => $factura->client_id, 'invoice_id' => $factura->id,
            'payment_date' => now()->toDateString(), 'payment_method' => 'cash',
            'amount_paid' => 1500, 'status' => 'issued', 'created_by' => $this->user->id,
        ]);

        app(\App\Services\Invoicing\LancamentoDoRecibo::class)->lancar($recibo, [], $this->user->id);

        $this->assertEqualsWithDelta(1500, (float) $caixa->fresh()->current_balance, 0.01);

        // Apagado por baixo, como aconteceu antes de o gancho estornar.
        DB::table('invoicing_receipts')->where('id', $recibo->id)->update(['deleted_at' => now()]);

        $this->correr(aplicar: true);

        $this->assertSame(0, Transaction::withoutGlobalScopes()
            ->where('related_type', \App\Models\Invoicing\Receipt::class)
            ->where('related_id', $recibo->id)->count());
        $this->assertEqualsWithDelta(0, (float) $caixa->fresh()->current_balance, 0.01,
            'o saldo da gaveta volta ao que era');
    }
}
