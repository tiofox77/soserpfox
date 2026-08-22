<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_sales_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_quote_id')->constrained('invoicing_sales_quotes')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('invoicing_products')->onDelete('cascade');
            $table->string('product_name');
            // Descrição longa do serviço/artigo, por baixo do nome. É a razão
            // de ser do orçamento: um serviço não cabe num nome de artigo.
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->string('unit', 20)->default('un');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            // Referência à taxa (opcional). Sem FK de propósito: o orçamento
            // guarda a taxa como decimal em tax_rate; o id fica só como pista e
            // não vale prender-nos ao nome da tabela de impostos, que difere
            // entre instalações.
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->integer('order')->default(0);
            // AGT DS.120 — persistidos já no orçamento para a conversão em
            // factura não perder o motivo de isenção nem a região fiscal.
            $table->string('tax_country_region', 10)->nullable();
            $table->string('tax_code', 20)->nullable();
            $table->string('tax_exemption_code', 20)->nullable();
            $table->string('tax_exemption_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_sales_quote_items');
    }
};
