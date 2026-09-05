<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A gorjeta e a taxa de serviço — DUAS COISAS, e não uma com dois nomes.
 *
 * Não havia nem uma nem outra. Quem quisesse registar gorjeta metia-a como
 * artigo, o que a misturava com as vendas e estragava tanto o relatório como o
 * acerto do caixa ao fecho. Mas juntá-las agora numa coluna só seria repetir o
 * erro noutro sítio, porque são mesmo diferentes:
 *
 *   · TAXA DE SERVIÇO — cobrada PELA CASA, uma percentagem da conta. É
 *     receita do restaurante, entra na factura como linha e é tributada como
 *     qualquer outra. O cliente paga-a porque está na conta.
 *
 *   · GORJETA — dada PELO CLIENTE, por cima da conta, para o pessoal. NÃO é
 *     venda da casa e não entra na factura: pô-la lá seria declarar como
 *     receita dinheiro que não é da empresa. Mas passa pela caixa — e por
 *     isso tem de ser registada, senão o dinheiro contado ao fecho do turno
 *     nunca bate com o que o sistema diz que devia lá estar.
 *
 * A distinção não é académica: é a diferença entre a caixa fechar certa e o
 * operador levar com uma diferença que não sabe explicar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            // Zero por omissão: a maioria das casas em Angola não cobra
            // serviço, e ligar isto sozinho punha-lhes uma cobrança nova na
            // conta dos clientes sem ninguém ter pedido.
            $table->decimal('service_charge_percent', 5, 2)->default(0)->after('allow_negative_stock');

            // O artigo do catálogo que representa a taxa na factura. O
            // `ModuleInvoiceService` exige que cada linha tenha artigo — sem
            // isto a factura era recusada na emissão.
            $table->unsignedBigInteger('service_charge_product_id')->nullable()->after('service_charge_percent');

            $table->boolean('tips_enabled')->default(true)->after('service_charge_product_id');
        });

        Schema::table('restaurant_orders', function (Blueprint $table) {
            // Fica na comanda e NÃO no total: o total é o que se factura.
            $table->decimal('tip_amount', 12, 2)->default(0)->after('grand_total');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn(['service_charge_percent', 'service_charge_product_id', 'tips_enabled']);
        });

        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->dropColumn('tip_amount');
        });
    }
};
