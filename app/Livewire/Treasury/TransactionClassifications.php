<?php

namespace App\Livewire\Treasury;

use App\Models\Treasury\TransactionCategory;
use App\Models\Treasury\TransactionType;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Classificação de Transações')]
class TransactionClassifications extends Component
{
    use WithPagination;

    public string $kind = 'type';
    public string $search = '';
    public bool $showModal = false;
    public bool $showDeleteModal = false;
    public ?int $recordId = null;
    public string $recordToDelete = '';
    public array $form = [];

    public function mount(string $kind = 'type'): void
    {
        abort_unless(in_array($kind, ['type', 'category'], true), 404);
        $this->kind = $kind;
        $this->ensureDefaults();
        $this->resetForm();
    }

    private function ensureDefaults(): void
    {
        TransactionCategory::seedDefaultsForTenant((int) activeTenantId());
    }

    private function resetForm(): void
    {
        $this->form = $this->kind === 'type'
            ? ['name' => '', 'code' => '', 'nature' => 'income', 'description' => '', 'color' => 'blue', 'icon' => 'fa-exchange-alt', 'is_active' => true, 'sort_order' => 0]
            : ['name' => '', 'code' => '', 'transaction_type_id' => '', 'description' => '', 'is_active' => true, 'sort_order' => 0];
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function create(): void
    {
        $this->recordId = null;
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $record = $this->queryModel()->findOrFail($id);
        $this->recordId = $record->id;
        $this->form = $this->kind === 'type'
            ? $record->only(['name', 'code', 'nature', 'description', 'color', 'icon', 'is_active', 'sort_order'])
            : $record->only(['name', 'code', 'transaction_type_id', 'description', 'is_active', 'sort_order']);
        $this->form['transaction_type_id'] ??= '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $table = $this->kind === 'type' ? 'treasury_transaction_types' : 'treasury_transaction_categories';
        $rules = [
            'form.name' => ['required', 'string', 'max:150'],
            'form.code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique($table, 'code')->where('tenant_id', activeTenantId())->ignore($this->recordId)],
            'form.description' => ['nullable', 'string', 'max:1000'],
            'form.is_active' => ['boolean'],
            'form.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
        if ($this->kind === 'type') {
            $rules += ['form.nature' => ['required', Rule::in(['income', 'expense', 'transfer'])], 'form.color' => ['nullable', 'string', 'max:30'], 'form.icon' => ['nullable', 'string', 'max:60']];
        } else {
            $rules['form.transaction_type_id'] = ['nullable', Rule::exists('treasury_transaction_types', 'id')->where('tenant_id', activeTenantId())];
        }
        $this->validate($rules, ['form.code.regex' => 'O código só pode conter letras, números, hífen e underscore.']);

        $data = $this->form + ['tenant_id' => activeTenantId()];
        if ($this->kind === 'category') $data['transaction_type_id'] = $data['transaction_type_id'] ?: null;
        $model = $this->kind === 'type' ? TransactionType::class : TransactionCategory::class;
        $model::updateOrCreate(['id' => $this->recordId, 'tenant_id' => activeTenantId()], $data);

        $this->dispatch('success', message: ($this->kind === 'type' ? 'Tipo' : 'Categoria') . ' guardado(a) com sucesso!');
        $this->closeModal();
    }

    public function toggleStatus(int $id): void
    {
        $record = $this->queryModel()->findOrFail($id);
        $record->update(['is_active' => !$record->is_active]);
    }

    public function confirmDelete(int $id): void
    {
        $record = $this->queryModel()->findOrFail($id);
        $this->recordId = $record->id;
        $this->recordToDelete = $record->name;
        $this->showDeleteModal = true;
    }

    public function deleteRecord(): void
    {
        $record = $this->queryModel()->findOrFail($this->recordId);
        $used = $record->transactions()->exists()
            || ($this->kind === 'type' && $record->categories()->exists());
        if ($used) {
            $record->update(['is_active' => false]);
            $message = 'Já existem movimentos associados; o registo foi desativado para preservar o histórico.';
        } else {
            $record->delete();
            $message = 'Registo eliminado com sucesso!';
        }
        $this->closeDeleteModal();
        $this->dispatch('success', message: $message);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->recordId = null;
        $this->resetForm();
        $this->resetValidation();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->recordId = null;
        $this->recordToDelete = '';
    }

    private function queryModel()
    {
        $model = $this->kind === 'type' ? TransactionType::class : TransactionCategory::class;
        return $model::where('tenant_id', activeTenantId());
    }

    public function render()
    {
        $query = $this->queryModel();
        if ($this->kind === 'category') $query->with('transactionType');
        if ($this->search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"));
        }
        return view('livewire.treasury.transaction-classifications', [
            'records' => $query->orderBy('sort_order')->orderBy('name')->paginate(12),
            'types' => TransactionType::where('tenant_id', activeTenantId())->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
