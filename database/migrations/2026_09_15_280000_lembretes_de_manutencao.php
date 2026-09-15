<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OS LEMBRETES DE MANUTENÇÃO (15/09/2026, OF-11).
 *
 * A próxima revisão de cada viatura, por km ou por data — calculada quando a
 * ordem fica concluída, a partir do intervalo da viatura ou do da oficina — e
 * a lista de quem chamar: revisões a chegar e seguro, inspecção e livrete a
 * caducar.
 *
 * `workshop_reminders` é a memória dos contactos: quem foi avisado, por onde e
 * de QUAL vencimento (`due_key`). É ela que impede o envio automático de
 * repetir o SMS e que tira da lista quem já foi chamado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('workshop_vehicles', 'next_service_km')) {
                $table->unsignedInteger('service_interval_km')->nullable()->after('mileage');
                $table->unsignedTinyInteger('service_interval_months')->nullable()->after('service_interval_km');
                $table->date('last_service_date')->nullable()->after('service_interval_months');
                $table->unsignedInteger('last_service_km')->nullable()->after('last_service_date');
                $table->date('next_service_date')->nullable()->after('last_service_km');
                $table->unsignedInteger('next_service_km')->nullable()->after('next_service_date');
                $table->date('reminders_paused_until')->nullable()->after('next_service_km');
            }
        });

        if (! Schema::hasTable('workshop_settings')) {
            Schema::create('workshop_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
                $table->unsignedInteger('service_interval_km')->default(10000);
                $table->unsignedTinyInteger('service_interval_months')->default(6);
                $table->unsignedSmallInteger('remind_days_before')->default(15);
                $table->unsignedInteger('remind_km_before')->default(1000);
                $table->unsignedSmallInteger('documents_days_before')->default(30);
                $table->boolean('auto_reminders')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('workshop_reminders')) {
            Schema::create('workshop_reminders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('workshop_vehicles')->cascadeOnDelete();
                $table->string('kind', 20);
                $table->string('due_key', 40);
                $table->string('channel', 20);
                $table->string('note', 500)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['vehicle_id', 'kind', 'due_key']);
                $table->index(['tenant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_reminders');
        Schema::dropIfExists('workshop_settings');

        Schema::table('workshop_vehicles', function (Blueprint $table) {
            $table->dropColumn(['service_interval_km', 'service_interval_months', 'last_service_date', 'last_service_km', 'next_service_date', 'next_service_km', 'reminders_paused_until']);
        });
    }
};
