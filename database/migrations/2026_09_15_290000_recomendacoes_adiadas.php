<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AS RECOMENDAÇÕES ADIADAS (15/09/2026, OF-12).
 *
 * O que o cliente recusou ou deixou para depois — uma linha recusada no
 * orçamento, uma linha adiada pela oficina, um ponto amarelo ou vermelho da
 * inspecção — fica guardado POR VIATURA, com a data em que se volta a propor.
 * Na visita seguinte aparece na ordem para juntar com um clique; entretanto,
 * os Lembretes chamam o cliente.
 *
 * A linha copia-se (nome, preço, quantidade): a ordem de origem pode ser
 * apagada ou facturada sem que a recomendação se perca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshop_deferred_items')) {
            return;
        }

        Schema::create('workshop_deferred_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('workshop_vehicles')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('workshop_work_orders')->nullOnDelete();
            $table->unsignedBigInteger('work_order_item_id')->nullable()->index();
            $table->string('type', 10)->default('service');
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('code', 60)->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('hours', 8, 2)->default(0);
            $table->string('origin', 20);
            $table->string('severity', 10)->nullable();
            $table->date('follow_up_on')->nullable();
            $table->string('status', 15)->default('pendente');
            $table->foreignId('resolved_work_order_id')->nullable()->constrained('workshop_work_orders')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'follow_up_on']);
            $table->index(['vehicle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_deferred_items');
    }
};
