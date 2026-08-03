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
        Schema::create('agt_iec_pautal_codes', function (Blueprint $table) {
            $table->id();
            // Código pautal aduaneiro (ex: 22030000, 24021000)
            $table->string('pautal_code', 16)->unique();
            // Descrição do produto/categoria
            $table->string('description', 500);
            // Categoria (bebida_alcoolica, tabaco, combustivel, veiculo, etc.)
            $table->string('category', 64)->index();
            // Taxa IEC (% ad valorem)
            $table->decimal('rate_percentage', 5, 2)->default(0);
            // Valor específico (AOA por unidade) — se aplicável
            $table->decimal('specific_amount', 12, 2)->nullable();
            $table->string('unit_of_measure', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agt_iec_pautal_codes');
    }
};
