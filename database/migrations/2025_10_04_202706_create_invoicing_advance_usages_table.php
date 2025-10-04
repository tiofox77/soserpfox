<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_advance_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('invoicing_advances')->onDelete('cascade');
            
            // FK genérica para diferentes tipos de faturas
            $table->foreignId('invoice_id')->nullable();
            $table->string('invoice_type')->nullable(); // SalesInvoice, PurchaseInvoice, etc
            
            $table->decimal('amount_used', 15, 2);
            $table->date('usage_date');
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['advance_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_advance_usages');
    }
};
