<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interruptor da máscara de dinheiro nos inputs de preço/valores.
 *
 * Os separadores e as casas decimais já vêm de `number_format` e
 * `decimal_places`; isto só liga ou desliga a máscara (1.234,56) nos ecrãs de
 * emissão e no POS. Nasce LIGADA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->boolean('price_mask_enabled')->default(true)->after('decimal_places');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->dropColumn('price_mask_enabled');
        });
    }
};
