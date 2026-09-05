<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aparência da carta online: capa, segunda cor, tema, e pratos em destaque.
 *
 * A carta já tinha um título, uma descrição e uma cor. O que faltava era o que
 * a faz parecer a casa e não um catálogo: uma fotografia em cima, um par de
 * cores em vez de uma, e a possibilidade de pôr em primeiro lugar aquilo que o
 * dono quer vender hoje.
 *
 * Os destaques ficam em tabela PRÓPRIA e não numa coluna dos artigos: o
 * catálogo é partilhado com a facturação, o POS e o PWA, e uma coluna
 * «destaque» lá dentro seria uma decisão do restaurante a viajar por módulos
 * que nada têm que ver com isso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->string('menu_cover')->nullable()->after('menu_logo');
            $table->string('menu_accent_color', 20)->nullable()->after('menu_primary_color');
            // 'claro' | 'escuro' — uma carta de jantar quer fundo escuro; uma
            // pastelaria quer luz. Guardado como string e não enum para não
            // pagar uma migração de cada vez que se acrescentar um tema.
            $table->string('menu_theme', 10)->default('claro')->after('menu_accent_color');
            $table->string('menu_destaques_titulo', 120)->nullable()->after('menu_theme');
        });

        Schema::create('restaurant_menu_destaques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('invoicing_products')->onDelete('cascade');
            $table->integer('ordem')->default(0);
            $table->timestamps();

            // Um prato só se destaca uma vez.
            $table->unique(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_menu_destaques');

        Schema::table('restaurant_settings', function (Blueprint $table) {
            $table->dropColumn(['menu_cover', 'menu_accent_color', 'menu_theme', 'menu_destaques_titulo']);
        });
    }
};
