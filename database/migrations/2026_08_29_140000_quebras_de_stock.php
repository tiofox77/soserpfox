<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As quebras: o produto que expirou, se estragou, partiu ou se perdeu.
 *
 * O restaurante já tinha o seu desperdício (preso à comanda); o resto da casa
 * não tinha NADA — o artigo estragado ou saía do stock por um "ajuste" mudo,
 * ou ficava lá a fingir que existia. As duas mentiras custam dinheiro: uma
 * esconde a perda, a outra vende o que não há.
 *
 * Isto é o registo GENÉRICO, na Facturação, porque é lá que vivem os artigos
 * de toda a gente — salão, oficina e restaurante incluídos. Cada quebra:
 *
 *   · lança um movimento de stock `out` (as linhas são a fonte de verdade;
 *     o observer aplica o agregado — nunca se toca no stock à mão);
 *   · congela o CUSTO do momento, porque o relatório de perdas tem de
 *     sobreviver a mudanças de preço posteriores;
 *   · tem motivo de uma lista fechada, porque cem "outros" não ensinam nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_wastes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->string('reason', 30); // expirado|estragado|partido|perdido|uso_interno|outro
            $table->text('notes')->nullable();

            // O custo congelado no momento da quebra.
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);

            // O movimento de stock que esta quebra lançou — e o inverso, se
            // for anulada. Nunca se apaga: anula-se com o movimento contrário.
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->unsignedBigInteger('reversal_movement_id')->nullable();
            $table->timestamp('annulled_at')->nullable();
            $table->unsignedBigInteger('annulled_by')->nullable();

            // De que módulo veio o registo (invoicing|salon|workshop|restaurant)
            // — o relatório separa por cá.
            $table->string('source_module', 20)->default('invoicing');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'reason']);
            $table->index(['tenant_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_wastes');
    }
};
