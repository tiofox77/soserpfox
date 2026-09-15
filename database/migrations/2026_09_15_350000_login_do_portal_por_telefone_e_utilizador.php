<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ENTRAR NO PORTAL DO CLIENTE POR TELEFONE OU NOME DE UTILIZADOR (15/09/2026).
 *
 * Pedido do dono: além do email, o cliente entra com o número de telefone ou
 * com um nome de utilizador que a empresa lhe cria — e recebe os dados de
 * entrada por email.
 *
 * `portal_phone` é o telefone já NORMALIZADO (só algarismos, sem o 244 de
 * Angola) — o que se compara na entrada, com índice. Mantém-no o modelo a cada
 * gravação, a partir do telemóvel (ou do telefone, se não houver telemóvel).
 * `portal_username` é único dentro da empresa; entre empresas pode repetir-se,
 * como o email: a senha decide, e o cliente escolhe a empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoicing_clients', function (Blueprint $table) {
            if (! Schema::hasColumn('invoicing_clients', 'portal_username')) {
                $table->string('portal_username', 50)->nullable()->after('portal_access');
                $table->string('portal_phone', 20)->nullable()->after('portal_username');
                $table->unique(['tenant_id', 'portal_username'], 'clients_tenant_portal_username_unique');
                $table->index('portal_username');
                $table->index('portal_phone');
            }
        });

        // Os clientes que já existem ganham o telefone normalizado.
        DB::table('invoicing_clients')->where(fn ($q) => $q->whereNotNull('mobile')->orWhereNotNull('phone'))
            ->select(['id', 'phone', 'mobile'])->orderBy('id')
            ->chunkById(500, function ($clientes) {
                foreach ($clientes as $c) {
                    $numero = \App\Support\TelefoneDoPortal::normalizar($c->mobile ?: $c->phone);
                    if ($numero) {
                        DB::table('invoicing_clients')->where('id', $c->id)->update(['portal_phone' => $numero]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('invoicing_clients', function (Blueprint $table) {
            $table->dropUnique('clients_tenant_portal_username_unique');
            $table->dropIndex(['portal_username']);
            $table->dropIndex(['portal_phone']);
            $table->dropColumn(['portal_username', 'portal_phone']);
        });
    }
};
