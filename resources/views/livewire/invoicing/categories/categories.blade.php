<div>
    <!-- Header -->
    <div class="mb-4 sm:mb-6 bg-gradient-to-r from-cyan-600 to-blue-600 rounded-2xl shadow-lg p-4 sm:p-6 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3 sm:mr-4">
                    <i class="fas fa-folder text-xl sm:text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-lg sm:text-2xl font-bold">Categorias</h2>
                    <p class="text-cyan-100 text-xs sm:text-sm">Gerir categorias e subcategorias</p>
                </div>
            </div>
            @can('invoicing.categories.create')
            <button wire:click="create"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-70 scale-95"
                    class="group bg-white text-cyan-600 hover:bg-cyan-50 px-4 sm:px-6 py-2 sm:py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105 text-sm sm:text-base disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="create">
                    <i class="fas fa-plus mr-2 group-hover:rotate-90 transition-transform duration-300"></i>Nova Categoria
                </span>
                <span wire:loading wire:target="create">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Abrindo...
                </span>
            </button>
            @endcan
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-6 mb-4 sm:mb-6 stagger-animation">
        <!-- Total Categorias -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-cyan-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-cyan-500/50 icon-float">
                    <i class="fas fa-folder text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-cyan-600 font-semibold mb-2">Total Categorias</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $categories->total() }}</p>
            <p class="text-xs text-gray-500">Categorias registadas</p>
        </div>

        <!-- Categorias Principais -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-folder-open text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Principais</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ \App\Models\Category::where('tenant_id', auth()->user()->tenant_id)->whereNull('parent_id')->count() }}</p>
            <p class="text-xs text-gray-500">Categorias pai</p>
        </div>

        <!-- Subcategorias -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float">
                    <i class="fas fa-sitemap text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">Subcategorias</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ \App\Models\Category::where('tenant_id', auth()->user()->tenant_id)->whereNotNull('parent_id')->count() }}</p>
            <p class="text-xs text-gray-500">Categorias filhas</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-cyan-600"></i>
                Filtros
            </h3>
            <button wire:click="clearFilters" class="text-sm text-cyan-600 hover:text-cyan-700 font-semibold flex items-center">
                <i class="fas fa-redo mr-1"></i>Limpar Filtros
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Search -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Nome da categoria..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            <!-- Type Filter -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-layer-group mr-1"></i>Tipo
                </label>
                <select wire:model.live="typeFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 appearance-none bg-white text-sm">
                    <option value="">Todas</option>
                    <option value="main">Principais</option>
                    <option value="sub">Subcategorias</option>
                </select>
            </div>

            <!-- Per Page -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-list mr-1"></i>Por Página
                </label>
                <select wire:model.live="perPage" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 appearance-none bg-white text-sm">
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
                    <i class="fas fa-list mr-2 text-cyan-600"></i>
                    Lista de Categorias
                </h3>
                <span class="text-sm text-gray-600 font-semibold">
                    <i class="fas fa-folder mr-1"></i>{{ $categories->total() }} Total Categorias
                </span>
            </div>
        </div>
        
        <!-- Table Header -->
        <div class="overflow-x-auto">
        <div class="grid grid-cols-12 gap-4 px-4 sm:px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-600 uppercase min-w-[500px]">
            <div class="col-span-5 sm:col-span-4 flex items-center">
                <i class="fas fa-folder mr-2 text-cyan-500"></i>Categoria
            </div>
            <div class="col-span-2 hidden md:flex items-center">
                <i class="fas fa-palette mr-2 text-purple-500"></i>Cor
            </div>
            <div class="col-span-2 hidden lg:flex items-center">
                <i class="fas fa-icons mr-2 text-orange-500"></i>Ícone
            </div>
            <div class="col-span-2 hidden sm:flex items-center">
                <i class="fas fa-sort-numeric-up mr-2 text-blue-500"></i>Ordem
            </div>
            <div class="col-span-3 sm:col-span-1 flex items-center">
                <i class="fas fa-check-circle mr-2 text-green-500"></i>Status
            </div>
            <div class="col-span-2 sm:col-span-1 flex items-center justify-end">
                <i class="fas fa-cog mr-2 text-gray-500"></i>Ações
            </div>
        </div>
        
        <!-- Table Body -->
        <div class="divide-y divide-gray-100">
            @forelse($categories as $category)
                <div class="group grid grid-cols-12 gap-4 px-4 sm:px-6 py-3 sm:py-4 hover:bg-cyan-50 transition-all duration-300 items-center min-w-[500px]">
                    <!-- Categoria -->
                    <div class="col-span-5 sm:col-span-4 flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold shadow-lg flex-shrink-0" style="background: {{ $category->color }}">
                            <i class="fas {{ $category->icon }}"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-900 truncate">{{ $category->name }}</p>
                            @if($category->parent)
                                <p class="text-xs text-gray-500 flex items-center">
                                    <i class="fas fa-level-up-alt mr-1"></i>{{ $category->parent->name }}
                                </p>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Cor -->
                    <div class="col-span-2 hidden md:block">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-lg shadow-md border-2 border-gray-200" style="background: {{ $category->color }}"></div>
                            <span class="text-xs font-mono text-gray-600">{{ $category->color }}</span>
                        </div>
                    </div>
                    
                    <!-- Ícone -->
                    <div class="col-span-2 hidden lg:block">
                        <span class="inline-flex items-center px-2.5 py-1 bg-orange-100 text-orange-700 rounded-lg text-xs font-semibold">
                            <i class="fas {{ $category->icon }} mr-1"></i>{{ $category->icon }}
                        </span>
                    </div>
                    
                    <!-- Ordem -->
                    <div class="col-span-2 hidden sm:block">
                        <span class="inline-flex items-center px-2.5 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-bold">
                            <i class="fas fa-sort-numeric-up mr-1"></i>{{ $category->order }}
                        </span>
                    </div>
                    
                    <!-- Status -->
                    <div class="col-span-3 sm:col-span-1">
                        @if($category->is_active)
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
                    <div class="col-span-2 sm:col-span-1 flex items-center justify-end space-x-1">
                        @can('invoicing.categories.edit')
                        <button wire:click="edit({{ $category->id }})"
                                wire:loading.attr="disabled"
                                class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg hover:scale-110 disabled:opacity-50" title="Editar">
                            <i class="fas fa-edit text-xs" wire:loading.remove></i>
                            <i class="fas fa-spinner fa-spin text-xs" wire:loading></i>
                        </button>
                        @endcan
                        @can('invoicing.categories.delete')
                        <button wire:click="confirmDelete({{ $category->id }})"
                                wire:loading.attr="disabled"
                                class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg hover:scale-110 disabled:opacity-50" title="Excluir">
                            <i class="fas fa-trash text-xs" wire:loading.remove></i>
                            <i class="fas fa-spinner fa-spin text-xs" wire:loading></i>
                        </button>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-folder text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhuma categoria encontrada</h3>
                    <p class="text-gray-500 mb-4">Crie uma nova categoria para começar</p>
                </div>
            @endforelse
        </div>

        @if($categories->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
                {{ $categories->links() }}
            </div>
        @endif
        </div>
    </div>

    <!-- Modals -->
    @include('livewire.invoicing.categories.partials.form-modal')
    <x-delete-confirmation-modal 
        :itemName="$deletingCategoryName" 
        entityType="a categoria" 
        icon="fa-folder-minus" 
    />
</div>
