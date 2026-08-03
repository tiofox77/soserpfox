<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registo de actividade sobre produtos.
 *
 * O produto já era eliminado com soft delete (fica na base de dados), mas não
 * havia forma de saber QUEM o eliminou, QUANDO, nem o que foi alterado ao longo
 * do tempo — e não havia sequer forma de o restaurar. Num ERP com várias caixas
 * a mexer no mesmo catálogo, "o produto desapareceu" tinha de ser investigado à
 * mão na base de dados.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_activity_logs')) {
            return;
        }

        Schema::create('product_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id')->nullable();

            // criado | actualizado | eliminado | restaurado | stock
            $table->string('action', 20);

            // Nome do produto no momento da acção: se for eliminado à força
            // mais tarde, o registo continua legível.
            $table->string('product_name')->nullable();
            $table->text('description')->nullable();

            // Antes/depois dos campos alterados
            $table->json('changes')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_activity_logs');
    }
};
