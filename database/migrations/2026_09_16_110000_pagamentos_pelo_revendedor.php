<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O REVENDEDOR PAGA PELO CLIENTE (16/09/2026).
 *
 * O pagamento à plataforma é manual (transferência + comprovativo) e quem o
 * confirma é o super admin. O revendedor passa a poder fazê-lo em nome das
 * empresas dele — nos pedidos de plano e nas facturas de renovação — e fica
 * escrito que foi ele, para o super admin o ver ao aprovar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'reseller_id')) {
                $table->foreignId('reseller_id')->nullable()->after('user_id')->constrained('resellers')->nullOnDelete();
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'payment_proof')) {
                $table->string('payment_proof')->nullable()->after('payment_reference');
                // Enviado e por confirmar: é isto que põe a factura na fila do super admin.
                $table->timestamp('payment_submitted_at')->nullable()->after('payment_proof');
                $table->foreignId('payment_submitted_by_reseller_id')->nullable()->after('payment_submitted_at')->constrained('resellers')->nullOnDelete();
                $table->text('payment_rejection_reason')->nullable()->after('payment_submitted_by_reseller_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'payment_proof')) {
                $table->dropConstrainedForeignId('payment_submitted_by_reseller_id');
                $table->dropColumn(['payment_proof', 'payment_submitted_at', 'payment_rejection_reason']);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'reseller_id')) {
                $table->dropConstrainedForeignId('reseller_id');
            }
        });
    }
};
