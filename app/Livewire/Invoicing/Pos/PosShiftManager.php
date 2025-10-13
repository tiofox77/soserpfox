<?php

namespace App\Livewire\Invoicing\Pos;

use App\Models\Invoicing\PosShift;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('POS - Ponto de Venda')]
class PosShiftManager extends Component
{
    public $currentShift = null;
    public $showOpenShiftModal = false;
    public $showCloseShiftModal = false;
    
    // Abertura de turno
    public $opening_balance = 0;
    public $opening_notes = '';
    
    // Fechamento de turno
    public $actual_cash = 0;
    public $closing_notes = '';
    public $difference_reason = '';

    public function mount()
    {
        $this->loadCurrentShift();
    }

    public function loadCurrentShift()
    {
        $this->currentShift = PosShift::where('tenant_id', activeTenantId())
            ->where('user_id', auth()->id())
            ->where('status', 'open')
            ->with(['transactions'])
            ->first();
    }

    public function openShiftModal()
    {
        // Verificar se já tem turno aberto
        if ($this->currentShift) {
            $this->dispatch('error', message: 'Você já tem um turno aberto!');
            return;
        }

        $this->reset(['opening_balance', 'opening_notes']);
        $this->showOpenShiftModal = true;
    }

    public function openShift()
    {
        $this->validate([
            'opening_balance' => 'required|numeric|min:0',
        ], [
            'opening_balance.required' => 'Informe o saldo inicial',
            'opening_balance.numeric' => 'O saldo deve ser um número',
            'opening_balance.min' => 'O saldo não pode ser negativo',
        ]);

        try {
            $shift = PosShift::create([
                'tenant_id' => activeTenantId(),
                'user_id' => auth()->id(),
                'shift_number' => PosShift::generateShiftNumber(),
                'status' => 'open',
                'opened_at' => now(),
                'opening_balance' => $this->opening_balance,
                'opening_notes' => $this->opening_notes,
                'opened_ip' => request()->ip(),
            ]);

            $this->currentShift = $shift;
            $this->showOpenShiftModal = false;
            $this->dispatch('success', message: '✅ Turno aberto com sucesso!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: '❌ Erro ao abrir turno: ' . $e->getMessage());
        }
    }

    public function closeShiftModal()
    {
        if (!$this->currentShift) {
            $this->dispatch('error', message: 'Não há turno aberto!');
            return;
        }

        $this->actual_cash = $this->currentShift->opening_balance + $this->currentShift->cash_sales;
        $this->reset(['closing_notes', 'difference_reason']);
        $this->showCloseShiftModal = true;
    }

    public function closeShift()
    {
        $this->validate([
            'actual_cash' => 'required|numeric|min:0',
        ], [
            'actual_cash.required' => 'Informe o valor em dinheiro contado',
            'actual_cash.numeric' => 'O valor deve ser um número',
        ]);

        try {
            $this->currentShift->close(
                $this->actual_cash,
                $this->closing_notes,
                $this->difference_reason
            );

            $this->dispatch('success', message: '✅ Turno fechado com sucesso!');
            $this->showCloseShiftModal = false;
            $this->loadCurrentShift();
        } catch (\Exception $e) {
            $this->dispatch('error', message: '❌ Erro ao fechar turno: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.invoicing.pos.pos-shift-manager');
    }
}
