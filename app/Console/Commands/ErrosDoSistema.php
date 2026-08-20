<?php

namespace App\Console\Commands;

use App\Models\ErroDoSistema;
use App\Services\Agent\NotificarOpenClaw;
use Illuminate\Console\Command;

/**
 * Os problemas do sistema, para quem não é o agente.
 *
 * O agente externo lê isto por `GET /api/agent/v1/logs/errors`. Isto é a
 * mesma informação para quem está à frente de uma consola — e é o que permite
 * confirmar, em produção, que a captura está mesmo a acontecer.
 *
 * Só lê, excepto quando se lhe pede explicitamente para fechar ou empurrar.
 */
class ErrosDoSistema extends Command
{
    protected $signature = 'erros:ver
        {--todos : inclui os já resolvidos}
        {--nivel= : error|critical|alert|emergency}
        {--horas= : só o que aconteceu nas últimas N horas}
        {--detalhe= : mostra o contexto completo de um id}
        {--resolver= : marca um id como resolvido}
        {--empurrar : envia os que faltam para o webhook do agente}
        {--teste : escreve UM erro de mentira e confirma que foi apanhado}';

    protected $description = 'Vê os erros do sistema, agrupados por problema';

    public function handle(): int
    {
        if ($id = $this->option('detalhe')) {
            return $this->detalhe((int) $id);
        }

        if ($id = $this->option('resolver')) {
            return $this->resolver((int) $id);
        }

        if ($this->option('teste')) {
            return $this->teste();
        }

        if ($this->option('empurrar')) {
            $r = app(NotificarOpenClaw::class)->empurrar();
            $this->info("Enviados: {$r['enviados']}. " . ($r['motivo'] ? "({$r['motivo']})" : ''));

            return self::SUCCESS;
        }

        $q = ErroDoSistema::query()->orderByDesc('ultima_vez');

        if (!$this->option('todos')) {
            $q->whereNull('resolvido_em');
        }

        if ($n = $this->option('nivel')) {
            $q->where('nivel', $n);
        }

        if ($h = $this->option('horas')) {
            $q->where('ultima_vez', '>=', now()->subHours((int) $h));
        }

        $erros = $q->limit(40)->get();

        if ($erros->isEmpty()) {
            $this->info('Nada a assinalar.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'nível', 'x', 'última vez', 'onde', 'mensagem'],
            $erros->map(fn (ErroDoSistema $e) => [
                $e->id,
                $e->nivel,
                $e->ocorrencias,
                $e->ultima_vez?->diffForHumans(),
                $e->ficheiro ? mb_strimwidth("{$e->ficheiro}:{$e->linha}", 0, 38, '…') : '—',
                mb_strimwidth($e->mensagem, 0, 60, '…'),
            ])->all()
        );

        $abertos = ErroDoSistema::abertos()->count();
        $porAvisar = ErroDoSistema::abertos()->whereNull('notificado_em')->count();

        $this->info("{$abertos} problema(s) por resolver; {$porAvisar} por comunicar ao agente.");
        $this->line('Detalhe de um: php artisan erros:ver --detalhe=<id>');

        return self::SUCCESS;
    }

    /**
     * Prova que a corrente toda está ligada.
     *
     * Escreve um erro de mentira e vai ver se ele chegou à tabela. Serve para
     * responder à pergunta que de outro modo não tem resposta: "está mesmo a
     * apanhar, ou o tap está pendurado num canal de log que esta instalação
     * não usa?". Sem isto, a diferença entre "não houve erros" e "a captura
     * está morta" é invisível — e são a mesma coisa vista de fora.
     */
    private function teste(): int
    {
        $marca = 'ensaio da captura de erros ' . bin2hex(random_bytes(4));

        \Log::error($marca, ['origem' => 'erros:ver --teste']);

        $erro = ErroDoSistema::where('mensagem', $marca)->first();

        if (!$erro) {
            $this->error('A captura NÃO está a funcionar: o erro foi escrito no log e não chegou à tabela.');
            $this->line('Ver o `tap` em config/logging.php e `agent.erros.capturar` em config/agent.php.');

            return self::FAILURE;
        }

        $this->info("A captura está viva. Erro de ensaio gravado como #{$erro->id}.");

        // Não deixar lixo: o ensaio fecha-se a si próprio.
        $erro->forceFill([
            'resolvido_em'  => now(),
            'resolvido_por' => 'ensaio',
            'notificado_em' => now(),
            'nota'          => 'erro de ensaio, criado por erros:ver --teste',
        ])->save();

        $this->line('Marcado como resolvido — não fica a pedir atenção.');

        return self::SUCCESS;
    }

    private function detalhe(int $id): int
    {
        $e = ErroDoSistema::find($id);

        if (!$e) {
            $this->error("Não há erro #{$id}.");

            return self::FAILURE;
        }

        $this->line("<info>#{$e->id}</info>  [{$e->nivel}]  {$e->ocorrencias} ocorrência(s)");
        $this->line("Primeira: {$e->primeira_vez}   Última: {$e->ultima_vez}");
        $this->line('Onde: ' . ($e->ficheiro ? "{$e->ficheiro}:{$e->linha}" : '—'));
        $this->line('URL: ' . ($e->url ?: '—') . '   Empresa: ' . ($e->tenant_id ?: '—'));
        $this->newLine();
        $this->line("<comment>{$e->mensagem}</comment>");
        $this->newLine();

        // Os segredos já foram substituídos por [oculto] antes de a linha ser
        // gravada — ver App\Services\Agent\RegistoDeErros::redigir.
        $this->line(json_encode($e->contexto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function resolver(int $id): int
    {
        $e = ErroDoSistema::find($id);

        if (!$e) {
            $this->error("Não há erro #{$id}.");

            return self::FAILURE;
        }

        $e->forceFill([
            'resolvido_em'  => now(),
            'resolvido_por' => 'consola',
            'notificado_em' => $e->notificado_em ?? now(),
        ])->save();

        // Se voltar a acontecer, o RegistoDeErros reabre-o sozinho.
        $this->info("#{$id} marcado como resolvido. Se voltar a acontecer, reabre.");

        return self::SUCCESS;
    }
}
