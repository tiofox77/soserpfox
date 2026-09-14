<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O ÚLTIMO ACESSO É DA PESSOA NUMA EMPRESA, E NÃO DA PESSOA NO SISTEMA.
 *
 * A lista de empresas da plataforma dizia «entrou há 15 min» da LUK-SIMOES,
 * LDA, que não tinha actividade desde dia 4: o dono é o mesmo da Farmácia Luk
 * Simões, e o `users.last_login_at` é um só para as três empresas dele. Quem
 * trabalha numa acendia as outras.
 *
 * A coluna nasce vazia de propósito. Não há de onde tirar o passado com
 * verdade (o login da auditoria grava a empresa de ORIGEM, não a activa), e
 * enchê-la com o `last_login_at` repetia o erro. Quem tem uma empresa só
 * continua a ser lido pelo `last_login_at` até à primeira visita; quem tem
 * várias passa a contar a partir de agora.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tenant_user', 'ultimo_acesso_em')) {
            return;
        }

        Schema::table('tenant_user', function (Blueprint $table) {
            $table->timestamp('ultimo_acesso_em')->nullable()->after('joined_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenant_user', 'ultimo_acesso_em')) {
            Schema::table('tenant_user', function (Blueprint $table) {
                $table->dropColumn('ultimo_acesso_em');
            });
        }
    }
};
