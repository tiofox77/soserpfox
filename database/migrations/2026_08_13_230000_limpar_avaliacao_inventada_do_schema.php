<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Apaga a avaliação que nunca foi recolhida.
 *
 * A migração das definições de Schema.org semeou `schema_rating_value = 4.8` e
 * `schema_review_count = 150`. Não há em lado nenhum do sistema onde recolher
 * avaliações de clientes: os números foram escritos à mão numa migração e daí
 * seguiam para o JSON-LD da página pública, que é precisamente o que o Google lê
 * para desenhar as estrelinhas no resultado de pesquisa. É a mesma invenção que
 * já tinha sido retirada dos preços da FAQ.
 *
 * Só se limpa o que ainda está exactamente como foi semeado. Se alguém escreveu
 * outro valor de propósito, esse fica — não cabe a uma migração desfazer uma
 * decisão de quem gere a plataforma.
 */
return new class extends Migration
{
    /** Chave => valor semeado, o único que esta migração se autoriza a apagar. */
    private const SEMEADOS = [
        'schema_rating_value' => '4.8',
        'schema_review_count' => '150',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) {
            return;
        }

        foreach (self::SEMEADOS as $chave => $valorSemeado) {
            DB::table('system_settings')
                ->where('key', $chave)
                ->where('value', $valorSemeado)
                ->update(['value' => null, 'updated_at' => now()]);

            // A definição é lida através do cache; sem isto o valor antigo
            // continuava a ser servido durante a hora seguinte.
            Cache::forget("setting_{$chave}");
        }
    }

    /**
     * Não repõe nada. Voltar a escrever 4,8 em 150 avaliações seria repetir a
     * invenção — e a linha continua lá, pronta a receber números verdadeiros.
     */
    public function down(): void
    {
        //
    }
};
