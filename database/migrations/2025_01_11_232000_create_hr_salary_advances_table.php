<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('hr_employees')->onDelete('cascade');
            $table->string('advance_number')->unique();
            
            // Valores
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->decimal('base_salary', 15, 2); // Salário base na data da solicitação
            $table->decimal('max_allowed', 15, 2); // Máximo permitido (ex: 50% do salário)
            
            // Parcelamento
            $table->integer('installments')->default(1); // Número de parcelas
            $table->decimal('installment_amount', 15, 2)->default(0);
            $table->integer('installments_paid')->default(0);
            $table->decimal('balance', 15, 2)->default(0); // Saldo devedor
            
            // Datas
            $table->date('request_date');
            $table->date('payment_date')->nullable();
            $table->date('first_deduction_date')->nullable(); // Data da primeira dedução
            
            // Motivo e detalhes
            $table->text('reason');
            $table->text('notes')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'in_deduction', 'completed', 'cancelled'])
                  ->default('pending');
            $table->text('rejection_reason')->nullable();
            
            // Aprovação
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            
            // Pagamento
            $table->foreignId('paid_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['request_date', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_salary_advances');
    }
};
