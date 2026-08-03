<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Move;
use App\Models\Accounting\MoveLine;
use App\Models\Accounting\Journal;
use App\Models\Accounting\Period;
use App\Models\Accounting\Account;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Lançamentos contabilísticos automáticos a partir dos módulos operacionais.
 *
 * AGNÓSTICO AO PLANO: as contas são resolvidas por `integration_key` (não por códigos
 * fixos), tal como as demonstrações. Funciona quer o tenant use PGC-AO quer SNC, desde
 * que o plano tenha as chaves de integração mapeadas.
 */
class PostingService
{
    public function postSalesInvoice($invoice)
    {
        return DB::transaction(function () use ($invoice) {
            $tenantId = $invoice->tenant_id;
            $journal = $this->journal($tenantId, 'sale');
            $period = $this->period($tenantId, $invoice->date);

            $move = $this->createMove($tenantId, $journal, $period, $invoice->date,
                $invoice->number, 'Venda ' . $invoice->number . ' - ' . ($invoice->customer_name ?? 'Cliente'), $invoice);

            $base = $invoice->subtotal ?? round($invoice->total / 1.14, 2);
            $vat = round($invoice->total - $base, 2);

            $this->line($tenantId, $move, $this->key($tenantId, 'receivables'), $invoice->total, 0, 'Cliente: ' . ($invoice->customer_name ?? 'N/D'));
            $this->line($tenantId, $move, $this->key($tenantId, 'sales'), 0, $base, 'Vendas - ' . $invoice->number);
            if ($vat > 0) {
                $this->line($tenantId, $move, $this->key($tenantId, 'vat_collected'), 0, $vat, 'IVA - ' . $invoice->number);
            }

            return $this->finalize($move);
        });
    }

    public function postReceipt($receipt)
    {
        return DB::transaction(function () use ($receipt) {
            $tenantId = $receipt->tenant_id;
            $journal = $this->journal($tenantId, ($receipt->payment_method ?? 'cash') === 'cash' ? 'cash' : 'bank');
            $period = $this->period($tenantId, $receipt->date);

            $move = $this->createMove($tenantId, $journal, $period, $receipt->date,
                $receipt->number, 'Recebimento ' . $receipt->number, $receipt);

            $moneyKey = ($receipt->payment_method ?? 'cash') === 'cash' ? 'cash' : 'bank';
            $this->line($tenantId, $move, $this->key($tenantId, $moneyKey), $receipt->amount, 0, 'Recebimento de ' . ($receipt->customer_name ?? 'Cliente'));
            $this->line($tenantId, $move, $this->key($tenantId, 'receivables'), 0, $receipt->amount, 'Recebimento de ' . ($receipt->customer_name ?? 'Cliente'));

            return $this->finalize($move);
        });
    }

    public function postPurchase($purchase)
    {
        return DB::transaction(function () use ($purchase) {
            $tenantId = $purchase->tenant_id;
            $journal = $this->journal($tenantId, 'purchase');
            $period = $this->period($tenantId, $purchase->date);

            $move = $this->createMove($tenantId, $journal, $period, $purchase->date,
                $purchase->number ?? 'COMPRA-' . now()->format('YmdHis'),
                'Compra ' . ($purchase->supplier_name ?? 'Fornecedor'), $purchase);

            $base = $purchase->subtotal ?? round($purchase->total / 1.14, 2);
            $vat = round($purchase->total - $base, 2);

            $this->line($tenantId, $move, $this->key($tenantId, 'cogs'), $base, 0, 'Compra - ' . ($purchase->supplier_name ?? 'N/D'));
            if ($vat > 0) {
                $this->line($tenantId, $move, $this->key($tenantId, 'vat_paid'), $vat, 0, 'IVA - Compra');
            }
            $this->line($tenantId, $move, $this->key($tenantId, 'payables'), 0, $purchase->total, 'Fornecedor: ' . ($purchase->supplier_name ?? 'N/D'));

            return $this->finalize($move);
        });
    }

    public function postPayment($payment)
    {
        return DB::transaction(function () use ($payment) {
            $tenantId = $payment->tenant_id;
            $moneyKey = ($payment->payment_method ?? 'bank') === 'cash' ? 'cash' : 'bank';
            $journal = $this->journal($tenantId, $moneyKey === 'cash' ? 'cash' : 'bank');
            $period = $this->period($tenantId, $payment->date);

            $move = $this->createMove($tenantId, $journal, $period, $payment->date,
                $payment->number, 'Pagamento ' . $payment->number, $payment);

            $this->line($tenantId, $move, $this->key($tenantId, 'payables'), $payment->amount, 0, 'Pagamento a ' . ($payment->supplier_name ?? 'Fornecedor'));
            $this->line($tenantId, $move, $this->key($tenantId, $moneyKey), 0, $payment->amount, 'Pagamento a ' . ($payment->supplier_name ?? 'Fornecedor'));

            return $this->finalize($move);
        });
    }

