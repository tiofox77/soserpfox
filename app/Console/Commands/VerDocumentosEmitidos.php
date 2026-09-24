<?php

namespace App\Console\Commands;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
                            {--limite=10 : quantos mostrar}
                            {--numero= : fim do número, separados por vírgula (FR4226S61319N/003254,FR4226S61319N/003253): mostra cada factura por inteiro}
                            {--janela=10 : minutos à volta, para as vendas vizinhas e os movimentos dos artigos}
                            {--auditar : auditoria das vendas e do stock de --tenant entre --de e --ate}
                            {--de= : primeiro dia (AAAA-MM-DD)}
                            {--ate= : último dia (AAAA-MM-DD)}';

    protected $description = 'Mostra os últimos documentos emitidos e o imposto de cada um; com --numero, a vida inteira de uma factura; com --auditar, as vendas e o stock de um período (só leitura)';

    public function handle(): int
    {
        if (filled($this->option('numero'))) {
            return $this->porNumero();
        }

        if ($this->option('auditar')) {
            return (new \App\Services\Invoicing\AuditoriaDasVendas(
                (int) $this->option('tenant'),
                (string) ($this->option('de') ?: now()->toDateString()),
                (string) ($this->option('ate') ?: now()->toDateString()),
            ))->relatar($this);
        }

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

    /**
     * A VIDA INTEIRA DE UMA FACTURA — para quando alguém diz «duplicou sozinho».
     *
     * Duas facturas iguais podem ter nascido de dois cliques, de um reenvio do
     * PWA ou de dois aparelhos. O que as separa está espalhado: o identificador
     * da tentativa (`local_uuid`), o pedido que as gravou (a trilha guarda a
     * rota, o IP e o request_id), o turno, a tesouraria e o stock. Isto junta
     * tudo, lado a lado, sem escrever nada.
     */
    private function porNumero(): int
    {
        $fins = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('numero')))));
        $janela = max(1, (int) $this->option('janela'));

        $facturas = DB::table('invoicing_sales_invoices')
            ->when(filled($this->option('tenant')), fn ($q) => $q->where('tenant_id', (int) $this->option('tenant')))
            ->where(function ($q) use ($fins) {
                foreach ($fins as $fim) {
                    $q->orWhere('invoice_number', 'like', '%' . $fim);
                }
            })
            ->orderBy('id')
            ->get();

        if ($facturas->isEmpty()) {
            $this->error('Nenhuma factura acaba em: ' . implode(', ', $fins));

            return self::FAILURE;
        }

        $utilizadores = DB::table('users')->whereIn('id', $facturas->pluck('created_by')->filter())->pluck('name', 'id');
        $produtos = collect();

        foreach ($facturas as $f) {
            $this->newLine();
            $this->info(" ══ {$f->invoice_number}  (id {$f->id}, empresa #{$f->tenant_id})");
            $this->line("   série interna: " . (DB::table('invoicing_series')->where('id', $f->series_id)->value('series_code') ?? '—')
                . " · tipo {$f->invoice_type} · estado {$f->status} · AGT " . ($f->agt_status ?: '—'));
            $this->line("   criada {$f->created_at} · entrada no sistema {$f->system_entry_date} · por #{$f->created_by} " . ($utilizadores[$f->created_by] ?? '?'));
            $this->line("   local_uuid " . ($f->local_uuid ?: '—') . " · origem {$f->source_billing}/" . ($f->source_module ?: '—') . " · armazém " . ($f->warehouse_id ?: '—'));
            $this->line('   total ' . number_format((float) $f->total, 2, ',', ' ') . ' · pago ' . number_format((float) $f->paid_amount, 2, ',', ' ')
                . ' · entregue ' . ($f->amount_received !== null ? number_format((float) $f->amount_received, 2, ',', ' ') : '—') . " · {$f->payment_method}");
            $this->line('   hash ' . substr((string) $f->hash, 0, 12) . '… · anterior ' . substr((string) $f->hash_previous, 0, 12) . '…');

            $linhas = DB::table('invoicing_sales_invoice_items')->where('sales_invoice_id', $f->id)->orderBy('order')->get();
            $this->table(['artigo', 'nome', 'qtd', 'preço', 'total'], $linhas->map(fn ($l) => [
                $l->product_id ?: '—', mb_strimwidth((string) $l->product_name, 0, 40, '…'),
                (float) $l->quantity, number_format((float) $l->unit_price, 2, ',', ' '), number_format((float) $l->total, 2, ',', ' '),
            ])->all());
            $produtos = $produtos->merge($linhas->pluck('product_id')->filter());

            $mov = DB::table('invoicing_stock_movements')
                ->where('reference_type', \App\Models\Invoicing\SalesInvoice::class)->where('reference_id', $f->id)->orderBy('id')->get();
            $this->line('   stock: ' . ($mov->isEmpty() ? 'NENHUM movimento' : $mov->map(fn ($m) => "#{$m->id} artigo {$m->product_id} {$m->type} " . (float) $m->quantity
                . ' (' . ($m->balance_before ?? '?') . ' → ' . ($m->balance_after ?? '?') . ") às {$m->created_at}")->implode(' | ')));

            $turno = DB::table('invoicing_pos_shift_transactions')
                ->where('reference_type', \App\Models\Invoicing\SalesInvoice::class)->where('reference_id', $f->id)->orderBy('id')->get();
            $this->line('   turno: ' . ($turno->isEmpty() ? 'nenhum movimento' : $turno->map(fn ($t) => "turno {$t->shift_id} {$t->type} {$t->payment_method} "
                . number_format((float) $t->amount, 2, ',', ' ') . " às {$t->created_at}")->implode(' | ')));

            $tesouraria = DB::table('treasury_transactions')
                ->where(fn ($q) => $q->where('invoice_id', $f->id)
                    ->orWhere(fn ($q) => $q->where('related_type', \App\Models\Invoicing\SalesInvoice::class)->where('related_id', $f->id)))
                ->where('tenant_id', $f->tenant_id)->orderBy('id')->get();
            $this->line('   tesouraria: ' . ($tesouraria->isEmpty() ? 'nenhum movimento' : $tesouraria->map(fn ($t) => "{$t->transaction_number} {$t->type} "
                . number_format((float) $t->amount, 2, ',', ' ') . ' caixa ' . ($t->cash_register_id ?: '—') . " às {$t->created_at}")->implode(' | ')));

            // O PEDIDO que a gravou: dois cliques são dois request_id do mesmo IP e da mesma rota.
            $trilha = DB::table('audit_trail')
                ->where('auditable_type', \App\Models\Invoicing\SalesInvoice::class)->where('auditable_id', $f->id)
                ->orderBy('id')->limit(6)->get(['event', 'user_id', 'channel', 'route', 'request_id', 'ip_address', 'user_agent', 'created_at']);
            foreach ($trilha as $a) {
                $this->line("   trilha: {$a->event} às {$a->created_at} · #{$a->user_id} · {$a->channel} · " . ($a->route ?: '—')
                    . ' · pedido ' . substr((string) $a->request_id, 0, 13) . ' · ' . ($a->ip_address ?: '—') . ' · ' . mb_strimwidth((string) $a->user_agent, 0, 60, '…'));
            }

            $agt = DB::table('agt_submissions')->where('document_type', \App\Models\Invoicing\SalesInvoice::class)->where('document_id', $f->id)
                ->orderBy('id')->get(['status', 'error_code', 'created_at']);
            if ($agt->isNotEmpty()) {
                $this->line('   AGT: ' . $agt->map(fn ($s) => "{$s->status}" . ($s->error_code ? " ({$s->error_code})" : '') . " às {$s->created_at}")->implode(' | '));
            }
        }

        // AS VIZINHAS NO TEMPO: o que o mesmo balcão emitiu à volta.
        $primeira = $facturas->first();
        $de = \Carbon\Carbon::parse($facturas->min('created_at'))->subMinutes($janela);
        $ate = \Carbon\Carbon::parse($facturas->max('created_at'))->addMinutes($janela);

        $vizinhas = DB::table('invoicing_sales_invoices')
            ->where('tenant_id', $primeira->tenant_id)
            ->whereBetween('created_at', [$de, $ate])
            ->orderBy('id')
            ->get(['id', 'invoice_number', 'created_at', 'created_by', 'total', 'local_uuid', 'status']);

        $this->newLine();
        $this->info(" ══ Facturas da empresa entre {$de} e {$ate}");
        $this->table(['id', 'número', 'criada', 'por', 'total', 'local_uuid', 'estado', 'linhas'], $vizinhas->map(fn ($v) => [
            $v->id, $v->invoice_number, $v->created_at, $v->created_by, number_format((float) $v->total, 2, ',', ' '),
            substr((string) $v->local_uuid, 0, 13) ?: '—', $v->status,
            DB::table('invoicing_sales_invoice_items')->where('sales_invoice_id', $v->id)
                ->get(['product_id', 'quantity'])->map(fn ($l) => ($l->product_id ?: '?') . '×' . (float) $l->quantity)->implode(' '),
        ])->all());

        // O STOCK DOS ARTIGOS: o que está agora, e cada movimento na janela.
        foreach ($produtos->unique()->values() as $pid) {
            $nome = DB::table('invoicing_products')->where('id', $pid)->value('name');
            $agora = DB::table('invoicing_stocks')->where('tenant_id', $primeira->tenant_id)->where('product_id', $pid)
                ->get(['warehouse_id', 'quantity', 'updated_at'])
                ->map(fn ($s) => "armazém {$s->warehouse_id}: " . (float) $s->quantity . " (mudou {$s->updated_at})")->implode(' | ');
            $this->newLine();
            $this->info(" ══ Artigo {$pid} " . mb_strimwidth((string) $nome, 0, 50, '…') . ' — agora ' . ($agora ?: 'sem linha de stock'));

            $movs = DB::table('invoicing_stock_movements')->where('tenant_id', $primeira->tenant_id)->where('product_id', $pid)
                ->whereBetween('created_at', [$de, $ate])->orderBy('id')
                ->get(['id', 'type', 'quantity', 'balance_before', 'balance_after', 'reference_type', 'reference_id', 'created_at', 'notes']);
            $this->table(['mov', 'tipo', 'qtd', 'antes', 'depois', 'referência', 'quando', 'nota'], $movs->map(fn ($m) => [
                $m->id, $m->type, (float) $m->quantity, $m->balance_before ?? '—', $m->balance_after ?? '—',
                class_basename((string) $m->reference_type) . ' ' . $m->reference_id, $m->created_at, mb_strimwidth((string) $m->notes, 0, 40, '…'),
            ])->all());
        }

        return self::SUCCESS;
    }
}
