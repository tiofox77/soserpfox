<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AS FOTOGRAFIAS DA VIATURA — antes, durante e depois (15/09/2026).
 *
 * Pedido: «uma área para juntar imagens da viatura, antes e depois,
 * principalmente para bate-chapa, pintura e outros». Cada fotografia diz a
 * FASE (antes/durante/depois/dano), o SERVIÇO e a ZONA do carro — é pela zona
 * que o «antes» e o «depois» se põem lado a lado. Pode ficar ligada a uma
 * folha de obra, e é essa ligação que a mostra ao cliente no portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshop_vehicle_photos')) {
            return;
        }

        Schema::create('workshop_vehicle_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('workshop_vehicles')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('workshop_work_orders')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phase', 12)->default('antes');
            $table->string('service', 20)->default('outros');
            $table->string('zone', 24)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('file_path', 500);
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 60)->nullable();
            $table->unsignedInteger('file_size')->default(0);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['work_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_vehicle_photos');
    }
};
