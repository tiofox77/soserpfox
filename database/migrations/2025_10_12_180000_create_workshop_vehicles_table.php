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
        Schema::create('workshop_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('plate')->unique(); // Matrícula
            $table->string('vehicle_number')->unique(); // Número interno
            
            // Dados do Proprietário
            $table->string('owner_name');
            $table->string('owner_phone')->nullable();
            $table->string('owner_email')->nullable();
            $table->string('owner_nif')->nullable();
            $table->text('owner_address')->nullable();
            
            // Dados do Veículo
            $table->string('brand'); // Marca (Toyota, Mercedes, etc)
            $table->string('model'); // Modelo (Corolla, C-Class, etc)
            $table->integer('year')->nullable(); // Ano
            $table->string('color')->nullable(); // Cor
            $table->string('vin')->nullable(); // Número do Chassis
            $table->string('engine_number')->nullable(); // Número do Motor
            $table->enum('fuel_type', ['Gasolina', 'Diesel', 'Elétrico', 'Híbrido', 'GPL'])->nullable();
            $table->integer('mileage')->default(0); // Quilometragem
            
            // Documentos do Veículo
            $table->string('registration_document')->nullable(); // Livrete
            $table->date('registration_expiry')->nullable();
            $table->string('insurance_company')->nullable();
            $table->string('insurance_policy')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->date('inspection_expiry')->nullable(); // Inspeção
            
            // Status
            $table->enum('status', ['active', 'in_service', 'completed', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'plate']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_vehicles');
    }
};
