<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OS ESTADOS DA VIATURA — um catálogo de cada empresa (15/09/2026).
 *
 * Eram quatro escritos numa coluna ENUM (activa, em serviço, concluída,
 * inactiva): nenhuma oficina podia dizer «aguarda orçamento» ou «pronta para
 * entrega». A coluna passa a texto e guarda o CÓDIGO do estado; os quatro de
 * sempre continuam com o mesmo código, e as viaturas que já existem não mudam.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workshop_vehicle_statuses')) {
            Schema::create('workshop_vehicle_statuses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('code', 40);
                $table->string('name', 80);
                $table->string('color', 20)->default('cinza');
                // A viatura neste estado pode receber uma ordem de serviço nova?
                $table->boolean('accepts_orders')->default(true);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
                $table->unique(['tenant_id', 'name']);
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE workshop_vehicles MODIFY status VARCHAR(40) NOT NULL DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_vehicle_statuses');
        // A coluna fica em texto: voltar ao ENUM rebentava com as viaturas que
        // já tenham um estado criado pela empresa.
    }
};
