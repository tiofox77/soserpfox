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
        Schema::create('accounting_document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            // Campos principais baseados no Excel
            $table->string('code', 10)->comment('Código do documento (ex: 101, 211, 541)');
            $table->string('description')->comment('Descrição do tipo de documento');
            $table->string('journal_code', 10)->nullable()->comment('Código do diário associado');
            
            // Relacionamento com Journal
            $table->foreignId('journal_id')->nullable()->constrained('accounting_journals')->onDelete('set null');
            
            // Flags booleanas do Excel
            $table->boolean('recapitulativos')->default(false)->comment('Documentos recapitulativos');
            $table->boolean('retencao_fonte')->default(false)->comment('Retenção na fonte');
            $table->boolean('bal_financeira')->default(true)->comment('Balancete financeira');
            $table->boolean('bal_analitica')->default(false)->comment('Balancete analítica');
            
            // Campos numéricos
            $table->integer('rec_informacao')->default(0)->comment('Rec. Informação');
            $table->integer('tipo_doc_imo')->default(0)->comment('Tipo Doc. Imo.');
            $table->integer('calculo_fluxo_caixa')->default(0)->comment('Cálculo Fluxo Caixa');
            
            // Campos adicionais para gestão
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'journal_id']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_document_types');
    }
};
