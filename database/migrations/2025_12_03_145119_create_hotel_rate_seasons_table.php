<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela de Épocas/Temporadas
        Schema::create('hotel_rate_seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name'); // Ex: Alta, Média, Baixa, Natal, Carnaval
            $table->string('color')->default('#6366f1');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price_modifier', 5, 2)->default(1.00); // 1.5 = +50%, 0.8 = -20%
            $table->enum('modifier_type', ['multiplier', 'fixed', 'percentage'])->default('multiplier');
            $table->integer('priority')->default(0); // Maior prioridade prevalece
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'start_date', 'end_date']);
        });

        // Tabela de Tarifas por Dia da Semana
        Schema::create('hotel_weekday_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('hotel_room_types')->cascadeOnDelete();
            $table->tinyInteger('day_of_week'); // 0=Domingo, 1=Segunda, ..., 6=Sábado
            $table->decimal('price_modifier', 5, 2)->default(1.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['tenant_id', 'room_type_id', 'day_of_week']);
        });

        // Tabela de Tarifas Especiais por Data
        Schema::create('hotel_special_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('room_type_id')->nullable()->constrained('hotel_room_types')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('price', 12, 2)->nullable(); // Preço fixo
            $table->decimal('price_modifier', 5, 2)->nullable(); // Ou modificador
            $table->string('reason')->nullable(); // Ex: Feriado, Evento especial
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_special_rates');
        Schema::dropIfExists('hotel_weekday_rates');
        Schema::dropIfExists('hotel_rate_seasons');
    }
};