    /**
     * Lançamento de FOLHA DE SALÁRIOS (Folha → Contabilidade).
     *
     * Dr Custos com Pessoal .......... bruto + INSS empregador
     *   Cr INSS a pagar (trabalhador) ......... total_inss_employee
     *   Cr INSS a pagar (empregador) .......... total_inss_employer
     *   Cr Retenção IRT ....................... total_irt
     *   Cr Remunerações a pagar ............... bruto − INSS trabalhador − IRT
     * (Outras deduções — adiantamentos, alimentação — são acertadas no pagamento.)
     *
     * @param object $payroll  Modelo/objeto hr_payrolls (com os totais agregados)
     * @return Move|null
     */
    public function postPayroll($payroll)
    {
        return DB::transaction(function () use ($payroll) {
            $tenantId = $payroll->tenant_id;
            $date = $payroll->period_end ?? $payroll->period_start ?? now()->toDateString();
            if ($date instanceof \DateTimeInterface) {
                $date = $date->format('Y-m-d');
            }

            $journal = Journal::where('tenant_id', $tenantId)->where('type', 'payroll')->first()
                ?? Journal::where('tenant_id', $tenantId)->where('type', 'general')->first();
            if (!$journal) {
                throw new \Exception('Diário de salários/geral não encontrado');
            }
            $period = $this->period($tenantId, $date);

            $ref = $payroll->payroll_number ?? ('FOLHA-' . ($payroll->id ?? now()->format('YmdHis')));

            // Idempotência: não duplicar o lançamento da mesma folha
            $existing = Move::where('tenant_id', $tenantId)->where('ref', $ref)->where('journal_id', $journal->id)->first();
            if ($existing) {
                return $existing;
            }

            $gross = round((float) ($payroll->total_gross_salary ?? 0), 2);
            $inssEmp = round((float) ($payroll->total_inss_employee ?? 0), 2);
            $inssEr = round((float) ($payroll->total_inss_employer ?? 0), 2);
            $irt = round((float) ($payroll->total_irt ?? 0), 2);
            $payable = round($gross - $inssEmp - $irt, 2);

            if ($gross <= 0) {
                throw new \Exception('Folha sem valor bruto para lançar');
            }

            $move = $this->createMove($tenantId, $journal, $period, $date, $ref, 'Folha de salários ' . $ref, $payroll);

            $this->line($tenantId, $move, $this->key($tenantId, 'payroll'), $gross + $inssEr, 0, 'Custos com o pessoal');
            $this->line($tenantId, $move, $this->key($tenantId, 'inss_employee'), 0, $inssEmp, 'INSS trabalhador (3%)');
            $this->line($tenantId, $move, $this->key($tenantId, 'inss_employer'), 0, $inssEr, 'INSS empregador (8%)');
            $this->line($tenantId, $move, $this->key($tenantId, 'withholding_irt'), 0, $irt, 'IRT retido');
            $this->line($tenantId, $move, $this->key($tenantId, 'salaries_payable'), 0, $payable, 'Remunerações líquidas a pagar');

            return $this->finalize($move);
        });
    }

    // ─────────────────────────── Helpers ───────────────────────────

    protected function journal($tenantId, $type): Journal
    {
        $journal = Journal::where('tenant_id', $tenantId)->where('type', $type)->first()
            ?? Journal::where('tenant_id', $tenantId)->where('type', 'general')->first();
        if (!$journal) {
            throw new \Exception("Diário '{$type}' (nem geral) encontrado");
        }
        return $journal;
    }

    protected function period($tenantId, $date): Period
    {
        $period = Period::where('tenant_id', $tenantId)
            ->where('date_start', '<=', $date)
            ->where('date_end', '>=', $date)
            ->where('state', 'open')
            ->first();
        if (!$period) {
            throw new \Exception('Período contabilístico não encontrado ou fechado');
        }
        return $period;
    }

    protected function createMove($tenantId, $journal, $period, $date, $ref, $narration, $source = null): Move
    {
        return Move::create([
            'tenant_id' => $tenantId,
            'journal_id' => $journal->id,
            'period_id' => $period->id,
            'date' => $date,
            'ref' => $ref,
            'narration' => $narration,
            'state' => 'draft',
            'created_by' => auth()->id() ?? ($source->processed_by ?? $source->created_by ?? 1),
            'total_debit' => 0,
            'total_credit' => 0,
        ]);
    }

    protected function line($tenantId, Move $move, $accountId, $debit, $credit, $name): void
    {
        $debit = round((float) $debit, 2);
        $credit = round((float) $credit, 2);
        if ($debit == 0.0 && $credit == 0.0) {
            return;
        }
        MoveLine::create([
            'tenant_id' => $tenantId,
            'move_id' => $move->id,
            'account_id' => $accountId,
            'name' => $name,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $debit - $credit,
        ]);
    }

    protected function finalize(Move $move): Move
    {
        $totalDebit = $move->lines()->sum('debit');
        $totalCredit = $move->lines()->sum('credit');
        if (abs($totalDebit - $totalCredit) >= 0.01) {
            throw new \Exception("Lançamento desequilibrado: D={$totalDebit} C={$totalCredit}");
        }
        $move->update(['state' => 'posted', 'total_debit' => $totalDebit, 'total_credit' => $totalCredit]);
        return $move;
    }

    /**
     * Resolve a conta MOVIMENTÁVEL de um integration_key (agnóstico ao plano).
     * Se a âncora for uma conta-mãe (is_view), desce à 1ª folha da subárvore.
     */
    protected function key($tenantId, string $integrationKey): int
    {
        $acc = Account::where('tenant_id', $tenantId)->where('integration_key', $integrationKey)->first();
        if (!$acc) {
            throw new \Exception("Conta com integration_key '{$integrationKey}' não encontrada no plano do tenant");
        }
        if (!$acc->is_view) {
            return $acc->id;
        }
        $leaf = Account::where('tenant_id', $tenantId)
            ->where('is_view', false)
            ->where('code', 'like', $acc->code . '%')
            ->orderBy('code')
            ->first();
        if (!$leaf) {
            throw new \Exception("Sem conta movimentável para '{$integrationKey}'");
        }
        return $leaf->id;
    }

    public function validateBalance(Move $move): bool
    {
        return abs($move->lines()->sum('debit') - $move->lines()->sum('credit')) < 0.01;
    }
}
