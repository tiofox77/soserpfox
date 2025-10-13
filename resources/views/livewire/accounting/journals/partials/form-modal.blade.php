<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4"
     style="backdrop-filter: blur(4px);"
     x-show="true"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     @click.self="$wire.closeModal()">
    
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden"
         x-show="true"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         @click.stop>
        
        {{-- Header --}}
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                    <i class="fas fa-book text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">
                        {{ $editMode ? 'Editar Diário' : 'Novo Diário' }}
                    </h3>
                    <p class="text-blue-100 text-sm">Configure o diário contabilístico</p>
                </div>
            </div>
            <button wire:click="closeModal" 
                    class="text-white hover:text-blue-100 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <form wire:submit.prevent="save" class="overflow-y-auto max-h-[calc(90vh-180px)] p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-hashtag mr-1 text-blue-600"></i>Código *
                    </label>
                    <input type="text" wire:model="code" 
                           class="w-full px-4 py-2.5 border @error('code') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500"
                           placeholder="Ex: VEND"
                           required>
                    @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-tag mr-1 text-blue-600"></i>Nome *
                    </label>
                    <input type="text" wire:model="name" 
                           class="w-full px-4 py-2.5 border @error('name') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500"
                           placeholder="Nome do diário"
                           required>
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-folder mr-1 text-blue-600"></i>Tipo *
                    </label>
                    <select wire:model="type" 
                            class="w-full px-4 py-2.5 border @error('type') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500"
                            required>
                        <option value="">Selecione...</option>
                        <option value="sale">Vendas</option>
                        <option value="purchase">Compras</option>
                        <option value="cash">Caixa</option>
                        <option value="bank">Banco</option>
                        <option value="payroll">Salários</option>
                        <option value="adjustment">Ajustes</option>
                    </select>
                    @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-barcode mr-1 text-blue-600"></i>Prefixo Sequência *
                    </label>
                    <input type="text" wire:model="sequence_prefix" 
                           class="w-full px-4 py-2.5 border @error('sequence_prefix') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-blue-500"
                           placeholder="Ex: VD-"
                           required>
                    @error('sequence_prefix') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-sort-numeric-up mr-1 text-blue-600"></i>Último Número
                    </label>
                    <input type="number" wire:model="last_number" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500"
                           min="0">
                    <p class="text-xs text-gray-500 mt-1">Será incrementado automaticamente</p>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-toggle-on mr-1 text-blue-600"></i>Status
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="active" 
                               class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">Diário ativo</span>
                    </label>
                </div>

                <div class="md:col-span-2">
                    <div class="border-t border-gray-200 pt-4 mt-2">
                        <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-cog mr-2 text-blue-600"></i>
                            Contas Padrão (Opcional)
                        </h4>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-arrow-right mr-1 text-green-600"></i>Conta Débito Padrão
                    </label>
                    <select wire:model="default_debit_account_id" 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                        <option value="">Nenhuma</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Conta sugerida para débito</p>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-arrow-left mr-1 text-red-600"></i>Conta Crédito Padrão
                    </label>
                    <select wire:model="default_credit_account_id" 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                        <option value="">Nenhuma</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Conta sugerida para crédito</p>
                </div>
            </div>
        </form>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-end space-x-3 border-t border-gray-200">
            <button wire:click="closeModal" 
                    type="button"
                    class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-100 transition font-semibold">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button wire:click="save" 
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-not-allowed"
                    type="submit"
                    class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:shadow-lg transition font-semibold">
                <span wire:loading.remove wire:target="save">
                    <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Guardar' }}
                </span>
                <span wire:loading wire:target="save">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                </span>
            </button>
        </div>
    </div>
</div>
