<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Põe (ou estende) o período de teste da subscrição de uma empresa.
 *
 * SIMULAÇÃO POR OMISSÃO. Isto mexe em receita: dar dias de teste a um plano
 * pago é entregar o serviço de graça durante esse tempo, e a decisão é de
 * quem gere a plataforma — não do código.
 *
 * A data conta-se a partir de HOJE, não da data em que a subscrição nasceu.
 * Estender um teste que já expirou há uma semana e dar-lhe "14 dias" a
 * contar de trás não daria dia nenhum.
 *
 *   php artisan subscricao:trial --tenant=57 --dias=14
 *   php artisan subscricao:trial --tenant=57 --dias=14 --aplicar
 */
class DefinirTrialDaEmpresa extends Command
{
    protected $signature = 'subscricao:trial
                            {--tenant= : id da empresa}
                            {--email= : email da empresa, se não souber o id}
                            {--dias=14 : dias de teste a contar de hoje}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Define o período de teste da subscrição de uma empresa (simulação por omissão)';

    public function handle(): int
    {
        $empresa = $this->option('tenant')
            ? Tenant::find($this->option('tenant'))
            : Tenant::where('email', trim((string) $this->option('email')))->first();

        if (!$empresa) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        $subscricao = $empresa->activeSubscription;

        if (!$subscricao) {
            $this->error("A empresa #{$empresa->id} não tem subscrição activa. Aprove primeiro um pedido.");

            return self::FAILURE;
        }

        $dias = max(1, (int) $this->option('dias'));
        $fim = now()->addDays($dias);
        $estado = \App\Support\EstadoDaSubscricao::para($empresa);

        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line('  email:    ' . $empresa->email);
        $this->line('  plano:    ' . ($subscricao->plan->name ?? '—') . '   valor: ' . number_format((float) $subscricao->amount, 2, ',', ' '));
        $this->line('  estado:   ' . $estado['rotulo'] . ' — ' . $estado['detalhe']);
        $this->newLine();
        $this->line('  teste actual: ' . ($subscricao->trial_ends_at?->format('d/m/Y H:i') ?: '(não tem)'));
        $this->line('  passa a:      ' . $fim->format('d/m/Y H:i') . "   ({$dias} dias a contar de hoje)");
        $this->line(str_repeat('=', 62));

        if ((float) $subscricao->amount > 0) {
            $this->warn('  Atenção: é um plano PAGO. São ' . $dias . ' dias de serviço sem cobrança.');
        }

        if (!$this->option('aplicar')) {
            $this->newLine();
            $this->warn('  Nada foi gravado. Repita com --aplicar.');

            return self::SUCCESS;
        }

        $subscricao->update([
            'status'        => 'trial',
            'trial_ends_at' => $fim,
            // O período corrente acompanha o teste: deixá-lo para trás punha a
            // subscrição a dizer que já acabou enquanto o teste ainda corre.
            'ends_at'       => $fim,
        ]);

        $this->newLine();
        $this->info('  ✓ Teste até ' . $fim->format('d/m/Y') . '. Estado: '
            . \App\Support\EstadoDaSubscricao::para($empresa->fresh())['rotulo']);

        return self::SUCCESS;
    }
}
