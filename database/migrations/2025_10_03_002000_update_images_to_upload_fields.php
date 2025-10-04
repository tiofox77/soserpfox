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
        // Atualizar tabela de clientes - logo passa a ser caminho de arquivo
        Schema::table('invoicing_clients', function (Blueprint $table) {
            $table->string('logo')->nullable()->change(); // Já existe, só garantir que é nullable
        });

        // Atualizar tabela de fornecedores - logo passa a ser caminho de arquivo
        Schema::table('invoicing_suppliers', function (Blueprint $table) {
            $table->string('logo')->nullable()->change(); // Já existe, só garantir que é nullable
        });

        // Atualizar tabela de produtos - adicionar imagem destaque, galeria e stock min/max
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->string('featured_image')->nullable()->after('description');
            $table->json('gallery')->nullable()->after('featured_image'); // Array de imagens
            $table->integer('stock_min')->default(0)->after('stock_quantity');
            $table->integer('stock_max')->nullable()->after('stock_min');
        });

        // Atualizar tabela de marcas - logo passa a ser caminho de arquivo
        Schema::table('invoicing_brands', function (Blueprint $table) {
            $table->string('logo')->nullable()->change(); // Já existe
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_products', function (Blueprint $table) {
            $table->dropColumn(['featured_image', 'gallery', 'stock_min', 'stock_max']);
        });
    }
};
