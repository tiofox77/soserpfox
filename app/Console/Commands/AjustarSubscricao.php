<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\CicloDeFacturacao;
use Illuminate\Console\Command;

/**
 * Acerta o período da subscrição VIVA de uma empresa.
 *
 * Existe para o caso concreto de 2026-09-02: a Free Dation ficou com um anual
 * de 14 meses (12 + 2 de oferta) quando o acordo era sem oferta — e o dono
 * quer «só 364 dias». Mas serve para qualquer acerto de período, e por isso
 * é genérico: dias a contar de hoje, ou tirar a oferta ao período actual.
 *
 * A SECO por omissão: mostra as subscrições da empresa (todas, vivas e
 * mortas — é aqui que se vê uma empresa com duas vivas) e o que faria. Só
 * escreve com --aplicar. E nunca inventa: se houver mais do que uma viva,
 * recusa-se e manda escolher pelo id.
 */
class AjustarSubscricao extends Command
{
    protected $signature = 'subscricao:ajustar
        {--tenant= : id da empresa}
        {--id= : id da subscrição, quando a empresa tem mais do que uma viva}
        {--dias= : o período passa a acabar daqui a N dias}
        {--sem-oferta : tira os 2 meses de oferta do anual (fim = início + 12 meses) e grava com_oferta=false}
        {--valor= : preço acordado para o período (o que a renovação repete)}
        {--ciclo= : monthly|quarterly|semiannual|yearly}
        {--activar : deixa de ser período de teste e passa a subscrição a sério}
        {--aplicar : escreve de facto}';

    protected $description = 'Acerta o fim do período da subscrição viva de uma empresa (a seco por omissão)';

    public function handle(): int
    {
        $tenant = Tenant::find((int) $this->option('tenant'));

        if (! $tenant) {
            $this->error('Empresa não encontrada. Use --tenant=ID.');

            return self::FAILURE;
        }

        $this->line("Empresa: #{$tenant->id}  {$tenant->name}");

        // Documentos fiscais: quanto já gastou do que o plano lhe dá.
        $tecto = $tenant->limiteDeDocumentos();
        $emitidos = $tenant->documentosEmitidos();
        $this->line('  Documentos fiscais emitidos: '.number_format($emitidos, 0, ',', '.')
            .($tecto === null ? '  (sem tecto)' : '  de '.number_format($tecto, 0, ',', '.')
                .($emitidos >= $tecto ? '  <fg=red>ESGOTADO</>' : '')));

        $doPlano = $tenant->activeSubscription?->plan?->max_documents;
        $this->line('  Tecto do plano: '.($doPlano === null ? 'nenhum' : number_format((int) $doPlano, 0, ',', '.'))
            .'   |   gravado nesta subscrição: '
            .($tenant->activeSubscription?->max_documentos === null ? 'nenhum' : (string) $tenant->activeSubscription->max_documentos));

        $this->newLine();

        $todas = Subscription::where('tenant_id', $tenant->id)->with('plan')->orderByDesc('id')->get();

        if ($todas->isEmpty()) {
            $this->comment('Esta empresa não tem subscrição nenhuma.');

            return self::SUCCESS;
        }

        $this->line('Subscrições (todas):');
        foreach ($todas as $s) {
            $this->line(sprintf(
                '  #%-5d %-14s %-10s %-12s oferta=%s dias=%s  %s → %s  %s',
                $s->id,
                $s->plan?->name ?? '(sem plano)',
                $s->status,
                $s->billing_cycle,
                ($s->com_oferta ?? true) ? 'sim' : 'não',
                $s->dias_personalizados ?: '—',
                $s->current_period_start?->format('d/m/Y') ?? '—',
                $s->current_period_end?->format('d/m/Y') ?? '—',
                $s->current_period_end ? 'faltam '.max(0, (int) now()->diffInDays($s->current_period_end, false)).'d' : ''
            ));
        }
        $this->newLine();

        $vivas = $todas->filter(fn ($s) => in_array($s->status, ['active', 'trial'], true)
            && ($s->current_period_end === null || $s->current_period_end->isFuture()));

        if ($vivas->count() > 1) {
            $this->warn('Há '.$vivas->count().' subscrições VIVAS — é por isso que a lista e o modal discordam.');
        }

        $alvo = $this->option('id')
            ? $todas->firstWhere('id', (int) $this->option('id'))
            : ($vivas->count() === 1 ? $vivas->first() : null);

        if (! $alvo) {
            $this->error($vivas->count() > 1
                ? 'Escolha qual pelo --id=… (as vivas: '.$vivas->pluck('id')->implode(', ').').'
                : 'Não há subscrição viva para acertar.');

            return self::FAILURE;
        }

        $dias = (int) $this->option('dias');
        $semOferta = (bool) $this->option('sem-oferta');

        if ($dias <= 0 && ! $semOferta) {
            $this->comment('Nada a fazer: indique --dias=N ou --sem-oferta.');

            return self::SUCCESS;
        }

        // Um só «agora»: o fim e a contagem saem do mesmo relógio, senão
        // «364» lê-se 363 pelos milissegundos entre os dois.
        $hoje = now();

        if ($dias > 0) {
            // «N dias» é ATÉ AO FIM do N-ésimo dia — quem pede 364 dias quer
            // ler «faltam 364», não «363d 23h».
            $novoFim = $hoje->copy()->addDays($dias)->endOfDay();
        } else {
            // Sem oferta: o período actual passa a valer 12 meses a contar do
            // início que já tinha — não se reinicia o relógio.
            $inicio = $alvo->current_period_start?->copy() ?? $hoje->copy();
            $novoFim = CicloDeFacturacao::fim($inicio, $alvo->billing_cycle, false);
        }

        $this->line("Alvo: subscrição #{$alvo->id} ({$alvo->plan?->name}, {$alvo->billing_cycle})");
        $this->line('  fim actual: '.($alvo->current_period_end?->format('d/m/Y') ?? '—')
            .'  →  novo fim: '.$novoFim->format('d/m/Y')
            .'  (faltam '.max(0, (int) $hoje->diffInDays($novoFim, false)).' dias)');

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->comment('A SECO. Corra com --aplicar para gravar.');

            return self::SUCCESS;
        }

