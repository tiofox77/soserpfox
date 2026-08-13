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

    // Pós-fecho: turno fechado mais recente para impressão/PDF
    public $lastClosedShiftId = null;
    public $showAfterCloseModal = false;
    
    // Abertura de turno
    public $opening_balance = 0;
    public $opening_notes = '';
    
    // Fechamento de turno
    public $actual_cash = null;   // null → o operador tem de digitar o valor contado
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
            $this->dispatch('error', message: __('Você já tem um turno aberto!'));
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
            'opening_balance.required' => __('Informe o saldo inicial'),
            'opening_balance.numeric' => __('O saldo deve ser um número'),
            'opening_balance.min' => __('O saldo não pode ser negativo'),
        ]);

        try {
            $tenantId = activeTenantId();
            $shift = PosShift::createSafely([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'status' => 'open',
                'opened_at' => now(),
                'opening_balance' => $this->opening_balance,
                'opening_notes' => $this->opening_notes,
                'opened_ip' => request()->ip(),
            ], $tenantId);

            $this->currentShift = $shift;
            $this->showOpenShiftModal = false;
            // O emoji fica FORA da cadeia traduzida: é decoração, igual nas três
            // línguas, e metê-lo dentro da chave obrigava cada tradutor a copiá-lo
            // à mão — um ✅ perdido bastaria para a tradução deixar de casar.
            $this->dispatch('success', message: '✅ ' . __('Turno aberto com sucesso!'));
        } catch (\Exception $e) {
            // Nunca 'texto ' . $e->getMessage(): a ordem das palavras muda de língua
            // para língua, e a mensagem do erro tem de poder ir para outro sítio da frase.
            $this->dispatch('error', message: '❌ ' . __('Erro ao abrir turno: :erro', ['erro' => $e->getMessage()]));
        }
    }

    public function closeShiftModal()
    {
        if (!$this->currentShift) {
            $this->dispatch('error', message: __('Não há turno aberto!'));
            return;
        }

        // NÃO pré-preencher com o valor esperado: o operador tem de contar e
        // digitar o dinheiro real, senão a diferença de caixa dá sempre 0,00 e
        // quebras/excessos nunca são detetados. O esperado continua visível no
        // resumo do modal.
        $this->reset(['actual_cash', 'closing_notes', 'difference_reason']);
        $this->showCloseShiftModal = true;
    }

    public function closeShift()
    {
        $this->validate([
            'actual_cash' => 'required|numeric|min:0',
        ], [
            'actual_cash.required' => __('Informe o valor em dinheiro contado'),
            'actual_cash.numeric' => __('O valor deve ser um número'),
        ]);

        try {
            $closedShiftId = $this->currentShift->id;
            $this->currentShift->close(
                $this->actual_cash,
                $this->closing_notes,
                $this->difference_reason
            );

            $this->dispatch('success', message: '✅ ' . __('Turno fechado com sucesso!'));
            $this->showCloseShiftModal = false;

            // Disponibilizar opções de impressão/PDF do resumo
            $this->lastClosedShiftId = $closedShiftId;
            $this->showAfterCloseModal = true;

            $this->loadCurrentShift();
        } catch (\Exception $e) {
            $this->dispatch('error', message: '❌ ' . __('Erro ao fechar turno: :erro', ['erro' => $e->getMessage()]));
        }
    }

    public function closeAfterCloseModal()
    {
        $this->showAfterCloseModal = false;
        $this->lastClosedShiftId = null;
    }

    public function render()
    {
        return view('livewire.invoicing.pos.pos-shift-manager');
    }
}
