<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('employee_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Dados Pessoais
            $table->string('first_name');
            $table->string('last_name');
            $table->string('full_name')->virtualAs("CONCAT(first_name, ' ', last_name)");
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['M', 'F', 'Outro'])->nullable();
            $table->string('nif')->nullable()->unique(); // NIF Angola
            $table->string('bi_number')->nullable(); // Bilhete de Identidade
            $table->date('bi_expiry_date')->nullable();
            $table->string('social_security_number')->nullable(); // Nº INSS
            
            // Contactos
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            
            // Dados Profissionais
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hr_positions')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->enum('employment_type', ['Contrato', 'Freelancer', 'Estágio', 'Temporário'])->default('Contrato');
            $table->enum('status', ['active', 'suspended', 'terminated', 'on_leave'])->default('active');
            
            // Dados Bancários
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('iban')->nullable();
            
            // Beneficiários (JSON)
            $table->json('beneficiaries')->nullable();
            
            // Foto
            $table->string('photo')->nullable();
            
            // Notas
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'department_id']);
            $table->index('nif');
        });
    }

    public function down()
    {
        Schema::dropIfExists('hr_employees');
    }
};
