<?php

namespace App\Console\Commands\Campanha;

use App\Services\Campanha\ResultadosDaCampanha;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * RECONCILIAR A CAMPANHA: eventos Meta vs inscrições vs empresas vs 1.º uso,
 * com os testes de fora. Serve para perceber «a Meta diz 4, eu confirmo 2».
 *
 * Só lê. Não envia nada para a Meta nem cria dados.
 */
class ConversoesDaCampanha extends Command
{
    protected $signature = 'campanha:conversoes
        {--desde= : data inicial (Y-m-d); por omissão há 30 dias}
        {--ate= : data final (Y-m-d); por omissão agora}
        {--tudo : inclui os testes na lista (continuam fora das contas comerciais)}
        {--json : devolve tudo em JSON}';

    protected $description = 'Separa eventos Meta, inscrições únicas, empresas criadas e primeira utilização, excluindo testes';

    public function handle(): int
    {
        $desde = $this->option('desde') ? Carbon::parse($this->option('desde'))->startOfDay() : now()->subDays(30);
        $ate = $this->option('ate') ? Carbon::parse($this->option('ate'))->endOfDay() : now();

        $r = ResultadosDaCampanha::resumo($desde, $ate);

        if ($this->option('json')) {
            $this->line((string) json_encode($r + ['linhas' => $r['linhas']->all()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info(sprintf('Campanha de %s a %s', $r['periodo']['desde'], $r['periodo']['ate']));
        $this->newLine();

        $linhas = $this->option('tudo') ? $r['linhas'] : $r['linhas']->where('teste', false);
        $this->table(
            ['Empresa', 'Email', 'Plano', 'Estado', 'Origem', 'Quando', 'Já entrou', 'Eventos', 'Teste'],
            $linhas->map(fn ($l) => [
                $l['empresa'] ?? '—',
                $l['email'] ?? '—',
                $l['plano'] ?? '—',
                $l['estado'] ?? '—',
                $l['utm_source'] ?? '—',
                optional($l['quando'])->format('d/m/Y H:i') ?? '—',
                $l['primeira_utilizacao'] ? 'sim' : 'não',
                $l['eventos_gravados'],
                $l['teste'] ? 'TESTE' : '',
            ])->all(),
        );

        $this->newLine();
        $this->line('<comment>Resultados (testes excluídos):</comment>');
        $this->table(['Medida', 'Valor'], [
            ['Eventos CompleteRegistration disparados', $r['eventos_completeregistration']],
            ['Inscrições únicas (comerciais)', $r['inscricoes_unicas']],
            ['Empresas criadas', $r['empresas_criadas']],
            ['Primeira utilização (já entraram)', $r['primeira_utilizacao']],
            ['Testes excluídos', $r['testes_excluidos']],
        ]);

        $this->newLine();
        $this->line('Se a Meta mostrar MAIS eventos do que os disparados aqui, a diferença é da');
        $this->line('atribuição da Meta (visualizações, janelas de conversão) ou de outra fonte de');
        $this->line('pixel — não de duplicação nossa. Marque os testes em config/campanha.php.');

        return self::SUCCESS;
    }
}
