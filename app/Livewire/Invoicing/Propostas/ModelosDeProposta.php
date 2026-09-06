<?php

namespace App\Livewire\Invoicing\Propostas;

use App\Services\Invoicing\Propostas\GestaoDeModelos;
use App\Services\Invoicing\Propostas\ModelosDeArranque;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A lista de modelos de proposta da empresa, e a porta para os criar — o
 * ecrã de sempre. Tudo grava pela `GestaoDeModelos`, partilhada com o React.
 */
#[Layout('layouts.app')]
#[Title('Modelos de Proposta')]
class ModelosDeProposta extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $mostrarNovo = false;

    public function mount(): void
    {
        if (!auth()->user()->can('invoicing.sales.quotes.view')) {
            abort(403);
        }
    }

    private function gestao(): GestaoDeModelos
    {
        return new GestaoDeModelos((int) activeTenantId());
    }

    public function criarDeArranque(string $chave)
    {
        if (!auth()->user()->can('invoicing.sales.quotes.create')) {
            $this->dispatch('error', message: __('Sem permissão para criar modelos.'));

            return null;
        }

        try {
            $modelo = $this->gestao()->criarDeArranque($chave, auth()->id());
        } catch (DomainException $e) {
            $this->dispatch('error', message: $e->getMessage());

            return null;
        }

        return redirect()->route('invoicing.sales.quote-templates.edit', $modelo->id);
    }

    public function criarVazio()
    {
        if (!auth()->user()->can('invoicing.sales.quotes.create')) {
            $this->dispatch('error', message: __('Sem permissão para criar modelos.'));

            return null;
        }

        $modelo = $this->gestao()->criarVazio(auth()->id());

        return redirect()->route('invoicing.sales.quote-templates.edit', $modelo->id);
    }

    public function duplicar(int $id)
    {
        if (!auth()->user()->can('invoicing.sales.quotes.create')) {
            $this->dispatch('error', message: __('Sem permissão para criar modelos.'));

            return null;
        }

        try {
            $copia = $this->gestao()->duplicar($id, auth()->id());
        } catch (DomainException $e) {
            $this->dispatch('error', message: $e->getMessage());

            return null;
        }

        return redirect()->route('invoicing.sales.quote-templates.edit', $copia->id);
    }

    public function tornarPadrao(int $id): void
    {
        if (!auth()->user()->can('invoicing.sales.quotes.edit')) {
            $this->dispatch('error', message: __('Sem permissão para editar modelos.'));

            return;
        }

        try {
            $modelo = $this->gestao()->tornarPadrao($id);
        } catch (DomainException $e) {
            $this->dispatch('error', message: $e->getMessage());

            return;
        }

        $this->dispatch('success', message: __('":nome" passa a ser o modelo padrão.', ['nome' => $modelo->nome]));
    }

    public function eliminar(int $id): void
    {
        if (!auth()->user()->can('invoicing.sales.quotes.delete')) {
            $this->dispatch('error', message: __('Sem permissão para eliminar modelos.'));

            return;
        }

        try {
            $this->gestao()->eliminar($id);
        } catch (DomainException $e) {
            $this->dispatch('error', message: $e->getMessage());

            return;
        }

        $this->dispatch('success', message: __('Modelo eliminado.'));
    }

    public function render()
    {
        return view('livewire.invoicing.propostas.modelos-de-proposta', [
            'modelos' => $this->gestao()->lista($this->search)->paginate(12),
            'arranque' => ModelosDeArranque::catalogo(),
        ]);
    }
}
