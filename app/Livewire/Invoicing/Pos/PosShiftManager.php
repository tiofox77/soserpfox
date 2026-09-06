<?php

namespace App\Livewire\Invoicing\Pos;

use App\Services\POS\TurnosDoPos;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O turno do balcão, o ecrã de sempre: abrir, fechar, imprimir o resumo.
 * Tudo o que mexe no turno e na caixa vive na `TurnosDoPos`, partilhada
 * com o ecrã em React.
 */
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

    public $assignedCashRegisterId = null;
    public $assignedCashRegisterName = null;
    public $assignedCashRegisterStatus = null;

    public function mount()
    {
        $this->loadCurrentShift();
        $this->loadAssignedCashRegister();
    }

    private function turnos(): TurnosDoPos
    {
        return new TurnosDoPos((int) activeTenantId(), (int) auth()->id());
    }

    private function loadAssignedCashRegister(): void
    {
        $cash = $this->turnos()->caixaAtribuida();
        $this->assignedCashRegisterId = $cash?->id;
        $this->assignedCashRegisterName = $cash?->name;
        $this->assignedCashRegisterStatus = $cash?->status;
    }

    public function loadCurrentShift()
    {
        $this->currentShift = $this->turnos()->actual();
    }

    public function openShiftModal()
    {
        if ($this->currentShift) {
            $this->dispatch('error', message: __('Você já tem um turno aberto!'));

            return;
        }

        $this->reset(['opening_balance', 'opening_notes']);
        $this->showOpenShiftModal = true;
    }

    public function openShift()
    {
        $this->validate(TurnosDoPos::regrasDeAbertura(), TurnosDoPos::mensagensDeAbertura());

        try {
            $this->currentShift = $this->turnos()->abrir((float) $this->opening_balance, $this->opening_notes ?: null, request()->ip());
            $this->showOpenShiftModal = false;
            $this->loadAssignedCashRegister();

            // O emoji fica FORA da cadeia traduzida: é decoração, igual nas três línguas.
            $this->dispatch('success', message: '✅ ' . __('Turno aberto com sucesso!'));
        } catch (DomainException $e) {
            $this->dispatch('error', message: $e->getMessage());
        } catch (\Exception $e) {
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
        // digitar o dinheiro real, senão a diferença de caixa dá sempre 0,00.
        $this->reset(['actual_cash', 'closing_notes', 'difference_reason']);
        $this->showCloseShiftModal = true;
    }

    public function closeShift()
    {
        $this->validate(TurnosDoPos::regrasDeFecho(), TurnosDoPos::mensagensDeFecho());

        try {
            $fechado = $this->turnos()->fechar((float) $this->actual_cash, $this->closing_notes ?: null, $this->difference_reason ?: null);

            $this->dispatch('success', message: '✅ ' . __('Turno fechado com sucesso!'));
            $this->showCloseShiftModal = false;

            // Disponibilizar opções de impressão/PDF do resumo
            $this->lastClosedShiftId = $fechado->id;
            $this->showAfterCloseModal = true;

            $this->loadCurrentShift();
            $this->loadAssignedCashRegister();
        } catch (DomainException $e) {
            $this->dispatch('error', message: $e->getMessage());
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
