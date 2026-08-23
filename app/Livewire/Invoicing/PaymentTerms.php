<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\PaymentTerm;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Gestão do catálogo de condições de pagamento da empresa.
 *
 * O utilizador cria as suas (pronto pagamento, 15 dias, 30 dias, depósito, …);
 * cada cliente aponta para uma delas e o nº de dias alimenta o vencimento.
 */
#[Layout('layouts.app')]
#[Title('Condições de Pagamento')]
class PaymentTerms extends Component
{
    public $showModal = false;
    public $editingId = null;

    public $name = '';
    public $days = 0;
    public $is_default = false;
    public $is_active = true;

    public function mount()
    {
        PaymentTerm::provisionarPadroes(activeTenantId());
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'days' => 'required|integer|min:0|max:3650',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function novo()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function editar($id)
    {
        $t = PaymentTerm::where('tenant_id', activeTenantId())->findOrFail($id);
        $this->editingId = $t->id;
        $this->name = $t->name;
        $this->days = $t->days;
        $this->is_default = $t->is_default;
        $this->is_active = $t->is_active;
        $this->showModal = true;
    }

    public function guardar()
    {
        $this->validate();
        $tenantId = activeTenantId();

        // Nome único por empresa (excepto a própria em edição).
        $duplicado = PaymentTerm::where('tenant_id', $tenantId)
            ->where('name', $this->name)
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();
        if ($duplicado) {
            $this->addError('name', __('Já existe uma condição com esse nome.'));
            return;
        }

        $dados = [
            'name' => $this->name,
            'days' => (int) $this->days,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
        ];

        if ($this->editingId) {
            $t = PaymentTerm::where('tenant_id', $tenantId)->findOrFail($this->editingId);
            $t->update($dados);
        } else {
            $dados['tenant_id'] = $tenantId;
            $dados['sort_order'] = (int) (PaymentTerm::where('tenant_id', $tenantId)->max('sort_order')) + 1;
            $t = PaymentTerm::create($dados);
        }

        // Só uma pode ser a padrão.
        if ($t->is_default) {
            PaymentTerm::where('tenant_id', $tenantId)
                ->where('id', '!=', $t->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Condição de pagamento guardada.')]);
    }

    public function alternarActiva($id)
    {
        $t = PaymentTerm::where('tenant_id', activeTenantId())->findOrFail($id);
        $t->update(['is_active' => !$t->is_active]);
    }

    public function eliminar($id)
    {
        $t = PaymentTerm::where('tenant_id', activeTenantId())->findOrFail($id);
        // A FK em invoicing_clients é ON DELETE SET NULL: os clientes ficam sem
        // condição, não se perde nada crítico.
        $t->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Condição de pagamento eliminada.')]);
    }

    private function resetForm()
    {
        $this->reset(['editingId', 'name', 'days', 'is_default']);
        $this->is_active = true;
        $this->days = 0;
    }

    public function render()
    {
        $terms = PaymentTerm::where('tenant_id', activeTenantId())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('livewire.invoicing.payment-terms', compact('terms'));
    }
}
