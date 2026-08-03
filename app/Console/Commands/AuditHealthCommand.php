<?php

namespace App\Console\Commands;

use App\Models\AuditTrail;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Verifica se a trilha de auditoria está viva e íntegra.
 *
 * Existe porque o gravador falha em SILÊNCIO, de propósito: uma avaria a
 * registar nunca pode derrubar uma venda. O efeito colateral é que "a auditoria
 * está parada" e "não houve actividade" ficam indistinguíveis a olho.
 *
 * Correr depois de cada deploy e, idealmente, uma vez por dia.
 */
class AuditHealthCommand extends Command
{
    protected $signature = 'audit:health
                            {--tenant= : Limitar a uma empresa}
                            {--horas=24 : Janela para considerar a trilha activa}';

    protected $description = 'Confirma que a auditoria está a registar e que a cadeia está intacta';

    public function handle(): int
    {
        $horas = (int) $this->option('horas');
        $falhou = false;

        // 1. A tabela existe e o mecanismo está ligado?
        if (!config('audit.enabled', true)) {
            $this->warn('AVISO: a auditoria está DESLIGADA (config/audit.enabled).');
            $falhou = true;
        }

        $modelos = (array) config('audit.models', []);
        $this->line('Modelos auditados: ' . count($modelos));

        foreach ($modelos as $modelo) {
            if (!class_exists($modelo)) {
                $this->error("  classe inexistente na allowlist: {$modelo}");
                $falhou = true;
            }
        }

        // 2. Está a escrever?
        $recentes = AuditTrail::where('created_at', '>=', now()->subHours($horas))->count();
        $total    = AuditTrail::count();

        $this->line("Linhas nas últimas {$horas}h: {$recentes}   ·   total: {$total}");

        if ($total === 0) {
            $this->warn('AVISO: a trilha está VAZIA. Ou nunca registou, ou o observer não está ligado.');
            $falhou = true;
        }

        // 3. A cadeia está intacta, empresa a empresa?
        $empresas = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->pluck('id')
            : AuditTrail::distinct()->pluck('tenant_id');

        foreach ($empresas as $tenantId) {
            $problemas = AuditTrail::verificarCadeia((int) $tenantId);

            if (empty($problemas)) {
                $this->info("  empresa {$tenantId}: cadeia íntegra");
                continue;
            }

            $falhou = true;
            $this->error("  empresa {$tenantId}: " . count($problemas) . ' problema(s)');

            foreach (array_slice($problemas, 0, 5) as $p) {
                $this->error("     sequência {$p['sequencia']} — {$p['motivo']}");
            }
        }

        // 4. Segredos: varrer o que já lá está, porque corrigir depois obriga a
        //    apagar linhas — e apagar linhas parte a cadeia.
        $proibidos = array_map('strtolower', config('audit.redacted', []));
        $suspeitas = 0;

        AuditTrail::whereNotNull('new_values')->chunk(500, function ($linhas) use ($proibidos, &$suspeitas) {
            foreach ($linhas as $linha) {
                foreach (array_keys((array) $linha->new_values) as $campo) {
                    if (in_array(strtolower($campo), $proibidos, true)
                        && $linha->new_values[$campo] !== '[oculto]') {
                        $suspeitas++;
                    }
                }
            }
        });

        if ($suspeitas > 0) {
            $this->error("PERIGO: {$suspeitas} linha(s) com campo sensível em claro.");
            $falhou = true;
        } else {
            $this->info('  nenhum segredo em claro');
        }

        $this->newLine();

        if ($falhou) {
            $this->error('Auditoria COM PROBLEMAS.');
            return self::FAILURE;
        }

        $this->info('Auditoria saudável.');

        return self::SUCCESS;
    }
}
