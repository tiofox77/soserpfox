<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atualizações (F4): versões publicadas + o alvo POR-TENANT que o super admin
 * decide. Tabelas do lado do SERVIDOR de licenças (cloud). Aditivas e inertes
 * até haver uma versão publicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_updates', function (Blueprint $table) {
            $table->id();
            $table->string('versao')->unique();          // ex.: 1.2.0
            $table->string('min_versao')->nullable();     // versão mínima para saltar
            $table->text('notas')->nullable();
            $table->string('pacote_url');
            $table->string('pacote_sha256', 64);
            $table->boolean('obrigatorio')->default(false);
            // Rollout global: 'none' = só a quem estiver em app_update_targets;
            // 'all' = toda a gente. O canary faz-se com 'none' + alvos.
            $table->enum('rollout', ['none', 'all'])->default('none');
            $table->text('manifesto');                    // token SOSERP-UPD assinado
            $table->timestamps();

            $table->index('rollout');
        });

        Schema::create('app_update_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('versao');
            $table->timestamps();

            $table->unique(['tenant_id', 'versao']);
            $table->index('versao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_update_targets');
        Schema::dropIfExists('app_updates');
    }
};
