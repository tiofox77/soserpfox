<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hr_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('hr_employees')->onDelete('cascade');
            $table->string('contract_number')->unique();
            
            // Tipo e Datas
            $table->enum('contract_type', ['Determinado', 'Indeterminado', 'Estágio', 'Freelancer'])->default('Determinado');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('trial_period_end')->nullable();
            
            // Remuneração
            $table->decimal('base_salary', 15, 2); // Salário base
            $table->decimal('food_allowance', 15, 2)->default(0); // Subsídio de alimentação
            $table->decimal('transport_allowance', 15, 2)->default(0); // Subsídio de transporte
            $table->decimal('housing_allowance', 15, 2)->default(0); // Subsídio de habitação
            $table->decimal('other_allowances', 15, 2)->default(0); // Outros subsídios
            $table->enum('payment_frequency', ['Mensal', 'Quinzenal', 'Semanal'])->default('Mensal');
            
            // Horário
            $table->integer('weekly_hours')->default(40);
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            
            // Benefícios
            $table->boolean('has_health_insurance')->default(false);
            $table->boolean('has_life_insurance')->default(false);
            $table->integer('vacation_days_per_year')->default(22); // 22 dias em Angola
            
            // Impostos e Contribuições
            $table->boolean('subject_to_irt')->default(true); // Sujeito a IRT
            $table->boolean('subject_to_inss')->default(true); // Sujeito a INSS
            $table->decimal('irt_percentage', 5, 2)->nullable(); // % IRT se fixo
            
            // Documentos
            $table->string('contract_file')->nullable();
            
            // Status
            $table->enum('status', ['active', 'expired', 'terminated', 'suspended'])->default('active');
            $table->text('termination_reason')->nullable();
            $table->date('termination_date')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'employee_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('hr_contracts');
    }
};
