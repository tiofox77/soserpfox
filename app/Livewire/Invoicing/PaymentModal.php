<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\Advance;
use App\Services\Invoicing\RegistoDePagamento;
use Livewire\Component;

/*
 * O REGISTO DO PAGAMENTO VIVE NO `RegistoDePagamento`: o recibo, o movimento
 * de tesouraria, o adiantamento usado, o excedente que vira adiantamento e a
 * AGT depois do commit. Este modal — que sete ecrãs incluem — e a API em
 * React chamam o mesmo.
 */
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
            // Eloquent à cara do utilizador não lhe diz nada — e o que ele
            // precisa é de recarregar, não de perceber Eloquent.
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
        $ctx = app(RegistoDePagamento::class)->contexto((string) $this->invoiceType, (int) $this->invoiceId, activeTenantId());

        $this->invoice = $ctx['factura'];
        $this->available_advances = $ctx['adiantamentos'];
        $this->available_accounts = $ctx['contas'];
        $this->available_cash_registers = $ctx['caixas'];
        $this->selected_account_id = $ctx['conta_padrao'] ?? $this->selected_account_id;
        $this->selected_cash_register_id = $ctx['caixa_padrao'] ?? $this->selected_cash_register_id;

        $this->total_due = $ctx['por_pagar'];
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

        try {
            $r = app(RegistoDePagamento::class)->registar((string) $this->invoiceType, (int) $this->invoiceId, [
                'amount' => $this->amount,
                'payment_method' => $this->payment_method,
                'account_id' => $this->selected_account_id,
                'cash_register_id' => $this->selected_cash_register_id,
                'reference' => $this->reference,
                'notes' => $this->notes,
                'advance_id' => $this->use_advance ? $this->advance_id : null,
                'advance_amount' => $this->use_advance ? $this->advance_amount : 0,
            ], activeTenantId(), auth()->id());
        } catch (\Exception $e) {
            \Log::error('Erro ao registrar pagamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('❌ Erro ao registrar pagamento: :detalhe', ['detalhe' => $e->getMessage()])]);

            return;
        }

        $this->invoice = $r['factura'];

        \Log::info('Pagamento registrado com sucesso', [
            'invoice_id' => $this->invoiceId,
            'new_status' => $this->invoice->status,
        ]);

        $message = '✅ Pagamento registrado com sucesso! Status: ' . $this->invoice->status_label
            . ($r['aviso_agt'] ? ' | ' . $r['aviso_agt'] : '');

        if ($r['adiantamento_criado']) {
            $message .= ' | 💰 Adiantamento de ' . number_format($r['excedente'], 2) . ' AOA criado automaticamente!';
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message
        ]);

        $this->dispatch('paymentRegistered');
        $this->close();
    }

    public function close()
    {
        $this->show = false;
        $this->reset(['amount', 'payment_method', 'reference', 'notes', 'use_advance', 'advance_id', 'advance_amount']);
    }

    public function render()
    {
        return view('livewire.invoicing.payment-modal');
    }
}
