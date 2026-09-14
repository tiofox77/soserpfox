<?php

namespace App\Console\Commands;

use App\Models\AGT\AGTSubmission;
use App\Services\AGT\GestaoAgt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * AS SUBMISSÕES À AGT POR AMBIENTE — só lê.
 *
 * O despacho, a consulta e o reenvio passaram a tocar SÓ nas submissões do
 * ambiente activo da empresa (uma de produção nunca vai à AGT de testes, nem
 * o contrário). As submissões anteriores à coluna `agt_environment` (Julho de
 * 2026) foram preenchidas com «sandbox»: numa empresa que já emite em
 * produção, uma dessas ainda por concluir ficaria à espera para sempre. Isto
 * mostra se há alguma, antes de alguém se perguntar porque não seguiu.
 */
class AgtSubmissoesPorAmbiente extends Command
{
    protected $signature = 'agt:submissoes-por-ambiente {--empresa= : Só esta empresa (id)}';

    protected $description = 'Mostra as submissões à AGT por ambiente e as que ficam paradas por serem de outro ambiente (só lê)';

    public function handle(): int
    {
        $empresa = $this->option('empresa') !== null ? (int) $this->option('empresa') : null;

        $linhas = AGTSubmission::withoutGlobalScopes()
            ->from('agt_submissions as s')
            ->leftJoin('invoicing_settings as d', 'd.tenant_id', '=', 's.tenant_id')
            ->leftJoin('tenants as t', 't.id', '=', 's.tenant_id')
            ->when($empresa !== null, fn ($q) => $q->where('s.tenant_id', $empresa))
            ->select('s.tenant_id', 't.name as empresa', 'd.agt_environment as activo', 's.agt_environment as da_submissao', 's.status',
                DB::raw('COUNT(*) as n'), DB::raw('MIN(s.created_at) as primeira'), DB::raw('MAX(s.created_at) as ultima'))
            ->groupBy('s.tenant_id', 't.name', 'd.agt_environment', 's.agt_environment', 's.status')
            ->orderBy('s.tenant_id')
            ->get();

        $this->table(['Empresa', 'Nome', 'Activo', 'Da submissão', 'Estado', 'N', 'Primeira', 'Última'],
            $linhas->map(fn ($l) => [$l->tenant_id, mb_strimwidth((string) $l->empresa, 0, 28, '…'), GestaoAgt::normalizar($l->activo), $l->da_submissao, $l->status, $l->n, $l->primeira, $l->ultima])->all());

        $paradas = $linhas->filter(fn ($l) => in_array($l->status, ['pending', 'submitted'], true)
            && GestaoAgt::normalizar($l->activo) !== GestaoAgt::normalizar($l->da_submissao));

        $this->newLine();

        if ($paradas->isEmpty()) {
            $this->info('Nenhuma submissão por concluir fica parada por ser de outro ambiente.');
        } else {
            $this->warn('Por concluir e de OUTRO ambiente (ficam à espera até a empresa lá voltar):');
            $this->table(['Empresa', 'Nome', 'Activo', 'Da submissão', 'Estado', 'N', 'Primeira', 'Última'],
                $paradas->map(fn ($l) => [$l->tenant_id, $l->empresa, GestaoAgt::normalizar($l->activo), $l->da_submissao, $l->status, $l->n, $l->primeira, $l->ultima])->values()->all());
        }

        return self::SUCCESS;
    }
}
