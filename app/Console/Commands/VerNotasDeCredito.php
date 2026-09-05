<?php

namespace App\Console\Commands;

use App\Models\Invoicing\CreditNote;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * As notas de crédito de uma empresa, ao lado da factura que anulam. SÓ LÊ.
 *
 * PORQUE EXISTE. A AGT recusa com E43 quando a soma do que se anula passa o
 * que a factura ainda tem por anular. Para perceber uma dessas recusas é
 * preciso ver três números ao mesmo tempo: o total da nota, o total da factura
 * base, e o que já foi anulado dessa factura por outras notas. Sem isto era
 * preciso adivinhar, e a resposta da AGT só diz o primeiro.
 *
 * Com `--id` mostra também as linhas da nota, que é onde se vê de onde vem o
 * valor a mais.
 */
class VerNotasDeCredito extends Command
{
    protected $signature = 'notas-credito:ver
                            {--tenant= : id da empresa}
                            {--numero= : procura a nota pelo número, em todas as empresas}
                            {--id= : uma nota em concreto, com as linhas}
                            {--limite=15 : quantas mostrar}';

    protected $description = 'Mostra notas de crédito e a factura que anulam, com o que já estava anulado (só leitura)';

    public function handle(): int
    {
        // Pelo NÚMERO, quando não se sabe de que empresa é a nota — que é o
        // caso normal quando alguém a manda por mensagem. Aceita o número
        // interno ou o da AGT, inteiro ou só o fim ('/000002').
        if ($numero = $this->option('numero')) {
            $achadas = CreditNote::withoutGlobalScopes()
                ->where('credit_note_number', 'like', '%' . $numero . '%')
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'tenant_id', 'credit_note_number', 'total', 'status', 'invoice_id']);

            if ($achadas->isEmpty()) {
                $this->warn('Nenhuma nota com esse número.');

                return self::SUCCESS;
            }

            $this->table(
                ['id', 'empresa', 'número', 'total', 'estado', 'factura'],
                $achadas->map(fn ($n) => [
                    $n->id,
                    $n->tenant_id,
                    $n->credit_note_number,
                    number_format((float) $n->total, 2, ',', '.'),
                    $n->status,
                    $n->invoice_id ?: 'SEM FACTURA',
                ])->all()
            );

            $this->line('Para ver as linhas: notas-credito:ver --tenant=<empresa> --id=<id>');

            return self::SUCCESS;
        }

        $empresa = Tenant::find($this->option('tenant'));

        if (!$empresa) {
            $this->error('Empresa não encontrada. Use --tenant=<id> ou --numero=<nº>.');

            return self::FAILURE;
        }

        $this->line('<options=bold> EMPRESA #' . $empresa->id . ' — ' . $empresa->name . '</>');

        $notas = CreditNote::withoutGlobalScopes()
            ->with(['invoice', 'items'])
            ->where('tenant_id', $empresa->id)
            ->when($this->option('id'), fn ($q) => $q->where('id', $this->option('id')))
            ->orderByDesc('id')
            ->limit((int) $this->option('limite'))
            ->get();

        if ($notas->isEmpty()) {
            $this->warn('Sem notas de crédito.');

            return self::SUCCESS;
        }

        $linhas = [];

        foreach ($notas as $nota) {
            $factura = $nota->invoice;

            // O que TODAS as notas emitidas já tiram a esta factura.
            $jaAnulado = $factura
                ? CreditNote::withoutGlobalScopes()
                    ->where('invoice_id', $factura->id)
                    ->where('status', 'issued')
                    ->sum('total')
                : 0;

            $excesso = $factura ? (float) $jaAnulado - (float) $factura->total : 0;

            $linhas[] = [
                $nota->id,
                $nota->credit_note_number ?? $nota->number ?? '—',
                $nota->status,
                number_format((float) $nota->total, 2, ',', ' '),
                $factura ? $factura->invoice_number : '(sem factura)',
                $factura ? number_format((float) $factura->total, 2, ',', ' ') : '—',
                number_format((float) $jaAnulado, 2, ',', ' '),
                $excesso > 0.01 ? 'EXCEDE em ' . number_format($excesso, 2, ',', ' ') : 'ok',
            ];
        }

        $this->table(
            ['#', 'nota', 'estado', 'total da nota', 'factura base', 'total da factura', 'já anulado', 'veredicto'],
            $linhas
        );

        if ($this->option('id')) {
            $nota = $notas->first();
            $this->newLine();
            $this->line('<options=bold> LINHAS DA NOTA #' . $nota->id . '</>');

            $this->table(
                ['artigo', 'qtd', 'preço unit.', 'desc%', 'imposto', 'total'],
                $nota->items->map(fn ($i) => [
                    mb_substr((string) ($i->product_name ?? $i->description ?? '—'), 0, 38),
                    $i->quantity,
                    number_format((float) $i->unit_price, 2, ',', ' '),
                    $i->discount_percent ?? 0,
                    number_format((float) ($i->tax_amount ?? 0), 2, ',', ' '),
                    number_format((float) ($i->total ?? 0), 2, ',', ' '),
                ])->all()
            );

            $somaLinhas = $nota->items->sum(fn ($i) => (float) ($i->total ?? 0));
            $this->line('  soma das linhas: ' . number_format($somaLinhas, 2, ',', ' ')
                . '   ·   total gravado na nota: ' . number_format((float) $nota->total, 2, ',', ' '));

            // AO LADO, o que a factura base realmente levava. É aqui que se vê
            // se foram devolvidas mais unidades do que as que se venderam, ou
            // se a nota foi apontada à factura errada.
            if ($nota->invoice) {
                $nota->invoice->loadMissing('items');
                $this->newLine();
                $this->line('<options=bold> LINHAS DA FACTURA BASE ' . $nota->invoice->invoice_number . '</>');

                $this->table(
                    ['artigo', 'qtd', 'preço unit.', 'desc%', 'total'],
                    $nota->invoice->items->map(fn ($i) => [
                        mb_substr((string) ($i->product_name ?? $i->description ?? '—'), 0, 38),
                        $i->quantity,
                        number_format((float) $i->unit_price, 2, ',', ' '),
                        $i->discount_percent ?? 0,
                        number_format((float) ($i->total ?? 0), 2, ',', ' '),
                    ])->all()
                );
            }
        }

        return self::SUCCESS;
    }
}
