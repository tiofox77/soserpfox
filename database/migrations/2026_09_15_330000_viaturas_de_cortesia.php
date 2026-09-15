<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AS VIATURAS DE CORTESIA (15/09/2026, OF-17).
 *
 * As viaturas da oficina que se emprestam ao cliente enquanto o carro dele está
 * a ser arranjado, e cada empréstimo: a quem, ligado a que ordem, com os km e o
 * combustível à saída e à entrada. «Emprestada» não é um estado gravado na
 * viatura — é haver um empréstimo por devolver.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workshop_courtesy_cars')) {
            Schema::create('workshop_courtesy_cars', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('plate', 20);
                $table->string('brand', 100);
                $table->string('model', 100);
                $table->string('color', 50)->nullable();
                $table->unsignedSmallInteger('year')->nullable();
                $table->string('fuel_type', 20)->nullable();
                $table->unsignedInteger('mileage')->default(0);
                $table->unsignedTinyInteger('fuel_level')->nullable();
                $table->string('status', 20)->default('disponivel');
                $table->date('insurance_expiry')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'plate']);
            });
        }

        if (! Schema::hasTable('workshop_courtesy_loans')) {
            Schema::create('workshop_courtesy_loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('courtesy_car_id')->constrained('workshop_courtesy_cars')->cascadeOnDelete();
                $table->foreignId('work_order_id')->nullable()->constrained('workshop_work_orders')->nullOnDelete();
                $table->string('driver_name', 150);
                $table->string('driver_phone', 30)->nullable();
                $table->string('driver_licence', 40)->nullable();
                $table->timestamp('out_at');
                $table->timestamp('expected_return_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->unsignedInteger('mileage_out');
                $table->unsignedInteger('mileage_in')->nullable();
                $table->unsignedTinyInteger('fuel_out')->nullable();
                $table->unsignedTinyInteger('fuel_in')->nullable();
                $table->text('notes_out')->nullable();
                $table->text('damages_in')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'returned_at']);
                $table->index(['courtesy_car_id', 'returned_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_courtesy_loans');
        Schema::dropIfExists('workshop_courtesy_cars');
    }
};
