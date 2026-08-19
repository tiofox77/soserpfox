<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preço por módulo e período de teste por módulo.
 *
 * modules.default_price — o preço mensal sugerido de cada módulo. Serve
 * para montar um plano à medida somando o que se escolhe, em vez de o
 * super admin ter de calcular de cabeça.
 *
 * tenant_module.trial_ends_at — um módulo pode ser dado a experimentar sem
 * que o resto do plano esteja em teste. Passada a data, o Tenant::hasModule
 * deixa de o dar como disponível — que é o único portão por onde o acesso
 * aos módulos passa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $t) {
            $t->decimal('default_price', 12, 2)->default(0)->after('is_active');
        });

        Schema::table('tenant_module', function (Blueprint $t) {
            $t->timestamp('trial_ends_at')->nullable()->after('is_active');
            // Quanto se cobra por este módulo a ESTA empresa. Fica registado
            // para se saber de onde veio o total do plano à medida.
            $t->decimal('price', 12, 2)->nullable()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $t) {
            $t->dropColumn('default_price');
        });

        Schema::table('tenant_module', function (Blueprint $t) {
            $t->dropColumn(['trial_ends_at', 'price']);
        });
    }
};
