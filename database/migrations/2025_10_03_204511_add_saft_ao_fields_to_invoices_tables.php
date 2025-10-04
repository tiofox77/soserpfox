<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Adicionar campos SAFT-AO em sales_invoices
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->string('atcud')->nullable()->after('invoice_number'); // Código Único do Documento
            $table->string('invoice_type', 2)->default('FT')->after('atcud'); // FT, FR, FS, NC, ND
            $table->enum('invoice_status', ['N', 'A', 'F'])->default('N')->after('status'); // Normal, Anulado, Finalizado
            $table->timestamp('invoice_status_date')->nullable()->after('invoice_status');
            $table->string('source_id')->nullable()->after('invoice_status_date'); // User ID
            $table->string('source_billing')->default('SOSERP/1.0')->after('source_id');
            $table->text('hash')->nullable()->after('source_billing'); // Assinatura digital
            $table->string('hash_control')->default('1')->after('hash');
            $table->string('hash_previous')->nullable()->after('hash_control');
            $table->timestamp('system_entry_date')->nullable()->after('invoice_date');
            $table->decimal('net_total', 15, 2)->default(0)->after('subtotal'); // Total sem IVA
            $table->decimal('gross_total', 15, 2)->default(0)->after('total'); // Total com IVA
            $table->decimal('tax_payable', 15, 2)->default(0)->after('tax_amount'); // IVA a pagar
        });

        // Adicionar campos SAFT-AO em purchase_invoices
        Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->string('atcud')->nullable()->after('invoice_number');
            $table->string('invoice_type', 2)->default('FT')->after('atcud');
            $table->enum('invoice_status', ['N', 'A', 'F'])->default('N')->after('status');
            $table->timestamp('invoice_status_date')->nullable()->after('invoice_status');
            $table->string('source_id')->nullable()->after('invoice_status_date');
            $table->string('source_billing')->default('SOSERP/1.0')->after('source_id');
            $table->text('hash')->nullable()->after('source_billing');
            $table->string('hash_control')->default('1')->after('hash');
            $table->string('hash_previous')->nullable()->after('hash_control');
            $table->timestamp('system_entry_date')->nullable()->after('invoice_date');
            $table->decimal('net_total', 15, 2)->default(0)->after('subtotal');
            $table->decimal('gross_total', 15, 2)->default(0)->after('total');
            $table->decimal('tax_payable', 15, 2)->default(0)->after('tax_amount');
        });

        // Adicionar campo NIF em clients (se não existir)
        if (!Schema::hasColumn('invoicing_clients', 'tax_id')) {
            Schema::table('invoicing_clients', function (Blueprint $table) {
                $table->string('tax_id')->nullable()->after('email'); // NIF
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_sales_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'atcud', 'invoice_type', 'invoice_status', 'invoice_status_date',
                'source_id', 'source_billing', 'hash', 'hash_control', 'hash_previous',
                'system_entry_date', 'net_total', 'gross_total', 'tax_payable'
            ]);
        });

        Schema::table('invoicing_purchase_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'atcud', 'invoice_type', 'invoice_status', 'invoice_status_date',
                'source_id', 'source_billing', 'hash', 'hash_control', 'hash_previous',
                'system_entry_date', 'net_total', 'gross_total', 'tax_payable'
            ]);
        });

        if (Schema::hasColumn('invoicing_clients', 'tax_id')) {
            Schema::table('invoicing_clients', function (Blueprint $table) {
                $table->dropColumn('tax_id');
            });
        }
    }
};
