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
        Schema::create('workshop_work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('workshop_work_orders')->onDelete('cascade');
            $table->foreignId('service_id')->nullable()->constrained('workshop_services')->onDelete('set null');
            
            // Tipo: Serviço ou Peça
            $table->enum('type', ['service', 'part'])->default('service');
            
            // Dados do Item
            $table->string('code')->nullable(); // Código do serviço ou peça
            $table->string('name'); // Nome/Descrição
            $table->text('description')->nullable();
            
            // Quantidades
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            
            // Para serviços
            $table->decimal('hours', 5, 2)->nullable(); // Horas trabalhadas
            $table->foreignId('mechanic_id')->nullable()->constrained('hr_employees')->onDelete('set null');
            
            // Para peças
            $table->string('part_number')->nullable(); // Número da peça
            $table->string('brand')->nullable(); // Marca da peça
            $table->boolean('is_original')->default(false); // Peça original ou compatível
            
            $table->timestamps();
            
            $table->index('work_order_id');
            $table->index(['type', 'work_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_work_order_items');
    }
};
