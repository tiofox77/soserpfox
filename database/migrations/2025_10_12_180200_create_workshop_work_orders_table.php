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
        Schema::create('workshop_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('order_number')->unique(); // Número da OS
            $table->foreignId('vehicle_id')->constrained('workshop_vehicles')->onDelete('cascade');
            $table->foreignId('mechanic_id')->nullable()->constrained('hr_employees')->onDelete('set null');
            
            // Datas
            $table->dateTime('received_at'); // Data de entrada
            $table->dateTime('scheduled_for')->nullable(); // Data agendada
            $table->dateTime('started_at')->nullable(); // Início do serviço
            $table->dateTime('completed_at')->nullable(); // Data de conclusão
            $table->dateTime('delivered_at')->nullable(); // Data de entrega
            
            // Quilometragem na entrada
            $table->integer('mileage_in')->default(0);
            
            // Descrição do problema
            $table->text('problem_description');
            $table->text('diagnosis')->nullable(); // Diagnóstico do mecânico
            $table->text('work_performed')->nullable(); // Trabalho realizado
            $table->text('recommendations')->nullable(); // Recomendações
            
            // Status
            $table->enum('status', [
                'pending',      // Pendente
                'scheduled',    // Agendada
                'in_progress',  // Em andamento
                'waiting_parts',// Aguardando peças
                'completed',    // Concluída
                'delivered',    // Entregue
                'cancelled'     // Cancelada
            ])->default('pending');
            
            // Prioridade
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            
            // Valores
            $table->decimal('labor_total', 10, 2)->default(0); // Total mão de obra
            $table->decimal('parts_total', 10, 2)->default(0); // Total peças
            $table->decimal('discount', 10, 2)->default(0); // Desconto
            $table->decimal('tax', 10, 2)->default(0); // IVA
            $table->decimal('total', 10, 2)->default(0); // Total
            
            // Pagamento
            $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending');
            $table->decimal('paid_amount', 10, 2)->default(0);
            
            // Garantia
            $table->integer('warranty_days')->default(30);
            $table->date('warranty_expires')->nullable();
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'mechanic_id']);
            $table->index('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_work_orders');
    }
};
