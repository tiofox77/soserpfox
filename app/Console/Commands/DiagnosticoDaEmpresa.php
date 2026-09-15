<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Plataforma\DiagnosticoDaEmpresa as Diagnostico;
use Illuminate\Console\Command;

/**
 * UMA EMPRESA ACABADA DE INSCREVER: FICOU TUDO PRONTO? — só lê.
 *
 * As verificações vivem em App\Services\Plataforma\DiagnosticoDaEmpresa, que a
 * API do agente também usa (GET tenants/{id}/diagnostico). Isto só as escreve
 * na consola — sem criar nada (os `*:backfill` é que criam, e só por ordem).
 */
class DiagnosticoDaEmpresa extends Command
{
    protected $signature = 'empresa:diagnostico {--tenant= : id da empresa}';

    protected $description = 'Confere se uma empresa ficou completa depois da inscrição: dono, papéis, subscrição, módulos, permissões, impostos, armazém, pagamentos, séries e erros (só lê)';

    public function handle(Diagnostico $diagnostico): int
    {
        $t = Tenant::find((int) $this->option('tenant'));

        if (! $t) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        $d = $diagnostico->para($t);
        $e = $d['empresa'];
        $sim = fn ($v) => $v ? 'sim' : 'NÃO';

        $this->info("EMPRESA #{$e['id']} — {$e['nome']}");
        $this->line("  NIF: {$e['nif']} · regime: {$e['regime']} · activa: {$sim($e['activa'])} · criada: {$e['criada_em']}");

        $this->newLine();
        $this->info('Utilizadores');
        foreach ($d['utilizadores'] as $u) {
            $this->line(sprintf('  #%d %s <%s> · activo na empresa: %s · conta activa: %s · email verificado: %s · papéis: %s',
                $u['id'], $u['nome'], $u['email'], $sim($u['activo_na_empresa']), $sim($u['conta_activa']),
                $u['email_verificado'] ? 'sim' : 'não', $u['papeis'] ? implode(', ', $u['papeis']) : '(nenhum)'));
        }
        $this->line('  Papéis: ' . ($d['papeis'] === [] ? '(nenhum)' : collect($d['papeis'])->map(fn ($p) => "{$p['nome']} ({$p['permissoes']})")->implode(', ')));

        $this->newLine();
        $this->info('Subscrição');
        if ($s = $d['subscricao']) {
            $this->line(sprintf('  plano: %s · estado: %s · teste até: %s · termina: %s · valor: %s',
                $s['plano'] ?? '(sem plano)', $s['estado'], $s['teste_ate'] ?? '—', $s['termina'] ?? '—', $s['valor'] ?? '—'));
        }
        foreach ($d['pedidos'] as $o) {
            $this->line(sprintf('  pedido #%d · %s · plano %s · %s', $o['id'], $o['estado'], $o['plano'] ?? '—', $o['criado_em']));
        }

        $this->newLine();
        $this->info('Módulos');
        $this->line('  activos: ' . (implode(', ', $d['modulos']['activos']) ?: '(nenhum)'));
        $this->line('  no plano: ' . (implode(', ', $d['modulos']['no_plano']) ?: '(nenhum)'));
        if ($d['modulos']['activos_fora_do_plano']) {
            $this->line('  (activos fora do plano: ' . implode(', ', $d['modulos']['activos_fora_do_plano']) . ')');
        }
        foreach ($d['modulos']['permissoes_do_super_admin'] as $slug => $n) {
            $this->line(sprintf('  permissões do Super Admin em %-14s %d', $slug . ':', $n));
        }

        $this->newLine();
        $this->info('Facturação');
        $f = $d['facturacao'];
        $this->line('  definições: ' . $sim($f['definicoes']) . ($f['definicoes'] ? " · ambiente AGT: {$f['ambiente_agt']} · envio automático: " . ($f['envio_automatico_agt'] ? 'sim' : 'não') . ' · CAE: ' . ($f['cae'] ?: '—') : ''));
        $this->line("  impostos: {$f['impostos']} · armazém principal: " . ($f['armazem_principal'] ?? 'NÃO') . " · formas de pagamento: {$f['formas_de_pagamento']} · categorias: {$f['categorias']}");
        $this->line('  séries: ' . ($f['series'] === [] ? '(nenhuma)' : collect($f['series'])->map(fn ($s) => "{$s['tipo']}:{$s['prefixo']}" . ($s['agt_series_id'] ? " [AGT {$s['agt_ambiente']}]" : '') . ($s['activa'] ? '' : ' (inactiva)'))->implode(', ')));
        if ($f['contas_de_tesouraria'] !== null) {
            $this->line("  contas de tesouraria: {$f['contas_de_tesouraria']}");
        }

        $this->newLine();
        $this->info('Erros desta empresa desde a criação');
        if ($d['erros'] === []) {
            $this->line('  nenhum');
        }
        foreach ($d['erros'] as $er) {
            $this->line(sprintf('  #%d %s x%d %s — %s', $er['id'], $er['nivel'], $er['ocorrencias'], $er['ultima_vez'], mb_strimwidth($er['mensagem'], 0, 120, '…')));
        }

        $this->newLine();
        if ($d['completa']) {
            $this->info('✓ Nada em falta: a empresa ficou completa.');
        } else {
            $this->warn('Em falta:');
            foreach ($d['faltas'] as $falta) {
                $this->line("  ✗ {$falta}");
            }
        }

        return self::SUCCESS;
    }
}
