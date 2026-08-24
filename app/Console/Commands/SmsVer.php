<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ver que SMS saíram, e porquê.
 *
 * Existe porque um SMS repetido é dinheiro a sair, e a discussão sobre "está a
 * repetir" não se resolve por adivinhação: resolve-se olhando para o que foi
 * enviado, a quem, de que tipo e a que horas. Só LEITURA.
 */
class SmsVer extends Command
{
    protected $signature = 'sms:ver
        {--dias=7 : janela a analisar}
        {--tipo= : filtrar por tipo (ex.: pagamento_pendente, subs_factura_vencida)}
        {--repetidos : mostrar apenas o que saiu mais do que uma vez}
        {--limite=30 : linhas do detalhe}';

    protected $description = 'Mostra os SMS enviados, agrupados por tipo, e aponta repetições';

    public function handle(): int
    {
        $desde = now()->subDays(max(1, (int) $this->option('dias')));

        $base = fn () => DB::table('sms_logs')
            ->where('created_at', '>=', $desde)
            ->when($this->option('tipo'), fn ($q) => $q->where('type', $this->option('tipo')));

        $this->info('SMS desde ' . $desde->format('d/m/Y H:i'));
        $this->newLine();

        // Por tipo: o retrato de onde o saldo está a ir.
        $porTipo = $base()->selectRaw('type, COUNT(*) n, MAX(created_at) ultimo')
            ->groupBy('type')->orderByDesc('n')->get();

        if ($porTipo->isEmpty()) {
            $this->comment('Nenhum SMS nesta janela.');

            return self::SUCCESS;
        }

        $this->table(['tipo', 'quantos', 'último'],
            $porTipo->map(fn ($r) => [$r->type ?: '—', $r->n, $r->ultimo])->all());

        // Repetições: mesmo destinatário + mesmo tipo mais do que uma vez.
        $repetidos = $base()
            ->selectRaw('type, recipient, COUNT(*) n, MIN(created_at) primeiro, MAX(created_at) ultimo')
            ->groupBy('type', 'recipient')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('n')->limit(20)->get();

        if ($repetidos->isNotEmpty()) {
            $this->newLine();
            $this->warn('REPETIÇÕES (mesmo número, mesmo tipo):');
            $this->table(['tipo', 'número', 'vezes', 'primeiro', 'último'],
                $repetidos->map(fn ($r) => [$r->type ?: '—', $r->recipient, $r->n, $r->primeiro, $r->ultimo])->all());
        } else {
            $this->newLine();
            $this->info('Sem repetições (mesmo número + mesmo tipo) nesta janela.');
        }

        if (!$this->option('repetidos')) {
            $detalhe = $base()->orderByDesc('id')->limit((int) $this->option('limite'))
                ->get(['id', 'created_at', 'type', 'recipient', 'status', 'tenant_id']);

            $this->newLine();
            $this->line('Últimos envios:');
            $this->table(['id', 'quando', 'tipo', 'para', 'estado', 'empresa'],
                $detalhe->map(fn ($r) => [
                    $r->id, $r->created_at, $r->type ?: '—', $r->recipient, $r->status, $r->tenant_id ?? '—',
                ])->all());
        }

        return self::SUCCESS;
    }
}
