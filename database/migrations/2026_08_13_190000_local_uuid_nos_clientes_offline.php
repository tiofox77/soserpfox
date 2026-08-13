<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identificador local do cliente criado offline.
 *
 * O PWA já enviava o `local_uuid` na criação de clientes e o controlador já o
 * devolvia na resposta — mas não havia coluna onde o guardar, portanto nunca
 * serviu para nada.
 *
 * O que falhava: a desduplicação só olhava para o NIF. Um cliente sem NIF — o
 * caso do balcão, que é a maioria no POS — duplicava-se sempre que a resposta
 * do servidor se perdesse. E perde-se: o pedido chega, o cliente é criado, a
 * ligação cai antes da resposta, o PWA conta como falha e reenvia. Dois
 * registos para a mesma pessoa, e as vendas seguintes divididas entre eles.
 *
 * O índice único fecha a janela mesmo quando dois pedidos chegam ao mesmo
 * tempo. É por empresa: dois inquilinos podem ter o mesmo uuid sem se
 * atrapalharem.
 *
 * Vários NULL convivem num índice único do MySQL, portanto as linhas que já
 * existem não são afectadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoicing_clients')) {
            return;
        }

        if (Schema::hasColumn('invoicing_clients', 'local_uuid')) {
            return;
        }

        Schema::table('invoicing_clients', function (Blueprint $table) {
            $table->string('local_uuid', 80)->nullable()->after('id');

            $table->unique(['tenant_id', 'local_uuid'], 'invoicing_clients_tenant_local_uuid_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoicing_clients') || !Schema::hasColumn('invoicing_clients', 'local_uuid')) {
            return;
        }

        Schema::table('invoicing_clients', function (Blueprint $table) {
            $table->dropUnique('invoicing_clients_tenant_local_uuid_unique');
            $table->dropColumn('local_uuid');
        });
    }
};
