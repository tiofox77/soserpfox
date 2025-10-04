# Implementação de Modais CRUD e Notificações Toastr

## ✅ O que foi implementado

### 1. **Sistema de Notificações Toastr**
- ✅ Toastr CSS e JS adicionados no layout (`layouts/superadmin.blade.php`)
- ✅ Configuração completa com estilos personalizados
- ✅ Listeners Livewire para notificações (`success`, `error`, `warning`, `info`)
- ✅ Integração com flash messages do Laravel

### 2. **Tenants - CRUD Completo**
- ✅ Componente Livewire atualizado (`Tenants.php`)
- ✅ Modal moderna com gradiente azul/roxo
- ✅ Notificações Toastr implementadas
- ✅ Funções: Create, Edit, Delete, ToggleStatus
- ✅ Design responsivo com ícones coloridos

### 3. **Modules - CRUD Completo**
- ✅ Componente Livewire atualizado (`Modules.php`)
- ✅ Modal moderna com gradiente roxo/rosa
- ✅ Notificações Toastr implementadas
- ✅ Funções: Create, Edit, Delete, ToggleStatus
- ✅ Proteção para módulos Core
- ✅ Botão de criar no header

### 4. **Plans - CRUD Completo**
- ✅ Componente Livewire atualizado (`Plans.php`)
- ✅ Funções: Create, Edit, Delete, ToggleStatus
- ✅ Gestão de Features (adicionar/remover)
- ✅ Notificações Toastr implementadas
- ⚠️ **FALTA**: Adicionar modal na view

### 5. **Billing - CRUD Completo**
- ✅ Componente Livewire atualizado (`Billing.php`)
- ✅ Funções: Create, Edit, Delete, MarkAsPaid
- ✅ Cálculo automático de totais
- ✅ Notificações Toastr implementadas
- ⚠️ **FALTA**: Adicionar modal na view

---

## 🔧 Modais Restantes para Implementar

### Modal para Plans

Adicionar no final de `plans.blade.php` antes do `</div>` final:

```blade
<!-- Modal Plans -->
@if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity backdrop-blur-sm" wire:click="closeModal"></div>
            
            <div class="relative bg-white rounded-2xl max-w-3xl w-full shadow-2xl transform transition-all" @click.stop>
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-green-600 to-teal-600 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center text-white">
                        <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-tags text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold">
                            {{ $editingPlanId ? 'Editar Plano' : 'Novo Plano' }}
                        </h3>
                    </div>
                    <button wire:click="closeModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Modal Body -->
                <form wire:submit.prevent="save" class="p-6 max-h-[70vh] overflow-y-auto">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag text-green-500 mr-2"></i>Nome *
                            </label>
                            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            @error('name') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-link text-teal-500 mr-2"></i>Slug *
                            </label>
                            <input wire:model="slug" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent transition">
                            @error('slug') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-euro-sign text-green-500 mr-2"></i>Preço Mensal *
                            </label>
                            <input wire:model="price_monthly" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            @error('price_monthly') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-euro-sign text-blue-500 mr-2"></i>Preço Anual *
                            </label>
                            <input wire:model="price_yearly" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            @error('price_yearly') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-users text-orange-500 mr-2"></i>Máx. Utilizadores *
                            </label>
                            <input wire:model="max_users" type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            @error('max_users') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-database text-purple-500 mr-2"></i>Storage (MB) *
                            </label>
                            <input wire:model="max_storage_mb" type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                            @error('max_storage_mb') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-gift text-pink-500 mr-2"></i>Trial (dias) *
                            </label>
                            <input wire:model="trial_days" type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                            @error('trial_days') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-sort text-gray-500 mr-2"></i>Ordem *
                            </label>
                            <input wire:model="order" type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-gray-500 focus:border-transparent transition">
                            @error('order') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-align-left text-gray-500 mr-2"></i>Descrição *
                            </label>
                            <textarea wire:model="description" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition"></textarea>
                            @error('description') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <!-- Features -->
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-list text-blue-500 mr-2"></i>Funcionalidades
                            </label>
                            <div class="flex space-x-2 mb-3">
                                <input wire:model="newFeature" wire:keydown.enter.prevent="addFeature" type="text" placeholder="Digite uma funcionalidade" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                <button type="button" wire:click="addFeature" class="px-4 py-2.5 bg-blue-500 text-white rounded-xl hover:bg-blue-600 transition">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="space-y-2">
                                @foreach($features as $index => $feature)
                                    <div class="flex items-center justify-between px-4 py-2 bg-gray-50 rounded-lg">
                                        <span class="text-sm text-gray-700"><i class="fas fa-check text-green-500 mr-2"></i>{{ $feature }}</span>
                                        <button type="button" wire:click="removeFeature({{ $index }})" class="text-red-500 hover:text-red-700">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        
                        <div class="col-span-2 flex space-x-4">
                            <label class="flex-1 flex items-center px-4 py-3 bg-green-50 rounded-xl cursor-pointer hover:bg-green-100 transition">
                                <input wire:model="is_active" type="checkbox" class="rounded border-gray-300 text-green-600 focus:ring-green-500 w-5 h-5">
                                <span class="ml-3 text-sm font-semibold text-gray-700">
                                    <i class="fas fa-power-off text-green-500 mr-2"></i>Ativo
                                </span>
                            </label>
                            
                            <label class="flex-1 flex items-center px-4 py-3 bg-blue-50 rounded-xl cursor-pointer hover:bg-blue-100 transition">
                                <input wire:model="is_featured" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-5 h-5">
                                <span class="ml-3 text-sm font-semibold text-gray-700">
                                    <i class="fas fa-star text-blue-500 mr-2"></i>Destaque
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-green-600 to-teal-600 text-white rounded-xl font-semibold hover:from-green-700 hover:to-teal-700 shadow-lg hover:shadow-xl transition">
                            <i class="fas {{ $editingPlanId ? 'fa-save' : 'fa-plus' }} mr-2"></i>
                            {{ $editingPlanId ? 'Atualizar' : 'Criar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
```

