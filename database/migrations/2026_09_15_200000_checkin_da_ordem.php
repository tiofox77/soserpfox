<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O CHECK-IN DA VIATURA — o que se regista quando o carro entra (15/09/2026).
 *
 * OF-01 do roteiro da oficina: combustível, danos marcados no desenho do carro,
 * acessórios e objectos deixados, luzes acesas no painel, número de chaves e a
 * assinatura do cliente. Um por ordem de serviço. Os km à entrada continuam a
 * ser os da ordem (`mileage_in`): não se guardam em dois sítios.
 *
 * `signed_hash` é a impressão do que o cliente assinou: se alguém muda os danos
 * depois, a assinatura deixa de valer e o ecrã pede outra.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshop_work_order_checkins')) {
            return;
        }

        Schema::create('workshop_work_order_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('work_order_id')->unique()->constrained('workshop_work_orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('fuel_level')->nullable();
            $table->json('damages')->nullable();
            $table->json('accessories')->nullable();
            $table->json('warning_lights')->nullable();
            $table->unsignedTinyInteger('keys_count')->nullable();
            $table->text('belongings')->nullable();
            $table->text('notes')->nullable();
            $table->mediumText('signature')->nullable();
            $table->string('signed_by', 150)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_hash', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_work_order_checkins');
    }
};
