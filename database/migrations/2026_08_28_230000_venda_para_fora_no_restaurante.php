<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A venda para fora: take-away e entrega.
 *
 * A coluna `channel` já aceitava os quatro canais desde o início — e o produto
 * nunca escrevia os dois últimos, porque não havia onde guardar aquilo de que
 * uma venda para fora precisa: para quem é, para onde vai, e quanto custa
 * levá-la lá. Sem isso, um restaurante que venda para fora não tinha por onde
 * registar essas vendas, e elas ficavam de fora dos relatórios por canal.
 *
 * Tudo nulo: uma comanda de mesa não tem nada disto, e são a esmagadora
 * maioria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            // Quem levanta ou recebe. O `client_id` continua a ser o cliente
            // de facturação; isto é a pessoa concreta desta entrega, que
            // muitas vezes não é cliente nenhum.
            $table->string('customer_name', 150)->nullable()->after('client_id');
            $table->string('customer_phone', 30)->nullable()->after('customer_name');

            // Só para entrega. Guardado como texto livre de propósito: em
            // Luanda a morada é uma referência («ao lado da farmácia»), não um
            // par rua-número, e um formulário com campos separados obrigaria a
            // inventar dados que ninguém tem.
            $table->text('delivery_address')->nullable()->after('customer_phone');
            $table->decimal('delivery_fee', 12, 2)->default(0)->after('delivery_address');

            // Quando saiu para o cliente. Distingue «pronto na cozinha» de
            // «já vai a caminho», que é a pergunta que se faz ao telefone.
            $table->timestamp('dispatched_at')->nullable()->after('confirmed_at');

            // Os relatórios por canal filtram por isto e ordenam por data.
            $table->index(['tenant_id', 'channel', 'created_at'], 'rest_orders_canal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->dropIndex('rest_orders_canal_idx');
            $table->dropColumn([
                'customer_name', 'customer_phone', 'delivery_address',
                'delivery_fee', 'dispatched_at',
            ]);
        });
    }
};
