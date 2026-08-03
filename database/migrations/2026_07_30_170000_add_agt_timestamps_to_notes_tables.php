<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datas de submissão/validação AGT nas notas de crédito e débito.
 *
 * As facturas e recibos já as tinham; as notas não. Os models declaravam-nas em
 * $fillable e o AGTService escreve-as em cada submissão, mas como as colunas não
 * existiam o serviço filtrava-as pelo esquema e a informação era descartada em
 * silêncio — ficava impossível provar QUANDO uma NC/ND foi transmitida, o que a
 * certificação exige. A guia de transporte tinha submitted_at mas não
 * validated_at, pelo mesmo motivo.
 */
return new class extends Migration
{
    /** [tabela => [colunas a garantir]] */
    private const ALVOS = [
        'invoicing_credit_notes'    => ['agt_submitted_at', 'agt_validated_at'],
        'invoicing_debit_notes'     => ['agt_submitted_at', 'agt_validated_at'],
        'invoicing_transport_guides' => ['agt_validated_at'],
    ];

    public function up(): void
    {
        foreach (self::ALVOS as $tabela => $colunas) {
            if (!Schema::hasTable($tabela)) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) use ($tabela, $colunas) {
                foreach ($colunas as $coluna) {
                    if (Schema::hasColumn($tabela, $coluna)) {
                        continue;
                    }
                    $table->timestamp($coluna)->nullable()->after('agt_status');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::ALVOS as $tabela => $colunas) {
            if (!Schema::hasTable($tabela)) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) use ($tabela, $colunas) {
                $existentes = array_filter($colunas, fn ($c) => Schema::hasColumn($tabela, $c));
                if ($existentes) {
                    $table->dropColumn(array_values($existentes));
                }
            });
        }
    }
};
