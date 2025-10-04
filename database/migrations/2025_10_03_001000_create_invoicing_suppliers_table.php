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
        Schema::create('invoicing_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            
            // Tipo de fornecedor
            $table->enum('type', ['pessoa_fisica', 'pessoa_juridica'])->default('pessoa_juridica');
            
            // Dados básicos
            $table->string('name');
            $table->string('nif')->nullable();
            $table->string('logo')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            
            // Endereço
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Angola');
            
            // Dados comerciais
            $table->string('tax_regime')->default('geral');
            $table->boolean('is_iva_subject')->default(true);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->integer('payment_term_days')->default(30);
            
            // Informações adicionais
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['tenant_id', 'is_active']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_suppliers');
    }
};
