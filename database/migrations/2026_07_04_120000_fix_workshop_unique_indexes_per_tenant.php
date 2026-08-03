<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torna os identificadores da Oficina únicos POR TENANT (não globais).
 *
 * plate, vehicle_number, service_code e order_number são gerados/introduzidos por
 * tenant, mas tinham índice UNIQUE global -> dois tenants não podiam ter a mesma
 * matrícula ou número de OS (mesmo bug do NIF e do shift_number).
 *
 * Seguro: como eram únicos globais, não existem pares (tenant_id, coluna) duplicados,
 * por isso os índices compostos criam-se sem conflito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_vehicles', function (Blueprint $table) {
            $table->dropUnique('workshop_vehicles_plate_unique');
            $table->dropUnique('workshop_vehicles_vehicle_number_unique');
            $table->unique(['tenant_id', 'plate'], 'workshop_vehicles_tenant_plate_unique');
            $table->unique(['tenant_id', 'vehicle_number'], 'workshop_vehicles_tenant_number_unique');
        });

        Schema::table('workshop_services', function (Blueprint $table) {
            $table->dropUnique('workshop_services_service_code_unique');
            $table->unique(['tenant_id', 'service_code'], 'workshop_services_tenant_code_unique');
        });

        Schema::table('workshop_work_orders', function (Blueprint $table) {
            $table->dropUnique('workshop_work_orders_order_number_unique');
            $table->unique(['tenant_id', 'order_number'], 'workshop_work_orders_tenant_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_vehicles', function (Blueprint $table) {
            $table->dropUnique('workshop_vehicles_tenant_plate_unique');
            $table->dropUnique('workshop_vehicles_tenant_number_unique');
            $table->unique('plate', 'workshop_vehicles_plate_unique');
            $table->unique('vehicle_number', 'workshop_vehicles_vehicle_number_unique');
        });

        Schema::table('workshop_services', function (Blueprint $table) {
            $table->dropUnique('workshop_services_tenant_code_unique');
            $table->unique('service_code', 'workshop_services_service_code_unique');
        });

        Schema::table('workshop_work_orders', function (Blueprint $table) {
            $table->dropUnique('workshop_work_orders_tenant_number_unique');
            $table->unique('order_number', 'workshop_work_orders_order_number_unique');
        });
    }
};
