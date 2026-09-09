<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AS COLUNAS QUE FALTAVAM À ORDEM DE MANUTENÇÃO DO HOTEL.
 *
 * O ecrã `/hotel/maintenance` NUNCA CONSEGUIU GRAVAR uma ordem. O modelo
 * declarava oito colunas que a tabela não tem — `type`, `estimated_cost`,
 * `actual_cost`, `estimated_time`, `actual_time`, `resolution_notes`,
 * `images` — e o formulário mandava-as todas. O insert saía com elas e o MySQL
 * respondia:
 *
 *     SQLSTATE[42S22]: Column not found: 1054 Unknown column 'type'
 *
 * Não era um caso de canto: era o botão de gravar, em qualquer ordem.
 *
 * A RESOLUÇÃO É EM DOIS SENTIDOS:
 *
 *  · O que a tabela JÁ TINHA com outro nome fica com o nome da tabela, e o
 *    modelo é que se corrige: `resolution` (não `resolution_notes`) e `cost`
 *    (não `actual_cost`). Acrescentar colunas gémeas era ficar com duas
 *    verdades sobre quanto custou o arranjo.
 *  · O que o ecrã pede e não existe em lado nenhum entra aqui: o TIPO da
 *    ordem (preventiva, correctiva, urgência) e a estimativa de custo e de
 *    tempo — que é o que se preenche ao abrir a ordem, antes de se saber o
 *    real.
 *
 * `actual_time` e `images` saem do modelo sem coluna nova: o tempo real
 * calcula-se de `started_at` a `completed_at`, e as fotografias já têm
 * `photos_before` e `photos_after`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hotel_maintenance_orders')) {
            return;
        }

        Schema::table('hotel_maintenance_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('hotel_maintenance_orders', 'type')) {
                $table->string('type')->default('corrective')->after('order_number');
            }

            if (! Schema::hasColumn('hotel_maintenance_orders', 'estimated_cost')) {
                $table->decimal('estimated_cost', 12, 2)->nullable()->after('cost');
            }

            if (! Schema::hasColumn('hotel_maintenance_orders', 'estimated_time')) {
                // Em MINUTOS, que é como o ecrã sempre o pediu.
                $table->unsignedInteger('estimated_time')->nullable()->after('estimated_cost');
            }
        });

        /*
         * QUEM REPORTOU PODE NÃO TER FICHA DE PESSOAL.
         *
         * `reported_by` aponta para `hotel_staff` e era NOT NULL. O ecrã
         * preenchia-o com a ficha da pessoa que estava a abrir a ordem — e
         * quem não tem ficha (o gerente, um recepcionista sem registo no
         * pessoal do hotel) mandava NULL. Era a SEGUNDA razão pela qual gravar
         * uma ordem falhava sempre:
         *
         *     SQLSTATE[HY000]: Field 'reported_by' doesn't have a default value
         *
         * Uma avaria reportada por alguém sem ficha continua a ser uma avaria.
         */
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE hotel_maintenance_orders MODIFY reported_by BIGINT UNSIGNED NULL'
        );

        /*
         * E A DESCRIÇÃO TAMBÉM PODE FALTAR — era a TERCEIRA razão.
         *
         * `description` era TEXT NOT NULL sem omissão, e o formulário sempre
         * a deixou em branco. «Lâmpada do corredor» diz tudo o que há a dizer;
         * obrigar a escrever um parágrafo para abrir uma ordem é o que faz
         * ninguém abrir ordem nenhuma.
         */
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE hotel_maintenance_orders MODIFY description TEXT NULL'
        );

        /*
         * E O CUSTO REAL SÓ EXISTE DEPOIS DE SE ARRANJAR.
         *
         * `cost` era NOT NULL com omissão `0.00`, e uma ordem ainda por fazer
         * mostrava «0,00 Kz» de custo real — que se lê como «não custou nada»,
         * e não como «ainda não se sabe».
         */
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE hotel_maintenance_orders MODIFY cost DECIMAL(12,2) NULL DEFAULT NULL'
        );

        /*
         * QUEM ABRE E QUEM ARRANJA SÃO PESSOAL DO HOTEL, NÃO UTILIZADORES.
         *
         * `reported_by` e `assigned_to` apontavam para `users`. O modelo diz
         * `Staff` nas duas relações e a caixa de escolha do ecrã sempre listou
         * `hotel_staff`: gravar uma atribuição dava erro de chave estrangeira,
         * e nos casos em que o número existia por acaso nas duas tabelas a
         * ficha mostrava o UTILIZADOR com o mesmo número — outra pessoa.
         *
         * É a mesma correcção que a oficina levou no `mechanic_id`. E faz
         * sentido: uma camareira arranja o quarto sem ter conta no sistema.
         */
        foreach (['reported_by', 'assigned_to'] as $coluna) {
            \Illuminate\Support\Facades\DB::table('hotel_maintenance_orders')
                ->whereNotNull($coluna)
                ->whereNotIn($coluna, \Illuminate\Support\Facades\DB::table('hotel_staff')->select('id'))
                ->update([$coluna => null]);
        }

        Schema::table('hotel_maintenance_orders', function (Blueprint $table) {
            $table->dropForeign(['reported_by']);
            $table->dropForeign(['assigned_to']);

            $table->foreign('reported_by')->references('id')->on('hotel_staff')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('hotel_staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hotel_maintenance_orders')) {
            return;
        }

        // A volta atrás repõe as chaves em `users` — e limpa primeiro o que lá
        // não existe, senão a própria migração falha.
        foreach (['reported_by', 'assigned_to'] as $coluna) {
            \Illuminate\Support\Facades\DB::table('hotel_maintenance_orders')
                ->whereNotNull($coluna)
                ->whereNotIn($coluna, \Illuminate\Support\Facades\DB::table('users')->select('id'))
                ->update([$coluna => null]);
        }

        Schema::table('hotel_maintenance_orders', function (Blueprint $table) {
            $table->dropForeign(['reported_by']);
            $table->dropForeign(['assigned_to']);
            $table->foreign('reported_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();

            foreach (['type', 'estimated_cost', 'estimated_time'] as $coluna) {
                if (Schema::hasColumn('hotel_maintenance_orders', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
