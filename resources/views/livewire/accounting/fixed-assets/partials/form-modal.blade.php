{{-- Fixed Asset Form Modal --}}
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 bg-gradient-to-r from-purple-600 to-indigo-600 flex items-center justify-between rounded-t-xl">
            <h2 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-building mr-2"></i>
                {{ $assetId ? 'Editar' : 'Novo' }} Ativo Fixo
            </h2>
            <button wire:click="$set('showModal', false)" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <form wire:submit.prevent="save" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Código --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Código *</label>
                    <input type="text" wire:model="code" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                           placeholder="FA-001">
                    @error('code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                {{-- Nome --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nome *</label>
                    <input type="text" wire:model="name" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                           placeholder="Ex: Computador Dell">
                    @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                {{-- Categoria --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Categoria</label>
                    <select wire:model="categoryId" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">Selecione...</option>
                        <option value="1">Equipamento Informático</option>
                        <option value="2">Mobiliário</option>
                        <option value="3">Veículos</option>
                        <option value="4">Edifícios</option>
                    </select>
                </div>

                {{-- Data Aquisição --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data Aquisição *</label>
                    <input type="date" wire:model="acquisitionDate" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    @error('acquisitionDate') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                {{-- Valor Aquisição --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Valor Aquisição (Kz) *</label>
                    <input type="number" wire:model="acquisitionValue" step="0.01"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                           placeholder="100000.00">
                    @error('acquisitionValue') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                {{-- Valor Residual --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Valor Residual (Kz)</label>
                    <input type="number" wire:model="residualValue" step="0.01"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                           placeholder="0.00">
                </div>

                {{-- Vida Útil --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Vida Útil (anos) *</label>
                    <input type="number" wire:model="usefulLife"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                           placeholder="5">
                    @error('usefulLife') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                {{-- Método Depreciação --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Método Depreciação *</label>
                    <select wire:model="depreciationMethod" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="linear">Linear (Quotas Constantes)</option>
                        <option value="declining_balance">Quotas Decrescentes</option>
                        <option value="units_of_production">Unidades de Produção</option>
                    </select>
                </div>

                {{-- Localização --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Localização</label>
                    <input type="text" wire:model="location" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                           placeholder="Ex: Escritório Central">
                </div>

                {{-- Número Série --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Número de Série</label>
                    <input type="text" wire:model="serialNumber" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                           placeholder="Ex: SN123456">
                </div>

                {{-- Descrição --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                    <textarea wire:model="description" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                              placeholder="Descrição detalhada do ativo..."></textarea>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                <button type="button" wire:click="$set('showModal', false)"
                        class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-semibold">
                    Cancelar
                </button>
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50 cursor-not-allowed"
                        class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-semibold">
                    <span wire:loading.remove wire:target="save">
                        <i class="fas fa-save mr-2"></i>Salvar
                    </span>
                    <span wire:loading wire:target="save">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
