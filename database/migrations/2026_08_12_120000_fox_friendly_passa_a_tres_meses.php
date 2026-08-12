<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O FOX Friendly passa de 6 para 3 meses.
 *
 * Estava escrito "6 meses" no banner do painel, na descrição do plano, na
 * lista de vantagens e no balão da raposa da barra lateral — e 180 dias na
 * base. Meio ano de ERP completo, com todos os módulos e 999 utilizadores,
 * é muito para uma promoção de entrada.
 *
 * Quem já está lá dentro não é mexido: as subscrições em curso guardam a
 * própria data de fim e ficam com os 6 meses que lhes foram prometidos. Isto
 * só muda o que se oferece de agora em diante.
 */
return new class extends Migration
{
    public function up(): void
    {
        $plano = DB::table('plans')->where('slug', 'fox-friendly')->first();

        if (!$plano) {
            return;
        }

        $vantagens = json_decode($plano->features ?? '[]', true) ?: [];

        foreach ($vantagens as $i => $vantagem) {
            $vantagens[$i] = str_replace('6 meses', '3 meses', $vantagem);
        }

        DB::table('plans')->where('slug', 'fox-friendly')->update([
            'trial_days'  => 90,
            'description' => str_replace('6 meses', '3 meses', $plano->description ?? ''),
            'features'    => json_encode($vantagens, JSON_UNESCAPED_UNICODE),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        $plano = DB::table('plans')->where('slug', 'fox-friendly')->first();

        if (!$plano) {
            return;
        }

        $vantagens = json_decode($plano->features ?? '[]', true) ?: [];

        foreach ($vantagens as $i => $vantagem) {
            $vantagens[$i] = str_replace('3 meses', '6 meses', $vantagem);
        }

        DB::table('plans')->where('slug', 'fox-friendly')->update([
            'trial_days'  => 180,
            'description' => str_replace('3 meses', '6 meses', $plano->description ?? ''),
            'features'    => json_encode($vantagens, JSON_UNESCAPED_UNICODE),
            'updated_at'  => now(),
        ]);
    }
};
