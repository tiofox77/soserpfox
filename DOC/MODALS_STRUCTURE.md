# Estrutura de Modals - Reorganização

## ✅ Implementado

### **Tenants**
- ✅ `resources/views/livewire/super-admin/tenants/partials/form-modal.blade.php`
- ✅ `resources/views/livewire/super-admin/tenants/partials/delete-modal.blade.php`
- ✅ `resources/views/livewire/super-admin/tenants/partials/view-modal.blade.php`
- ✅ View principal atualizada com `@include`

### **Modules**
- ✅ `resources/views/livewire/super-admin/modules/partials/form-modal.blade.php`
- ⚠️ **FALTA**: Criar delete-modal.blade.php
- ⚠️ **FALTA**: Criar view-modal.blade.php
- ⚠️ **FALTA**: Atualizar view principal

---

## 📋 Estrutura dos Arquivos

### **Form Modal** (Create & Edit)
- Arquivo: `form-modal.blade.php`
- Usado para criar e editar
- Verifica variável: `$showModal`
- Verifica se é edição: `$editingXxxId`
- Gradiente no header: Azul/Roxo (Tenants), Roxo/Rosa (Modules), Verde/Teal (Plans), Laranja/Vermelho (Billing)

### **Delete Modal** (Confirmação)
- Arquivo: `delete-modal.blade.php`
- Confirmação de exclusão
- Verifica variável: `$showDeleteModal`
- Gradiente vermelho/rosa no header
- Mostra nome do item a ser excluído: `$deletingXxxName`
- Botão de confirmar: `confirmDelete()`

### **View Modal** (Visualização de Detalhes)
- Arquivo: `view-modal.blade.php`
- Mostra detalhes completos
- Verifica variável: `$showViewModal`
- Objeto completo: `$viewingXxx`
- Gradiente azul/roxo no header
- Botão para editar: `editFromView()`

---

## 🔧 Para Completar

### **1. Modules** (Restantes)

**delete-modal.blade.php:**
- Copiar estrutura do Tenants delete-modal
- Alterar referências de Tenant para Module
- Verificar `$deletingModuleName`
- Adicionar verificação se é Core (não pode excluir)

**view-modal.blade.php:**
- Mostrar: Nome, Slug, Descrição, Ícone, Versão, Ordem
- Badge se é Core
- Badge se está Ativo
- Mostrar dependências se houver
- Mostrar quantos tenants usam

**Atualizar modules.blade.php:**
```blade
<!-- Modals -->
@include('livewire.super-admin.modules.partials.form-modal')
@include('livewire.super-admin.modules.partials.delete-modal')
@include('livewire.super-admin.modules.partials.view-modal')
```

---

### **2. Plans** (Todos)

**form-modal.blade.php:**
- Campos: Nome, Slug, Descrição, Preço Mensal, Preço Anual
- Máx Utilizadores, Storage, Trial Days, Ordem
- Gestão de Features (adicionar/remover lista)
- Checkboxes: is_active, is_featured
- Gradiente: Verde/Teal

**delete-modal.blade.php:**
- Verificar se tem subscrições ativas
- Mostrar aviso se não puder excluir
- Variável: `$showDeleteModal`, `$deletingPlanName`

**view-modal.blade.php:**
- Mostrar todas as informações do plano
- Lista de Features
- Cálculo de economia anual
- Quantas subscrições ativas tem

**Atualizar plans.blade.php:**
```blade
<!-- Modals -->
@include('livewire.super-admin.plans.partials.form-modal')
@include('livewire.super-admin.plans.partials.delete-modal')
@include('livewire.super-admin.plans.partials.view-modal')
```

---

### **3. Billing** (Todos)

**form-modal.blade.php:**
- Select de Tenant
- Campos: Invoice Number, Description, Status
- Data Emissão, Data Vencimento
- Subtotal, Tax, Total (calculado automaticamente)
- Gradiente: Laranja/Vermelho

**delete-modal.blade.php:**
- Confirmação simples
- Mostrar número da fatura
- Variável: `$showDeleteModal`, `$deletingInvoiceNumber`

