<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ENTREGA DA VIATURA COM ASSINATURA (15/09/2026, OF-13).
 *
 * O termo de levantamento: os km e o combustível à saída, o que se conferiu com
 * o cliente, quem levantou (e o documento), e a assinatura — presa ao que se
 * assinou, como no check-in. Guarda-se também o saldo em falta NESSE momento:
 * a viatura saiu com X por pagar, e isso não muda quando o recibo chegar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshop_work_order_handovers')) {
            return;
        }

        Schema::create('workshop_work_order_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('work_order_id')->unique()->constrained('workshop_work_orders')->cascadeOnDelete();
            $table->unsignedInteger('mileage_out')->nullable();
            $table->unsignedTinyInteger('fuel_level')->nullable();
            $table->json('checklist')->nullable();
            $table->string('received_by', 150)->nullable();
            $table->string('received_by_document', 40)->nullable();
            $table->text('notes')->nullable();
            $table->mediumText('signature')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_hash', 64)->nullable();
            $table->decimal('balance_due', 14, 2)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_work_order_handovers');
    }
};
