<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O MECÂNICO DE UMA LINHA DA ORDEM É DA OFICINA, NÃO DO RH.
 *
 * `workshop_work_order_items.mechanic_id` apontava para `hr_employees` desde
 * que a tabela nasceu. A caixa de escolha do ecrã, porém, sempre listou
 * `workshop_mechanics` — é a lista de mecânicos que o ecrã carrega. Ou seja: o
 * número que se gravava era de um mecânico e a chave exigia um funcionário.
 *
 * O resultado não era um erro, era pior: as linhas em que o número existia por
 * acaso nas duas tabelas gravavam-se, e a ficha mostrava o nome do FUNCIONÁRIO
 * COM O MESMO NÚMERO — outra pessoa. As restantes rebentavam com erro de chave
 * estrangeira ao gravar a linha.
 *
 * A chave da própria ordem (`workshop_work_orders.mechanic_id`) foi corrigida
 * em 2025-11-05; esta ficou para trás. É a mesma correcção.
 *
 * OS VALORES ÓRFÃOS PASSAM A NULO. Um número que não é de nenhum mecânico
 * desta oficina não identifica ninguém: mantê-lo era guardar a resposta errada
 * à pergunta «quem fez isto?». Fica escrito no log quantos foram.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workshop_work_order_items')) {
            return;
        }

        $orfaos = DB::table('workshop_work_order_items')
            ->whereNotNull('mechanic_id')
            ->whereNotIn('mechanic_id', DB::table('workshop_mechanics')->select('id'))
            ->update(['mechanic_id' => null]);

        if ($orfaos > 0) {
            \Log::info("Oficina: {$orfaos} linha(s) de ordem apontavam para um mecânico inexistente e ficaram sem mecânico.");
        }

        Schema::table('workshop_work_order_items', function (Blueprint $table) {
            $table->dropForeign(['mechanic_id']);
            $table->foreign('mechanic_id')
                ->references('id')->on('workshop_mechanics')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('workshop_work_order_items')) {
            return;
        }

        // A volta atrás recria a chave para `hr_employees` — e por isso limpa
        // primeiro o que lá não existe, senão a própria migração falha.
        DB::table('workshop_work_order_items')
            ->whereNotNull('mechanic_id')
            ->whereNotIn('mechanic_id', DB::table('hr_employees')->select('id'))
            ->update(['mechanic_id' => null]);

        Schema::table('workshop_work_order_items', function (Blueprint $table) {
            $table->dropForeign(['mechanic_id']);
            $table->foreign('mechanic_id')
                ->references('id')->on('hr_employees')
                ->onDelete('set null');
        });
    }
};
