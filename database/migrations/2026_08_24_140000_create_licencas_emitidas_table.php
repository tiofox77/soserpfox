<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As instalações on-premise que existem lá fora — uma linha por INSTALAÇÃO
 * (empresa + máquina), não por licença emitida: uma renovação actualiza a
 * mesma linha em vez de criar histórico que ninguém lê.
 *
 * É isto que dá a lista de "clientes offline": quem tem o soserp instalado,
 * com que licença, e quando é que aquela máquina falou connosco pela última
 * vez. Sem isto o painel só sabia dos pedidos, não das instalações a correr.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licencas_emitidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('license_request_id')->nullable()
                ->constrained('license_requests')->nullOnDelete();

            $table->string('fingerprint', 64)->nullable();   // null = licença flutuante
            $table->string('plano')->nullable();
            $table->json('modulos')->nullable();
            $table->unsignedSmallInteger('max_users')->nullable();

            $table->timestamp('emitida_em')->nullable();
            $table->timestamp('expira_em')->nullable();

            // Preenchidos pelo check-in da instalação:
            $table->timestamp('ultimo_checkin')->nullable();
            $table->string('versao_instalada', 40)->nullable();
            $table->string('ultimo_ip', 45)->nullable();

            $table->timestamps();

            // Uma instalação = uma empresa numa máquina.
            $table->unique(['tenant_id', 'fingerprint']);
            $table->index('ultimo_checkin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencas_emitidas');
    }
};
