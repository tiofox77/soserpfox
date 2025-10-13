<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_vacations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('hr_employees')->onDelete('cascade');
            $table->string('vacation_number')->unique();
            
            // Período de referência
            $table->integer('reference_year');
            $table->date('period_start'); // Início do período aquisitivo
            $table->date('period_end');   // Fim do período aquisitivo
            
            // Direito a férias
            $table->integer('entitled_days')->default(22); // Dias de direito (Angola: 22 dias úteis)
            $table->integer('working_months')->default(12); // Meses trabalhados
            $table->integer('calculated_days'); // Dias calculados proporcionais
            
            // Férias programadas
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('requested_days'); // Dias solicitados
            $table->integer('working_days');   // Dias úteis (excluindo fins de semana)
            
            // Financeiro
            $table->decimal('daily_rate', 15, 2)->default(0);
            $table->decimal('vacation_pay', 15, 2)->default(0); // Valor das férias
            $table->decimal('subsidy_amount', 15, 2)->default(0); // 14º mês (subsídio de férias)
            $table->decimal('total_amount', 15, 2)->default(0); // Total a receber
            
            // Controle
            $table->enum('status', ['pending', 'approved', 'rejected', 'in_progress', 'completed', 'cancelled'])
                  ->default('pending');
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Aprovação
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            
            // Substituição durante férias
            $table->foreignId('replacement_employee_id')->nullable()
                  ->constrained('hr_employees')
                  ->onDelete('set null');
            
            // Controle de pagamento
            $table->boolean('paid')->default(false);
            $table->date('paid_date')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['tenant_id', 'employee_id', 'reference_year']);
            $table->index(['tenant_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_vacations');
    }
};
