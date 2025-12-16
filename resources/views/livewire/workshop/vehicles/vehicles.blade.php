<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-car text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Gestão de Veículos</h2>
                    <p class="text-blue-100 text-sm">Gerencie os veículos dos seus clientes</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Veículo
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50">
                    <i class="fas fa-car text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Veículos Ativos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $vehicles->where('status', 'active')->count() }}
            </p>
            <p class="text-xs text-gray-500">Em circulação</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-orange-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl flex items-center justify-center shadow-lg shadow-orange-500/50">
                    <i class="fas fa-wrench text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-orange-600 font-semibold mb-2">Em Serviço</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $vehicles->where('status', 'in_service')->count() }}
            </p>
            <p class="text-xs text-gray-500">Na oficina</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-500/50">
                    <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-yellow-600 font-semibold mb-2">Docs Vencendo</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $vehicles->filter(function($v) { return $v->is_document_expired || count($v->expiring_documents) > 0; })->count() }}
            </p>
            <p class="text-xs text-gray-500">Requer atenção</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-gray-500 to-gray-600 rounded-2xl flex items-center justify-center shadow-lg shadow-gray-500/50">
                    <i class="fas fa-archive text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 font-semibold mb-2">Inativos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $vehicles->where('status', 'inactive')->count() }}
            </p>
            <p class="text-xs text-gray-500">Arquivados</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-blue-600"></i>
                Filtros Avançados
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-search mr-1 text-blue-600"></i>Pesquisar
                </label>
                <input type="text" wire:model.live="search" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                       placeholder="Matrícula, proprietário, marca, modelo...">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-info-circle mr-1 text-purple-600"></i>Status
                </label>
                <select wire:model.live="statusFilter" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    <option value="">Todos Status</option>
                    <option value="active">✅ Ativo</option>
                    <option value="in_service">🔧 Em Serviço</option>
                    <option value="completed">✔️ Concluído</option>
                    <option value="inactive">❌ Inativo</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Lista de Veículos --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        {{-- Header --}}
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2 text-blue-600"></i>
                    Lista de Veículos
                </h3>
                <span class="text-sm text-gray-600">
                    <i class="fas fa-database mr-1"></i>
                    Total: <span class="font-bold">{{ $vehicles->total() }}</span>
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-hashtag mr-2"></i>Matrícula
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-user mr-2"></i>Proprietário
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-car mr-2"></i>Veículo
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-tachometer-alt mr-2"></i>KM
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-file-alt mr-2"></i>Documentos
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-toggle-on mr-2"></i>Status
                        </th>
                        <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider">
                            <i class="fas fa-cog mr-2"></i>Ações
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($vehicles as $vehicle)
                        <tr class="hover:bg-blue-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-car text-blue-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-bold text-gray-900">{{ $vehicle->plate }}</div>
                                        <div class="text-xs text-gray-500">{{ $vehicle->vehicle_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ $vehicle->owner_name }}</div>
                                @if($vehicle->owner_phone)
                                    <div class="text-xs text-gray-500">
                                        <i class="fas fa-phone mr-1"></i>{{ $vehicle->owner_phone }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ $vehicle->brand }} {{ $vehicle->model }}</div>
                                <div class="text-xs text-gray-500">
                                    @if($vehicle->year)
                                        {{ $vehicle->year }} •
                                    @endif
                                    @if($vehicle->color)
                                        {{ $vehicle->color }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ number_format($vehicle->mileage, 0, ',', '.') }} km
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($vehicle->is_document_expired)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Vencido
                                    </span>
                                @elseif(count($vehicle->expiring_documents) > 0)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-clock mr-1"></i>
                                        Vencendo
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                        <i class="fas fa-check mr-1"></i>
                                        OK
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($vehicle->status === 'active')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>Ativo
                                    </span>
                                @elseif($vehicle->status === 'in_service')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                        <i class="fas fa-wrench mr-1"></i>Em Serviço
                                    </span>
                                @elseif($vehicle->status === 'completed')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                                        <i class="fas fa-flag-checkered mr-1"></i>Concluído
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                                        <i class="fas fa-times-circle mr-1"></i>Inativo
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <button wire:click="view({{ $vehicle->id }})" 
                                            class="text-purple-600 hover:text-purple-900 hover:bg-purple-50 p-2 rounded-lg transition-all"
                                            title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button wire:click="edit({{ $vehicle->id }})" 
                                            class="text-blue-600 hover:text-blue-900 hover:bg-blue-50 p-2 rounded-lg transition-all"
                                            title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button wire:click="delete({{ $vehicle->id }})" 
                                            onclick="return confirm('Tem certeza que deseja remover este veículo?')"
                                            class="text-red-600 hover:text-red-900 hover:bg-red-50 p-2 rounded-lg transition-all"
                                            title="Remover">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-car text-6xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500 text-lg font-medium">Nenhum veículo encontrado</p>
                                    <p class="text-gray-400 text-sm mt-2">Clique em "Novo Veículo" para começar</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $vehicles->links() }}
        </div>
    </div>

    {{-- Modal de Edição --}}
    @if($showModal)
        @include('livewire.workshop.vehicles.partials.form-modal')
    @endif
    
    {{-- Modal de Visualização --}}
    @if($showViewModal)
        @include('livewire.workshop.vehicles.partials.view-modal')
    @endif
</div>
