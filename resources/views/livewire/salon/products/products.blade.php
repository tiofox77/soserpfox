<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-emerald-500 to-teal-500 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-boxes text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Produtos</h2>
                    <p class="text-emerald-100 text-sm">Gestão de produtos do salão</p>
                </div>
            </div>
            <button wire:click="openModal" class="bg-white text-emerald-600 hover:bg-emerald-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Produto
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-emerald-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-500/50">
                    <i class="fas fa-box text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-emerald-600 font-semibold mb-2">Total Produtos</p>
            <p class="text-4xl font-bold text-gray-900">{{ $totalProducts }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Activos</p>
            <p class="text-4xl font-bold text-gray-900">{{ $totalActive }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-orange-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl flex items-center justify-center shadow-lg shadow-orange-500/50">
                    <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-orange-600 font-semibold mb-2">Stock Baixo</p>
            <p class="text-4xl font-bold text-gray-900">{{ $totalLowStock }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50">
                    <i class="fas fa-coins text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Valor em Stock</p>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($totalValue, 0, ',', '.') }}</p>
            <p class="text-xs text-gray-500">Kz</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-emerald-600"></i>Filtros
            </h3>
            @if($search || $categoryFilter || $stockFilter)
                <button wire:click="clearFilters" class="text-sm text-emerald-600 hover:text-emerald-700 font-semibold">
                    <i class="fas fa-times mr-1"></i>Limpar
                </button>
            @endif
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input wire:model.live="search" type="text" placeholder="Pesquisar produto..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition">
                </div>
            </div>
            <div>
                <select wire:model.live="categoryFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                    <option value="">Todas categorias</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select wire:model.live="stockFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500">
                    <option value="">Todo stock</option>
                    <option value="low">Stock baixo</option>
                    <option value="out">Sem stock</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">
                    <i class="fas fa-boxes mr-2 text-emerald-600"></i>Lista de Produtos
                </h3>
                <span class="text-sm text-gray-600 font-semibold">{{ $products->total() }} produtos</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-6">
            @forelse($products as $product)
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg transition group">
                    <div class="h-32 bg-gradient-to-br from-emerald-100 to-teal-100 flex items-center justify-center">
                        @if($product->featured_image)
                            <img src="{{ asset('storage/' . $product->featured_image) }}" class="h-full w-full object-cover">
                        @else
                            <i class="fas fa-box text-emerald-300 text-4xl"></i>
                        @endif
                    </div>
                    <div class="p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h4 class="font-bold text-gray-900 truncate">{{ $product->name }}</h4>
                                <p class="text-xs text-gray-500">{{ $product->code }}</p>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-bold rounded {{ $product->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                        
                        <p class="text-xl font-bold text-emerald-600 mb-2">{{ number_format($product->price, 0, ',', '.') }} Kz</p>
                        
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm {{ $product->stock_quantity <= $product->minimum_stock ? 'text-orange-600 font-bold' : 'text-gray-600' }}">
                                <i class="fas fa-cubes mr-1"></i>{{ $product->stock_quantity }} unid.
                            </span>
                            @if($product->category)
                                <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">{{ $product->category->name }}</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition">
                            <button wire:click="view({{ $product->id }})" class="flex-1 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-sm font-semibold transition">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button wire:click="openModal({{ $product->id }})" class="flex-1 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-semibold transition">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button wire:click="openDeleteModal({{ $product->id }})" class="flex-1 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-semibold transition">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-box-open text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum produto encontrado</h3>
                    <p class="text-gray-500">Adicione produtos para começar</p>
                </div>
            @endforelse
        </div>

        @if($products->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    <!-- Modals -->
    @include('livewire.salon.products.partials.form-modal')
    @include('livewire.salon.products.partials.view-modal')
    @include('livewire.salon.products.partials.delete-modal')

    <!-- Toastr -->
    @include('livewire.salon.partials.toastr-notifications')
</div>
