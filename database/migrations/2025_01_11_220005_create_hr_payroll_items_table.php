<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hr_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('hr_payrolls')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('hr_employees')->onDelete('cascade');
            $table->foreignId('contract_id')->nullable()->constrained('hr_contracts')->nullOnDelete();
            
            // VENCIMENTOS (Créditos)
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('food_allowance', 15, 2)->default(0);
            $table->decimal('transport_allowance', 15, 2)->default(0);
            $table->decimal('housing_allowance', 15, 2)->default(0);
            $table->decimal('overtime_pay', 15, 2)->default(0); // Horas extras
            $table->decimal('night_shift_pay', 15, 2)->default(0); // Trabalho noturno
            $table->decimal('holiday_pay', 15, 2)->default(0); // Feriados
            $table->decimal('commission', 15, 2)->default(0); // Comissões
            $table->decimal('bonus', 15, 2)->default(0); // Bônus
            $table->decimal('subsidy_13th', 15, 2)->default(0); // 13º mês
            $table->decimal('subsidy_14th', 15, 2)->default(0); // 14º mês (férias)
            $table->decimal('other_earnings', 15, 2)->default(0);
            
            // TOTAL BRUTO
            $table->decimal('gross_salary', 15, 2)->default(0); // Total bruto
            
            // DEDUÇÕES (Débitos)
            $table->decimal('irt_amount', 15, 2)->default(0); // IRT (Imposto sobre Rendimentos do Trabalho)
            $table->decimal('irt_base', 15, 2)->default(0); // Base de cálculo IRT
            $table->decimal('irt_rate', 5, 2)->default(0); // Taxa IRT aplicada
            
            $table->decimal('inss_employee', 15, 2)->default(0); // INSS empregado (3%)
            $table->decimal('inss_employer', 15, 2)->default(0); // INSS empregador (8%)
            $table->decimal('inss_base', 15, 2)->default(0); // Base de cálculo INSS
            
            $table->decimal('advance_payment', 15, 2)->default(0); // Adiantamento
            $table->decimal('loan_deduction', 15, 2)->default(0); // Empréstimo
            $table->decimal('absence_deduction', 15, 2)->default(0); // Faltas
            $table->decimal('late_deduction', 15, 2)->default(0); // Atrasos
            $table->decimal('other_deductions', 15, 2)->default(0);
            
            // TOTAL DEDUÇÕES
            $table->decimal('total_deductions', 15, 2)->default(0);
            
            // SALÁRIO LÍQUIDO
            $table->decimal('net_salary', 15, 2)->default(0); // Líquido a receber
            
            // Dias e Horas
            $table->integer('worked_days')->default(0);
            $table->integer('absence_days')->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('night_hours', 8, 2)->default(0);
            
            // Observações
            $table->json('calculation_details')->nullable(); // Detalhes do cálculo
            $table->text('notes')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'calculated', 'approved', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['payroll_id', 'employee_id']);
            $table->unique(['payroll_id', 'employee_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('hr_payroll_items');
    }
};