### Modal para Billing

Adicionar no final de `billing.blade.php` antes do `</div>` final:

```blade
<!-- Modal Billing -->
@if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity backdrop-blur-sm" wire:click="closeModal"></div>
            
            <div class="relative bg-white rounded-2xl max-w-2xl w-full shadow-2xl transform transition-all" @click.stop>
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-orange-600 to-red-600 rounded-t-2xl px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center text-white">
                        <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-file-invoice-dollar text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold">
                            {{ $editingInvoiceId ? 'Editar Fatura' : 'Nova Fatura' }}
                        </h3>
                    </div>
                    <button wire:click="closeModal" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Modal Body -->
                <form wire:submit.prevent="save" class="p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-building text-blue-500 mr-2"></i>Tenant *
                            </label>
                            <select wire:model="tenant_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                                <option value="">Selecione um tenant</option>
                                @foreach($tenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                            @error('tenant_id') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-hashtag text-purple-500 mr-2"></i>Nº Fatura *
                            </label>
                            <input wire:model="invoice_number" type="text" placeholder="INV-2025-001" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                            @error('invoice_number') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag text-orange-500 mr-2"></i>Status *
                            </label>
                            <select wire:model="status" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                                <option value="pending">Pendente</option>
                                <option value="paid">Pago</option>
                                <option value="overdue">Atrasado</option>
                                <option value="cancelled">Cancelado</option>
                            </select>
                            @error('status') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar text-green-500 mr-2"></i>Data Emissão *
                            </label>
                            <input wire:model="invoice_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                            @error('invoice_date') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar-times text-red-500 mr-2"></i>Data Vencimento *
                            </label>
                            <input wire:model="due_date" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition">
                            @error('due_date') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-align-left text-gray-500 mr-2"></i>Descrição *
                            </label>
                            <textarea wire:model="description" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition"></textarea>
                            @error('description') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-euro-sign text-blue-500 mr-2"></i>Subtotal *
                            </label>
                            <input wire:model="subtotal" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            @error('subtotal') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-percent text-purple-500 mr-2"></i>IVA/Taxa *
                            </label>
                            <input wire:model="tax" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                            @error('tax') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calculator text-green-500 mr-2"></i>Total *
                            </label>
                            <input wire:model="total" type="number" step="0.01" readonly class="w-full px-4 py-2.5 border border-gray-300 rounded-xl bg-gray-50 text-gray-700 font-bold">
                            @error('total') <span class="text-red-500 text-xs mt-1 block"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</span> @enderror
                        </div>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-orange-600 to-red-600 text-white rounded-xl font-semibold hover:from-orange-700 hover:to-red-700 shadow-lg hover:shadow-xl transition">
                            <i class="fas {{ $editingInvoiceId ? 'fa-save' : 'fa-plus' }} mr-2"></i>
                            {{ $editingInvoiceId ? 'Atualizar' : 'Criar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
```

---

## 📝 Instruções de Uso

### Como usar Toastr nas ações:
```php
// Sucesso
$this->dispatch('success', message: 'Operação realizada com sucesso!');

// Erro
$this->dispatch('error', message: 'Ocorreu um erro!');

// Aviso
$this->dispatch('warning', message: 'Atenção!');

// Informação
$this->dispatch('info', message: 'Informação importante!');
```

### Recursos das Modais:
- ✅ Design moderno com gradientes
- ✅ Ícones coloridos para cada campo
- ✅ Validação em tempo real
- ✅ Animações suaves
- ✅ Backdrop blur
- ✅ Responsivo
- ✅ Fechar com ESC ou clique fora
- ✅ Mensagens de erro estilizadas

---

## 🎨 Paleta de Cores das Modais

- **Tenants**: Azul/Roxo (#3B82F6 → #9333EA)
- **Modules**: Roxo/Rosa (#9333EA → #EC4899)
- **Plans**: Verde/Teal (#059669 → #0D9488)
- **Billing**: Laranja/Vermelho (#EA580C → #DC2626)
