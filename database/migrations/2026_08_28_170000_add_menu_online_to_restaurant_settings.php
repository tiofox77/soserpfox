<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O menu online do restaurante: a carta que o cliente vê no telemóvel.
 *
 * Nasce DESLIGADA. Um restaurante que não pediu isto não pode acordar com os
 * seus preços numa página pública — a carta é informação comercial, e quem a
 * publica decide quando.
 *
 * O `menu_slug` é único em TODA a tabela, e não por empresa: é o endereço
 * público (`/menu/o-pitéu`), e dois restaurantes com o mesmo endereço é um a
 * servir a carta do outro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $tabela) {
            // O endereço público. Nulo = ainda não foi publicado.
            $tabela->string('menu_slug', 80)->nullable()->unique()->after('next_order_number');

            // O interruptor. Desligado até alguém o ligar de propósito.
            $tabela->boolean('online_menu_enabled')->default(false)->after('menu_slug');

            // COMO O CLIENTE PEDE. Duas vias, e podem estar as duas ligadas:
            //
            //  · WhatsApp — a mensagem sai pronta, com a mesa e os artigos, e
            //    quem recebe é o restaurante. Funciona sem mais nada e é o que
            //    a maioria vai usar.
            //  · Pedido na página — cria a comanda directamente na mesa. É
            //    mais cómodo e é mais arriscado: qualquer pessoa com o
            //    endereço pode lançar pedidos. Fica desligado por omissão.
            $tabela->boolean('menu_whatsapp_enabled')->default(true)->after('online_menu_enabled');
            $tabela->boolean('menu_orders_enabled')->default(false)->after('menu_whatsapp_enabled');
            $tabela->string('menu_whatsapp_number', 30)->nullable()->after('menu_orders_enabled');

            // A cara da página.
            $tabela->string('menu_title', 120)->nullable()->after('menu_whatsapp_number');
            $tabela->text('menu_description')->nullable()->after('menu_title');
            $tabela->string('menu_logo')->nullable()->after('menu_description');
            $tabela->string('menu_primary_color', 20)->nullable()->after('menu_logo');

            // Mostrar preços na carta pública. Há casas que preferem não os
            // publicar — e uma carta sem preços continua a ser útil.
            $tabela->boolean('menu_show_prices')->default(true)->after('menu_primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $tabela) {
            $tabela->dropUnique(['menu_slug']);
            $tabela->dropColumn([
                'menu_slug',
                'online_menu_enabled',
                'menu_whatsapp_enabled',
                'menu_orders_enabled',
                'menu_whatsapp_number',
                'menu_title',
                'menu_description',
                'menu_logo',
                'menu_primary_color',
                'menu_show_prices',
            ]);
        });
    }
};
