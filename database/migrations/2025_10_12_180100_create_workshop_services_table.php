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
        Schema::create('workshop_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('service_code')->unique();
            $table->string('name'); // Nome do serviço
            $table->text('description')->nullable();
            
            // Categoria
            $table->enum('category', [
                'Manutenção',
                'Reparação',
                'Inspeção',
                'Pintura',
                'Mecânica',
                'Elétrica',
                'Chapa',
                'Pneus',
                'Outro'
            ])->default('Manutenção');
            
            // Preços
            $table->decimal('labor_cost', 10, 2)->default(0); // Mão de obra
            $table->decimal('estimated_hours', 5, 2)->default(1); // Horas estimadas
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_services');
    }
};