**view-modal.blade.php:**
- Mostrar todos os detalhes da fatura
- Tenant associado
- Status com cores
- Timeline de datas
- Valores detalhados
- Botão para marcar como paga (se pending)

**Atualizar billing.blade.php:**
```blade
<!-- Modals -->
@include('livewire.super-admin.billing.partials.form-modal')
@include('livewire.super-admin.billing.partials.delete-modal')
@include('livewire.super-admin.billing.partials.view-modal')
```

---

## 🎨 Paleta de Cores dos Headers

- **Tenants**: `from-blue-600 to-purple-600`
- **Modules**: `from-purple-600 to-pink-600`
- **Plans**: `from-green-600 to-teal-600`
- **Billing**: `from-orange-600 to-red-600`
- **Delete (Todos)**: `from-red-600 to-pink-600`

---

## 📦 Variáveis Necessárias nos Components Livewire

### Para cada área adicionar:

```php
// Modal de Formulário
public $showModal = false;

// Modal de Delete
public $showDeleteModal = false;
public $deletingXxxId = null;
public $deletingXxxName = '';

// Modal de View
public $showViewModal = false;
public $viewingXxx = null;

// Métodos adicionais necessários:
public function openDeleteModal($id) {
    $item = Model::findOrFail($id);
    $this->deletingXxxId = $id;
    $this->deletingXxxName = $item->name;
    $this->showDeleteModal = true;
}

public function closeDeleteModal() {
    $this->showDeleteModal = false;
    $this->deletingXxxId = null;
    $this->deletingXxxName = '';
}

public function confirmDelete() {
    $this->delete($this->deletingXxxId);
    $this->closeDeleteModal();
}

public function viewDetails($id) {
    $this->viewingXxx = Model::findOrFail($id);
    $this->showViewModal = true;
}

public function closeViewModal() {
    $this->showViewModal = false;
    $this->viewingXxx = null;
}

public function editFromView() {
    $this->edit($this->viewingXxx->id);
    $this->showViewModal = false;
}
```

---

## ✨ Benefícios da Reorganização

1. **Código Modular**: Cada modal em seu próprio arquivo
2. **Reutilização**: Fácil copiar estrutura para novas áreas
3. **Manutenção**: Mais fácil encontrar e editar modals
4. **Organização**: Estrutura clara de pastas `/partials/`
5. **Separação de Responsabilidades**: Create/Edit, Delete, View separados
6. **Facilita Testes**: Testar cada modal individualmente

---

## 🚀 Próximos Passos

1. Criar arquivos restantes para Modules
2. Criar todos os arquivos para Plans
3. Criar todos os arquivos para Billing
4. Atualizar components Livewire com métodos necessários
5. Atualizar botões nas listagens para abrir modals corretas
6. Testar todas as modals

---

## 📝 Template Rápido para Delete Modal

```blade
@if($showDeleteModal ?? false)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showDeleteModal') }" x-show="show" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity backdrop-blur-sm" wire:click="closeDeleteModal"></div>
            
            <div class="relative bg-white rounded-2xl max-w-md w-full shadow-2xl transform transition-all" @click.stop>
                <div class="bg-gradient-to-r from-red-600 to-pink-600 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center text-white">
                        <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-exclamation-triangle text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold">Confirmar Exclusão</h3>
                    </div>
                    <button wire:click="closeDeleteModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="p-6">
                    <div class="flex items-start space-x-4">
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-trash text-red-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-lg font-bold text-gray-900 mb-2">Tem certeza?</h4>
                            <p class="text-sm text-gray-600">Esta ação não pode ser desfeita.</p>
                            @if($deletingXxxName ?? false)
                                <div class="mt-3 p-3 bg-red-50 rounded-lg">
                                    <p class="text-sm font-semibold text-red-800">{{ $deletingXxxName }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button wire:click="closeDeleteModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button wire:click="confirmDelete" class="px-6 py-2.5 bg-gradient-to-r from-red-600 to-pink-600 text-white rounded-xl font-semibold hover:from-red-700 hover:to-pink-700 shadow-lg hover:shadow-xl transition">
                            <i class="fas fa-trash mr-2"></i>Sim, Excluir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
```
