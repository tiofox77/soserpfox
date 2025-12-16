<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-pink-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Clientes</h2>
                    <p class="text-pink-100 text-sm">Gerir clientes</p>
                </div>
            </div>
            <button wire:click="openModal" class="bg-white text-pink-600 hover:bg-pink-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Cliente
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 stagger-animation">
        <!-- Total Clientes -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-pink-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-pink-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-pink-500/50 icon-float">
                    <i class="fas fa-users text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-pink-600 font-semibold mb-2">Total Clientes</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $clients->total() }}</p>
            <p class="text-xs text-gray-500">Clientes registados</p>
        </div>

        <!-- Clientes VIP -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-500/50 icon-float">
                    <i class="fas fa-crown text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-yellow-600 font-semibold mb-2">Clientes VIP</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalVip }}</p>
            <p class="text-xs text-gray-500">Membros exclusivos</p>
        </div>

        <!-- Clientes Regulares -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float">
                    <i class="fas fa-user text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">Clientes Regulares</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $totalRegular }}</p>
            <p class="text-xs text-gray-500">Clientes frequentes</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-pink-600"></i>
                Filtros Avançados
            </h3>
            <button wire:click="clearFilters" class="text-sm text-pink-600 hover:text-green-700 font-semibold flex items-center">
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
                    <input wire:model.live="search" type="text" placeholder="Nome, email, telefone..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            <!-- VIP Filter -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-crown mr-1"></i>Tipo
                </label>
                <select wire:model.live="filterVip" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 appearance-none bg-white text-sm">
                    <option value="">Todos</option>
                    <option value="vip">VIP</option>
                    <option value="regular">Regulares</option>
                </select>
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
                </select>
            </div>
        </div>

        <!-- Active Filters Display -->
        @if($search || $filterVip)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="flex flex-wrap gap-2">
                    <span class="text-xs font-semibold text-gray-600">Filtros ativos:</span>
                    @if($search)
                        <span class="inline-flex items-center px-3 py-1 bg-pink-100 text-pink-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-search mr-1"></i>{{ $search }}
                            <button wire:click="$set('search', '')" class="ml-2 hover:text-pink-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($filterVip)
                        <span class="inline-flex items-center px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-crown mr-1"></i>{{ $filterVip === 'vip' ? 'VIP' : 'Regulares' }}
                            <button wire:click="$set('filterVip', '')" class="ml-2 hover:text-yellow-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- List -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2 text-pink-600"></i>
                    Lista de Clientes
                </h3>
                <span class="text-sm text-gray-600 font-semibold">
                    <i class="fas fa-users mr-1"></i>{{ $clients->total() }} Total Clientes
                </span>
            </div>
        </div>
        
        <!-- Table Header -->
        <div class="grid grid-cols-12 gap-4 px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-600 uppercase">
            <div class="col-span-3 flex items-center">
                <i class="fas fa-user mr-2 text-pink-500"></i>Cliente
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-phone mr-2 text-green-500"></i>Telefone
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-envelope mr-2 text-purple-500"></i>Email
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-calendar-check mr-2 text-blue-500"></i>Visitas
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-crown mr-2 text-yellow-500"></i>Status
            </div>
            <div class="col-span-1 flex items-center justify-end">
                <i class="fas fa-cog mr-2 text-gray-500"></i>Ações
            </div>
        </div>
        
        <!-- Table Body -->
        <div class="divide-y divide-gray-100">
            @forelse($clients as $client)
                <div class="group grid grid-cols-12 gap-4 px-6 py-4 hover:bg-pink-50 transition-all duration-300 items-center">
                    <!-- Cliente -->
                    <div class="col-span-3 flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-pink-500 to-purple-600 flex items-center justify-center text-white font-bold shadow-lg flex-shrink-0">
                            {{ strtoupper(substr($client->name, 0, 2)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-900 truncate">{{ $client->name }}</p>
                            <p class="text-xs text-gray-500">ID: {{ $client->id }}</p>
                        </div>
                    </div>
                    
                    <!-- Telefone -->
                    <div class="col-span-2">
                        @if($client->phone)
                            <p class="text-sm text-gray-700 flex items-center">
                                <i class="fas fa-phone text-green-500 mr-1.5"></i>{{ $client->phone }}
                            </p>
                        @else
                            <span class="text-xs text-gray-400 italic">Sem telefone</span>
                        @endif
                    </div>
                    
                    <!-- Email -->
                    <div class="col-span-2">
                        @if($client->email)
                            <p class="text-sm text-gray-700 flex items-center">
                                <i class="fas fa-envelope text-purple-500 mr-1.5"></i>
                                <span class="truncate">{{ $client->email }}</span>
                            </p>
                        @else
                            <span class="text-xs text-gray-400 italic">Sem email</span>
                        @endif
                    </div>
                    
                    <!-- Visitas -->
                    <div class="col-span-2">
                        <span class="inline-flex items-center px-2.5 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold">
                            <i class="fas fa-calendar-check mr-1"></i>{{ $client->total_visits ?? 0 }} visitas
                        </span>
                    </div>
                    
                    <!-- Status -->
                    <div class="col-span-2">
                        @if($client->is_vip)
                            <span class="inline-flex items-center px-2.5 py-1 bg-yellow-100 text-yellow-700 rounded-lg text-xs font-bold">
                                <i class="fas fa-crown mr-1"></i>VIP
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs font-bold">
                                <i class="fas fa-user mr-1"></i>Regular
                            </span>
                        @endif
                    </div>
                    
                    <!-- Ações -->
                    <div class="col-span-1 flex items-center justify-end space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button wire:click="view({{ $client->id }})" class="w-8 h-8 flex items-center justify-center bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Ver">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                        <button wire:click="openModal({{ $client->id }})" class="w-8 h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Editar">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        <button wire:click="toggleVip({{ $client->id }})" class="w-8 h-8 flex items-center justify-center {{ $client->is_vip ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-gray-400 hover:bg-gray-500' }} text-white rounded-lg transition shadow-md hover:shadow-lg" title="VIP">
                            <i class="fas fa-crown text-xs"></i>
                        </button>
                        <button wire:click="openDeleteModal({{ $client->id }})" class="w-8 h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Excluir">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum cliente encontrado</h3>
                    <p class="text-gray-500 mb-4">Crie um novo cliente para começar</p>
                </div>
            @endforelse
        </div>

        @if($clients->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $clients->links() }}
            </div>
        @endif
    </div>

    <!-- Modals -->
    @include('livewire.salon.clients.partials.form-modal')
    @include('livewire.salon.clients.partials.view-modal')
    @include('livewire.salon.clients.partials.delete-modal')

    <!-- Toastr Notifications -->
    @include('livewire.salon.partials.toastr-notifications')
</div>
