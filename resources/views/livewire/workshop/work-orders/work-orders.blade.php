<div>
    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" 
             class="mb-6 bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl shadow-lg p-4 text-white flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-2xl mr-3"></i>
                <span class="font-semibold">{{ session('success') }}</span>
            </div>
            <button @click="show = false" class="text-white hover:text-green-100 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-orange-600 to-red-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-clipboard-list text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Ordens de Serviço</h2>
                    <p class="text-orange-100 text-sm">Gerencie as ordens de serviço da oficina</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-orange-600 hover:bg-orange-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Nova OS
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-500/50">
                    <i class="fas fa-clock text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-yellow-600 font-semibold mb-2">Pendentes</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $workOrders->where('status', 'pending')->count() }}
            </p>
            <p class="text-xs text-gray-500">Aguardando início</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50">
                    <i class="fas fa-cog text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Em Andamento</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $workOrders->where('status', 'in_progress')->count() }}
            </p>
            <p class="text-xs text-gray-500">Sendo executadas</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Concluídas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $workOrders->whereIn('status', ['completed', 'delivered'])->count() }}
            </p>
            <p class="text-xs text-gray-500">Finalizadas</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-red-100 card-hover">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/50">
                    <i class="fas fa-exclamation-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-red-600 font-semibold mb-2">Urgentes</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $workOrders->where('priority', 'urgent')->count() }}
            </p>
            <p class="text-xs text-gray-500">Alta prioridade</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-orange-600"></i>
                Filtros Avançados
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-search mr-1 text-orange-600"></i>Pesquisar
                </label>
                <input type="text" wire:model.live="search" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                       placeholder="Nº OS, matrícula, proprietário...">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-info-circle mr-1 text-blue-600"></i>Status
                </label>
                <select wire:model.live="statusFilter" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                    <option value="">Todos Status</option>
                    <option value="pending">🕒 Pendente</option>
                    <option value="scheduled">📅 Agendada</option>
                    <option value="in_progress">🛠️ Em Andamento</option>
                    <option value="waiting_parts">⏸️ Aguardando Peças</option>
                    <option value="completed">✅ Concluída</option>
                    <option value="delivered">🏁 Entregue</option>
                    <option value="cancelled">❌ Cancelada</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-exclamation-circle mr-1 text-red-600"></i>Prioridade
                </label>
                <select wire:model.live="priorityFilter" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                    <option value="">Todas Prioridades</option>
                    <option value="low">🔽 Baixa</option>
                    <option value="normal">➡️ Normal</option>
                    <option value="high">🔼 Alta</option>
                    <option value="urgent">⚠️ Urgente</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Lista de Ordens de Serviço --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        {{-- Header --}}
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2 text-orange-600"></i>
                    Lista de Ordens de Serviço
                </h3>
                <span class="text-sm text-gray-600">
                    <i class="fas fa-database mr-1"></i>
                    Total: <span class="font-bold">{{ $workOrders->total() }}</span>
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase">Nº OS</th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase">Veículo</th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase">Problema</th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase">Mecânico</th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase">Data</th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase">Prioridade</th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-bold uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($workOrders as $order)
                            <tr class="hover:bg-blue-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-gray-900">{{ $order->order_number }}</div>
                                    <div class="text-xs text-gray-500">{{ $order->received_at->format('d/m/Y') }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $order->vehicle->plate }}</div>
                                    <div class="text-xs text-gray-500">{{ $order->vehicle->brand }} {{ $order->vehicle->model }}</div>
                                    <div class="text-xs text-gray-400">{{ $order->vehicle->owner_name }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-xs truncate">
                                        {{ $order->problem_description }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($order->mechanic)
                                        <div class="text-sm text-gray-900">{{ $order->mechanic->full_name }}</div>
                                    @else
                                        <span class="text-xs text-gray-400">Não atribuído</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $order->received_at->format('d/m/Y') }}</div>
                                    @if($order->scheduled_for)
                                        <div class="text-xs text-blue-600">
                                            <i class="fas fa-calendar mr-1"></i>{{ $order->scheduled_for->format('d/m H:i') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($order->priority === 'urgent')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>Urgente
                                        </span>
                                    @elseif($order->priority === 'high')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-800">
                                            <i class="fas fa-arrow-up mr-1"></i>Alta
                                        </span>
                                    @elseif($order->priority === 'low')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-800">
                                            <i class="fas fa-arrow-down mr-1"></i>Baixa
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                            Normal
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($order->status === 'pending')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-clock mr-1"></i>Pendente
                                        </span>
                                    @elseif($order->status === 'scheduled')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                            <i class="fas fa-calendar-check mr-1"></i>Agendada
                                        </span>
                                    @elseif($order->status === 'in_progress')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-800">
                                            <i class="fas fa-tools mr-1"></i>Em Andamento
                                        </span>
                                    @elseif($order->status === 'waiting_parts')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                                            <i class="fas fa-pause mr-1"></i>Aguardando Peças
                                        </span>
                                    @elseif($order->status === 'completed')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-1"></i>Concluída
                                        </span>
                                    @elseif($order->status === 'delivered')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-800">
                                            <i class="fas fa-flag-checkered mr-1"></i>Entregue
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800">
                                            <i class="fas fa-times mr-1"></i>Cancelada
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <button wire:click="edit({{ $order->id }})" 
                                                class="text-blue-600 hover:text-blue-900 hover:bg-blue-50 p-2 rounded-lg"
                                                title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button wire:click="delete({{ $order->id }})" 
                                                onclick="return confirm('Tem certeza?')"
                                                class="text-red-600 hover:text-red-900 hover:bg-red-50 p-2 rounded-lg"
                                                title="Remover">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <i class="fas fa-clipboard-list text-6xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500 text-lg font-medium">Nenhuma OS encontrada</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="bg-gray-50 px-6 py-4 border-t">
                {{ $workOrders->links() }}
            </div>
        </div>
    </div>

    {{-- Modal --}}
    @if($showModal)
        @include('livewire.workshop.work-orders.partials.form-modal')
    @endif
</div>
