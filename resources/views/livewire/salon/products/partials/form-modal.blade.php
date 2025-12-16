@if($showModal)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" wire:click="closeModal"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full">
            <div class="bg-gradient-to-r from-emerald-500 to-teal-500 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-box mr-3"></i>{{ $editingId ? 'Editar' : 'Novo' }} Produto
                    </h3>
                    <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <form wire:submit.prevent="save" class="p-6">
                <div class="space-y-4">
                    <!-- Nome e Código -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Nome *</label>
                            <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" placeholder="Nome do produto">
                            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Código</label>
                            <input wire:model="code" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" placeholder="Auto-gerado">
                        </div>
                    </div>

                    <!-- Categoria e Código de Barras -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Categoria</label>
                            <select wire:model="category_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                                <option value="">Selecione...</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Código de Barras</label>
                            <input wire:model="barcode" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" placeholder="EAN/UPC">
                        </div>
                    </div>

                    <!-- Preço e Custo -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Preço Venda *</label>
                            <div class="relative">
                                <input wire:model="price" type="number" step="0.01" class="w-full pl-4 pr-12 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" placeholder="0.00">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">Kz</span>
                            </div>
                            @error('price') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Custo</label>
                            <div class="relative">
                                <input wire:model="cost" type="number" step="0.01" class="w-full pl-4 pr-12 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" placeholder="0.00">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">Kz</span>
                            </div>
                        </div>
                    </div>

                    <!-- Stock -->
                    <div class="bg-emerald-50 rounded-xl p-4">
                        <label class="flex items-center gap-3 mb-3 cursor-pointer">
                            <input wire:model="manage_stock" type="checkbox" class="w-5 h-5 rounded text-emerald-600 focus:ring-emerald-500">
                            <span class="font-semibold text-gray-700">Gerir Stock</span>
                        </label>
                        @if($manage_stock)
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Quantidade</label>
                                    <input wire:model="stock_quantity" type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" placeholder="0">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Stock Mínimo</label>
                                    <input wire:model="minimum_stock" type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500" placeholder="5">
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Status -->
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input wire:model="is_active" type="checkbox" class="w-5 h-5 rounded text-emerald-600 focus:ring-emerald-500">
                        <span class="font-semibold text-gray-700">Produto Activo</span>
                    </label>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" wire:click="closeModal" class="px-5 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-500 text-white rounded-xl font-semibold hover:from-emerald-600 hover:to-teal-600 shadow-lg transition" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">
                            <i class="fas fa-check mr-2"></i>{{ $editingId ? 'Atualizar' : 'Criar' }}
                        </span>
                        <span wire:loading wire:target="save">
                            <i class="fas fa-spinner fa-spin mr-2"></i>A processar...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
