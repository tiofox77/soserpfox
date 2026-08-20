<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O histórico de envios do agente passa a guardar o destinatário por inteiro.
 *
 * A coluna chamava-se `destinatario_mascarado` e guardava 'ca***@dominio.ao'.
 * A máscara foi retirada de toda a API do agente a pedido de quem gere a
 * plataforma, e uma coluna com esse nome a guardar o endereço completo seria
 * uma mentira gravada no esquema — quem a lesse daqui a um ano ficaria
 * convencido de que o que lá está é seguro mostrar.
 *
 * As linhas antigas ficam como estão, com a máscara. Não há como as
 * reconstruir: o endereço completo nunca chegou a ser gravado. É preferível
 * ter histórico antigo cortado a inventar dados.
 *
 * 120 caracteres chegam para um email; era esse o tamanho e mantém-se.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('agent_messages')) {
            return;
        }

        if (Schema::hasColumn('agent_messages', 'destinatario_mascarado')
            && !Schema::hasColumn('agent_messages', 'destinatario')) {
            Schema::table('agent_messages', function (Blueprint $t) {
                $t->renameColumn('destinatario_mascarado', 'destinatario');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agent_messages')
            && Schema::hasColumn('agent_messages', 'destinatario')
            && !Schema::hasColumn('agent_messages', 'destinatario_mascarado')) {
            Schema::table('agent_messages', function (Blueprint $t) {
                $t->renameColumn('destinatario', 'destinatario_mascarado');
            });
        }
    }
};
