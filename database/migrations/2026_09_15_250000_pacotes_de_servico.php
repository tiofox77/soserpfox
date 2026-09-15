<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OS PACOTES DE SERVIÇO (15/09/2026, OF-07).
 *
 * «Revisão 10 000 km» = mão-de-obra + óleo + filtros, montado uma vez e posto
 * numa ordem com um clique. As linhas guardam-se com o pacote (JSON): o serviço
 * ou o artigo do catálogo, o nome, a quantidade, o preço e as horas. Mudar o
 * pacote depois não mexe nas ordens onde ele já entrou.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshop_packages')) {
            return;
        }

        Schema::create('workshop_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->json('lines');
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('hours', 8, 2)->default(0);
            $table->unsignedInteger('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_packages');
    }
};
