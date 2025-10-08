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
        Schema::create('equipment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            
            // Tipo de ação
            $table->enum('action_type', [
                'uso',           // Usado em evento/produção
                'reserva',       // Reservado
                'emprestimo',    // Emprestado
                'devolucao',     // Devolvido
                'manutencao',    // Manutenção realizada
                'avaria',        // Reportada avaria
                'reparacao',     // Reparado
                'transferencia'  // Transferido de local
            ]);
            
            // Relacionamentos
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Quem realizou a ação
            
            // Detalhes do uso
            $table->dateTime('start_datetime')->nullable();
            $table->dateTime('end_datetime')->nullable();
            $table->integer('hours_used')->nullable(); // Calculado automaticamente
            
            // Local e observações
            $table->string('location_from')->nullable();
            $table->string('location_to')->nullable();
            $table->text('notes')->nullable();
            
            // Status anterior e novo (para auditoria)
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['equipment_id', 'created_at']);
            $table->index(['tenant_id', 'action_type']);
            $table->index('event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_history');
    }
};
