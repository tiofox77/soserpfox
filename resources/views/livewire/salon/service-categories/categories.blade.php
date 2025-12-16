<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-folder-open text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Categorias de Serviços</h2>
                    <p class="text-purple-100 text-sm">Organizar serviços por categorias</p>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('salon.services') }}" class="bg-white/20 hover:bg-white/30 text-white px-4 py-3 rounded-xl font-semibold transition-all">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar aos Serviços
                </a>
                <button wire:click="openModal" class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-plus mr-2"></i>Nova Categoria
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Total Categorias -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50">
                    <i class="fas fa-folder text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">Total Categorias</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $categories->total() }}</p>
            <p class="text-xs text-gray-500">Categorias registadas</p>
        </div>

        <!-- Serviços Categorizados -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-indigo-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/50">
                    <i class="fas fa-spa text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-indigo-600 font-semibold mb-2">Serviços</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $categories->sum('services_count') }}</p>
            <p class="text-xs text-gray-500">Total de serviços</p>
        </div>

        <!-- Dica -->
        <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-2xl shadow-lg p-6 border border-amber-200">
            <div class="flex items-center mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl flex items-center justify-center shadow-lg shadow-amber-500/50">
                    <i class="fas fa-lightbulb text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-amber-700 font-semibold mb-2">Dica</p>
            <p class="text-sm text-amber-600">Use o campo "Ordem" para organizar as categorias como aparecem no agendamento.</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-purple-600"></i>
                Filtros
            </h3>
            <button wire:click="clearFilters" class="text-sm text-purple-600 hover:text-purple-700 font-semibold flex items-center">
                <i class="fas fa-redo mr-1"></i>Limpar
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live="search" type="text" placeholder="Nome da categoria..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            <!-- Per Page -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-list mr-1"></i>Por Página
                </label>
                <select wire:model.live="perPage" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
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
                    <i class="fas fa-list mr-2 text-purple-600"></i>
                    Lista de Categorias
                </h3>
                <span class="text-sm text-gray-600 font-semibold">
                    <i class="fas fa-folder mr-1"></i>{{ $categories->total() }} categorias
                </span>
            </div>
        </div>
        
        <!-- Table Header -->
        <div class="grid grid-cols-12 gap-4 px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-600 uppercase">
            <div class="col-span-1 flex items-center">
                <i class="fas fa-sort mr-2 text-gray-400"></i>Ordem
            </div>
            <div class="col-span-4 flex items-center">
                <i class="fas fa-folder mr-2 text-purple-500"></i>Categoria
            </div>
            <div class="col-span-3 flex items-center">
                <i class="fas fa-align-left mr-2 text-gray-400"></i>Descrição
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-spa mr-2 text-indigo-500"></i>Serviços
            </div>
            <div class="col-span-2 flex items-center justify-end">
                <i class="fas fa-cog mr-2 text-gray-500"></i>Ações
            </div>
        </div>
        
        <!-- Table Body -->
        <div class="divide-y divide-gray-100">
            @forelse($categories as $category)
                <div class="group grid grid-cols-12 gap-4 px-6 py-4 hover:bg-purple-50 transition-all duration-300 items-center">
                    <!-- Ordem -->
                    <div class="col-span-1">
                        <div class="flex items-center gap-1">
                            <button wire:click="moveUp({{ $category->id }})" class="w-6 h-6 bg-gray-100 hover:bg-gray-200 rounded flex items-center justify-center text-gray-500 text-xs">
                                <i class="fas fa-chevron-up"></i>
                            </button>
                            <span class="w-8 text-center font-bold text-gray-700">{{ $category->order }}</span>
                            <button wire:click="moveDown({{ $category->id }})" class="w-6 h-6 bg-gray-100 hover:bg-gray-200 rounded flex items-center justify-center text-gray-500 text-xs">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Categoria -->
                    <div class="col-span-4 flex items-center space-x-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center shadow-md" style="background-color: {{ $category->color }}20">
                            <i class="{{ $category->icon ?? 'fas fa-folder' }} text-xl" style="color: {{ $category->color }}"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-900 truncate">{{ $category->name }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="w-4 h-4 rounded-full border-2 border-white shadow" style="background-color: {{ $category->color }}"></span>
                                <span class="text-xs text-gray-500">{{ $category->color }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Descrição -->
                    <div class="col-span-3">
                        @if($category->description)
                            <p class="text-sm text-gray-600 truncate">{{ $category->description }}</p>
                        @else
                            <span class="text-xs text-gray-400 italic">Sem descrição</span>
                        @endif
                    </div>
                    
                    <!-- Serviços -->
                    <div class="col-span-2">
                        <span class="inline-flex items-center px-3 py-1.5 bg-indigo-100 text-indigo-700 rounded-lg text-sm font-bold">
                            <i class="fas fa-spa mr-1.5"></i>{{ $category->services_count }} serviços
                        </span>
                    </div>
                    
                    <!-- Ações -->
                    <div class="col-span-2 flex items-center justify-end space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button wire:click="openModal({{ $category->id }})" class="w-9 h-9 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button wire:click="openDeleteModal({{ $category->id }})" class="w-9 h-9 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-folder-open text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhuma categoria encontrada</h3>
                    <p class="text-gray-500 mb-4">Crie uma nova categoria para organizar os serviços</p>
                    <button wire:click="openModal" class="px-6 py-3 bg-purple-600 text-white rounded-xl font-semibold hover:bg-purple-700 transition">
                        <i class="fas fa-plus mr-2"></i>Criar Categoria
                    </button>
                </div>
            @endforelse
        </div>

        @if($categories->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $categories->links() }}
            </div>
        @endif
    </div>

    <!-- Ícones Sugeridos -->
    <div class="mt-6 bg-white rounded-2xl shadow-lg p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-icons mr-2 text-purple-600"></i>
            Ícones Sugeridos para Categorias
        </h3>
        <div class="grid grid-cols-6 md:grid-cols-12 gap-3">
            @foreach(['fas fa-cut', 'fas fa-spa', 'fas fa-magic', 'fas fa-paint-brush', 'fas fa-hand-sparkles', 'fas fa-gem', 'fas fa-eye', 'fas fa-lips', 'fas fa-hair', 'fas fa-bath', 'fas fa-pump-soap', 'fas fa-spray-can'] as $icon)
                <div class="p-3 bg-gray-50 rounded-xl text-center hover:bg-purple-50 transition cursor-pointer group" onclick="navigator.clipboard.writeText('{{ $icon }}')">
                    <i class="{{ $icon }} text-xl text-gray-600 group-hover:text-purple-600"></i>
                    <p class="text-xs text-gray-500 mt-1 truncate">{{ str_replace('fas fa-', '', $icon) }}</p>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 mt-3"><i class="fas fa-info-circle mr-1"></i> Clique no ícone para copiar o código</p>
    </div>

    <!-- Modals -->
    @include('livewire.salon.service-categories.partials.form-modal')
    @include('livewire.salon.service-categories.partials.delete-modal')

    <!-- Toastr Notifications -->
    @include('livewire.salon.partials.toastr-notifications')
</div>
