<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OS SINISTROS COM SEGURADORA (15/09/2026, OF-15).
 *
 * Uma ordem de bate-chapa paga por uma seguradora: a seguradora (um cliente da
 * casa, pessoa colectiva), o nº do processo e da apólice, a data do sinistro,
 * o perito, o valor que a seguradora aprovou e a FRANQUIA a cargo do cliente.
 *
 * Ao facturar saem DUAS facturas: a da seguradora (o total menos a franquia) e
 * a do cliente (a franquia). A da seguradora fica em `work_orders.invoice_id`,
 * como sempre; a da franquia em `excess_invoice_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshop_claims')) {
            return;
        }

        Schema::create('workshop_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('work_order_id')->unique()->constrained('workshop_work_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('insurer_client_id')->nullable()->index();
            $table->string('claim_number', 60)->nullable();
            $table->string('policy_number', 60)->nullable();
            $table->date('accident_date')->nullable();
            $table->string('adjuster_name', 150)->nullable();
            $table->string('adjuster_phone', 30)->nullable();
            $table->string('adjuster_email', 150)->nullable();
            $table->date('inspection_date')->nullable();
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->decimal('excess_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('aberto');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('excess_invoice_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_claims');
    }
};
