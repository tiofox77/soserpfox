<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * O VALOR ENTREGUE AO BALCÃO NÃO É O VALOR PAGO (23/09/2026).
 *
 * A venda do POS gravava em `paid_amount` o que o cliente ENTREGOU — troco
 * incluído. Uma venda de 12.700 paga com uma nota de 14.000 ficava «paga» em
 * 14.000: o painel da tesouraria mostrava «Já cobrado» acima do facturado, a
 * factura ficava com saldo negativo e o cliente com um crédito falso de 1.300
 * no extracto. A tesouraria estava certa (recebeu 12.700); a factura não.
 *
 * O valor entregue passa a ter a sua coluna — é dele que o talão tira o
 * «Recebido» e o «Troco» — e o `paid_amount` é o que a factura foi paga.
 *
 * O PASSADO: nas facturas-recibo do POS com mais pago do que o total, o
 * excesso é o troco — passa para a coluna nova e o pago fica o total. O
 * dinheiro da tesouraria não se toca.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('invoicing_sales_invoices', 'amount_received')) {
            Schema::table('invoicing_sales_invoices', function (Blueprint $t) {
                $t->decimal('amount_received', 15, 2)->nullable()->after('paid_amount');
            });
        }

        $acertadas = DB::table('invoicing_sales_invoices')
            ->where('invoice_type', 'FR')
            ->where('source_billing', 'P')
            ->whereNull('amount_received')
            ->whereRaw('paid_amount > total + 0.009')
            ->update([
                'amount_received' => DB::raw('paid_amount'),
                'paid_amount' => DB::raw('total'),
            ]);

        Log::info('Migração: troco das vendas do POS tirado do valor pago', ['facturas' => $acertadas]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoicing_sales_invoices', 'amount_received')) {
            return;
        }

        DB::table('invoicing_sales_invoices')
            ->whereNotNull('amount_received')
            ->whereRaw('amount_received > paid_amount')
            ->update(['paid_amount' => DB::raw('amount_received')]);

        Schema::table('invoicing_sales_invoices', function (Blueprint $t) {
            $t->dropColumn('amount_received');
        });
    }
};
