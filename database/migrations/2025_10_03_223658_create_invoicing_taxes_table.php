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
        Schema::create('invoicing_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            // Informações básicas
            $table->string('code', 20)->comment('Código do imposto ex: IVA14');
            $table->string('name', 100)->comment('Nome do imposto');
            $table->text('description')->nullable();
            
            // Taxa
            $table->decimal('rate', 5, 2)->comment('Taxa percentual ex: 14.00');
            
            // Tipo de imposto
            $table->enum('type', ['iva', 'irt', 'other'])->default('iva')->comment('Tipo: IVA, IRT, Outro');
            
            // Classificação SAFT
            $table->string('saft_code', 10)->nullable()->comment('Código SAFT-AO');
            $table->enum('saft_type', ['NOR', 'RED', 'ISE', 'NS', 'OUT'])->nullable()->comment('Tipo SAFT: Normal, Reduzida, Isento, Não Sujeito, Outro');
            
            // Razão de isenção (se aplicável)
            $table->string('exemption_reason', 255)->nullable()->comment('Motivo de isenção para SAFT');
            
            // Status
            $table->boolean('is_default')->default(false)->comment('Imposto padrão');
            $table->boolean('is_active')->default(true)->comment('Ativo/Inativo');
            
            // Configurações adicionais
            $table->boolean('include_in_price')->default(false)->comment('Incluído no preço');
            $table->boolean('compound_tax')->default(false)->comment('Imposto composto');
            
            $table->timestamps();
            
            // Índices
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_taxes');
    }
};
