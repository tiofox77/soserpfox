<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\PurchaseInvoice;
use App\Models\Invoicing\Receipt;
use App\Models\Invoicing\Advance;
use App\Models\Treasury\Transaction;
use App\Models\Treasury\PaymentMethod;
use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Services\Treasury\TreasuryMovementService;

class PaymentModal extends Component
{
    public $show = false;
    public $invoiceType; // 'sale' ou 'purchase'
    public $invoiceId;
    public $invoice;
    
    // Campos de pagamento
    public $amount = 0;
    public $payment_method = 'cash';
    public $selected_account_id = null;
    public $selected_cash_register_id = null;
    public $reference = '';
    public $notes = '';
    public $use_advance = false;
    public $advance_id = null;
    public $advance_amount = 0;
    
    // Info
    public $available_advances = [];
    public $available_accounts = [];
    public $available_cash_registers = [];
    public $total_due = 0;
    public $remaining_after_payment = 0;

    protected $listeners = ['openPaymentModal'];

    protected function rules()
    {
        return [
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required',
        ];
    }

    public function openPaymentModal($invoiceType, $invoiceId)
    {
        try {
            \Log::info('Abrindo modal de pagamento', [
                'invoice_type' => $invoiceType,
                'invoice_id' => $invoiceId,
            ]);
            
            $this->invoiceType = $invoiceType;
            $this->invoiceId = $invoiceId;
            $this->loadInvoice();
            $this->show = true;
            
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => __('💰 Modal de pagamento aberto')
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Quase sempre é o mesmo caso: o ecrã ficou de uma empresa
            // anterior e este id já não lhe pertence. Atirar o erro do
            // Eloquent à cara do utilizador ("No query results for model
            // [App\Models\...] 35") não lhe diz nada — e o que ele precisa é
            // de recarregar, não de perceber Eloquent.
            \Log::warning('Pagamento: documento fora da empresa activa', [
                'tipo'      => $invoiceType,
                'id'        => $invoiceId,
                'tenant_id' => activeTenantId(),
            ]);

            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => __('Este documento não pertence à empresa activa. A actualizar a lista…'),
            ]);
            $this->dispatch('recarregar-pagina');
        } catch (\Exception $e) {
            \Log::error('Erro ao abrir modal', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro ao abrir modal: :detalhe', ['detalhe' => $e->getMessage()])]);
        }
    }

    public function loadInvoice()
    {
        if ($this->invoiceType === 'sale') {
            $this->invoice = SalesInvoice::with('client')->findOrFail($this->invoiceId);
            
            // Buscar adiantamentos disponíveis do cliente
            $this->available_advances = Advance::where('tenant_id', activeTenantId())
                ->where('client_id', $this->invoice->client_id)
                ->where('status', 'available')
                ->where('remaining_amount', '>', 0)
                ->get();
        } else {
            $this->invoice = PurchaseInvoice::with('supplier')->findOrFail($this->invoiceId);
            $this->available_advances = collect();
        }

        // Carregar contas bancárias e caixas disponíveis
        $this->available_accounts = Account::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->with('bank')
            ->orderBy('is_default', 'desc')
            ->orderBy('account_name')
            ->get();

        $this->available_cash_registers = CashRegister::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        // Selecionar conta/caixa padrão
        $defaultAccount = $this->available_accounts->where('is_default', true)->first();
        if ($defaultAccount) {
            $this->selected_account_id = $defaultAccount->id;
        }

        $defaultCash = $this->available_cash_registers->where('is_default', true)->first();
        if ($defaultCash) {
            $this->selected_cash_register_id = $defaultCash->id;
        }

        $this->total_due = $this->invoice->total - ($this->invoice->paid_amount ?? 0);
        $this->amount = $this->total_due;
        $this->calculateRemaining();
    }

    public function updatedAmount()
    {
        $this->calculateRemaining();
    }

    public function updatedUseAdvance()
    {
        if ($this->use_advance && $this->available_advances->isNotEmpty()) {
            $this->advance_id = $this->available_advances->first()->id;
            $this->updatedAdvanceId();
        } else {
            $this->advance_amount = 0;
            $this->calculateRemaining();
        }
    }

    public function updatedAdvanceId()
    {
        if ($this->advance_id) {
            $advance = Advance::find($this->advance_id);
            if ($advance) {
                $this->advance_amount = min($advance->remaining_amount, $this->total_due);
                $this->calculateRemaining();
            }
        }
    }

    public function calculateRemaining()
    {
        $total_payment = ($this->amount ?? 0) + ($this->advance_amount ?? 0);
        $this->remaining_after_payment = max(0, $this->total_due - $total_payment);
    }

    public function registerPayment()
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Erro de validação: :detalhe', ['detalhe' => implode(', ', $e->validator->errors()->all())])]);
            return;
        }

        DB::beginTransaction();
        try {
            \Log::info('Iniciando registro de pagamento', [
                'invoice_type' => $this->invoiceType,
                'invoice_id' => $this->invoiceId,
                'amount' => $this->amount,
            ]);
            $total_payment = $this->amount + $this->advance_amount;
            
            // Atualizar fatura
            $new_paid_amount = ($this->invoice->paid_amount ?? 0) + $total_payment;
            $this->invoice->paid_amount = $new_paid_amount;
            
            // Atualizar status automaticamente
            if ($new_paid_amount >= $this->invoice->total) {
                $this->invoice->status = 'paid';
            } elseif ($new_paid_amount > 0) {
                $this->invoice->status = 'partially_paid';
            }
            
            $this->invoice->save();

            // Criar recibo se houver pagamento em dinheiro/transferência
            $receipt = null;
            if ($this->amount > 0) {
                $receipt = Receipt::create([
                    'tenant_id' => activeTenantId(),
                    'type' => $this->invoiceType,
                    'client_id' => $this->invoiceType === 'sale' ? $this->invoice->client_id : null,
                    'supplier_id' => $this->invoiceType === 'purchase' ? $this->invoice->supplier_id : null,
                    // Cada tipo na SUA coluna. Escrever o id de uma factura
                    // de compra em `invoice_id` — que tem chave estrangeira
                    // para as facturas de VENDA — fazia a base recusar a
                    // linha inteira, e pagar uma compra nunca funcionou.
                    // É o mesmo que o movimento de caixa aqui ao lado já faz.
                    'invoice_id' => $this->invoiceType === 'sale' ? $this->invoiceId : null,
                    'purchase_invoice_id' => $this->invoiceType === 'purchase' ? $this->invoiceId : null,
                    'payment_date' => now(),
                    'payment_method' => $this->payment_method,
                    'amount_paid' => $this->amount,
                    'reference' => $this->reference,
                    'notes' => $this->notes,
                    'status' => 'issued',
                    'created_by' => auth()->id(),
                ]);

                // Criar transação na Tesouraria
                $this->createTreasuryTransaction($receipt->id);
            }

            // Usar adiantamento se selecionado
            if ($this->use_advance && $this->advance_id && $this->advance_amount > 0) {
                $advance = Advance::find($this->advance_id);
                $advance->use($this->advance_amount, $this->invoiceId);
            }

            // Verificar se há pagamento excedente e criar adiantamento
            $overpayment = $total_payment - $this->total_due;
            if ($overpayment > 0 && $this->invoiceType === 'sale') {
                $advance = Advance::create([
                    'tenant_id' => activeTenantId(),
                    'client_id' => $this->invoice->client_id,
                    'payment_date' => now(),
                    'payment_method' => $this->payment_method,
                    'amount' => $overpayment,
                    'used_amount' => 0,
                    'remaining_amount' => $overpayment,
                    'purpose' => 'Excedente do pagamento da fatura ' . $this->invoice->invoice_number,
                    'notes' => 'Adiantamento criado automaticamente - Pagamento de ' . number_format($total_payment, 2) . ' AOA para fatura de ' . number_format($this->total_due, 2) . ' AOA',
                    'status' => 'available',
                    'created_by' => auth()->id(),
                ]);

                \Log::info('Adiantamento automático criado', [
                    'advance_id' => $advance->id,
                    'advance_number' => $advance->advance_number,
                    'amount' => $overpayment,
                ]);
            }

            DB::commit();

            \Log::info('Pagamento registrado com sucesso', [
                'invoice_id' => $this->invoiceId,
                'new_status' => $this->invoice->status,
            ]);

            // O recibo emitido no pagamento é documento fiscal (RC) e não era
            // enviado à AGT — ficava só na aplicação. Depois do commit: já está
            // gravado, e uma falha da AGT não o pode desfazer.
            $avisoAgt = '';

            // Só o recibo de VENDA vai à AGT. Um recibo de compra regista
            // dinheiro que a empresa PAGOU a um fornecedor: não é documento
            // fiscal emitido por ela e submetê-lo seria declarar como receita
            // uma despesa. Nunca aconteceu porque pagar uma compra rebentava
            // antes de chegar aqui — agora que funciona, tem de estar travado.
            if (isset($receipt) && $this->invoiceType === 'sale') {
                $agt = \App\Services\AGT\AutoSubmissao::submeter($receipt);

                if ($agt['enviado']) {
                    $avisoAgt = ' | Recibo submetido à AGT';
                } elseif ($agt['erro']) {
                    $avisoAgt = ' | Recibo por submeter à AGT: ' . $agt['erro'];
                }
            }

            // Mensagem de sucesso com informação de adiantamento
            $message = '✅ Pagamento registrado com sucesso! Status: ' . $this->invoice->status_label . $avisoAgt;
            if (isset($overpayment) && $overpayment > 0) {
                $message .= ' | 💰 Adiantamento de ' . number_format($overpayment, 2) . ' AOA criado automaticamente!';
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message
            ]);

            $this->dispatch('paymentRegistered');
            $this->close();

        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('Erro ao registrar pagamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('❌ Erro ao registrar pagamento: :detalhe', ['detalhe' => $e->getMessage()])]);
        }
    }

    public function close()
    {
        $this->show = false;
        $this->reset(['amount', 'payment_method', 'reference', 'notes', 'use_advance', 'advance_id', 'advance_amount']);
    }

    // Helpers para integração com Tesouraria
    /**
     * Mapa canónico: forma de pagamento do ecrã → método de tesouraria.
     * O 'type' TEM de ser um dos tipos reais (cash, card, bank_transfer,
     * digital_wallet, check, other) — é por ele que a tesouraria decide se o
     * valor entra na caixa. Antes gravava-se 'bank', que não existe.
     */
    private const MAPA_METODOS = [
        'cash'       => ['CASH',       'Dinheiro',               'cash'],
        'transfer'   => ['TRANSFER',   'Transferência Bancária', 'bank_transfer'],
        'multicaixa' => ['MULTICAIXA', 'Multicaixa',             'card'],
        'tpa'        => ['TPA',        'TPA',                    'card'],
        'check'      => ['CHECK',      'Cheque',                 'check'],
        'mbway'      => ['MBWAY',      'MB Way',                 'digital_wallet'],
        'other'      => ['OTHER',      'Outro',                  'other'],
    ];

    private function getTreasuryPaymentMethod(): PaymentMethod
    {
        [$code, $methodName, $type] = self::MAPA_METODOS[$this->payment_method]
            ?? self::MAPA_METODOS['cash'];

        $tenantId = activeTenantId();

        // Procurar pelo CÓDIGO (é o índice único real: tenant_id + code). Procurar
        // pelo nome rebentava com "Duplicate entry" assim que o cliente renomeasse
        // o método — a meio de um pagamento, com a fatura já emitida.
        $method = PaymentMethod::where('tenant_id', $tenantId)
            ->where(function ($q) use ($code, $methodName) {
                $q->where('code', $code)->orWhere('name', $methodName);
            })
            ->first();

        if (!$method) {
            $method = PaymentMethod::create([
                'tenant_id' => $tenantId,
                'code'      => $code,
                'name'      => $methodName,
                'type'      => $type,
                'is_active' => true,
            ]);
        }

        return $method;
    }

    private function getDefaultAccountOrCashRegisterId(PaymentMethod $method)
    {
        return app(TreasuryMovementService::class)->destination(
            $method,
            activeTenantId(),
            $this->selected_account_id ? (int) $this->selected_account_id : null,
            $this->selected_cash_register_id ? (int) $this->selected_cash_register_id : null,
            auth()->id(),
        );
    }

    private function createTreasuryTransaction($receiptId = null)
    {
        if ($this->amount <= 0) {
            return;
        }

        $method = $this->getTreasuryPaymentMethod();
        $accountOrCash = $this->getDefaultAccountOrCashRegisterId($method);

        app(TreasuryMovementService::class)->post([
            'tenant_id' => activeTenantId(),
            'user_id' => auth()->id(),
            'transaction_number' => $this->generateTransactionNumber(),
            'type' => $this->invoiceType === 'sale' ? 'income' : 'expense',
            'category' => $this->invoiceType === 'sale' ? 'customer_payment' : 'supplier_payment',
            'amount' => $this->amount,
            'currency' => 'AOA',
            'transaction_date' => now(),
            'payment_method_id' => $method->id,
            'account_id' => $accountOrCash['account_id'],
            'cash_register_id' => $accountOrCash['cash_register_id'],
            'invoice_id' => $this->invoiceType === 'sale' ? $this->invoiceId : null,
            'purchase_id' => $this->invoiceType === 'purchase' ? $this->invoiceId : null,
            'reference' => $this->reference ?: "Recibo #{$receiptId}",
            'description' => "Pagamento de " . ($this->invoiceType === 'sale' ? 'fatura de venda' : 'fatura de compra') . " #{$this->invoice->invoice_number}",
            'notes' => $this->notes,
            'status' => 'completed',
            'is_reconciled' => false,
        ]);

    }

    private function updateAccountBalance($accountOrCash)
    {
        $isIncome = $this->invoiceType === 'sale';
        
        // Atualizar conta bancária
        if ($accountOrCash['account_id']) {
            $account = Account::find($accountOrCash['account_id']);
            if ($account) {
                if ($isIncome) {
                    $account->current_balance += $this->amount;
                } else {
                    $account->current_balance -= $this->amount;
                }
                $account->save();
                
                \Log::info('Saldo da conta atualizado', [
                    'account_id' => $account->id,
                    'account_name' => $account->account_name,
                    'new_balance' => $account->current_balance,
                ]);
            }
        }

        // Atualizar caixa
        if ($accountOrCash['cash_register_id']) {
            $cashRegister = CashRegister::find($accountOrCash['cash_register_id']);
            if ($cashRegister) {
                if ($isIncome) {
                    $cashRegister->current_balance += $this->amount;
                } else {
                    $cashRegister->current_balance -= $this->amount;
                }
                $cashRegister->save();
                
                \Log::info('Saldo do caixa atualizado', [
                    'cash_register_id' => $cashRegister->id,
                    'cash_register_name' => $cashRegister->name,
                    'new_balance' => $cashRegister->current_balance,
                ]);
            }
        }
    }

    private function generateTransactionNumber()
    {
        // Delega no gerador robusto do modelo (MAX real + à prova de colisão).
        // O TreasuryMovementService::post() volta a gerar o número na hora, por
        // isto é só defensivo caso o valor seja usado noutro sítio.
        return Transaction::gerarNumero(activeTenantId());
    }

    public function render()
    {
        return view('livewire.invoicing.payment-modal');
    }
}
