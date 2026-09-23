<?php

namespace Database\Seeders;

use App\Services\AGT\GestaoAgt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * DS.120 v1.1 — Anexo 9.5: CAE (Classificação das Actividades Económicas).
 *
 * A lista inteira da CAE-Rev.2 do INE Angola, em `data/cae_rev2.php`:
 * secções, divisões, grupos, classes e as 575 subclasses de 5 dígitos, que
 * são o que a AGT recebe em eacCode.
 *
 * Até 23/09/2026 este seeder tinha só as secções, umas 30 divisões e 8
 * códigos de 5 dígitos, e 4 desses nem sequer são de Angola (47210, 62020,
 * 96021, 96022, da CAE portuguesa). Um código que não é da lista oficial
 * fica INACTIVO: não se apaga, porque uma empresa pode tê-lo gravado. O ecrã
 * mostra-o como «código gravado» e obriga a escolher um da lista ao guardar.
 *
 * Pode correr as vezes que for preciso: actualiza pelo código.
 */
class AGTCaeCodeSeeder extends Seeder
{
    public function run(): void
    {
        $agora = now();
        $linhas = array_map(fn (array $l) => [
            'code' => $l[0],
            'level' => $l[1],
            'parent_code' => $l[2],
            'description' => $l[3],
            'is_active' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ], require __DIR__ . '/data/cae_rev2.php');

        $oficiais = array_column($linhas, 'code');

        $desactivados = DB::transaction(function () use ($linhas, $oficiais) {
            foreach (array_chunk($linhas, 200) as $bloco) {
                DB::table('agt_cae_codes')->upsert($bloco, ['code'], ['level', 'parent_code', 'description', 'is_active', 'updated_at']);
            }

            $fora = DB::table('agt_cae_codes')->whereNotIn('code', $oficiais)->where('is_active', true)->pluck('code')->all();
            DB::table('agt_cae_codes')->whereIn('code', $fora)->update(['is_active' => false, 'updated_at' => now()]);

            return $fora;
        });

        Cache::forget(GestaoAgt::CHAVE_DO_CAE);
        Cache::forget('agt_cae_classes'); // a chave de antes, com as classes de 4 e 5 dígitos misturadas

        $this->command?->info(count($linhas) . ' códigos da CAE-Rev.2 ('
            . count(array_filter($linhas, fn ($l) => $l['level'] === 'subclass')) . ' subclasses).');
        $this->command?->info('Desactivados por não serem da lista oficial: ' . ($desactivados ? implode(', ', $desactivados) : 'nenhum') . '.');

        $this->relatarEmpresasForaDaLista();
    }

    /**
     * Só diz: as empresas com um CAE que não é subclasse activa. Mudar o
     * código de uma empresa é decisão dela (ou de quem a ajuda).
     */
    private function relatarEmpresasForaDaLista(): void
    {
        $validos = DB::table('agt_cae_codes')->where('level', 'subclass')->where('is_active', true)->pluck('code')->all();

        $fora = DB::table('invoicing_settings')
            ->whereNotNull('agt_eac_code')
            ->where('agt_eac_code', '<>', '')
            ->whereNotIn('agt_eac_code', $validos)
            ->get(['tenant_id', 'agt_eac_code']);

        if ($fora->isEmpty()) {
            $this->command?->info('Todas as empresas com CAE têm um código da lista oficial.');

            return;
        }

        $this->command?->warn($fora->count() . ' empresa(s) com CAE fora da lista oficial:');
        foreach ($fora->groupBy('agt_eac_code') as $codigo => $empresas) {
            $this->command?->warn("  {$codigo}: empresas " . $empresas->pluck('tenant_id')->implode(', '));
        }
    }
}
