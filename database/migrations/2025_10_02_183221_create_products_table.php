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
        Schema::create('invoicing_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            
            // Tipo
            $table->enum('type', ['produto', 'servico'])->default('produto');
            
            // Identificação
            $table->string('code')->unique(); // Código do produto/serviço
            $table->string('name'); // Nome
            $table->text('description')->nullable();
            
            // Categoria
            $table->string('category')->nullable(); // Software, Hardware, Consultoria, etc
            
            // Preços (em Kwanzas)
            $table->decimal('price', 15, 2)->default(0); // Preço base
            $table->decimal('cost', 15, 2)->default(0); // Custo
            
            // IVA
            $table->boolean('is_iva_subject')->default(true); // Sujeito a IVA
            $table->decimal('iva_rate', 5, 2)->default(14); // Taxa de IVA (14%)
            $table->string('iva_reason')->nullable(); // Motivo de isenção (se aplicável)
            
            // Estoque (se aplicável)
            $table->boolean('manage_stock')->default(false);
            $table->integer('stock_quantity')->default(0);
            $table->integer('minimum_stock')->default(0);
            
            // Unidade
            $table->string('unit')->default('UN'); // UN, HR, KG, etc
            
            // Status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['tenant_id', 'is_active']);
            $table->index('code');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_products');
    }
};
