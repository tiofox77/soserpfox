<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_debit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debit_note_id')->constrained('invoicing_debit_notes')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('invoicing_products')->onDelete('set null');
            
            $table->string('description');
            $table->decimal('quantity', 15, 3);
            $table->string('unit')->default('un');
            $table->decimal('unit_price', 15, 2);
            
            // Descontos
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            
            // Taxas
            $table->decimal('tax_rate', 5, 2)->default(14);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            
            $table->integer('order')->default(0);
            $table->timestamps();
            
            $table->index('debit_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_debit_note_items');
    }
};
