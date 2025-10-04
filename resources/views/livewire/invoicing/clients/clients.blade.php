<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Clientes</h2>
                    <p class="text-green-100 text-sm">Gerir clientes</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-green-600 hover:bg-green-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Cliente
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 stagger-animation">
        <!-- Total Clientes -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-users text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Total Clientes</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $clients->total() }}</p>
            <p class="text-xs text-gray-500">Clientes registados</p>
        </div>

        <!-- Pessoa Jurídica -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-building text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Pessoa Jurídica</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ \App\Models\Client::where('tenant_id', auth()->user()->tenant_id)->where('type', 'pessoa_juridica')->count() }}</p>
            <p class="text-xs text-gray-500">Empresas</p>
        </div>

        <!-- Pessoa Física -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float">
                    <i class="fas fa-user text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">Pessoa Física</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ \App\Models\Client::where('tenant_id', auth()->user()->tenant_id)->where('type', 'pessoa_fisica')->count() }}</p>
            <p class="text-xs text-gray-500">Indivíduos</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-green-600"></i>
                Filtros Avançados
            </h3>
            <button wire:click="clearFilters" class="text-sm text-green-600 hover:text-green-700 font-semibold flex items-center">
                <i class="fas fa-redo mr-1"></i>Limpar Filtros
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live="search" type="text" placeholder="Nome, NIF, email, telefone..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            <!-- Type Filter -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-user-tag mr-1"></i>Tipo
                </label>
                <select wire:model.live="typeFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 appearance-none bg-white text-sm">
                    <option value="">Todos</option>
                    <option value="pessoa_juridica">Pessoa Jurídica</option>
                    <option value="pessoa_fisica">Pessoa Física</option>
                </select>
            </div>

            <!-- City Filter -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-city mr-1"></i>Cidade
                </label>
                <select wire:model.live="cityFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 appearance-none bg-white text-sm">
                    <option value="">Todas</option>
                    @foreach($cities as $city)
                        <option value="{{ $city }}">{{ $city }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Per Page -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-list mr-1"></i>Por Página
                </label>
                <select wire:model.live="perPage" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 appearance-none bg-white text-sm">
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <!-- Date Range -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar-alt mr-1"></i>Data de Cadastro (De)
                </label>
                <input wire:model.live="dateFrom" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar-alt mr-1"></i>Data de Cadastro (Até)
                </label>
                <input wire:model.live="dateTo" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all text-sm">
            </div>
        </div>

        <!-- Active Filters Display -->
        @if($search || $typeFilter || $cityFilter || $dateFrom || $dateTo)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="flex flex-wrap gap-2">
                    <span class="text-xs font-semibold text-gray-600">Filtros ativos:</span>
                    @if($search)
                        <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-search mr-1"></i>{{ $search }}
                            <button wire:click="$set('search', '')" class="ml-2 hover:text-green-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($typeFilter)
                        <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-user-tag mr-1"></i>{{ $typeFilter === 'pessoa_juridica' ? 'Pessoa Jurídica' : 'Pessoa Física' }}
                            <button wire:click="$set('typeFilter', '')" class="ml-2 hover:text-blue-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($cityFilter)
                        <span class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-city mr-1"></i>{{ $cityFilter }}
                            <button wire:click="$set('cityFilter', '')" class="ml-2 hover:text-purple-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($dateFrom)
                        <span class="inline-flex items-center px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-calendar mr-1"></i>De: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}
                            <button wire:click="$set('dateFrom', '')" class="ml-2 hover:text-orange-900">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    @endif
                    @if($dateTo)
                        <span class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">
                            <i class="fas fa-calendar mr-1"></i>Até: {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
                            <button wire:click="$set('dateTo', '')" class="ml-2 hover:text-red-900">
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
                    <i class="fas fa-list mr-2 text-green-600"></i>
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
                <i class="fas fa-user mr-2 text-green-500"></i>Cliente
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-id-card mr-2 text-blue-500"></i>NIF
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-envelope mr-2 text-purple-500"></i>Contato
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-map-marker-alt mr-2 text-red-500"></i>Localização
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-tag mr-2 text-orange-500"></i>Tipo
            </div>
            <div class="col-span-1 flex items-center justify-end">
                <i class="fas fa-cog mr-2 text-gray-500"></i>Ações
            </div>
        </div>
        
        <!-- Table Body -->
        <div class="divide-y divide-gray-100">
            @forelse($clients as $client)
                <div class="group grid grid-cols-12 gap-4 px-6 py-4 hover:bg-green-50 transition-all duration-300 items-center">
                    <!-- Cliente -->
                    <div class="col-span-3 flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white font-bold shadow-lg flex-shrink-0">
                            {{ strtoupper(substr($client->name, 0, 2)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-900 truncate">{{ $client->name }}</p>
                            <p class="text-xs text-gray-500">ID: {{ $client->id }}</p>
                        </div>
                    </div>
                    
                    <!-- NIF -->
                    <div class="col-span-2">
                        <span class="inline-flex items-center px-2.5 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold">
                            <i class="fas fa-id-card mr-1"></i>{{ $client->nif }}
                        </span>
                    </div>
                    
                    <!-- Contato -->
                    <div class="col-span-2">
                        @if($client->email)
                            <p class="text-sm text-gray-700 flex items-center mb-1">
                                <i class="fas fa-envelope text-purple-500 mr-1.5"></i>
                                <span class="truncate">{{ $client->email }}</span>
                            </p>
                        @endif
                        @if($client->phone)
                            <p class="text-sm text-gray-700 flex items-center">
                                <i class="fas fa-phone text-green-500 mr-1.5"></i>{{ $client->phone }}
                            </p>
                        @else
                            <span class="text-xs text-gray-400 italic">Sem contato</span>
                        @endif
                    </div>
                    
                    <!-- Localização -->
                    <div class="col-span-2">
                        @if($client->city)
                            <p class="text-sm text-gray-700 flex items-center">
                                <i class="fas fa-map-marker-alt text-red-500 mr-1.5"></i>{{ $client->city }}
                            </p>
                        @else
                            <span class="text-xs text-gray-400 italic">Não informado</span>
                        @endif
                    </div>
                    
                    <!-- Tipo -->
                    <div class="col-span-2">
                        <span class="inline-flex items-center px-2.5 py-1 {{ $client->type === 'pessoa_juridica' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }} rounded-lg text-xs font-bold">
                            <i class="fas {{ $client->type === 'pessoa_juridica' ? 'fa-building' : 'fa-user' }} mr-1"></i>
                            {{ $client->type === 'pessoa_juridica' ? 'Empresa' : 'Pessoa Física' }}
                        </span>
                    </div>
                    
                    <!-- Ações -->
                    <div class="col-span-1 flex items-center justify-end space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button wire:click="edit({{ $client->id }})" class="w-8 h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Editar">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        <button wire:click="confirmDelete({{ $client->id }})" class="w-8 h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition shadow-md hover:shadow-lg" title="Excluir">
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
    @include('livewire.invoicing.clients.partials.form-modal')
    <x-delete-confirmation-modal 
        :itemName="$deletingClientName" 
        entityType="o cliente" 
        icon="fa-user-times" 
    />
</div>
