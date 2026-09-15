<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A INSPECÇÃO DIGITAL COM SEMÁFORO (15/09/2026, OF-02).
 *
 * Os MODELOS são de cada empresa («Revisão geral — 33 pontos»): os pontos
 * escrevem-se um por linha, «Secção: ponto». A INSPECÇÃO de uma ordem copia os
 * pontos do modelo no dia em que começa — mudar o modelo depois não mexe nas
 * inspecções já feitas. Cada ponto fica OK, Atenção, Urgente ou Não se aplica,
 * com nota e fotografia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workshop_inspection_templates')) {
            Schema::create('workshop_inspection_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('description', 500)->nullable();
                $table->text('points');
                $table->unsignedSmallInteger('points_count')->default(0);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'name']);
            });
        }

        if (! Schema::hasTable('workshop_work_order_inspections')) {
            Schema::create('workshop_work_order_inspections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('work_order_id')->constrained('workshop_work_orders')->cascadeOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('workshop_inspection_templates')->nullOnDelete();
                $table->string('name', 120);
                $table->json('results');
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'work_order_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_work_order_inspections');
        Schema::dropIfExists('workshop_inspection_templates');
    }
};
