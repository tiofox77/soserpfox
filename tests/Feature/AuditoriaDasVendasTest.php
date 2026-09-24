<?php

namespace Tests\Feature;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\Invoicing\Stock;
use App\Models\Invoicing\StockMovement;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * `documentos:ver --auditar` encontra as marcas do caso da Luk Simões
 * (23/09/2026): a venda repetida e o stock que desceu uma vez por duas.
 */
class AuditoriaDasVendasTest extends TenantTestCase
{
    public function test_encontra_a_venda_repetida_e_a_quebra_na_cadeia_dos_saldos(): void
    {
        $armazem = getOrCreateDefaultWarehouse()->id;
        $artigo = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Inalador ' . uniqid(), 'type' => 'produto',
            'price' => 500, 'cost' => 200, 'unit' => 'UN', 'manage_stock' => true, 'is_active' => true,
        ]);

        $venda = function (string $numero, string $quando) use ($artigo, $armazem) {
            $f = SalesInvoice::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenant->id, 'client_id' => $this->cliente->id, 'invoice_number' => $numero,
                'invoice_type' => 'FR', 'invoice_date' => now()->toDateString(), 'status' => 'paid',
                'subtotal' => 500, 'tax_amount' => 0, 'total' => 500, 'paid_amount' => 500, 'amount_received' => 500,
                'payment_method' => 'cash', 'created_by' => $this->user->id,
            ]);
            DB::table('invoicing_sales_invoices')->where('id', $f->id)->update(['created_at' => $quando]);
            SalesInvoiceItem::create([
                'sales_invoice_id' => $f->id, 'product_id' => $artigo->id, 'product_name' => $artigo->name,
                'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 500, 'order' => 1,
            ]);
            // As duas leram 11 e gravaram 10: a marca da baixa perdida.
            StockMovement::semAplicarStock(fn () => StockMovement::create([
                'tenant_id' => $this->tenant->id, 'warehouse_id' => $armazem, 'product_id' => $artigo->id, 'type' => 'out',
                'quantity' => 1, 'balance_before' => 11, 'balance_after' => 10,
                'reference_type' => SalesInvoice::class, 'reference_id' => $f->id, 'user_id' => $this->user->id,
            ]));

            return $f;
        };

        $hoje = now()->toDateString();
        $venda('FR TESTE/000001', "{$hoje} 11:55:53");
        $venda('FR TESTE/000002', "{$hoje} 11:55:54");
        Stock::updateOrCreate(['tenant_id' => $this->tenant->id, 'warehouse_id' => $armazem, 'product_id' => $artigo->id], ['quantity' => 10]);

        Artisan::call('documentos:ver', ['--auditar' => true, '--tenant' => $this->tenant->id, '--de' => $hoje, '--ate' => $hoje]);
        $saida = Artisan::output();

        $this->assertStringContainsString('FR TESTE/000001 (11:55:53) e FR TESTE/000002 (11:55:54): 1 s', $saida);
        $this->assertStringContainsString('quebras na cadeia dos saldos', $saida);
        $this->assertStringContainsString('esperava 10, leu 11', $saida);
        $this->assertStringContainsString('ponto(s) a ver', $saida);
    }

    public function test_os_turnos_refeitos_e_so_o_operador_pedido(): void
    {
        $hoje = now()->toDateString();
        $turno = \App\Models\Invoicing\PosShift::createSafely([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id,
            'status' => 'open', 'opened_at' => now(), 'opening_balance' => 1000,
        ], $this->tenant->id);
        $turno->addTransaction(['type' => 'invoice', 'payment_method' => 'cash', 'amount' => 2000, 'description' => 'Venda']);
        // Fechou com 500 a menos na gaveta: esperava 3000, contou 2500.
        $turno->fresh()->close(2500, null, 'Troco mal dado');

        Artisan::call('documentos:ver', ['--auditar' => true, '--tenant' => $this->tenant->id, '--de' => $hoje, '--ate' => $hoje, '--operador' => $this->user->id]);
        $saida = Artisan::output();

        $this->assertStringContainsString('Só o operador #' . $this->user->id, $saida);
        $this->assertStringContainsString('numerário esperado na gaveta 3 000,00 · contado 2 500,00', $saida);
        $this->assertStringContainsString('falta de caixa no fecho: 500,00 Kz — motivo: Troco mal dado', $saida);

        // Outro operador não vê este turno.
        Artisan::call('documentos:ver', ['--auditar' => true, '--tenant' => $this->tenant->id, '--de' => $hoje, '--ate' => $hoje, '--operador' => 999999]);
        $this->assertStringContainsString('(nenhum turno no período)', Artisan::output());
    }
}
