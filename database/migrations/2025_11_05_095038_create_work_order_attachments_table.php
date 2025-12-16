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
        Schema::create('workshop_work_order_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('filename'); // Nome gerado único
            $table->string('original_filename'); // Nome original do arquivo
            $table->string('file_path'); // Caminho no storage
            $table->string('file_type', 50); // image, document, etc
            $table->unsignedBigInteger('file_size'); // Tamanho em bytes
            $table->string('mime_type', 100); // image/jpeg, application/pdf, etc
            $table->string('category', 50)->default('other'); // photo_before, photo_after, etc
            $table->text('description')->nullable();
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
            $table->index('category');
            $table->index('file_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_work_order_attachments');
    }
};
