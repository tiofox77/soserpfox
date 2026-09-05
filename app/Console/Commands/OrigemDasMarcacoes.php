<?php

namespace App\Console\Commands;

use App\Models\Salon\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marcações do salão que ficaram sem origem.
 *
 * A coluna `source` era um enum que não conhecia 'system' — que é justamente o
 * valor por omissão do ecrã de nova marcação. Num MySQL estrito, gravar dava
 * erro e a marcação não chegava a existir; num MySQL permissivo — o mais comum
 * em alojamento partilhado — o MySQL avisa e guarda a **cadeia vazia**.
 *
 * As marcações assim ficam órfãs de origem: não entram nem em «online» nem em
 * «sistema», e o filtro da listagem nunca as apanha.
 *
 * Lê e conta. Só escreve com `--aplicar`, e diz exactamente o que vai mudar.
 */
class OrigemDasMarcacoes extends Command
{
    protected $signature = 'salao:origens
                            {--aplicar : escreve mesmo (sem isto só conta)}
                            {--origem=system : a origem a pôr nas que estão vazias}';

    protected $description = 'Conta (e repara) marcações do salão que ficaram sem origem';

    public function handle(): int
    {
        if (! Schema::hasTable('salon_appointments')) {
            $this->warn('Esta instalação não tem o módulo do salão.');

            return self::SUCCESS;
        }

        $origem = (string) $this->option('origem');

        if (! array_key_exists($origem, Appointment::SOURCES)) {
            $this->error("A origem '{$origem}' não existe. Conhecidas: " . implode(', ', array_keys(Appointment::SOURCES)));

            return self::FAILURE;
        }

        // A coluna aceita mesmo esta origem? Se a migração ainda não correu,
        // escrever aqui só voltaria a deixar a cadeia vazia.
        $tipo = DB::selectOne(
            'SELECT COLUMN_TYPE t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['salon_appointments', 'source']
        );

        $cabe = $tipo && str_contains($tipo->t, "'" . $origem . "'");

        $this->line('Coluna: ' . ($tipo->t ?? 'ausente'));
        $this->line("A origem '{$origem}' cabe na coluna: " . ($cabe ? 'sim' : 'NÃO'));
        $this->newLine();

        $porEmpresa = DB::table('salon_appointments')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('source', '')->orWhereNull('source'))
            ->selectRaw('tenant_id, COUNT(*) n')
            ->groupBy('tenant_id')
            ->orderByDesc('n')
            ->get();

        $total = (int) $porEmpresa->sum('n');

        if ($total === 0) {
            $this->info('Nenhuma marcação sem origem. Nada a fazer.');

            return self::SUCCESS;
        }

        $this->warn("{$total} marcação(ões) sem origem:");

        foreach ($porEmpresa as $linha) {
            $this->line("  empresa {$linha->tenant_id}: {$linha->n}");
        }

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->line("Para corrigir: salao:origens --aplicar --origem={$origem}");

            return self::SUCCESS;
        }

        if (! $cabe) {
            $this->error('A coluna ainda não aceita essa origem — corra a migração primeiro.');

            return self::FAILURE;
        }

        $mexidas = DB::table('salon_appointments')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('source', '')->orWhereNull('source'))
            ->update(['source' => $origem]);

        $this->info("{$mexidas} marcação(ões) passaram a '{$origem}'.");

        return self::SUCCESS;
    }
}
