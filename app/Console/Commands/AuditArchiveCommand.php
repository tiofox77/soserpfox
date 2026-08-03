<?php

namespace App\Console\Commands;

use App\Models\AuditTrail;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Arquiva a trilha antiga para ficheiro e liberta a tabela.
 *
 * Uma venda de balcão com três artigos gera 14 linhas de auditoria (medido). A
 * 50 vendas por dia são ~255 mil linhas e ~120 MB por ano, por empresa. Sem
 * arquivo, a tabela cresce para sempre e acaba por pesar nas consultas.
 *
 * Arquivar parte a cadeia de hashes por construção — as linhas removidas
 * deixam um buraco. Por isso:
 *
 *  1. Nunca se apaga sem escrever primeiro o ficheiro, e sem o reler para
 *     confirmar que ficou lá.
 *  2. Grava-se uma linha-marco no fim, dentro da própria trilha, com o
 *     intervalo arquivado e o hash da última linha removida. A verificação da
 *     cadeia lê o marco e sabe que o salto é legítimo — em vez de acusar
 *     adulteração.
 */
class AuditArchiveCommand extends Command
{
    protected $signature = 'audit:archive
                            {--tenant= : Só esta empresa}
                            {--days= : Sobrepõe audit.retention_days}
                            {--dry-run : Só mostra o que faria}';

    protected $description = 'Arquiva a trilha de auditoria antiga para ficheiro e liberta a tabela';

    public function handle(): int
    {
        $dias = (int) ($this->option('days') ?? config('audit.retention_days', 0));

        if ($dias <= 0) {
            $this->warn('Retenção a 0 (manter tudo). Defina AUDIT_RETENTION_DAYS ou use --days.');

            return self::SUCCESS;
        }

        $corte  = now()->subDays($dias);
        $seco   = (bool) $this->option('dry-run');

        $this->info("Corte: {$corte->format('Y-m-d')} (retenção de {$dias} dias)" . ($seco ? '  [simulação]' : ''));
        $this->newLine();

        $empresas = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->pluck('id')
            : AuditTrail::select('tenant_id')->distinct()->pluck('tenant_id');

        $totalArquivado = 0;

        foreach ($empresas as $tenantId) {
            $totalArquivado += $this->arquivarEmpresa((int) $tenantId, $corte, $seco);
        }

        $this->newLine();
        $this->info($seco
            ? "Simulação: {$totalArquivado} linhas seriam arquivadas."
            : "Arquivadas {$totalArquivado} linhas.");

        return self::SUCCESS;
    }

    private function arquivarEmpresa(int $tenantId, \Carbon\Carbon $corte, bool $seco): int
    {
        $base = AuditTrail::where('tenant_id', $tenantId)
            ->where('created_at', '<', $corte)
            ->where('event', '!=', 'audit.archived');   // marcos nunca se arquivam

        $quantas = (clone $base)->count();

        if ($quantas === 0) {
            return 0;
        }

        $nome = Tenant::find($tenantId)?->name ?? "#{$tenantId}";
        $this->line("  {$nome}: {$quantas} linhas");

        if ($seco) {
            return $quantas;
        }

        $primeira = (clone $base)->orderBy('sequence')->first();
        $ultima   = (clone $base)->orderByDesc('sequence')->first();

        $ficheiro = sprintf(
            'auditoria/tenant-%d/%s_seq-%d-a-%d.jsonl',
            $tenantId,
            $corte->format('Ymd'),
            $primeira->sequence,
            $ultima->sequence
        );

        // Escrita em streaming: a alternativa era carregar tudo em memória, e
        // "tudo" pode ser meio milhão de linhas.
        $caminho = Storage::disk('local')->path($ficheiro);
        @mkdir(dirname($caminho), 0775, true);

        $handle = fopen($caminho, 'w');
        $escritas = 0;

        (clone $base)->orderBy('sequence')->chunk(1000, function ($linhas) use ($handle, &$escritas) {
            foreach ($linhas as $linha) {
                fwrite($handle, json_encode($linha->toArray(), JSON_UNESCAPED_UNICODE) . "\n");
                $escritas++;
            }
        });

        fclose($handle);

        // Reler antes de apagar. Sem esta confirmação, um disco cheio ou uma
        // permissão em falta apagavam a trilha e deixavam um ficheiro truncado.
        $linhasNoFicheiro = 0;
        $leitura = fopen($caminho, 'r');
        while (fgets($leitura) !== false) {
            $linhasNoFicheiro++;
        }
        fclose($leitura);

        if ($linhasNoFicheiro !== $escritas || $escritas !== $quantas) {
            $this->error("    ficheiro incompleto ({$linhasNoFicheiro}/{$quantas}) — nada foi apagado");

            return 0;
        }

        DB::transaction(function () use ($base, $tenantId, $primeira, $ultima, $ficheiro, $quantas) {
            // O marco entra ANTES da remoção: se algo falhar a seguir, fica um
            // marco a mais (inofensivo, e visível) em vez de um buraco mudo.
            AuditTrail::registar([
                'tenant_id'       => $tenantId,
                'event'           => 'audit.archived',
                'auditable_type'  => AuditTrail::class,
                'auditable_label' => basename($ficheiro),
                'actor_name'      => 'consola',
                'actor_type'      => 'console',
                'channel'         => 'console',
                'route'           => 'artisan audit:archive',
                'metadata'        => [
                    'ficheiro'        => $ficheiro,
                    'linhas'          => $quantas,
                    'sequencia_de'    => $primeira->sequence,
                    'sequencia_ate'   => $ultima->sequence,
                    'hash_da_ultima'  => $ultima->hash,
                    'ate'             => $ultima->created_at?->toDateTimeString(),
                ],
            ]);

            // Remoção em massa pelo query builder: não passa pelos eventos do
            // modelo, portanto o guarda append-only não a bloqueia. É o único
            // caminho autorizado a apagar, e só depois do ficheiro confirmado.
            $base->delete();
        });

        $this->line("    → storage/app/{$ficheiro}");

        return $quantas;
    }
}
