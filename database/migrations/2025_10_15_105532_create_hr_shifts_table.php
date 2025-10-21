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
        Schema::create('hr_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name'); // Ex: Turno Manhã, Turno Tarde, Turno Noite
            $table->string('code')->nullable(); // Código do turno (ex: T1, T2, T3)
            $table->text('description')->nullable();
            $table->time('start_time'); // Hora início (08:00)
            $table->time('end_time'); // Hora fim (16:00)
            $table->decimal('hours_per_day', 5, 2); // Horas por dia (8.00)
            $table->json('work_days')->nullable(); // Dias da semana que trabalha [1,2,3,4,5] = Seg-Sex
            $table->string('color', 7)->default('#3b82f6'); // Cor para identificação visual
            $table->boolean('is_night_shift')->default(false); // Se é turno noturno
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['tenant_id', 'is_active']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_shifts');
    }
};
