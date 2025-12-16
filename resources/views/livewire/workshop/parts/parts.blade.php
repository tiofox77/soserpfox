<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-orange-600 to-red-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-boxes text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Gestão de Peças</h2>
                    <p class="text-orange-100 text-sm">Produtos vinculados ao módulo de faturação (AGT Angola)</p>
                </div>
            </div>
            <button wire:click="create" 
                    class="px-6 py-3 bg-white text-orange-600 rounded-xl font-semibold hover:bg-orange-50 transition-all shadow-lg flex items-center">
                <i class="fas fa-plus mr-2"></i>Nova Peça
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- Search --}}
            <div class="relative">
                <input type="text" wire:model.live.debounce="search" 
                       class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent" 
                       placeholder="Buscar por nome, SKU ou código de barras...">
                <i class="fas fa-search absolute left-3 top-3.5 text-gray-400"></i>
            </div>

            {{-- Category Filter --}}
            <div>
                <select wire:model.live="categoryFilter" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    <option value="">Todas as Categorias</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            
            {{-- Info AGT --}}
            <div class="flex items-center text-sm text-blue-600 bg-blue-50 rounded-xl px-4 py-2">
                <i class="fas fa-info-circle mr-2"></i>
                <span>Produtos do módulo Faturação (Conformidade AGT)</span>
            </div>
        </div>
    </div>

    {{-- Parts Table --}}
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-orange-50 to-red-50 border-b-2 border-orange-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Peça</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">SKU</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Categoria</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Preço</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Stock</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($products as $product)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-500 rounded-xl flex items-center justify-center text-white font-bold mr-3">
                                        {{ strtoupper(substr($product->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ $product->name }}</div>
                                        @if($product->barcode)
                                            <div class="text-xs text-gray-500">
                                                <i class="fas fa-barcode mr-1"></i>{{ $product->barcode }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-lg text-sm font-mono">
                                    {{ $product->sku }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-gray-700">{{ $product->category->name ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-gray-900">{{ number_format($product->price, 2, ',', '.') }} Kz</div>
                                @if($product->cost)
                                    <div class="text-xs text-gray-500">Custo: {{ number_format($product->cost, 2, ',', '.') }} Kz</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($product->track_inventory)
                                    <div class="flex items-center">
                                        <span class="font-semibold {{ $product->stock <= $product->min_stock ? 'text-red-600' : 'text-green-600' }}">
                                            {{ $product->stock }}
                                        </span>
                                        @if($product->stock <= $product->min_stock)
                                            <i class="fas fa-exclamation-triangle text-red-500 ml-2" title="Stock baixo"></i>
                                        @endif
                                    </div>
                                    @if($product->min_stock)
                                        <div class="text-xs text-gray-500">Mín: {{ $product->min_stock }}</div>
                                    @endif
                                @else
                                    <span class="text-gray-400 text-sm">Não rastreado</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $product->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $product->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center space-x-2">
                                    <button wire:click="view({{ $product->id }})" 
                                            class="p-2 text-cyan-600 hover:bg-cyan-50 rounded-lg transition-all" 
                                            title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button wire:click="edit({{ $product->id }})" 
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-all" 
                                            title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button wire:click="delete({{ $product->id }})" 
                                            wire:confirm="Tem certeza que deseja remover esta peça?"
                                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-all" 
                                            title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <i class="fas fa-box-open text-6xl mb-4"></i>
                                    <p class="text-lg font-semibold">Nenhuma peça cadastrada</p>
                                    <p class="text-sm">Clique em "Nova Peça" para começar</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $products->links() }}
        </div>
    </div>

    {{-- Form Modal --}}
    @if($showModal)
        @include('livewire.workshop.parts.partials.form-modal')
    @endif

    {{-- View Modal --}}
    @if($showViewModal)
        @include('livewire.workshop.parts.partials.view-modal')
    @endif
</div>
