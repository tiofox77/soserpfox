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
        Schema::create('workshop_mechanics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Dados Pessoais
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('mobile')->nullable();
            $table->string('document')->nullable();
            $table->text('address')->nullable();
            $table->string('photo')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('hire_date')->nullable();
            
            // Profissionais
            $table->json('specialties')->nullable(); // ['Mecânica Geral', 'Motor', 'Suspensão', etc]
            $table->enum('level', ['junior', 'pleno', 'senior', 'master'])->default('pleno');
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('daily_rate', 10, 2)->default(0);
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available')->default(true);
            
            // Observações
            $table->text('notes')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
            
            // Índices
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_mechanics');
    }
};
