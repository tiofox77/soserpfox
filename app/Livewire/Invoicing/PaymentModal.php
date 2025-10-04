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
                'message' => '💰 Modal de pagamento aberto'
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao abrir modal', ['error' => $e->getMessage()]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Erro ao abrir modal: ' . $e->getMessage()
            ]);
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
                'message' => 'Erro de validação: ' . implode(', ', $e->validator->errors()->all())
            ]);
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
                    'invoice_id' => $this->invoiceId,
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

            // Mensagem de sucesso com informação de adiantamento
            $message = '✅ Pagamento registrado com sucesso! Status: ' . $this->invoice->status_label;
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
                'message' => '❌ Erro ao registrar pagamento: ' . $e->getMessage()
            ]);
        }
    }

    public function close()
    {
        $this->show = false;
        $this->reset(['amount', 'payment_method', 'reference', 'notes', 'use_advance', 'advance_id', 'advance_amount']);
    }

    // Helpers para integração com Tesouraria
    private function getTreasuryPaymentMethodId()
    {
        // Mapear métodos de pagamento para IDs da tesouraria
        $mapping = [
            'cash' => 'Dinheiro',
            'transfer' => 'Transferência Bancária',
            'multicaixa' => 'Multicaixa',
            'tpa' => 'TPA',
            'check' => 'Cheque',
            'mbway' => 'MB Way',
            'other' => 'Outro',
        ];

        $methodName = $mapping[$this->payment_method] ?? 'Dinheiro';
        
        // Buscar ou criar método de pagamento
        $method = PaymentMethod::where('tenant_id', activeTenantId())
            ->where('name', $methodName)
            ->first();

        if (!$method) {
            // Gerar código único
            $code = strtoupper(str_replace(' ', '_', $this->payment_method));
            
            $method = PaymentMethod::create([
                'tenant_id' => activeTenantId(),
                'code' => $code,
                'name' => $methodName,
                'type' => $this->payment_method === 'cash' ? 'cash' : 'bank',
                'is_active' => true,
            ]);
        }

        return $method->id;
    }

    private function getDefaultAccountOrCashRegisterId()
    {
        // Se for dinheiro, usar caixa selecionado ou padrão
        if ($this->payment_method === 'cash') {
            return [
                'cash_register_id' => $this->selected_cash_register_id,
                'account_id' => null,
            ];
        }

        // Se for transferência/banco, usar conta selecionada ou padrão
        return [
            'cash_register_id' => null,
            'account_id' => $this->selected_account_id,
        ];
    }

    private function createTreasuryTransaction($receiptId = null)
    {
        if ($this->amount <= 0) {
            return;
        }

        $accountOrCash = $this->getDefaultAccountOrCashRegisterId();

        Transaction::create([
            'tenant_id' => activeTenantId(),
            'user_id' => auth()->id(),
            'transaction_number' => $this->generateTransactionNumber(),
            'type' => $this->invoiceType === 'sale' ? 'income' : 'expense',
            'category' => $this->invoiceType === 'sale' ? 'customer_payment' : 'supplier_payment',
            'amount' => $this->amount,
            'currency' => 'AOA',
            'transaction_date' => now(),
            'payment_method_id' => $this->getTreasuryPaymentMethodId(),
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

        // Atualizar saldo da conta bancária ou caixa
        $this->updateAccountBalance($accountOrCash);
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
        $year = date('Y');
        
        $lastTransaction = Transaction::where('tenant_id', activeTenantId())
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastTransaction ? ((int) substr($lastTransaction->transaction_number, -4)) + 1 : 1;

        return sprintf('TRX-%s-%04d', $year, $nextNumber);
    }

    public function render()
    {
        return view('livewire.invoicing.payment-modal');
    }
}
