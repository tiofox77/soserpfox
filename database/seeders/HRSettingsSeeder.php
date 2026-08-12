<?php

namespace Database\Seeders;

use App\Services\HR\DefinicoesRH;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Definições de RH — legislação laboral angolana.
 *
 * O CATÁLOGO JÁ NÃO VIVE AQUI. Vive em App\Services\HR\DefinicoesRH, e a razão
 * é o defeito que este ficheiro teve desde sempre: tinha `$tenantId = 1;`
 * escrito à mão, com um comentário "Ajustar conforme necessário" que nunca foi
 * ajustado. Resultado: só a empresa 1 alguma vez teve configurações de RH, e
 * todas as outras com o módulo activo abriam /hr/settings e não viam nada.
 *
 * Com o catálogo num serviço, o mesmo código serve o seeder, o comando
 * `hr:settings-sync` e o próprio ecrã, que passa a criar as definições em falta
 * na primeira visita. Deixa de haver forma de uma empresa ficar sem elas.
 */
class HRSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Todas as empresas com o módulo de RH. Uma empresa sem RH não precisa
        // destas linhas, e criá-las seria encher a tabela sem proveito.
        $empresas = DB::table('tenant_module')
            ->join('modules', 'modules.id', '=', 'tenant_module.module_id')
            ->where('modules.slug', 'rh')
            ->distinct()
            ->pluck('tenant_module.tenant_id');

        if ($empresas->isEmpty()) {
            $this->command?->warn('Nenhuma empresa com o módulo de RH activo — nada a semear.');

            return;
        }

        $total = 0;

        foreach ($empresas as $tenantId) {
            $criadas = DefinicoesRH::garantirPara((int) $tenantId);
            $total  += $criadas;

            $this->command?->line(sprintf(
                '  empresa %-5s %s',
                $tenantId,
                $criadas > 0 ? "{$criadas} definição(ões) criada(s)" : 'já estava completa'
            ));
        }

        $this->command?->info("✓ Configurações de RH: {$total} criada(s) em {$empresas->count()} empresa(s).");
    }
}
