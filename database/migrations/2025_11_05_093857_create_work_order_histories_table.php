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
        Schema::create('workshop_work_order_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 50); // created, updated, status_changed, etc
            $table->string('field_name', 100)->nullable(); // campo que foi alterado
            $table->text('old_value')->nullable(); // valor antigo
            $table->text('new_value')->nullable(); // valor novo
            $table->text('description'); // descrição da ação
            $table->json('metadata')->nullable(); // dados adicionais
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
                  
            // Índices para performance
            $table->index('work_order_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_work_order_history');
    }
};
