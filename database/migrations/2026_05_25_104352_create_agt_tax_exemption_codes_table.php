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
        Schema::create('agt_tax_exemption_codes', function (Blueprint $table) {
            $table->id();
            // Tipo de imposto AGT (DS.120 §4.1)
            $table->string('tax_type', 8)->index(); // IVA | IS | IEC | NS
            // Código de isenção (M01..M93, S01..S03, I01..I16)
            $table->string('code', 16)->unique();
            // Razão / descrição da isenção
            $table->text('description');
            // Base legal (artigo, decreto)
            $table->string('legal_basis', 255)->nullable();
            // Activo (alguns códigos podem ser descontinuados)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tax_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agt_tax_exemption_codes');
    }
};
