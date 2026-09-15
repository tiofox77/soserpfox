<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O REGISTO DE TEMPOS POR TAREFA (15/09/2026, OF-06).
 *
 * Um mecânico começa, pausa (pára) e volta a começar o trabalho numa ordem — e,
 * se quiser, numa linha de serviço dela. Cada período é uma linha: começou,
 * acabou, minutos. As horas TRABALHADAS comparam-se com as horas VENDIDAS (as
 * da linha do serviço): é daí que sai a eficiência de cada mecânico.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshop_time_entries')) {
            return;
        }

        Schema::create('workshop_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('workshop_work_orders')->cascadeOnDelete();
            $table->foreignId('work_order_item_id')->nullable()->constrained('workshop_work_order_items')->nullOnDelete();
            $table->foreignId('mechanic_id')->constrained('workshop_mechanics')->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('minutes')->default(0);
            $table->string('notes', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'mechanic_id', 'ended_at']);
            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_time_entries');
    }
};
