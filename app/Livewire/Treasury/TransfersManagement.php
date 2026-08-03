<?php

namespace App\Livewire\Treasury;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use App\Models\Treasury\Transfer;
use App\Models\Treasury\Transaction;
use App\Models\Treasury\Account;
use App\Models\Treasury\CashRegister;

#[Layout('layouts.app')]
#[Title('Transferências')]
class TransfersManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $showDeleteModal = false;
    public $transferToDelete = null;

    // Form ("account:ID" ou "cash:ID")
    public $fromSelection = '';
    public $toSelection = '';
    public $amount = '';
    public $fee = 0;
    public $transfer_date;
    public $currency = 'AOA';
    public $description = '';
    public $reference = '';

    public function mount()
    {
        $this->transfer_date = now()->format('Y-m-d');
    }

    protected function rules()
    {
        return [
            'fromSelection' => 'required|string',
            'toSelection' => 'required|string|different:fromSelection',
            'amount' => 'required|numeric|min:0.01',
            'fee' => 'nullable|numeric|min:0',
            'transfer_date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ];
    }

    protected $messages = [
        'toSelection.different' => 'A conta de destino deve ser diferente da origem.',
    ];

    public function create()
    {
        $this->reset(['fromSelection', 'toSelection', 'amount', 'fee', 'description', 'reference']);
        $this->fee = 0;
        $this->transfer_date = now()->format('Y-m-d');
        $this->resetValidation();
        $this->showModal = true;
    }

    /** Divide "account:5" → ['account_id'=>5] / "cash:3" → ['cash_register_id'=>3] */
    private function parseSelection(string $sel): array
    {
        [$type, $id] = array_pad(explode(':', $sel), 2, null);
        return $type === 'cash'
            ? ['cash_register_id' => (int) $id, 'account_id' => null]
            : ['account_id' => (int) $id, 'cash_register_id' => null];
    }

    private function selectionName(string $sel): string
    {
        $p = $this->parseSelection($sel);
        if (!empty($p['account_id'])) {
            return optional(Account::find($p['account_id']))->account_name ?? 'Conta';
        }
        return optional(CashRegister::find($p['cash_register_id']))->name ?? 'Caixa';
    }

    private function nextTransferNumber(int $tenantId): string
    {
        $last = Transfer::where('tenant_id', $tenantId)->whereYear('created_at', date('Y'))->orderByDesc('id')->first();
        $n = $last ? ((int) substr($last->transfer_number, -4)) + 1 : 1;
        return 'TRF-' . date('Y') . '-' . str_pad($n, 4, '0', STR_PAD_LEFT);
    }

    private function nextTransactionBase(int $tenantId): int
    {
        $last = Transaction::where('tenant_id', $tenantId)->whereYear('created_at', date('Y'))->orderByDesc('id')->first();
        return $last ? ((int) substr($last->transaction_number, -4)) + 1 : 1;
    }

    public function save()
    {
        $this->validate();
        $tenantId = activeTenantId();
        $from = $this->parseSelection($this->fromSelection);
        $to = $this->parseSelection($this->toSelection);
        $amount = (float) $this->amount;
        $fee = (float) ($this->fee ?: 0);

        DB::transaction(function () use ($tenantId, $from, $to, $amount, $fee) {
            $number = $this->nextTransferNumber($tenantId);
            $fromName = $this->selectionName($this->fromSelection);
            $toName = $this->selectionName($this->toSelection);

            $transfer = Transfer::create([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'from_account_id' => $from['account_id'],
                'from_cash_register_id' => $from['cash_register_id'],
                'to_account_id' => $to['account_id'],
                'to_cash_register_id' => $to['cash_register_id'],
                'transfer_number' => $number,
                'amount' => $amount,
                'currency' => $this->currency,
                'fee' => $fee,
                'transfer_date' => $this->transfer_date,
                'description' => $this->description,
                'reference' => $this->reference,
                'status' => 'completed',
            ]);

            $base = $this->nextTransactionBase($tenantId);
            $mkTx = function (array $target, string $type, float $amt, string $desc, string $category) use ($tenantId, $transfer, &$base) {
                Transaction::create([
                    'tenant_id' => $tenantId,
                    'user_id' => auth()->id(),
                    'account_id' => $target['account_id'] ?? null,
                    'cash_register_id' => $target['cash_register_id'] ?? null,
                    'transaction_number' => 'TRX-' . date('Y') . '-' . str_pad($base++, 4, '0', STR_PAD_LEFT),
                    'type' => $type,
                    'category' => $category,
                    'amount' => $amt,
                    'currency' => $this->currency,
                    'transaction_date' => $this->transfer_date,
                    'reference' => $transfer->transfer_number,
                    'related_type' => Transfer::class,
                    'related_id' => $transfer->id,
                    'description' => $desc,
                    'status' => 'completed',
                ]);
            };

            // Saída na origem + entrada no destino
            $mkTx($from, 'expense', $amount, "Transferência {$transfer->transfer_number} → {$toName}", 'transfer');
            $mkTx($to, 'income', $amount, "Transferência {$transfer->transfer_number} ← {$fromName}", 'transfer');
            if ($fee > 0) {
                $mkTx($from, 'expense', $fee, "Taxa da transferência {$transfer->transfer_number}", 'transfer_fee');
            }

            // Atualizar saldos: origem − (amount + fee) ; destino + amount
            $this->applyBalance($from, -($amount + $fee));
            $this->applyBalance($to, $amount);
        });

        $this->showModal = false;
        $this->dispatch('success', message: 'Transferência registada com sucesso!');
    }

    private function applyBalance(array $target, float $delta): void
    {
        if (!empty($target['account_id'])) {
            Account::where('id', $target['account_id'])->increment('current_balance', $delta);
        } elseif (!empty($target['cash_register_id'])) {
            CashRegister::where('id', $target['cash_register_id'])->increment('current_balance', $delta);
        }
    }

    public function confirmDelete($id)
    {
        $this->transferToDelete = Transfer::where('tenant_id', activeTenantId())->findOrFail($id);
        $this->showDeleteModal = true;
    }

    public function deleteTransfer()
    {
        if (!$this->transferToDelete) {
            return;
        }
        $t = $this->transferToDelete;

        DB::transaction(function () use ($t) {
            // Reverter saldos
            $from = ['account_id' => $t->from_account_id, 'cash_register_id' => $t->from_cash_register_id];
            $to = ['account_id' => $t->to_account_id, 'cash_register_id' => $t->to_cash_register_id];
            $this->applyBalance($from, (float) $t->amount + (float) $t->fee);
            $this->applyBalance($to, -((float) $t->amount));

            // Apagar transações ligadas + a transferência
            Transaction::where('tenant_id', $t->tenant_id)
                ->where('related_type', Transfer::class)
                ->where('related_id', $t->id)
                ->delete();
            $t->delete();
        });

        $this->showDeleteModal = false;
        $this->transferToDelete = null;
        $this->dispatch('success', message: 'Transferência anulada e saldos revertidos.');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showDeleteModal = false;
        $this->transferToDelete = null;
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $transfers = Transfer::with(['fromAccount', 'toAccount', 'fromCashRegister', 'toCashRegister', 'user'])
            ->where('tenant_id', $tenantId)
            ->when($this->search, fn ($q) => $q->where(function ($sub) {
                $sub->where('transfer_number', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%')
                    ->orWhere('reference', 'like', '%' . $this->search . '%');
            }))
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->paginate(15);

        $accounts = Account::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('account_name')->get();
        $cashRegisters = CashRegister::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get();

        $stats = [
            'count' => Transfer::where('tenant_id', $tenantId)->count(),
            'total' => (float) Transfer::where('tenant_id', $tenantId)->sum('amount'),
        ];

        return view('livewire.treasury.transfers.transfers', compact('transfers', 'accounts', 'cashRegisters', 'stats'));
    }
}
