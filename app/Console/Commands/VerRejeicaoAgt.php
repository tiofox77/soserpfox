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
        {--simular : re-mapeia o documento agora (DocumentMapper) e mostra o que SERIA enviado — não envia nada}
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

                // Linhas que ENVIÁMOS, com o imposto por linha vs. o que a AGT
                // apura (base × %, arredondado normalmente). É aqui que se vê o
                // E70 ao cêntimo.
                $linhas = $this->encontraLinhas(is_array($s->request_payload) ? $s->request_payload : []);
                if ($linhas) {
                    $this->line('     linhas enviadas (base × % → apurado vs enviado):');
                    foreach ($linhas as $ln) {
                        $base = (float) ($ln['creditAmount'] ?? $ln['debitAmount'] ?? 0);
                        foreach (($ln['taxes'] ?? []) as $t) {
                            $pct = (float) ($t['taxPercentage'] ?? 0);
                            $enviado = (float) ($t['taxContribution'] ?? 0);
                            $apurado = round($base * $pct / 100, 2);
                            $marca = (abs($apurado - $enviado) >= 0.005) ? '  <<< DIFERE' : '';
                            $this->line(sprintf(
                                '        linha %-3s base=%-16s %%=%-6s apurado=%-16s enviado=%-16s%s',
                                (string) ($ln['lineNumber'] ?? '?'),
                                number_format($base, 2, '.', ''),
                                rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.'),
                                number_format($apurado, 2, '.', ''),
                                number_format($enviado, 2, '.', ''),
                                $marca
                            ));
                        }
                    }
                }

                // Dry-run: re-mapeia o documento AGORA e mostra o que seria
                // enviado com o código actual — para confirmar a correcção sem
                // comunicar nada à AGT.
                if ($this->option('simular') && $s->document_type && $s->document_id) {
                    try {
                        $doc = $s->document_type::withoutGlobalScopes()->find($s->document_id);
                        if ($doc) {
                            $novo = (new \App\Services\AGT\DocumentMapper())->map($doc);
                            $this->line('     SIMULAÇÃO (o que seria enviado agora):');

                            // O envelope também: depois de a AGT recusar o
                            // schema 1.2 (2026-09-02), a pergunta é «que
                            // versão SAIRIA agora?» — sem enviar nada.
                            $definicoes = \App\Models\Invoicing\InvoicingSettings::forTenant((int) $s->tenant_id);
                            $versao = $definicoes->agt_schema_version
                                ?? \App\Services\AGT\AGTPayloadBuilder::SCHEMA_VERSION;
                            $origem = $definicoes->agt_schema_version ? 'fixada nesta empresa' : 'omissão da plataforma';
                            $software = (new \App\Services\AGT\AGTPayloadBuilder($definicoes))->softwareInfo();
                            $detalhe = $software['softwareInfoDetail'] ?? $software;
                            $this->line("        schemaVersion={$versao} ({$origem})  software="
                                .($detalhe['productId'] ?? '?')
                                .' v'.($detalhe['productVersion'] ?? '?')
                                .' cert='.($detalhe['softwareValidationNumber'] ?? '?'));
                            foreach (($novo['lines'] ?? []) as $ln) {
                                $base = (float) ($ln['creditAmount'] ?? $ln['debitAmount'] ?? 0);
                                foreach (($ln['taxes'] ?? []) as $t) {
                                    $pct = (float) ($t['taxPercentage'] ?? 0);
                                    $env = (float) ($t['taxContribution'] ?? 0);
                                    $ap  = round($base * $pct / 100, 2);
                                    $ceil = ceil($base * $pct) / 100 === $env; // já vem ceil?
                                    $this->line(sprintf(
                                        '        linha %-3s base=%-16s %%=%-6s apurado(round)=%-16s enviaria=%-16s',
                                        (string) ($ln['lineNumber'] ?? '?'),
                                        number_format($base, 2, '.', ''),
                                        rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.'),
                                        number_format($ap, 2, '.', ''),
                                        number_format($env, 2, '.', '')
                                    ));
                                }
                            }
                            $t = $novo['documentTotals'] ?? [];
                            $this->line(sprintf('        totais: net=%s taxPayable=%s gross=%s',
                                number_format((float) ($t['netTotal'] ?? 0), 2, '.', ''),
                                number_format((float) ($t['taxPayable'] ?? 0), 2, '.', ''),
                                number_format((float) ($t['grossTotal'] ?? 0), 2, '.', '')
                            ));
                        }
                    } catch (\Throwable $e) {
                        $this->line('     SIMULAÇÃO falhou: ' . $e->getMessage());
                    }
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

    /** Procura recursivamente o primeiro array 'lines' no payload enviado. */
    private function encontraLinhas(array $payload): array
    {
        if (isset($payload['lines']) && is_array($payload['lines'])) {
            return $payload['lines'];
        }
        foreach ($payload as $v) {
            if (is_array($v)) {
                $r = $this->encontraLinhas($v);
                if ($r) {
                    return $r;
                }
            }
        }
        return [];
    }
}
