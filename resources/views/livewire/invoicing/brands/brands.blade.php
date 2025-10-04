<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-pink-600 to-rose-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-tag text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Marcas</h2>
                    <p class="text-pink-100 text-sm">Gerir marcas de produtos</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-pink-600 hover:bg-pink-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Nova Marca
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 stagger-animation">
        <!-- Total Marcas -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-pink-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-pink-500 to-rose-600 rounded-2xl flex items-center justify-center shadow-lg shadow-pink-500/50 icon-float">
                    <i class="fas fa-tag text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-pink-600 font-semibold mb-2">Total Marcas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $brands->total() }}</p>
            <p class="text-xs text-gray-500">Marcas registadas</p>
        </div>

        <!-- Marcas Ativas -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Ativas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ \App\Models\Brand::where('tenant_id', auth()->user()->tenant_id)->where('is_active', true)->count() }}</p>
            <p class="text-xs text-gray-500">Em uso</p>
        </div>

        <!-- Marcas Inativas -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-gray-500 to-gray-600 rounded-2xl flex items-center justify-center shadow-lg shadow-gray-500/50 icon-float">
                    <i class="fas fa-times-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 font-semibold mb-2">Inativas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ \App\Models\Brand::where('tenant_id', auth()->user()->tenant_id)->where('is_active', false)->count() }}</p>
            <p class="text-xs text-gray-500">Desativadas</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-pink-600"></i>
                Filtros
            </h3>
            <button wire:click="clearFilters" class="text-sm text-pink-600 hover:text-pink-700 font-semibold flex items-center">
                <i class="fas fa-redo mr-1"></i>Limpar Filtros
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Search -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live="search" type="text" placeholder="Nome da marca..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            <!-- Per Page -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-list mr-1"></i>Por Página
                </label>
                <select wire:model.live="perPage" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 appearance-none bg-white text-sm">
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <!-- List -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2 text-pink-600"></i>
                    Lista de Marcas
                </h3>
                <span class="text-sm text-gray-600 font-semibold">
                    <i class="fas fa-tag mr-1"></i>{{ $brands->total() }} Total Marcas
                </span>
            </div>
        </div>
        
        <!-- Table Header -->
        <div class="grid grid-cols-12 gap-4 px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-600 uppercase">
            <div class="col-span-5 flex items-center">
                <i class="fas fa-tag mr-2 text-pink-500"></i>Marca
            </div>
            <div class="col-span-3 flex items-center">
                <i class="fas fa-globe mr-2 text-blue-500"></i>Website
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-sort-numeric-up mr-2 text-purple-500"></i>Ordem
            </div>
            <div class="col-span-1 flex items-center">
                <i class="fas fa-check-circle mr-2 text-green-500"></i>Status
            </div>
            <div class="col-span-1 flex items-center justify-end">
                <i class="fas fa-cog mr-2 text-gray-500"></i>Ações
            </div>
        </div>
        
        <!-- Table Body -->
        <div class="divide-y divide-gray-100">
            @forelse($brands as $brand)
                <div class="group grid grid-cols-12 gap-4 px-6 py-4 hover:bg-pink-50 transition-all duration-300 items-center">
                    <!-- Marca -->
                    <div class="col-span-5 flex items-center space-x-3">
                        @if($brand->logo)
                            <img src="{{ Storage::url($brand->logo) }}" alt="{{ $brand->name }}" class="w-10 h-10 rounded-full object-cover shadow-lg flex-shrink-0">
                        @else
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center text-white font-bold shadow-lg flex-shrink-0">
                                {{ strtoupper(substr($brand->name, 0, 2)) }}
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-900 truncate">{{ $brand->name }}</p>
                            @if($brand->description)
                                <p class="text-xs text-gray-500 truncate">{{ $brand->description }}</p>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Website -->
                    <div class="col-span-3">
                        @if($brand->website)
                            <a href="{{ $brand->website }}" target="_blank" class="text-sm text-blue-600 hover:text-blue-800 flex items-center truncate">
                                <i class="fas fa-external-link-alt mr-1"></i>
                                <span class="truncate">{{ str_replace(['http://', 'https://'], '', $brand->website) }}</span>
                            </a>
                        @else
                            <span class="text-xs text-gray-400 italic">Sem website</span>
                        @endif
                    </div>
                    
                    <!-- Ordem -->
                    <div class="col-span-2">
                        <span class="inline-flex items-center px-2.5 py-1 bg-purple-100 text-purple-700 rounded-lg text-xs font-bold">
                            <i class="fas fa-sort-numeric-up mr-1"></i>{{ $brand->order }}
                        </span>
                    </div>
                    
                    <!-- Status -->
                    <div class="col-span-1">
                        @if($brand->is_active)
                            <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">
                                <i class="fas fa-check mr-1"></i>Ativa
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-bold">
                                <i class="fas fa-times mr-1"></i>Inativa
                            </span>
                        @endif
                    </div>
                    
                    <!-- Ações -->
                    <div class="col-span-1 flex items-center justify-end space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button wire:click="edit({{ $brand->id }})" class="w-8 h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Editar">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        <button wire:click="confirmDelete({{ $brand->id }})" class="w-8 h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Excluir">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-tag text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhuma marca encontrada</h3>
                    <p class="text-gray-500 mb-4">Crie uma nova marca para começar</p>
                </div>
            @endforelse
        </div>

        @if($brands->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $brands->links() }}
            </div>
        @endif
    </div>

    <!-- Modals -->
    @include('livewire.invoicing.brands.partials.form-modal')
    <x-delete-confirmation-modal 
        :itemName="$deletingBrandName" 
        entityType="a marca" 
        icon="fa-tags" 
    />
</div>
