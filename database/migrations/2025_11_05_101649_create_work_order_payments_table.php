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
        Schema::create('workshop_work_order_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('payment_date');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 50); // cash, transfer, card, check, other
            $table->string('reference', 100)->nullable(); // Referência bancária, nº cheque, etc
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('work_order_id')
                  ->references('id')
                  ->on('workshop_work_orders')
                  ->onDelete('cascade');
                  
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            // Índices
            $table->index('work_order_id');
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_work_order_payments');
    }
};
