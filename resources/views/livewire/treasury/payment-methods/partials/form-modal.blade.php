<!-- Modal Criar/Editar Método de Pagamento -->
@if($showModal)
<div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeModal"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal Panel -->
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-money-bill-wave mr-3"></i>
                        {{ $editMode ? 'Editar Método de Pagamento' : 'Novo Método de Pagamento' }}
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <form wire:submit.prevent="save">
                <div class="px-6 py-6 space-y-6">
                    <!-- Grid 2 colunas -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Nome -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tag mr-1 text-green-600"></i>Nome do Método*
                            </label>
                            <input type="text" wire:model="form.name" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200"
                                   placeholder="Ex: Dinheiro, Transferência Bancária">
                            @error('form.name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Código -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-barcode mr-1 text-green-600"></i>Código*
                            </label>
                            <input type="text" wire:model="form.code" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200"
                                   placeholder="Ex: CASH, TRANSFER">
                            @error('form.code') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Tipo -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-list mr-1 text-green-600"></i>Tipo*
                            </label>
                            <select wire:model="form.type" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200">
                                <option value="manual">Manual</option>
                                <option value="automatic">Automático</option>
                                <option value="online">Online</option>
                            </select>
                            @error('form.type') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Ícone -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-icons mr-1 text-green-600"></i>Ícone
                            </label>
                            <input type="text" wire:model="form.icon" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200"
                                   placeholder="fa-money-bill">
                            @error('form.icon') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Cor -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-palette mr-1 text-green-600"></i>Cor
                            </label>
                            <select wire:model="form.color" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200">
                                <option value="green">Verde</option>
                                <option value="blue">Azul</option>
                                <option value="purple">Roxo</option>
                                <option value="orange">Laranja</option>
                                <option value="gray">Cinza</option>
                            </select>
                            @error('form.color') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Taxa Percentual -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-percentage mr-1 text-green-600"></i>Taxa (%)
                            </label>
                            <input type="number" step="0.01" wire:model="form.fee_percentage" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200"
                                   placeholder="0.00">
                            @error('form.fee_percentage') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Taxa Fixa -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-dollar-sign mr-1 text-green-600"></i>Taxa Fixa
                            </label>
                            <input type="number" step="0.01" wire:model="form.fee_fixed" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200"
                                   placeholder="0.00">
                            @error('form.fee_fixed') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Descrição -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-align-left mr-1 text-green-600"></i>Descrição
                            </label>
                            <textarea wire:model="form.description" rows="3"
                                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200"
                                      placeholder="Descrição do método de pagamento"></textarea>
                            @error('form.description') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Checkboxes -->
                        <div class="md:col-span-2 flex items-center space-x-6">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="form.requires_account" class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                                <span class="ml-2 text-sm text-gray-700">Requer Conta Bancária</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="form.is_active" class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                                <span class="ml-2 text-sm text-gray-700">Ativo</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                    <button type="button" wire:click="closeModal"
                            class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-100 transition">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl font-semibold hover:from-green-700 hover:to-emerald-700 transition shadow-lg">
                        <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar' : 'Criar' }} Método
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
