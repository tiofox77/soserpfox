<div class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        {{-- Header --}}
        <div class="sticky top-0 bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <div class="flex items-center text-white">
                <i class="fas fa-box text-2xl mr-3"></i>
                <h3 class="text-xl font-bold">{{ $editMode ? 'Editar Peça' : 'Nova Peça' }}</h3>
            </div>
            <button wire:click="closeModal" class="text-white hover:text-gray-200 transition-colors">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        {{-- Form --}}
        <form wire:submit.prevent="save" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Nome --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-tag text-orange-500 mr-2"></i>Nome da Peça *
                    </label>
                    <input type="text" wire:model="name" 
                           class="w-full px-4 py-2.5 border @error('name') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                           placeholder="Ex: Filtro de Óleo, Pastilhas de Freio...">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- SKU --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-hashtag text-orange-500 mr-2"></i>Código SKU *
                    </label>
                    <input type="text" wire:model="sku" 
                           class="w-full px-4 py-2.5 border @error('sku') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all font-mono"
                           placeholder="Ex: FLT-001">
                    @error('sku') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Código de Barras --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-barcode text-orange-500 mr-2"></i>Código de Barras
                    </label>
                    <input type="text" wire:model="barcode" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all font-mono"
                           placeholder="Ex: 7891234567890">
                </div>

                {{-- Categoria --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-folder text-orange-500 mr-2"></i>Categoria
                    </label>
                    <select wire:model="category_id" 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                        <option value="">Selecione...</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Fornecedor --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-truck text-orange-500 mr-2"></i>Fornecedor
                    </label>
                    <input type="text" wire:model="supplier" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                           placeholder="Nome do fornecedor">
                </div>

                {{-- Preço --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-money-bill-wave text-orange-500 mr-2"></i>Preço de Venda * (Kz)
                    </label>
                    <input type="number" wire:model="price" step="0.01" min="0"
                           class="w-full px-4 py-2.5 border @error('price') border-red-500 @else border-gray-300 @enderror rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                           placeholder="0.00">
                    @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Custo --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-calculator text-orange-500 mr-2"></i>Custo (Kz)
                    </label>
                    <input type="number" wire:model="cost" step="0.01" min="0"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                           placeholder="0.00">
                </div>

                {{-- Stock Atual --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-cubes text-orange-500 mr-2"></i>Stock Atual
                    </label>
                    <input type="number" wire:model="stock" step="1" min="0"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                           placeholder="0">
                </div>

                {{-- Stock Mínimo --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-exclamation-triangle text-orange-500 mr-2"></i>Stock Mínimo
                    </label>
                    <input type="number" wire:model="min_stock" step="1" min="0"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                           placeholder="0">
                </div>

                {{-- Descrição --}}
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-align-left text-orange-500 mr-2"></i>Descrição
                    </label>
                    <textarea wire:model="description" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all resize-none"
                              placeholder="Descrição detalhada da peça..."></textarea>
                </div>

                {{-- Opções --}}
                <div class="md:col-span-2 space-y-3">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="track_inventory" class="w-5 h-5 text-orange-600 rounded focus:ring-orange-500">
                        <span class="ml-3 text-sm font-medium text-gray-700">
                            <i class="fas fa-warehouse text-orange-500 mr-2"></i>Rastrear inventário (controle de stock)
                        </span>
                    </label>
                    
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_active" class="w-5 h-5 text-orange-600 rounded focus:ring-orange-500">
                        <span class="ml-3 text-sm font-medium text-gray-700">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>Peça ativa (disponível para uso)
                        </span>
                    </label>
                </div>

                {{-- Info AGT --}}
                <div class="md:col-span-2 bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 text-xl mr-3 mt-1"></i>
                        <div class="text-sm text-blue-800">
                            <p class="font-semibold mb-1">Conformidade AGT Angola</p>
                            <p>Esta peça será cadastrada no módulo de Faturação e poderá ser usada em faturas fiscais conforme regulamentação da AGT.</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 flex items-center justify-end space-x-3 border-t border-gray-200 rounded-b-2xl">
            <button type="button" wire:click="closeModal" 
                    class="px-6 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 font-semibold transition-all">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button type="submit" wire:click="save" wire:loading.attr="disabled"
                    class="px-6 py-2.5 bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 text-white rounded-xl font-semibold shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save">
                    <i class="fas fa-save mr-2"></i>{{ $editMode ? 'Atualizar Peça' : 'Salvar Peça' }}
                </span>
                <span wire:loading wire:target="save">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                </span>
            </button>
        </div>
    </div>
</div>
