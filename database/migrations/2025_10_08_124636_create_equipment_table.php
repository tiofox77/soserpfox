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
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            
            // Informações básicas
            $table->string('name');
            $table->string('category');
            $table->string('serial_number')->nullable()->unique();
            $table->string('location')->nullable(); // Local/Sítio onde está
            $table->text('description')->nullable();
            
            // Estado e disponibilidade
            $table->enum('status', [
                'disponivel',      // Disponível
                'reservado',       // Reservado para atividade
                'em_uso',          // Em uso
                'avariado',        // Avariado/Manutenção
                'manutencao',      // Em manutenção programada
                'emprestado',      // Emprestado/Alugado
                'descartado'       // Descartado/Inativo
            ])->default('disponivel');
            
            // Informações financeiras (restritas)
            $table->date('acquisition_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->decimal('current_value', 10, 2)->nullable();
            
            // Empréstimo/Aluguel  
            $table->unsignedBigInteger('borrowed_to_client_id')->nullable();
            $table->date('borrow_date')->nullable();
            $table->date('return_due_date')->nullable();
            $table->date('actual_return_date')->nullable();
            $table->decimal('rental_price_per_day', 10, 2)->nullable();
            
            // Manutenção
            $table->date('last_maintenance_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->text('maintenance_notes')->nullable();
            
            // Controle de uso
            $table->integer('total_uses')->default(0);
            $table->integer('total_hours_used')->default(0);
            
            // Imagem
            $table->string('image_path')->nullable();
            
            // Controle
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category']);
            $table->index('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
