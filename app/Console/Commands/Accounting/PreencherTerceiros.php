<?php

namespace App\Console\Commands\Accounting;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Preenche o TERCEIRO nas linhas de lançamento que já existiam.
 *
 * Até agora nenhuma integração gravava `partner_id` — a conta-corrente de
 * terceiros nasceria vazia para todo o histórico. Isto casa cada lançamento com
 * o documento que o originou (pelo `ref`, que é o número do documento) e põe o
 * cliente ou o fornecedor na linha da conta de terceiros:
 *
 *   faturas de venda, notas de crédito/débito, recibos de venda → cliente, conta 31…
 *   faturas de compra, recibos de compra (pagamentos)          → fornecedor, conta 32…
 *
 * As contas de terceiros saem das âncoras do plano (`integration_key`
 * receivables / payables), não de códigos fixos.
 *
 * Só toca linhas SEM terceiro — correr duas vezes não muda nada. Não altera
 * valores nem contas: é só a etiqueta. `--simular` mostra o que faria.
 *
 * Os RECIBOS DE COMPRA antigos foram lançados como recebimentos (Cr Clientes) —
 * esses não se etiquetam (não há linha de Fornecedores para etiquetar) e são
 * listados para correcção à mão; reescrever lançamentos confirmados não é
 * trabalho para um comando automático.
 */
class PreencherTerceiros extends Command
{
    protected $signature = 'accounting:preencher-terceiros
        {--tenant= : Só esta empresa}
        {--simular : Mostra o que faria, sem gravar}';

    protected $description = 'Preenche o cliente/fornecedor nas linhas de lançamento já existentes (conta-corrente de terceiros)';

    /**
     * [tabela, coluna do número, coluna do terceiro, tipo, âncora, filtro extra]
     */
    private const FONTES = [
        ['invoicing_sales_invoices',    'invoice_number',     'client_id',   'client',   'receivables', null],
        ['invoicing_credit_notes',      'credit_note_number', 'client_id',   'client',   'receivables', null],
        ['invoicing_debit_notes',       'debit_note_number',  'client_id',   'client',   'receivables', null],
        ['invoicing_receipts',          'receipt_number',     'client_id',   'client',   'receivables', 'sale'],
        ['invoicing_purchase_invoices', 'invoice_number',     'supplier_id', 'supplier', 'payables',    null],
        ['invoicing_receipts',          'receipt_number',     'supplier_id', 'supplier', 'payables',    'purchase'],
    ];

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');

        $tenants = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : DB::table('accounting_moves')->distinct()->orderBy('tenant_id')->pluck('tenant_id')->map(fn ($t) => (int) $t)->all();

        if (!$tenants) {
            $this->info('Não há lançamentos contabilísticos — nada a preencher.');
            return self::SUCCESS;
        }

        $total = 0;
        foreach ($tenants as $tenantId) {
            $total += $this->tratar($tenantId, $simular);
        }

        $this->newLine();
        $this->info(($simular ? '[SIMULAÇÃO] ' : '') . "Linhas com terceiro preenchido: {$total}");

        return self::SUCCESS;
    }

    private function tratar(int $tenantId, bool $simular): int
    {
        $ancoras = [
            'receivables' => $this->ancora($tenantId, 'receivables'),
            'payables'    => $this->ancora($tenantId, 'payables'),
        ];

        $linhas = [];
        $feitas = 0;

        foreach (self::FONTES as [$tabela, $numero, $coluna, $tipo, $ancora, $tipoRecibo]) {
            $codigo = $ancoras[$ancora];
            if ($codigo === null) {
                $linhas[] = [$tabela, $tipo, '—', "sem conta âncora «{$ancora}» no plano"];
                continue;
            }

            $q = $this->candidatas($tenantId, $tabela, $numero, $codigo)
                ->whereNotNull("d.{$coluna}");

            if ($tipoRecibo === 'sale') {
                // Recibos antigos podem não ter tipo: sem tipo, são de venda.
                $q->where(fn (Builder $w) => $w->where('d.type', 'sale')->orWhereNull('d.type'));
            } elseif ($tipoRecibo === 'purchase') {
                $q->where('d.type', 'purchase');
            }

            $n = $simular
                ? (clone $q)->count()
                : $q->update([
                    'l.partner_id'   => DB::raw("d.{$coluna}"),
                    'l.partner_type' => $tipo,
                    'l.document_ref' => DB::raw('COALESCE(l.document_ref, m.ref)'),
                ]);

            $feitas += $n;
            $linhas[] = [$tabela, $tipo, $n, ''];
        }

        $malLancados = $this->recibosDeCompraComoRecebimento($tenantId, $ancoras['receivables']);

        $this->newLine();
        $this->line("<info>Empresa {$tenantId}</info>");
        $this->table(['Documento', 'Terceiro', 'Linhas', 'Nota'], $linhas);

        if ($malLancados) {
            $this->warn("{$malLancados} recibo(s) de compra antigos estão lançados como recebimento (crédito em Clientes). "
                . 'Não se etiquetam; corrigir com um lançamento de regularização.');
        }

        return $feitas;
    }

    /** Linhas sem terceiro, na conta de terceiros, cujo lançamento casa com um documento. */
    private function candidatas(int $tenantId, string $tabela, string $numero, string $codigoAncora): Builder
    {
        return DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')
            ->join("{$tabela} as d", function ($j) use ($numero) {
                $j->on("d.{$numero}", '=', 'm.ref')->on('d.tenant_id', '=', 'm.tenant_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->where('m.tenant_id', $tenantId)
            ->whereNull('l.partner_id')
            ->where('a.code', 'like', $codigoAncora . '%');
    }

    private function recibosDeCompraComoRecebimento(int $tenantId, ?string $codigoClientes): int
    {
        if ($codigoClientes === null) {
            return 0;
        }

        return DB::table('accounting_move_lines as l')
            ->join('accounting_moves as m', 'm.id', '=', 'l.move_id')
            ->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')
            ->join('invoicing_receipts as r', function ($j) {
                $j->on('r.receipt_number', '=', 'm.ref')->on('r.tenant_id', '=', 'm.tenant_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->where('r.type', 'purchase')
            ->where('a.code', 'like', $codigoClientes . '%')
            ->distinct()
            ->count('m.id');
    }

    /** O código da conta-âncora (ex.: '31' Clientes) desta empresa, ou null. */
    private function ancora(int $tenantId, string $chave): ?string
    {
        $codigo = DB::table('accounting_accounts')
            ->where('tenant_id', $tenantId)
            ->where('integration_key', $chave)
            ->orderBy('code')
            ->value('code');

        return $codigo !== null ? (string) $codigo : null;
    }
}
