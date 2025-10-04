<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-warehouse text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Armazéns</h2>
                    <p class="text-indigo-100 text-sm">Gerir armazéns da empresa</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-indigo-600 hover:bg-indigo-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Armazém
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="mb-6 bg-green-50 border-l-4 border-green-500 rounded-xl p-4 shadow-md animate-fade-in">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                <p class="text-green-700 font-semibold">{{ session('message') }}</p>
            </div>
        </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 stagger-animation">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-indigo-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/50 icon-float">
                    <i class="fas fa-warehouse text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-indigo-600 font-semibold mb-2">Total Armazéns</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['total'] }}</p>
            <p class="text-xs text-gray-500">Armazéns registados</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Ativos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['active'] }}</p>
            <p class="text-xs text-gray-500">Em operação</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-red-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/50 icon-float">
                    <i class="fas fa-times-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-red-600 font-semibold mb-2">Inativos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['inactive'] }}</p>
            <p class="text-xs text-gray-500">Desativados</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-indigo-600"></i>
                Filtros Avançados
            </h3>
            <button wire:click="$set('search', '')" class="text-sm text-indigo-600 hover:text-indigo-700 font-semibold flex items-center">
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
                    <input type="text" wire:model.live.debounce.300ms="search" 
                           class="w-full pl-10 pr-4 py-3 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                           placeholder="Nome, código ou cidade...">
                </div>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-toggle-on mr-1"></i>Status
                </label>
                <select wire:model.live="statusFilter" 
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                    <option value="">Todos</option>
                    <option value="1">Ativos</option>
                    <option value="0">Inativos</option>
                </select>
            </div>

            <!-- Per Page -->
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-list mr-1"></i>Por Página
                </label>
                <select wire:model.live="perPage" 
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-indigo-50 to-purple-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-indigo-900 uppercase tracking-wider">
                            <i class="fas fa-warehouse mr-2"></i>Armazém
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-indigo-900 uppercase tracking-wider">
                            <i class="fas fa-barcode mr-2"></i>Código
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-indigo-900 uppercase tracking-wider">
                            <i class="fas fa-map-marker-alt mr-2"></i>Localização
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-indigo-900 uppercase tracking-wider">
                            <i class="fas fa-user-tie mr-2"></i>Gestor
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-indigo-900 uppercase tracking-wider">
                            <i class="fas fa-info-circle mr-2"></i>Status
                        </th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-indigo-900 uppercase tracking-wider">
                            <i class="fas fa-cog mr-2"></i>Ações
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($warehouses as $warehouse)
                    <tr class="hover:bg-indigo-50/50 transition group">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-12 w-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition">
                                    <i class="fas fa-warehouse text-white text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-bold text-gray-900 flex items-center">
                                        {{ $warehouse->name }}
                                        @if($warehouse->is_default)
                                            <span class="ml-2 px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full">
                                                <i class="fas fa-star mr-1"></i>Padrão
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <i class="fas fa-city mr-1"></i>{{ $warehouse->city ?? 'Não definida' }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-800 text-xs font-bold rounded-lg">
                                {{ $warehouse->code }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            <i class="fas fa-location-dot mr-1 text-indigo-500"></i>{{ $warehouse->location ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            @if($warehouse->manager)
                                <div class="flex items-center">
                                    <i class="fas fa-user-circle mr-2 text-indigo-500"></i>
                                    {{ $warehouse->manager->name }}
                                </div>
                            @else
                                <span class="text-gray-400 text-xs">Sem gestor</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($warehouse->is_active)
                                <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full flex items-center w-fit">
                                    <i class="fas fa-check-circle mr-1"></i>Ativo
                                </span>
                            @else
                                <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full flex items-center w-fit">
                                    <i class="fas fa-times-circle mr-1"></i>Inativo
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                @if(!$warehouse->is_default)
                                    <button wire:click="setDefault({{ $warehouse->id }})" 
                                            class="text-yellow-600 hover:text-yellow-900 transition p-2 hover:bg-yellow-50 rounded-lg"
                                            title="Definir como padrão">
                                        <i class="fas fa-star"></i>
                                    </button>
                                @endif
                                <button wire:click="toggleStatus({{ $warehouse->id }})" 
                                        class="text-blue-600 hover:text-blue-900 transition p-2 hover:bg-blue-50 rounded-lg"
                                        title="Alternar Status">
                                    <i class="fas fa-toggle-on"></i>
                                </button>
                                <button wire:click="edit({{ $warehouse->id }})" 
                                        class="text-indigo-600 hover:text-indigo-900 transition p-2 hover:bg-indigo-50 rounded-lg"
                                        title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="confirmDelete({{ $warehouse->id }})" 
                                        class="text-red-600 hover:text-red-900 transition p-2 hover:bg-red-50 rounded-lg"
                                        title="Excluir">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-warehouse text-gray-400 text-3xl"></i>
                                </div>
                                <p class="text-gray-500 text-lg font-semibold mb-2">Nenhum armazém encontrado</p>
                                <p class="text-gray-400 text-sm">Comece criando um novo armazém</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $warehouses->links() }}
        </div>
    </div>

    <!-- Modals -->
    @include('livewire.invoicing.warehouses.partials.form-modal')
    @include('livewire.invoicing.warehouses.partials.delete-modal')
</div>
