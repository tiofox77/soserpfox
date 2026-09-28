<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A LARGURA DO TALÃO (28/09/2026).
 *
 * O talão só existia a 80 mm. As máquinas portáteis com impressora embutida —
 * a Sunmi V2s, por exemplo — usam rolo de 58 mm (48 mm impressos): um talão de
 * 80 mm saía encolhido a 60 %, com letra ilegível e o QR da AGT pequeno de mais
 * para se ler, ou cortado à direita.
 *
 * Esta é a largura da EMPRESA; cada aparelho pode escolher a sua (fica no
 * próprio aparelho), porque há casas com impressora de balcão de 80 mm e
 * máquinas de 58 mm ao mesmo tempo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoicing_settings', 'pos_largura_talao')) {
            Schema::table('invoicing_settings', function (Blueprint $table) {
                $table->string('pos_largura_talao', 2)->default('80')->after('pos_formato_impressao');
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->dropColumn('pos_largura_talao');
        });
    }
};
