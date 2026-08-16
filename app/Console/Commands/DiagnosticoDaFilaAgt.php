<?php

namespace App\Console\Commands;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * De quem sao as tarefas paradas na fila do AGT, e o que isso quer dizer.
 *
 * SO LE. Nao despacha, nao apaga, nao reenfileira nada.
 *
 * A pergunta a que responde: as tarefas presas em `agt-polling` sao de
 * empresas com submissao automatica LIGADA ou DESLIGADA? A resposta importa
 * porque muda o que se deve fazer a seguir — se forem de empresas com o
 * automatico ligado, ha documentos a submeter que ninguem esta a confirmar; se
 * forem de submissoes manuais, o estrago e menor mas o resultado continua por
 * saber.
 *
 * O polling nao depende do automatico: e enfileirado pelo AGTService a seguir a
 * QUALQUER submissao aceite pela AGT (AGTService.php:241), venha ela do
 * automatico ou de alguem que carregou no botao. E por isso que vale a pena
 * medir, em vez de deduzir.
 *
 *   php artisan agt:diagnostico-fila
 */
class DiagnosticoDaFilaAgt extends Command
{
    protected $signature = 'agt:diagnostico-fila';

    protected $description = 'Mostra de que empresas sao as tarefas paradas na fila do AGT (só lê)';

