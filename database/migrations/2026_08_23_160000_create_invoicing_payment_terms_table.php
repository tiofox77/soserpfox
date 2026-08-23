<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Condições de pagamento (por empresa), geríveis pelo utilizador.
 *
 * Cada cliente aponta para uma condição (pronto pagamento, 15 dias, 30 dias,
 * depósito, …). O nº de dias alimenta o vencimento da factura. O catálogo é
 * por tenant e o utilizador pode acrescentar as suas; ficam já semeadas as
 * padrão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_payment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->unsignedInteger('days')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // O nome é único por empresa (não faz sentido duas "30 dias").
            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('invoicing_clients', function (Blueprint $table) {
            $table->foreignId('payment_term_id')
                ->nullable()
                ->after('payment_term_days')
                ->constrained('invoicing_payment_terms')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_term_id');
        });
        Schema::dropIfExists('invoicing_payment_terms');
    }
};
