<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-box text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Produtos/Serviços</h2>
                    <p class="text-purple-100 text-sm">Gerir catálogo de produtos</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Produto
            </button>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-400"></i>
            </div>
            <input wire:model.live="search" type="text" placeholder="Pesquisar produtos..." 
                   class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
        </div>
    </div>

    <!-- List -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-list mr-2 text-purple-600"></i>
                Lista de Produtos ({{ $products->total() }})
            </h3>
        </div>
        
        <div class="divide-y divide-gray-100 stagger-animation">
            @forelse($products as $product)
                <div class="group p-6 hover:bg-purple-50 transition-all duration-300 card-hover">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <span class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-700 rounded-lg text-xs font-bold mr-3">
                                    {{ $product->code }}
                                </span>
                                <h4 class="text-lg font-bold text-gray-900">{{ $product->name }}</h4>
                            </div>
                            @if($product->description)
                            <p class="text-sm text-gray-600 mb-2">{{ $product->description }}</p>
                            @endif
                            <div class="flex flex-wrap gap-3 text-sm">
                                <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-lg font-semibold">
                                    <i class="fas fa-money-bill-wave mr-1"></i>{{ number_format($product->price, 2) }} Kz/{{ $product->unit }}
                                </span>
                                <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-lg font-semibold">
                                    <i class="fas fa-percent mr-1"></i>IVA {{ $product->tax_rate }}%
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button wire:click="edit({{ $product->id }})" class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-semibold transition shadow-md hover:shadow-lg">
                                <i class="fas fa-edit mr-1"></i>Editar
                            </button>
                            <button wire:click="delete({{ $product->id }})" wire:confirm="Tem certeza?" class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-semibold transition shadow-md hover:shadow-lg">
                                <i class="fas fa-trash mr-1"></i>Excluir
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-box text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum produto encontrado</h3>
                    <p class="text-gray-500 mb-4">Crie um novo produto para começar</p>
                </div>
            @endforelse
        </div>

        @if($products->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-box mr-3"></i>{{ $editingProductId ? 'Editar' : 'Novo' }} Produto
                            </h3>
                            <button wire:click="closeModal" class="text-white hover:text-gray-200 transition">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <form wire:submit.prevent="save" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-barcode text-purple-500 mr-2"></i>Código *
                                </label>
                                <input wire:model="code" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                @error('code') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-cube text-purple-500 mr-2"></i>Unidade *
                                </label>
                                <select wire:model="unit" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                    <option value="UN">Unidade</option>
                                    <option value="HR">Hora</option>
                                    <option value="DIA">Dia</option>
                                    <option value="MÊS">Mês</option>
                                    <option value="SRV">Serviço</option>
                                </select>
                                @error('unit') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-tag text-purple-500 mr-2"></i>Nome *
                                </label>
                                <input wire:model="name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-align-left text-gray-500 mr-2"></i>Descrição
                                </label>
                                <textarea wire:model="description" rows="2" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>Preço (Kz) *
                                </label>
                                <input wire:model="price" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                @error('price') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-coins text-orange-500 mr-2"></i>Custo (Kz)
                                </label>
                                <input wire:model="cost" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-percent text-blue-500 mr-2"></i>Taxa IVA (%) *
                                </label>
                                <input wire:model="tax_rate" type="number" step="0.01" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                                @error('tax_rate') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button type="button" wire:click="closeModal" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-pink-700 shadow-lg hover:shadow-xl transition">
                                <i class="fas {{ $editingProductId ? 'fa-save' : 'fa-plus' }} mr-2"></i>
                                {{ $editingProductId ? 'Atualizar' : 'Criar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
