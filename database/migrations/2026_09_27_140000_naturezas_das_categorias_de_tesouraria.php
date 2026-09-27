<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A NATUREZA DE CADA CATEGORIA DE MOVIMENTO, POR EMPRESA (27/09/2026).
 *
 * O DRE Integrado tem de distinguir, nos movimentos da tesouraria, o que é
 * despesa de verdade do que é compra de stock, aquisição de activo, pagamento
 * de dívida ou simples transferência. As categorias de sistema já trazem a sua
 * natureza (`DreIntegrado::POR_OMISSAO`); aqui fica o que cada empresa decide
 * para as suas — e para as de sistema, se quiser outra.
 *
 * `categoria` é o CÓDIGO que os movimentos guardam (`treasury_transactions.category`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_naturezas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('categoria', 100);
            $table->string('natureza', 30);
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['tenant_id', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_naturezas');
    }
};
