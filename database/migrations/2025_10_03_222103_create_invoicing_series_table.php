<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoicing_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            // Tipo de Documento
            $table->enum('document_type', ['invoice', 'proforma', 'receipt', 'credit_note', 'debit_note'])->comment('Tipo de documento');
            
            // Série (ex: A, B, C, 01, 02, etc)
            $table->string('series_code', 10)->comment('Código da série após FT');
            
            // Nome/Descrição
            $table->string('name', 100)->comment('Nome da série ex: Vendas Loja, Vendas Online');
            
            // Formato completo: FT A/2025/000001
            $table->string('prefix', 10)->default('FT')->comment('Prefixo fixo (FT, PRF, RC)');
            $table->boolean('include_year')->default(true)->comment('Incluir ano no formato');
            
            // Numeração
            $table->unsignedInteger('next_number')->default(1)->comment('Próximo número sequencial');
            $table->unsignedInteger('number_padding')->default(6)->comment('Zeros à esquerda (6 = 000001)');
            
            // Configurações
            $table->boolean('is_default')->default(false)->comment('Série padrão para este tipo');
            $table->boolean('is_active')->default(true)->comment('Série ativa');
            
            // Validação de ano
            $table->year('current_year')->nullable()->comment('Ano atual da numeração');
            $table->boolean('reset_yearly')->default(true)->comment('Resetar numeração anualmente');
            
            // Restrições
            $table->text('description')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->unique(['tenant_id', 'document_type', 'series_code'], 'unique_tenant_document_series');
            $table->index(['tenant_id', 'document_type', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_series');
    }
};
