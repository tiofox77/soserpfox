<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Plataforma\TrocarDePlano;
use Illuminate\Console\Command;

/**
 * Troca o plano de uma empresa pela regra do TrocarDePlano.
 *
 * Mesma lógica do ecrã de administração — não uma cópia. Duas formas de
 * trocar de plano acabam por divergir, e a divergência aqui é uma empresa
 * com dois planos vivos ou com um teste que não devia ter.
 *
 * SIMULAÇÃO POR OMISSÃO: isto mexe em receita.
 */
class TrocarPlanoDaEmpresa extends Command
{
    protected $signature = 'empresas:trocar-plano
                            {--tenant= : id da empresa}
                            {--email= : email da empresa}
                            {--plano= : id ou slug do plano novo}
                            {--ciclo=monthly : monthly|quarterly|semiannual|yearly}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Troca o plano de uma empresa, cancelando o anterior (simulação por omissão)';

    public function handle(): int
    {
        $empresa = $this->option('tenant')
            ? Tenant::find($this->option('tenant'))
            : Tenant::where('email', trim((string) $this->option('email')))->first();

        if (!$empresa) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        $chave = trim((string) $this->option('plano'));
        $plano = is_numeric($chave) ? Plan::find($chave) : Plan::where('slug', $chave)->orWhere('name', $chave)->first();

        if (!$plano) {
            $this->error('Plano não encontrado: ' . $chave);
            $this->line('  Disponíveis: ' . Plan::pluck('name')->implode(', '));

            return self::FAILURE;
        }

        $vivas = $empresa->subscriptions()->whereNotIn('status', ['cancelled', 'expired'])->with('plan')->get();

        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line('  subscrições vivas agora: ' . $vivas->count());

        foreach ($vivas as $s) {
            $this->line(sprintf('    #%d %s — %s (%s)', $s->id, $s->plan->name ?? '?', $s->status,
                $s->ends_at?->format('d/m/Y') ?: 'sem fim'));
        }

        $this->newLine();
        $this->line('  passa a: ' . $plano->name . '  ' . number_format((float) $plano->getPrice($this->option('ciclo')), 2, ',', ' '));
        $this->line('  já teve plano antes: ' . ($empresa->subscriptions()->exists() ? 'SIM → entra activo, sem teste' : 'NÃO → começa em teste, se o plano tiver'));
        $this->line(str_repeat('=', 62));

        if (!$this->option('aplicar')) {
            $this->newLine();
            $this->warn('  Nada foi gravado. Repita com --aplicar.');

            return self::SUCCESS;
        }

        $nova = app(TrocarDePlano::class)->aplicar($empresa, $plano, (string) $this->option('ciclo'));

        $this->newLine();
        $this->info(sprintf('  ✓ %s — %s até %s. Canceladas: %d.',
            $plano->name, $nova->status, $nova->ends_at?->format('d/m/Y') ?: '—', $vivas->count()));

        return self::SUCCESS;
    }
}