    public function handle(): int
    {
        if (!$this->temTabelaDeFila()) {
            $this->error('Não há tabela `jobs`: a fila não é a da base de dados.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line(str_repeat('=', 66));
        $this->info(' FILA DO AGT — o que está parado, e de quem');
        $this->line(str_repeat('=', 66));

        $porFila = DB::table('jobs')
            ->selectRaw('queue, COUNT(*) total, MIN(created_at) mais_antiga, MAX(created_at) mais_recente')
            ->groupBy('queue')
            ->orderByDesc('total')
            ->get();

        // Sem sair já quando a fila está vazia. Uma fila vazia não quer dizer
        // que esteja tudo bem: pode não haver nada à espera E haver submissões
        // sem resposta, que é justamente o que interessa saber. Sair aqui
        // deixava a pergunta principal por responder.
        $this->newLine();

        if ($porFila->isEmpty()) {
            $this->info('  ✓ Não há nada à espera na fila.');
        } else {
            $this->line('  Por fila:');

            foreach ($porFila as $f) {
                $this->line(sprintf(
                    '    %-18s %5d tarefa(s)   da mais antiga %s à mais recente %s',
                    $f->queue,
                    $f->total,
                    $this->data($f->mais_antiga),
                    $this->data($f->mais_recente)
                ));
            }
        }

        $this->porEmpresa();
        $this->submissoesPorConfirmar();
        $this->trabalhador();

        return self::SUCCESS;
    }

    /** A conta que interessa: quem tem tarefas presas, e com o automático como? */
    private function porEmpresa(): void
    {
        $linhas = DB::table('jobs')->where('queue', 'agt-polling')->pluck('payload');

        if ($linhas->isEmpty()) {
            $this->info('  ✓ Nada parado em agt-polling.');

            return;
        }

        $porEmpresa = [];

        foreach ($linhas as $payload) {
            // O payload é JSON, e o objecto serializado vive lá dentro numa
            // string — com as aspas escapadas. Ler o JSON primeiro evita ter de
            // adivinhar quantas barras invertidas há pelo meio.
            $comando = json_decode((string) $payload, true)['data']['command'] ?? '';

            // Por expressão regular e não por unserialize: desserializar
            // executa código de uma classe que pode ter mudado desde que a
            // tarefa foi criada, e num diagnóstico de leitura isso é risco a
            // troco de nada.
            if (preg_match('/s:8:"tenantId";i:(\d+);/', $comando, $m)) {
                $id = (int) $m[1];
                $porEmpresa[$id] = ($porEmpresa[$id] ?? 0) + 1;
            } else {
                $porEmpresa[0] = ($porEmpresa[0] ?? 0) + 1;
            }
        }

        arsort($porEmpresa);

        $this->newLine();
        $this->line('  Tarefas de agt-polling, por empresa:');
        $this->newLine();
        $this->line(sprintf('    %-6s %-34s %8s  %s', 'ID', 'EMPRESA', 'PARADAS', 'SUBMISSÃO AUTOMÁTICA'));
        $this->line('    ' . str_repeat('-', 74));

        $ligado = 0;
        $desligado = 0;

        foreach ($porEmpresa as $tenantId => $quantas) {
            if ($tenantId === 0) {
                $this->warn(sprintf('    %-6s %-34s %8d  %s', '?', '(não foi possível ler a empresa)', $quantas, '—'));
                continue;
            }

            $empresa = Tenant::find($tenantId);
            $auto = $this->automaticoLigado($tenantId);

            $auto ? $ligado += $quantas : $desligado += $quantas;

            $this->line(sprintf(
                '    %-6d %-34s %8d  %s',
                $tenantId,
                mb_strimwidth((string) ($empresa->name ?? '(empresa apagada)'), 0, 34, '…'),
                $quantas,
                $auto ? 'LIGADA' : 'desligada'
            ));
        }

        $this->newLine();
        $this->line(sprintf('    De empresas com o automático LIGADO:    %d tarefa(s)', $ligado));
        $this->line(sprintf('    De empresas com o automático desligado: %d tarefa(s)', $desligado));

        if ($ligado > 0) {
            $this->newLine();
            $this->warn('    O automático está ligado nalgumas destas empresas: há documentos');
            $this->warn('    submetidos à AGT cujo resultado ninguém está a confirmar.');
        }
    }

    /** Quantas submissões ficaram à espera de resposta, que é o efeito visível disto. */
    private function submissoesPorConfirmar(): void
    {
        $this->newLine();

        if (!\Illuminate\Support\Facades\Schema::hasTable('agt_submissions')) {
            $this->warn('  Não existe a tabela agt_submissions.');

            return;
        }

        $porEstado = DB::table('agt_submissions')
            ->selectRaw('status, COUNT(*) total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        if ($porEstado->isEmpty()) {
            // Dito e não omitido: "nunca se submeteu nada" e "a fila está a dar
            // conta do recado" parecem iguais quando não se escreve nada, e são
            // conclusões opostas.
            $this->info('  Nunca foi submetido nenhum documento à AGT (tabela vazia).');

            return;
        }

        $this->line('  Submissões à AGT, por estado:');

        foreach ($porEstado as $e) {
            $this->line(sprintf('    %-16s %6d', $e->status ?? '(sem estado)', $e->total));
        }

        // `pending` e `submitted` são os estados por fechar: a AGT recebeu (ou
        // nem chegou a receber) e o resultado nunca foi confirmado. São estes
        // que o polling existe para resolver, e por isso são estes que dizem se
        // a fila parada deixou alguma coisa por acabar.
        $abertas = DB::table('agt_submissions')
            ->whereIn('status', ['pending', 'submitted'])
            ->selectRaw('tenant_id, COUNT(*) total, MIN(created_at) mais_antiga')
            ->groupBy('tenant_id')
            ->orderByDesc('total')
            ->get();

        $this->newLine();

        if ($abertas->isEmpty()) {
            $this->info('  ✓ Não há submissões por confirmar. Todas têm resposta da AGT.');

            return;
        }

        $this->warn('  Submissões SEM resposta confirmada (é isto que o polling faz):');
        $this->newLine();
        $this->line(sprintf('    %-6s %-34s %8s  %s', 'ID', 'EMPRESA', 'ABERTAS', 'SUBMISSÃO AUTOMÁTICA'));
        $this->line('    ' . str_repeat('-', 74));

        foreach ($abertas as $a) {
            $empresa = Tenant::find($a->tenant_id);

            $this->line(sprintf(
                '    %-6d %-34s %8d  %s',
                (int) $a->tenant_id,
                mb_strimwidth((string) ($empresa->name ?? '(empresa apagada)'), 0, 34, '…'),
                $a->total,
                $this->automaticoLigado((int) $a->tenant_id) ? 'LIGADA' : 'desligada'
            ));
        }
    }

    /** Se ninguém consome a fila, o resto do diagnóstico explica-se sozinho. */
    private function trabalhador(): void
    {
        $this->newLine();
        $this->line('  Fila configurada: ' . config('queue.default'));

        $presas = DB::table('jobs')->where('attempts', '>', 0)->count();
        $total  = DB::table('jobs')->count();

        $this->line(sprintf('    tarefas já tentadas alguma vez: %d de %d', $presas, $total));

        if ($total > 0 && $presas === 0) {
            $this->newLine();
            $this->warn('    NENHUMA foi sequer tentada. Isso não é uma falha de rede nem da AGT:');
            $this->warn('    é não haver trabalhador nenhum a consumir a fila (queue:work).');
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) {
            $falhadas = DB::table('failed_jobs')->count();
            $this->line(sprintf('    tarefas em failed_jobs: %d', $falhadas));
        }
    }

    private function automaticoLigado(int $tenantId): bool
    {
        try {
            return (bool) (InvoicingSettings::where('tenant_id', $tenantId)->value('agt_auto_submit'));
        } catch (\Throwable) {
            return false;
        }
    }

    private function data($valor): string
    {
        if (!$valor) {
            return '—';
        }

        // A coluna `created_at` da tabela `jobs` é um inteiro (timestamp Unix),
        // e não uma data — formatá-la como data devolvia 1970.
        return is_numeric($valor)
            ? date('d/m/Y', (int) $valor)
            : (string) $valor;
    }

    private function temTabelaDeFila(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('jobs');
    }
}
