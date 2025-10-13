<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hr_payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('payroll_number')->unique();
            
            // Período
            $table->integer('year');
            $table->integer('month'); // 1-12
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date')->nullable();
            
            // Totais Gerais
            $table->decimal('total_gross_salary', 15, 2)->default(0); // Total bruto
            $table->decimal('total_allowances', 15, 2)->default(0); // Total subsídios
            $table->decimal('total_bonuses', 15, 2)->default(0); // Total bônus
            $table->decimal('total_deductions', 15, 2)->default(0); // Total deduções
            $table->decimal('total_irt', 15, 2)->default(0); // Total IRT
            $table->decimal('total_inss_employee', 15, 2)->default(0); // Total INSS empregado (3%)
            $table->decimal('total_inss_employer', 15, 2)->default(0); // Total INSS empregador (8%)
            $table->decimal('total_net_salary', 15, 2)->default(0); // Total líquido
            
            // Estatísticas
            $table->integer('total_employees')->default(0);
            $table->integer('processed_employees')->default(0);
            
            // Status
            $table->enum('status', ['draft', 'processing', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'year', 'month']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'year', 'month']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('hr_payrolls');
    }
};
