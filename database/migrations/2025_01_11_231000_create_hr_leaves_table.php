<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('hr_employees')->onDelete('cascade');
            $table->string('leave_number')->unique();
            
            // Tipo de licença
            $table->enum('leave_type', [
                'sick',           // Doença
                'maternity',      // Maternidade
                'paternity',      // Paternidade
                'bereavement',    // Luto
                'marriage',       // Casamento
                'study',          // Estudos
                'unpaid',         // Sem vencimento
                'justified',      // Falta justificada
                'other'           // Outro
            ]);
            
            // Período
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days'); // Dias corridos
            $table->integer('working_days'); // Dias úteis
            
            // Detalhes
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->boolean('has_medical_certificate')->default(false);
            $table->string('document_path')->nullable(); // Caminho do documento anexo
            
            // Status e aprovação
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                  ->default('pending');
            $table->text('rejection_reason')->nullable();
            
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            
            // Controle de pagamento
            $table->boolean('paid')->default(true); // Por padrão, licenças são pagas
            $table->decimal('deduction_amount', 15, 2)->default(0); // Desconto se não pago
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'leave_type', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leaves');
    }
};
