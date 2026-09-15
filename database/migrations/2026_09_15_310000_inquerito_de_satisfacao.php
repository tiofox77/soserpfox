<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O INQUÉRITO DE SATISFAÇÃO (15/09/2026, OF-14).
 *
 * Um por ordem entregue: um link sem conta (`token`), a nota de 1 a 5, se
 * recomendaria a oficina e um comentário. O mecânico copia-se da ordem no
 * momento da entrega — é a ele que a nota conta, mesmo que a ordem mude depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshop_surveys')) {
            return;
        }

        Schema::create('workshop_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('work_order_id')->unique()->constrained('workshop_work_orders')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('workshop_vehicles')->nullOnDelete();
            $table->unsignedBigInteger('mechanic_id')->nullable()->index();
            $table->string('token', 48)->unique();
            $table->unsignedTinyInteger('score')->nullable();
            $table->boolean('would_recommend')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'answered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_surveys');
    }
};
