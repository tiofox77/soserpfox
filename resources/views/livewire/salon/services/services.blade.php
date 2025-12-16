<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-spa text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Serviços</h2>
                    <p class="text-indigo-100 text-sm">Gerir serviços do salão</p>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('salon.services.categories') }}" class="bg-white/20 hover:bg-white/30 text-white px-4 py-3 rounded-xl font-semibold transition-all">
                    <i class="fas fa-folder-open mr-2"></i>Gerir Categorias
                </a>
                <button wire:click="openModal" class="bg-white text-indigo-600 hover:bg-indigo-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-plus mr-2"></i>Novo Serviço
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Total Serviços -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-indigo-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/50">
                    <i class="fas fa-spa text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-indigo-600 font-semibold mb-2">Total Serviços</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalServices }}</p>
            <p class="text-xs text-gray-500">Serviços registados</p>
        </div>

        <!-- Serviços Ativos -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Serviços Ativos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalActive }}</p>
            <p class="text-xs text-gray-500">Disponíveis para agendamento</p>
        </div>

        <!-- Categorias -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50">
                    <i class="fas fa-folder text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">Categorias</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalCategories }}</p>
            <p class="text-xs text-gray-500">Grupos de serviços</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-indigo-600"></i>
                Filtros
            </h3>
            <button wire:click="clearFilters" class="text-sm text-indigo-600 hover:text-indigo-700 font-semibold flex items-center">
                <i class="fas fa-redo mr-1"></i>Limpar Filtros
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live="search" type="text" placeholder="Nome do serviço..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            <!-- Category Filter -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-folder mr-1"></i>Categoria
                </label>
                <select wire:model.live="categoryFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 appearance-none bg-white text-sm">
                    <option value="">Todas</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Per Page -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-list mr-1"></i>Por Página
                </label>
                <select wire:model.live="perPage" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 appearance-none bg-white text-sm">
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
                    <i class="fas fa-list mr-2 text-indigo-600"></i>
                    Lista de Serviços
                </h3>
                <span class="text-sm text-gray-600 font-semibold">
                    <i class="fas fa-spa mr-1"></i>{{ $services->total() }} Total Serviços
                </span>
            </div>
        </div>
        
        <!-- Table Header -->
        <div class="grid grid-cols-12 gap-4 px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-600 uppercase">
            <div class="col-span-3 flex items-center">
                <i class="fas fa-spa mr-2 text-indigo-500"></i>Serviço
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-folder mr-2 text-purple-500"></i>Categoria
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-clock mr-2 text-blue-500"></i>Duração
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-tag mr-2 text-green-500"></i>Preço
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-toggle-on mr-2 text-orange-500"></i>Status
            </div>
            <div class="col-span-1 flex items-center justify-end">
                <i class="fas fa-cog mr-2 text-gray-500"></i>Ações
            </div>
        </div>
        
        <!-- Table Body -->
        <div class="divide-y divide-gray-100">
            @forelse($services as $service)
                <div class="group grid grid-cols-12 gap-4 px-6 py-4 hover:bg-indigo-50 transition-all duration-300 items-center">
                    <!-- Serviço -->
                    <div class="col-span-3 flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold shadow-lg flex-shrink-0">
                            <i class="fas fa-spa"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-900 truncate">{{ $service->name }}</p>
                            <p class="text-xs text-gray-500">{{ $service->code ?? 'ID: ' . $service->id }}</p>
                        </div>
                    </div>
                    
                    <!-- Categoria -->
                    <div class="col-span-2">
                        @if($service->category)
                            <span class="inline-flex items-center px-2.5 py-1 bg-purple-100 text-purple-700 rounded-lg text-xs font-semibold">
                                <i class="fas fa-folder mr-1"></i>{{ $service->category->name }}
                            </span>
                        @else
                            <span class="text-xs text-gray-400 italic">Sem categoria</span>
                        @endif
                    </div>
                    
                    <!-- Duração -->
                    <div class="col-span-2">
                        <span class="inline-flex items-center px-2.5 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold">
                            <i class="fas fa-clock mr-1"></i>{{ $service->duration_formatted }}
                        </span>
                    </div>
                    
                    <!-- Preço -->
                    <div class="col-span-2">
                        <p class="font-bold text-gray-900">{{ number_format($service->price, 2, ',', '.') }} Kz</p>
                        @if($service->cost > 0)
                            <p class="text-xs text-gray-500">Custo: {{ number_format($service->cost, 2, ',', '.') }} Kz</p>
                        @endif
                    </div>
                    
                    <!-- Status -->
                    <div class="col-span-2">
                        @if($service->is_active)
                            <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-bold">
                                <i class="fas fa-check-circle mr-1"></i>Ativo
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs font-bold">
                                <i class="fas fa-times-circle mr-1"></i>Inativo
                            </span>
                        @endif
                        @if($service->online_booking)
                            <span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-xs ml-1" title="Agendamento Online">
                                <i class="fas fa-globe"></i>
                            </span>
                        @endif
                    </div>
                    
                    <!-- Ações -->
                    <div class="col-span-1 flex items-center justify-end space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button wire:click="view({{ $service->id }})" class="w-8 h-8 flex items-center justify-center bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Ver">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                        <button wire:click="openModal({{ $service->id }})" class="w-8 h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Editar">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        <button wire:click="toggleStatus({{ $service->id }})" class="w-8 h-8 flex items-center justify-center {{ $service->is_active ? 'bg-orange-500 hover:bg-orange-600' : 'bg-green-500 hover:bg-green-600' }} text-white rounded-lg transition shadow-md hover:shadow-lg" title="{{ $service->is_active ? 'Desativar' : 'Ativar' }}">
                            <i class="fas {{ $service->is_active ? 'fa-toggle-off' : 'fa-toggle-on' }} text-xs"></i>
                        </button>
                        <button wire:click="openDeleteModal({{ $service->id }})" class="w-8 h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Excluir">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-spa text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum serviço encontrado</h3>
                    <p class="text-gray-500 mb-4">Crie um novo serviço para começar</p>
                </div>
            @endforelse
        </div>

        @if($services->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $services->links() }}
            </div>
        @endif
    </div>

    <!-- Modals -->
    @include('livewire.salon.services.partials.form-modal')
    @include('livewire.salon.services.partials.view-modal')
    @include('livewire.salon.services.partials.delete-modal')

    <!-- Toastr Notifications -->
    @include('livewire.salon.partials.toastr-notifications')
</div>
