<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O NÚMERO DE SÉRIE DE UM EQUIPAMENTO É ÚNICO POR EMPRESA, E NÃO NO MUNDO.
 *
 * `events_equipments_manager.serial_number` tinha um índice único GLOBAL. Duas
 * empresas podem ter — e têm — o mesmo modelo de projector, com o número de
 * série que o fabricante lhe deu. Com o índice global, a segunda a registá-lo
 * levava um erro de base de dados por causa de um equipamento que não é seu e
 * que nunca chegará a ver.
 *
 * Pior do que o incómodo: era um canal entre empresas. Bastava tentar gravar um
 * número de série para saber se alguém, algures no sistema, já tinha aquele
 * aparelho.
 *
 * A regra da casa para isto está escrita e é sempre a mesma: identificador
 * gerado ou escrito por empresa leva índice composto `(tenant_id, coluna)`.
 * Foi o que se fez ao NIF dos clientes e ao número de turno do POS.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * OS REPETIDOS QUE JÁ LÁ ESTÃO.
         *
         * Não pode haver nenhum — o índice global impedia-o —, mas a migração
         * corre em bases que podem ter passado por importações com o índice
         * caído. Com o índice composto, repetidos DENTRO da mesma empresa
         * voltariam a ser recusados, e a migração morria a meio.
         */
        $repetidos = \DB::table('events_equipments_manager')
            ->whereNotNull('serial_number')
            ->whereNull('deleted_at')
            ->groupBy('tenant_id', 'serial_number')
            ->havingRaw('COUNT(*) > 1')
            ->selectRaw('tenant_id, serial_number')
            ->get();

        foreach ($repetidos as $r) {
            $ids = \DB::table('events_equipments_manager')
                ->where('tenant_id', $r->tenant_id)
                ->where('serial_number', $r->serial_number)
                ->orderBy('id')
                ->pluck('id')
                ->skip(1);

            // O primeiro fica com o número; os outros ficam sem — é preferível
            // a um equipamento que desaparece ou a uma migração que não corre.
            foreach ($ids as $id) {
                \DB::table('events_equipments_manager')->where('id', $id)->update(['serial_number' => null]);
            }
        }

        Schema::table('events_equipments_manager', function (Blueprint $tabela) {
            $tabela->dropUnique('equipment_serial_number_unique');
            $tabela->unique(['tenant_id', 'serial_number'], 'equipamentos_serie_por_empresa');
        });
    }

    public function down(): void
    {
        Schema::table('events_equipments_manager', function (Blueprint $tabela) {
            $tabela->dropUnique('equipamentos_serie_por_empresa');
            $tabela->unique('serial_number', 'equipment_serial_number_unique');
        });
    }
};