        $mudancas = [
            'current_period_end' => $novoFim,
            'ends_at' => $novoFim,
            // O acordo fica escrito: a renovação repete-o em vez de voltar aos
            // 14 meses ou de esquecer os dias à medida.
            'com_oferta' => $semOferta ? false : ($alvo->com_oferta ?? true),
            'dias_personalizados' => $dias > 0 ? $dias : $alvo->dias_personalizados,
        ];

        /*
         * O PREÇO ACORDADO E O CICLO FAZEM PARTE DO ACORDO.
         *
         * Uma empresa que fecha por 150 mil ao ano não paga a tabela: o
         * valor combinado tem de ficar ESCRITO na subscrição, senão a
         * renovação volta ao preço de lista e alguém tem de se lembrar de
         * corrigir à mão todos os anos.
         */
        if ($this->option('valor') !== null) {
            $mudancas['amount'] = (float) str_replace(',', '.', (string) $this->option('valor'));
        }

        if ($ciclo = $this->option('ciclo')) {
            $mudancas['billing_cycle'] = $ciclo;
        }

        /*
         * DEIXAR DE SER TESTE.
         *
         * O ecrã de entrada lê o `trial_ends_at` para a faixa «Período de
         * Teste Ativo». Enquanto essa data lá estiver, o cliente vê que
         * expira daí a duas semanas — por mais que o fim do período diga
         * outra coisa. Quem paga não está em teste.
         */
        if ($this->option('activar')) {
            $mudancas['status'] = 'active';
            $mudancas['trial_ends_at'] = null;
        }

        $alvo->update($mudancas);

        $this->info("Gravado: subscrição #{$alvo->id} acaba a {$novoFim->format('d/m/Y')}.");
        foreach (['amount' => 'preço', 'billing_cycle' => 'ciclo', 'status' => 'estado'] as $campo => $rotulo) {
            if (array_key_exists($campo, $mudancas)) {
                $this->line("  {$rotulo}: " . ($mudancas[$campo] ?? '—'));
            }
        }

        return self::SUCCESS;
    }
}
