<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A RETENÇÃO PASSA A APONTAR PARA UM IMPOSTO, como o IVA já apontava.
 *
 * Havia `default_tax_id` (o imposto do IVA) e, ao lado, dois números escritos
 * à mão: `default_tax_rate` e `default_irt_rate`. O do IVA já deriva do
 * imposto escolhido; falta o da retenção fazer o mesmo.
 *
 * E há impostos de IRT no catálogo para o efeito — doze, todos «IRT 6,5%
 * (Retenção)». Escrever 6,5 numa caixa ao lado de um catálogo que já sabe a
 * taxa eram duas verdades a competir, exactamente como no IVA.
 *
 * A COLUNA NASCE PREENCHIDA onde dá: para cada empresa que tenha um imposto
 * do tipo `irt` com a taxa que está nas definições, aponta-se para ele. Onde
 * não houver, fica a NULL e o número continua a valer — nenhuma empresa perde
 * a taxa que tinha.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoicing_settings', 'default_irt_tax_id')) {
            Schema::table('invoicing_settings', function (Blueprint $t) {
                $t->unsignedBigInteger('default_irt_tax_id')
                    ->nullable()
                    ->after('default_tax_id');
            });
        }

        /*
         * Ligar o que já existe.
         *
         * Casa-se pela TAXA e pelo tipo `irt` dentro da mesma empresa. Sem
         * correspondência não se inventa nada: fica NULL, e o ecrã mostra o
         * número que lá está com a nota de que não há imposto escolhido.
         */
        $ligados = 0;

        foreach (DB::table('invoicing_settings')->whereNull('default_irt_tax_id')->get(['id', 'tenant_id', 'default_irt_rate']) as $d) {
            $imposto = DB::table('invoicing_taxes')
                ->where('tenant_id', $d->tenant_id)
                ->where('type', 'irt')
                ->where('is_active', true)
                ->whereRaw('ABS(rate - ?) < 0.005', [(float) ($d->default_irt_rate ?? 0)])
                ->value('id');

            if ($imposto) {
                DB::table('invoicing_settings')->where('id', $d->id)->update(['default_irt_tax_id' => $imposto]);
                $ligados++;
            }
        }

        DB::table('invoicing_settings')->exists() && logger()->info('Retenção ligada ao imposto', ['empresas' => $ligados]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoicing_settings', 'default_irt_tax_id')) {
            Schema::table('invoicing_settings', function (Blueprint $t) {
                $t->dropColumn('default_irt_tax_id');
            });
        }
    }
};
