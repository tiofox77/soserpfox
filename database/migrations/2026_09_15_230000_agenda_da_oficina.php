<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A AGENDA DA OFICINA (15/09/2026, OF-05).
 *
 * Os ELEVADORES E BAIAS são os lugares onde se trabalha — é por eles que se
 * mede a capacidade do dia. As MARCAÇÕES ocupam um lugar (e um mecânico) das
 * tantas às tantas; quando o carro chega, a marcação passa a ordem de serviço e
 * fica ligada a ela. Um cliente que ainda não tem viatura na casa marca com a
 * matrícula, o nome e o telefone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workshop_bays')) {
            Schema::create('workshop_bays', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 80);
                $table->string('kind', 20)->default('elevador');
                $table->string('color', 20)->default('azul');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'name']);
            });
        }

        if (! Schema::hasTable('workshop_appointments')) {
            Schema::create('workshop_appointments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('vehicle_id')->nullable()->constrained('workshop_vehicles')->nullOnDelete();
                $table->string('plate', 20)->nullable();
                $table->string('customer_name', 150)->nullable();
                $table->string('customer_phone', 30)->nullable();
                $table->foreignId('bay_id')->nullable()->constrained('workshop_bays')->nullOnDelete();
                $table->foreignId('mechanic_id')->nullable()->constrained('workshop_mechanics')->nullOnDelete();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->string('service', 255);
                $table->string('status', 12)->default('marcada');
                $table->text('notes')->nullable();
                $table->foreignId('work_order_id')->nullable()->constrained('workshop_work_orders')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'starts_at']);
                $table->index(['bay_id', 'starts_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_appointments');
        Schema::dropIfExists('workshop_bays');
    }
};
