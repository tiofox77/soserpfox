<?php

namespace App\Console\Commands;

use App\Models\AGT\AGTSubmission;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Porque é que a AGT recusou este documento. SÓ LÊ.
 *
 * Mostra, para as empresas que batem com --empresa/--nif, as submissões à AGT
 * com o estado, o código e a mensagem de erro, o response_payload e a última
 * resposta crua registada no log de comunicação — que é onde a AGT diz, por
 * palavras dela, o que rejeitou (E39, E-xxx, etc.).
 *
 *   php artisan agt:ver-rejeicao --empresa=Free
 *   php artisan agt:ver-rejeicao --nif=5000000000 --doc=000001
 *   php artisan agt:ver-rejeicao --empresa=Free --todos   (não só as rejeitadas)
 */
class VerRejeicaoAgt extends Command
{
    protected $signature = 'agt:ver-rejeicao
        {--empresa= : parte do nome da empresa (LIKE, sem espaços por causa da rota)}
        {--nif= : NIF exacto da empresa}
        {--doc= : parte do número do documento (LIKE)}
        {--todos : mostra todos os estados, não só as rejeitadas}
        {--n=10 : quantas submissões por empresa}';

    protected $description = 'Mostra a razão da recusa da AGT por empresa/documento (só lê)';

    public function handle(): int
    {
        if (!Schema::hasTable('agt_submissions')) {
            $this->error('Não existe a tabela agt_submissions.');
            return self::FAILURE;
        }

        $empresas = Tenant::query()
            ->when($this->option('empresa'), fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($this->option('nif'), fn ($q, $v) => $q->where('nif', $v))
            ->orderBy('name')
            ->get();

        if ($empresas->isEmpty()) {
            $this->warn('Nenhuma empresa bate com o filtro dado.');
            return self::SUCCESS;
        }

        $n = max(1, (int) $this->option('n'));

        foreach ($empresas as $emp) {
            $this->newLine();
            $this->line(str_repeat('=', 72));
            $this->info(sprintf(' EMPRESA #%d — %s (NIF %s)', $emp->id, $emp->name, $emp->nif ?? '—'));
            $this->line(str_repeat('=', 72));

            $q = AGTSubmission::withoutGlobalScopes()->where('tenant_id', $emp->id);
            if (!$this->option('todos')) {
                $q->where('status', AGTSubmission::STATUS_REJECTED);
            }
            if ($doc = $this->option('doc')) {
                $q->where('document_number', 'like', "%{$doc}%");
            }
            $subs = $q->orderByDesc('id')->limit($n)->get();

            if ($subs->isEmpty()) {
                $this->line('  (sem submissões' . ($this->option('todos') ? '' : ' rejeitadas') . ')');
                continue;
            }

            foreach ($subs as $s) {
                $this->newLine();
                $this->line(sprintf('  ── #%d  %s  [%s]  tentativas %s  ambiente %s',
                    $s->id,
                    $s->document_number ?: '(sem nº)',
                    strtoupper((string) $s->status),
                    (string) ($s->retry_count ?? 0),
                    $s->agt_environment ?? '—'
                ));
                $this->line('     tipo: ' . ($s->document_type_code ?? '—') . '   doc: ' . ($s->document_type ?? '—') . '#' . ($s->document_id ?? '—'));
                if ($s->agt_reference) {
                    $this->line('     agt_reference: ' . $s->agt_reference);
                }
                $this->line('     error_code:    ' . ($s->error_code ?? '—'));
                $this->line('     error_message: ' . ($s->error_message ?? '—'));

                if (!empty($s->response_payload)) {
                    $this->line('     response_payload:');
                    $this->line($this->indenta(json_encode($s->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
                }

                // A última resposta crua da AGT — a fonte primária do motivo.
                if (Schema::hasTable('agt_communication_logs')) {
                    $log = DB::table('agt_communication_logs')
                        ->where('submission_id', $s->id)
                        ->orderByDesc('id')
                        ->first();
                    if ($log) {
                        $this->line('     última resposta AGT (HTTP ' . ($log->response_status ?? '—') . ', log #' . $log->id . '):');
                        $body = $log->response_body;
                        if (is_string($body)) {
                            $decoded = json_decode($body, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $body = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                        }
                        $this->line($this->indenta(mb_substr((string) $body, 0, 4000)));
                        if ($log->error_message) {
                            $this->line('     log.error_message: ' . $log->error_message);
                        }
                    }
                }
            }
        }

        $this->newLine();
        return self::SUCCESS;
    }

    private function indenta(string $txt): string
    {
        return collect(explode("\n", $txt))->map(fn ($l) => '        ' . $l)->implode("\n");
    }
}
