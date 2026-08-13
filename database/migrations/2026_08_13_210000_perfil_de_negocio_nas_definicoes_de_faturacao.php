<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil do negócio nas definições de faturação.
 *
 * Os campos de medicamento e de vestuário já existem no catálogo, mas não havia
 * como uma empresa dizer que trabalha com eles: ou apareciam a toda a gente, ou
 * a ninguém. Estas duas colunas são esse interruptor.
 *
 * Dois booleanos e não uma escolha única, de propósito: um supermercado com
 * balcão de farmácia é as duas coisas ao mesmo tempo, e uma escolha única
 * obrigava-o a mentir. "Normal" é simplesmente nenhum dos dois ligado — não é
 * um terceiro valor a guardar, logo não há coluna para ele.
 *
 * O perfil manda apenas no que APARECE por omissão. Não manda nos avisos de
 * receita e de psicotrópico do POS, que seguem os dados do artigo: uma
 * protecção que se desliga numa definição de visualização não é protecção.
 *
 * Guardas coluna a coluna porque esta base já levou migrações corridas à mão —
 * se uma rebentar a meio, o que já foi aplicado não volta a ser tentado.
 */
return new class extends Migration
{
    private const COLUNAS = ['profile_pharmacy', 'profile_clothing'];

    public function up(): void
    {
        if (!Schema::hasTable('invoicing_settings')) {
            return;
        }

        Schema::table('invoicing_settings', function (Blueprint $table) {
            // O `after` só entra se a âncora existir mesmo: noutra base o
            // esquema pode estar noutro estado, e um `after` para uma coluna
            // inexistente é erro de SQL, não um aviso.
            $ancora = Schema::hasColumn('invoicing_settings', 'pos_hide_out_of_stock')
                ? 'pos_hide_out_of_stock'
                : null;

            if (!Schema::hasColumn('invoicing_settings', 'profile_pharmacy')) {
                $coluna = $table->boolean('profile_pharmacy')
                    ->default(false)
                    ->comment('Trabalha com medicamentos');

                if ($ancora !== null) {
                    $coluna->after($ancora);
                }
            }

            if (!Schema::hasColumn('invoicing_settings', 'profile_clothing')) {
                // Encosta ao perfil de farmácia, que ou já lá estava, ou é
                // acrescentado nesta mesma instrução.
                $table->boolean('profile_clothing')
                    ->default(false)
                    ->after('profile_pharmacy')
                    ->comment('Trabalha com vestuário');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoicing_settings')) {
            return;
        }

        $existentes = array_values(array_filter(
            self::COLUNAS,
            fn (string $coluna) => Schema::hasColumn('invoicing_settings', $coluna)
        ));

        if ($existentes === []) {
            return;
        }

        Schema::table('invoicing_settings', function (Blueprint $table) use ($existentes) {
            $table->dropColumn($existentes);
        });
    }
};
