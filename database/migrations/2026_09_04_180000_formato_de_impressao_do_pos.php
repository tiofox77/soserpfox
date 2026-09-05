<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Em que papel sai a venda do balcão.
 *
 * O POS só sabia imprimir talão de 80 mm. Serve uma loja com impressora
 * térmica, e não serve quem vende a empresas e entrega uma factura em A4 —
 * que é a maioria de quem factura a sério em Angola.
 *
 * A4 POR OMISSÃO. É o formato que qualquer impressora imprime; o talão exige
 * uma impressora própria. Quem tem térmica troca uma vez nas definições e não
 * volta a pensar nisso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->string('pos_formato_impressao', 10)
                ->default('a4')
                ->after('pos_show_product_images')
                ->comment('a4 | talao — o papel em que a venda do POS sai por omissão');
        });
    }

    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->dropColumn('pos_formato_impressao');
        });
    }
};
