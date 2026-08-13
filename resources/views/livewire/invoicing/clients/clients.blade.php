<div>
    <!-- Header -->
    <div class="mb-4 sm:mb-6 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-lg p-4 sm:p-6 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3 sm:mr-4">
                    <i class="fas fa-users text-xl sm:text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-lg sm:text-2xl font-bold">{{ __('Clientes') }}</h2>
                    <p class="text-green-100 text-xs sm:text-sm">{{ __('Gerir clientes') }}</p>
                </div>
            </div>
            <button wire:click="create"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-70 scale-95"
                    class="group bg-white text-green-600 hover:bg-green-50 px-4 sm:px-6 py-2 sm:py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105 text-sm sm:text-base disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="create">
                    <i class="fas fa-plus mr-2 group-hover:rotate-90 transition-transform duration-300"></i>{{ __('Novo Cliente') }}
                </span>
                <span wire:loading wire:target="create">
                    <i class="fas fa-spinner fa-spin mr-2"></i>{{ __('Abrindo...') }}
                </span>
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-6 mb-4 sm:mb-6 stagger-animation">
        <!-- Total Clientes -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-users text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">{{ __('Total Clientes') }}</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $clients->total() }}</p>
            <p class="text-xs text-gray-500">{{ __('Clientes registados') }}</p>
        </div>

        <!-- Pessoa Jurídica -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-building text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">{{ __('Pessoa Jurídica') }}</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ \App\Models\Client::where('tenant_id', activeTenantId())->where('type', 'pessoa_juridica')->count() }}</p>
            <p class="text-xs text-gray-500">{{ __('Empresas') }}</p>
        </div>

        <!-- Pessoa Física -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float">
                    <i class="fas fa-user text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">{{ __('Pessoa Física') }}</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ \App\Models\Client::where('tenant_id', activeTenantId())->where('type', 'pessoa_fisica')->count() }}</p>
            <p class="text-xs text-gray-500">{{ __('Indivíduos') }}</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-green-600"></i>
                {{ __('Filtros Avançados') }}
            </h3>
            <button wire:click="clearFilters" class="text-sm text-green-600 hover:text-green-700 font-semibold flex items-center">
                <i class="fas fa-redo mr-1"></i>{{ __('Limpar Filtros') }}
            </button>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 sm:gap-4">
            <!-- Search -->
            <div class="col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>{{ __('Pesquisar') }}
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Nome, NIF, email, telefone...') }}" 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            <!-- Type Filter -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-user-tag mr-1"></i>{{ __('Tipo') }}
                </label>
                <select wire:model.live="typeFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 appearance-none bg-white text-sm">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="pessoa_juridica">{{ __('Pessoa Jurídica') }}</option>
                    <option value="pessoa_fisica">{{ __('Pessoa Física') }}</option>
                </select>
            </div>

            <!-- City Filter -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-city mr-1"></i>{{ __('Cidade') }}
                </label>
                <select wire:model.live="cityFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 appearance-none bg-white text-sm">
                    <option value="">{{ __('Todas') }}</option>
                    @foreach($cities as $city)
                        <option value="{{ $city }}">{{ $city }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Per Page -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-list mr-1"></i>{{ __('Por Página') }}
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
                    <i class="fas fa-calendar-alt mr-1"></i>{{ __('Data de Cadastro (De)') }}
                </label>
                <input wire:model.live="dateFrom" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar-alt mr-1"></i>{{ __('Data de Cadastro (Até)') }}
                </label>
                <input wire:model.live="dateTo" type="date" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all text-sm">
            </div>
        </div>

        <!-- Active Filters Display -->
        @if($search || $typeFilter || $cityFilter || $dateFrom || $dateTo)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="flex flex-wrap gap-2">
                    <span class="text-xs font-semibold text-gray-600">{{ __('Filtros ativos:') }}</span>
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
                    {{ __('Lista de Clientes') }}
                </h3>
                <span class="text-sm text-gray-600 font-semibold">
                    <i class="fas fa-users mr-1"></i>{{ $clients->total() }} Total Clientes
                </span>
            </div>
        </div>
        
        <!-- Table Header -->
        <div class="overflow-x-auto">
        <div class="grid grid-cols-12 gap-4 px-4 sm:px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-600 uppercase min-w-[600px]">
            <div class="col-span-4 sm:col-span-3 flex items-center">
                <i class="fas fa-user mr-2 text-green-500"></i>{{ __('Cliente') }}
            </div>
            <div class="col-span-2 flex items-center">
                <i class="fas fa-id-card mr-2 text-blue-500"></i>NIF
            </div>
            <div class="col-span-2 hidden md:flex items-center">
                <i class="fas fa-envelope mr-2 text-purple-500"></i>{{ __('Contato') }}
            </div>
            <div class="col-span-2 hidden lg:flex items-center">
                <i class="fas fa-map-marker-alt mr-2 text-red-500"></i>{{ __('Localização') }}
            </div>
            <div class="col-span-2 hidden sm:flex items-center">
                <i class="fas fa-tag mr-2 text-orange-500"></i>{{ __('Tipo') }}
            </div>
            <div class="col-span-4 sm:col-span-2 lg:col-span-1 flex items-center justify-end">
                <i class="fas fa-cog mr-2 text-gray-500"></i>{{ __('Ações') }}
            </div>
        </div>
        
        <!-- Table Body -->
        <div class="divide-y divide-gray-100">
            @forelse($clients as $client)
                <div class="group grid grid-cols-12 gap-4 px-4 sm:px-6 py-3 sm:py-4 hover:bg-green-50 transition-all duration-300 items-center min-w-[600px]">
                    <!-- Cliente -->
                    <div class="col-span-4 sm:col-span-3 flex items-center space-x-3">
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
                    <div class="col-span-2 hidden md:block">
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
                            <span class="text-xs text-gray-400 italic">{{ __('Sem contato') }}</span>
                        @endif
                    </div>
                    
                    <!-- Localização -->
                    <div class="col-span-2 hidden lg:block">
                        @if($client->city)
                            <p class="text-sm text-gray-700 flex items-center">
                                <i class="fas fa-map-marker-alt text-red-500 mr-1.5"></i>{{ $client->city }}
                            </p>
                        @else
                            <span class="text-xs text-gray-400 italic">{{ __('Não informado') }}</span>
                        @endif
                    </div>
                    
                    <!-- Tipo -->
                    <div class="col-span-2 hidden sm:block">
                        <span class="inline-flex items-center px-2.5 py-1 {{ $client->type === 'pessoa_juridica' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }} rounded-lg text-xs font-bold">
                            <i class="fas {{ $client->type === 'pessoa_juridica' ? 'fa-building' : 'fa-user' }} mr-1"></i>
                            {{ $client->type === 'pessoa_juridica' ? 'Empresa' : 'Pessoa Física' }}
                        </span>
                    </div>
                    
                    <!-- Ações -->
                    <div class="col-span-4 sm:col-span-2 lg:col-span-1 flex items-center justify-end space-x-1">
                        <button wire:click="viewClient({{ $client->id }})"
                                wire:loading.attr="disabled"
                                wire:target="viewClient({{ $client->id }})"
                                class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg hover:scale-110 disabled:opacity-50" title="{{ __('Ver Detalhes') }}">
                            <i class="fas fa-eye text-xs" wire:loading.remove wire:target="viewClient({{ $client->id }})"></i>
                            <i class="fas fa-spinner fa-spin text-xs" wire:loading wire:target="viewClient({{ $client->id }})"></i>
                        </button>
                        <button wire:click="edit({{ $client->id }})"
                                wire:loading.attr="disabled"
                                class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg hover:scale-110 disabled:opacity-50" title="{{ __('Editar') }}">
                            <i class="fas fa-edit text-xs" wire:loading.remove></i>
                            <i class="fas fa-spinner fa-spin text-xs" wire:loading></i>
                        </button>
                        <button wire:click="confirmDelete({{ $client->id }})"
                                wire:loading.attr="disabled"
                                class="w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg hover:scale-110 disabled:opacity-50" title="{{ __('Excluir') }}">
                            <i class="fas fa-trash text-xs" wire:loading.remove></i>
                            <i class="fas fa-spinner fa-spin text-xs" wire:loading></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('Nenhum cliente encontrado') }}</h3>
                    <p class="text-gray-500 mb-4">{{ __('Crie um novo cliente para começar') }}</p>
                </div>
            @endforelse
        </div>

        @if($clients->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
                {{ $clients->links() }}
            </div>
        @endif
        </div>
    </div>

    <!-- Modals -->
    @include('livewire.invoicing.clients.partials.form-modal')
    @include('livewire.invoicing.clients.partials.view-modal')
    <x-delete-confirmation-modal 
        :itemName="$deletingClientName" 
        entityType="o cliente" 
        icon="fa-user-times" 
    />
</div>
