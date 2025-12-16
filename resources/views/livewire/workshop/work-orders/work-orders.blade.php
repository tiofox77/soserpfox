<div>
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

    {{-- Lista de Ordens --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
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
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nº OS</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Veículo</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Problema</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Mecânico</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Prioridade</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($workOrders as $order)
                        <tr class="hover:bg-orange-50 transition-colors">
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
                                    <span class="text-xs text-gray-400 italic">Não atribuído</span>
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
                                @elseif($order->status === 'in_progress')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                        <i class="fas fa-tools mr-1"></i>Em Andamento
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
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-400 text-white">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <button wire:click="view({{ $order->id }})" 
                                            class="text-purple-600 hover:text-purple-900 hover:bg-purple-50 p-2 rounded-lg transition-all"
                                            title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button wire:click="edit({{ $order->id }})" 
                                            class="text-blue-600 hover:text-blue-900 hover:bg-blue-50 p-2 rounded-lg transition-all"
                                            title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button wire:click="delete({{ $order->id }})" 
                                            onclick="return confirm('Tem certeza que deseja remover esta OS?')"
                                            class="text-red-600 hover:text-red-900 hover:bg-red-50 p-2 rounded-lg transition-all"
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
                                <p class="text-gray-500 text-lg font-medium">Nenhuma ordem de serviço encontrada</p>
                                <p class="text-gray-400 text-sm mt-2">Clique em "Nova OS" para começar</p>
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

    {{-- Modal de Edição --}}
    @if($showModal)
        @include('livewire.workshop.work-orders.partials.form-modal')
    @endif
    
    {{-- Modal de Visualização --}}
    @if($showViewModal)
        @include('livewire.workshop.work-orders.partials.view-modal')
    @endif
    
    {{-- Modal de Adicionar Item --}}
    @if($showItemModal)
        @include('livewire.workshop.work-orders.partials.item-modal')
    @endif
    
    {{-- Modal de Upload de Anexos --}}
    @if($showUploadModal)
        @include('livewire.workshop.work-orders.partials.upload-modal')
    @endif
    
    {{-- Modal de Confirmação de Geração de Fatura --}}
    @if($showInvoiceConfirmModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            {{-- Overlay --}}
            <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" wire:click="closeInvoiceConfirmModal"></div>

            {{-- Modal --}}
            <div class="inline-block w-full max-w-lg my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-3xl">
                {{-- Header --}}
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-8 py-6">
                    <div class="flex items-center">
                        <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4 shadow-lg">
                            <i class="fas fa-file-invoice-dollar text-white text-3xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-2xl font-bold text-white">Gerar Fatura</h3>
                            <p class="text-green-100 text-sm mt-1">Converter ordem de serviço em fatura</p>
                        </div>
                        <button wire:click="closeInvoiceConfirmModal" class="text-white hover:bg-white/20 rounded-xl p-2 transition-all">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>

                {{-- Content --}}
                <div class="p-8">
                    {{-- Info Box --}}
                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-green-200 rounded-2xl p-6 mb-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <i class="fas fa-info-circle text-white text-xl"></i>
                                </div>
                            </div>
                            <div class="ml-4 flex-1">
                                <h4 class="text-lg font-bold text-green-900 mb-2">Esta ação irá:</h4>
                                <ul class="space-y-2 text-sm text-green-800">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-600 mt-0.5 mr-2"></i>
                                        <span>Criar uma fatura de venda com todos os itens desta OS</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-600 mt-0.5 mr-2"></i>
                                        <span>Vincular a fatura à ordem de serviço automaticamente</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-600 mt-0.5 mr-2"></i>
                                        <span>Redirecionar para o <strong>preview da fatura</strong> para revisão</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {{-- Warning Box --}}
                    <div class="bg-gradient-to-br from-yellow-50 to-amber-50 border-2 border-yellow-200 rounded-2xl p-4 mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl mr-3"></i>
                            <div class="flex-1">
                                <p class="text-sm text-yellow-800 font-medium">
                                    <strong>Atenção:</strong> Esta ação não pode ser desfeita. Certifique-se de que todos os itens e valores estão corretos.
                                </p>
                            </div>
                        </div>
                    </div>

                    <p class="text-center text-gray-700 text-lg font-semibold">
                        Deseja gerar a fatura desta ordem de serviço?
                    </p>
                </div>

                {{-- Footer --}}
                <div class="bg-gray-50 px-8 py-6 flex items-center justify-between border-t border-gray-200">
                    <button wire:click="closeInvoiceConfirmModal"
                            class="px-6 py-3 bg-white hover:bg-gray-100 text-gray-700 border-2 border-gray-300 rounded-xl font-semibold transition-all shadow-sm hover:shadow-md">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>

                    <button wire:click="generateInvoice"
                            wire:loading.attr="disabled"
                            class="px-8 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-bold transition-all shadow-lg hover:shadow-xl transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="generateInvoice">
                            <i class="fas fa-check-circle mr-2"></i>Sim, Gerar Fatura
                        </span>
                        <span wire:loading wire:target="generateInvoice">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Gerando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
