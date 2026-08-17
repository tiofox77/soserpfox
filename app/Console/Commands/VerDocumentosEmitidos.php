<?php

namespace App\Console\Commands;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Os últimos documentos emitidos por uma empresa. Só lê.
 *
 * Serve para confirmar em produção o que saiu MESMO no documento — a taxa, o
 * imposto e o motivo de isenção. Um sistema pode ter a configuração certa e
 * continuar a emitir errado; a única prova é o documento.
 */
class VerDocumentosEmitidos extends Command
{
    protected $signature = 'documentos:ver
                            {--tenant= : id da empresa}
                            {--limite=10 : quantos mostrar}';

    protected $description = 'Mostra os últimos documentos emitidos e o imposto de cada um (só leitura)';

    public function handle(): int
    {
        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada: --tenant=' . $this->option('tenant'));

            return self::FAILURE;
        }

        $docs = SalesInvoice::withoutGlobalScopes()
            ->where('tenant_id', $empresa->id)
            ->with('items')
            ->orderByDesc('id')
            ->limit((int) $this->option('limite'))
            ->get();

        $this->newLine();
        $this->info(" EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line(' Regime: ' . $empresa->regimeLabel());
        $this->line(' Documentos no total: ' . SalesInvoice::withoutGlobalScopes()->where('tenant_id', $empresa->id)->count());
        $this->newLine();

        $linhas = [];
        $comImposto = 0;

        foreach ($docs as $d) {
            $taxas = $d->items->pluck('tax_rate')->map(fn ($r) => rtrim(rtrim(number_format((float) $r, 2, ',', ''), '0'), ','))->unique()->implode(' / ');
            $motivos = $d->items->pluck('tax_exemption_code')->filter()->unique()->implode(' / ');

            if ((float) $d->tax_amount > 0) {
                $comImposto++;
            }

            $linhas[] = [
                $d->invoice_number ?: '(sem número)',
                optional($d->invoice_date)->format('d/m/Y'),
                $d->status,
                number_format((float) $d->total, 2, ',', ' '),
                number_format((float) $d->tax_amount, 2, ',', ' '),
                $taxas ?: '—',
                $motivos ?: '—',
            ];
        }

        $this->table(['número', 'data', 'estado', 'total', 'imposto', 'taxa(s)', 'isenção'], $linhas);

        if ($empresa->isExemptRegime() && $comImposto > 0) {
            $this->error("  ⚠ {$comImposto} destes documentos têm imposto, numa empresa em regime isento.");

            return self::FAILURE;
        }

        if ($empresa->isExemptRegime()) {
            $this->info('  ✓ Nenhum destes documentos liquidou imposto.');
        }

        return self::SUCCESS;
    }
}
