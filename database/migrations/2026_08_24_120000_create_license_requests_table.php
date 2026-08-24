<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos de licença vindos das instalações on-premise.
 *
 * O cliente instala, vê o ecrã de licença e SOLICITA — a instalação envia (ou o
 * cliente cola) os dados da empresa + a impressão digital da máquina. O super
 * admin aprova, e a licença emitida fica aqui para a instalação a ir buscar
 * sozinha no próximo arranque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_requests', function (Blueprint $table) {
            $table->id();
            // Código público do pedido: é por ele que a instalação volta a
            // perguntar "já foi aprovado?" sem precisar de autenticação.
            $table->string('codigo', 40)->unique();

            $table->string('empresa');
            $table->string('nif', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('responsavel')->nullable();
            $table->unsignedSmallInteger('utilizadores')->default(1);
            $table->string('fingerprint', 64)->nullable();
            $table->string('versao', 40)->nullable();
            $table->text('observacoes')->nullable();

            $table->enum('estado', ['pendente', 'aprovado', 'recusado'])->default('pendente');
            // Preenchidos na aprovação:
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->text('licenca')->nullable();          // o token emitido
            $table->string('motivo_recusa')->nullable();
            $table->timestamp('aprovado_em')->nullable();
            // Marca quando a instalação já veio buscar a licença.
            $table->timestamp('entregue_em')->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_requests');
    }
};
