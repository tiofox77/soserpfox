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

    protected $description = 'O percurso da inscrição: cliques, formulários iniciados, empresas, testes activados e primeira utilização, sem dados pessoais e sem testes';

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

        // Sem dados pessoais: a empresa pelo número, e nada de email nem NIF.
        $linhas = $this->option('tudo') ? $r['linhas'] : $r['linhas']->where('teste', false);
        $this->table(
            ['Empresa', 'Origem', 'Campanha', 'Módulo', 'Plano', 'Estado', 'Quando', 'Teste activado', '1.ª utilização', 'Conta de teste'],
            $linhas->map(fn ($l) => [
                '#' . $l['tenant_id'],
                $l['origem'] ?? '—',
                $l['utm_campaign'] ?? '—',
                $l['modulo'] ?? '—',
                $l['plano'] ?? '—',
                $l['estado'] ?? '—',
                optional($l['quando'])->format('d/m/Y H:i') ?? '—',
                $l['teste_activado'] ? 'sim' : 'não',
                $l['primeira_utilizacao_em'] ? $l['primeira_utilizacao_em']->format('d/m/Y H:i') : 'ainda não',
                $l['teste'] ? 'TESTE' : '',
            ])->all(),
        );

        $this->newLine();
        $this->line('<comment>O percurso (testes excluídos a partir das empresas):</comment>');
        $this->table(['Passo', 'Valor'], [
            ['Cliques para registo (visitantes)', $r['cliques_para_registo']],
            ['Formulários iniciados', $r['formularios_iniciados']],
            ['Empresas criadas', $r['empresas_criadas']],
            ['Testes activados', $r['testes_activados']],
            ['Primeira utilização (criou trabalho)', $r['primeira_utilizacao']],
            ['Conversões gravadas pelo servidor', $r['eventos_completeregistration']],
            ['Contas de teste excluídas', $r['testes_excluidos']],
        ]);

        $this->newLine();
        $this->line('Se a Meta mostrar MAIS eventos do que os disparados aqui, a diferença é da');
        $this->line('atribuição da Meta (visualizações, janelas de conversão) ou de outra fonte de');
        $this->line('pixel — não de duplicação nossa. Marque os testes em config/campanha.php.');

        return self::SUCCESS;
    }
}
