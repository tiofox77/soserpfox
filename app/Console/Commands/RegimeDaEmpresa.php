<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenant\TaxRegimeSyncer;
use Illuminate\Console\Command;

/**
 * O regime fiscal de uma empresa — e tudo o que ele arrasta atrás.
 *
 * MUDAR O REGIME NÃO É MUDAR UM CAMPO. O regime decide a taxa de IVA que cada
 * linha leva, o código de isenção que vai no SAFT, e o imposto por omissão dos
 * artigos. Gravar só a coluna deixava a empresa a dizer «Regime Geral» e a
 * emitir documentos isentos — e isso só aparece quando a AGT recusa.
 *
 * Por isso corre-se o mesmo caminho do ecrã de dados da empresa: grava-se o
 * regime e chama-se o TaxRegimeSyncer, que é quem alinha taxas, definições de
 * facturação e artigos.
 */
class RegimeDaEmpresa extends Command
{
    protected $signature = 'empresas:regime
                            {--tenant= : id da empresa}
                            {--regime= : regime_geral | regime_simplificado | regime_nao_sujeicao}
                            {--aplicar : grava (sem isto é simulação)}';

    protected $description = 'Define o regime fiscal de uma empresa e alinha taxas, definições e artigos';

    public function handle(): int
    {
        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada. Use --tenant=<id>.');

            return self::FAILURE;
        }

        $novo = $this->option('regime');

        $this->line('<options=bold>EMPRESA #' . $empresa->id . ' — ' . $empresa->name . '</>');
        $this->line('  regime actual: ' . ($empresa->regime ?: '(nenhum)'));

        if (!$novo) {
            $this->newLine();
            $this->line('Os regimes que existem:');
            foreach (Tenant::REGIMES as $chave => $r) {
                $this->line(sprintf('  %-22s %s', $chave, $r['label'] . ' — ' . ($r['exempt'] ? 'isento' : 'taxa ' . $r['default_rate'] . '%')));
            }

            return self::SUCCESS;
        }

        if (!isset(Tenant::REGIMES[$novo])) {
            $this->error("Regime '{$novo}' não existe.");

            return self::FAILURE;
        }

        $this->line('  passa a:       ' . $novo . '  (' . Tenant::REGIMES[$novo]['label'] . ')');

        if (!$this->option('aplicar')) {
            $this->newLine();
            $this->warn('SIMULAÇÃO. Repita com --aplicar para gravar.');

            return self::SUCCESS;
        }

        $anterior = $empresa->regime;
        $empresa->regime = $novo;
        $empresa->save();

        // Quem alinha as taxas, as definições e os artigos é o syncer.
        $resultado = (new TaxRegimeSyncer())->sync($empresa->fresh(), $anterior);

        $this->newLine();
        $this->info('Regime gravado: ' . $novo);

        foreach ((array) $resultado as $chave => $valor) {
            $this->line(sprintf('  %-24s %s', $chave, is_scalar($valor) ? $valor : json_encode($valor)));
        }

        return self::SUCCESS;
    }
}
