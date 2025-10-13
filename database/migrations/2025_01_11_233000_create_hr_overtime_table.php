<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_overtime', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('hr_employees')->onDelete('cascade');
            $table->foreignId('attendance_id')->nullable()->constrained('hr_attendances')->onDelete('set null');
            $table->string('overtime_number')->unique();
            
            // Data e período
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('total_hours', 5, 2); // Total de horas extras
            
            // Tipo de hora extra (Angola: 50% dias úteis, 100% fins de semana/feriados)
            $table->enum('overtime_type', [
                'weekday',        // Dia útil (50%)
                'weekend',        // Fim de semana (100%)
                'holiday',        // Feriado (100%)
                'night',          // Noturno (adicional 25%)
            ])->default('weekday');
            
            $table->decimal('multiplier', 5, 2)->default(1.5); // 1.5 = 50%, 2.0 = 100%
            
            // Cálculos financeiros
            $table->decimal('hourly_rate', 15, 2); // Taxa hora normal
            $table->decimal('overtime_rate', 15, 2); // Taxa hora extra
            $table->decimal('total_amount', 15, 2); // Total a receber
            
            // Detalhes
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            // Status e aprovação
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'cancelled'])
                  ->default('pending');
            $table->text('rejection_reason')->nullable();
            
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            
            // Pagamento
            $table->boolean('paid')->default(false);
            $table->date('paid_date')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users');
            $table->foreignId('payroll_id')->nullable()->constrained('hr_payrolls')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['date', 'overtime_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_overtime');
    }
};
