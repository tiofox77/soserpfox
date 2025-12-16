<div>
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-tools text-orange-500"></i>
                Manutenção
            </h1>
            <p class="text-gray-500 dark:text-gray-400">Ordens de manutenção preventiva e corretiva</p>
        </div>
        <div class="flex gap-2">
            <button wire:click="$set('viewMode', 'list')" class="px-4 py-2 rounded-lg transition {{ $viewMode === 'list' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                <i class="fas fa-list"></i>
            </button>
            <button wire:click="$set('viewMode', 'kanban')" class="px-4 py-2 rounded-lg transition {{ $viewMode === 'kanban' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                <i class="fas fa-columns"></i>
            </button>
            <button wire:click="openModal" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition flex items-center gap-2">
                <i class="fas fa-plus"></i> Nova Ordem
            </button>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="mb-4 p-4 bg-green-100 border border-green-200 text-green-700 rounded-lg flex items-center gap-2">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ $stats['total'] }}</p>
                </div>
                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-tools text-gray-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pendentes</p>
                    <p class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] }}</p>
                </div>
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Em Progresso</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $stats['in_progress'] }}</p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-wrench text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Urgentes</p>
                    <p class="text-2xl font-bold text-red-600">{{ $stats['urgent'] }}</p>
                </div>
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Concluídas Hoje</p>
                    <p class="text-2xl font-bold text-green-600">{{ $stats['completed_today'] }}</p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-check text-green-600"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros (Lista) --}}
    @if($viewMode === 'list')
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-6">
        <div class="flex flex-wrap gap-4">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Pesquisar..." class="px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 w-64">
            <select wire:model.live="filterStatus" class="px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                <option value="">Todos Status</option>
                <option value="pending">Pendente</option>
                <option value="in_progress">Em Progresso</option>
                <option value="waiting_parts">Aguarda Peças</option>
                <option value="completed">Concluída</option>
            </select>
            <select wire:model.live="filterPriority" class="px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                <option value="">Todas Prioridades</option>
                <option value="urgent">Urgente</option>
                <option value="high">Alta</option>
                <option value="normal">Normal</option>
                <option value="low">Baixa</option>
            </select>
            <select wire:model.live="filterCategory" class="px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                <option value="">Todas Categorias</option>
                <option value="electrical">Elétrica</option>
                <option value="plumbing">Canalização</option>
                <option value="hvac">Ar Condicionado</option>
                <option value="furniture">Mobiliário</option>
                <option value="appliance">Equipamentos</option>
                <option value="structural">Estrutural</option>
                <option value="other">Outro</option>
            </select>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Ordem</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Título</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Quarto</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300">Prioridade</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Atribuído</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($orders as $order)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 {{ $order->priority === 'urgent' ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                    <td class="px-4 py-3">
                        <span class="font-mono text-sm text-gray-600 dark:text-gray-400">{{ $order->order_number }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-800 dark:text-white">{{ $order->title }}</p>
                        <p class="text-xs text-gray-500">{{ $order->getCategoryLabel() }} • {{ $order->getTypeLabel() }}</p>
                    </td>
                    <td class="px-4 py-3">
                        @if($order->room)
                        <span class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-medium">{{ $order->room->room_number }}</span>
                        @else
                        <span class="text-gray-400 text-sm">Área comum</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-bold
                            {{ $order->priority === 'urgent' ? 'bg-red-100 text-red-700' : '' }}
                            {{ $order->priority === 'high' ? 'bg-amber-100 text-amber-700' : '' }}
                            {{ $order->priority === 'normal' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $order->priority === 'low' ? 'bg-gray-100 text-gray-700' : '' }}">
                            {{ $order->getPriorityLabel() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-bold
                            {{ $order->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                            {{ $order->status === 'in_progress' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $order->status === 'waiting_parts' ? 'bg-purple-100 text-purple-700' : '' }}
                            {{ $order->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                            {{ $order->status === 'cancelled' ? 'bg-gray-100 text-gray-700' : '' }}">
                            {{ $order->getStatusLabel() }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($order->assignee)
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $order->assignee->name }}</span>
                        @else
                        <button wire:click="assignToMe({{ $order->id }})" class="text-indigo-600 text-sm hover:underline">Atribuir a mim</button>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-1">
                            <button wire:click="viewOrder({{ $order->id }})" class="p-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200" title="Ver">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button wire:click="openModal({{ $order->id }})" class="p-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            @if($order->status !== 'completed')
                            <button wire:click="updateStatus({{ $order->id }}, 'completed')" class="p-2 bg-green-100 text-green-600 rounded-lg hover:bg-green-200" title="Concluir">
                                <i class="fas fa-check"></i>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                        <i class="fas fa-tools text-4xl mb-4 opacity-50"></i>
                        <p>Nenhuma ordem de manutenção</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t">{{ $orders->links() }}</div>
    </div>
    @endif

    {{-- Kanban View --}}
    @if($viewMode === 'kanban')
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        @foreach(['pending' => ['Pendentes', 'yellow'], 'in_progress' => ['Em Progresso', 'blue'], 'waiting_parts' => ['Aguarda Peças', 'purple'], 'completed' => ['Concluídas', 'green']] as $status => $info)
        <div class="bg-{{ $info[1] }}-50 dark:bg-{{ $info[1] }}-900/20 rounded-xl p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-{{ $info[1] }}-800 dark:text-{{ $info[1] }}-300">{{ $info[0] }}</h3>
                <span class="px-2 py-1 bg-{{ $info[1] }}-200 text-{{ $info[1] }}-800 rounded-full text-xs font-bold">{{ $ordersByStatus[$status]->count() }}</span>
            </div>
            <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                @forelse($ordersByStatus[$status] as $order)
                <div wire:click="viewOrder({{ $order->id }})" class="bg-white dark:bg-gray-800 rounded-lg p-3 shadow-sm cursor-pointer hover:shadow-md transition border-l-4
                    {{ $order->priority === 'urgent' ? 'border-red-500' : '' }}
                    {{ $order->priority === 'high' ? 'border-amber-500' : '' }}
                    {{ $order->priority === 'normal' ? 'border-blue-500' : '' }}
                    {{ $order->priority === 'low' ? 'border-gray-400' : '' }}">
                    <p class="font-medium text-gray-800 dark:text-white text-sm">{{ $order->title }}</p>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-xs text-gray-500">
                            @if($order->room) Q.{{ $order->room->room_number }} @else Área comum @endif
                        </span>
                        <span class="px-2 py-0.5 rounded text-xs {{ $order->priority === 'urgent' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $order->getPriorityLabel() }}
                        </span>
                    </div>
                </div>
                @empty
                <p class="text-center text-{{ $info[1] }}-600 py-4 text-sm">Nenhuma</p>
                @endforelse
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Modal Form --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center justify-between sticky top-0 bg-white dark:bg-gray-800">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                    {{ $editingId ? 'Editar Ordem' : 'Nova Ordem de Manutenção' }}
                </h3>
                <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Título *</label>
                    <input type="text" wire:model="title" placeholder="Ex: Ar condicionado não funciona" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    @error('title')<span class="text-red-500 text-xs">{{ $message }}</span>@enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Quarto</label>
                        <select wire:model="roomId" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                            <option value="">Área comum</option>
                            @foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->room_number }} - {{ $room->roomType->name ?? '' }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Atribuir a</label>
                        <select wire:model="assignedTo" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                            <option value="">Não atribuído</option>
                            @foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Tipo</label>
                        <select wire:model="type" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                            <option value="corrective">Corretiva</option>
                            <option value="preventive">Preventiva</option>
                            <option value="emergency">Emergência</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Prioridade</label>
                        <select wire:model="priority" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                            <option value="low">Baixa</option>
                            <option value="normal">Normal</option>
                            <option value="high">Alta</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Categoria</label>
                        <select wire:model="category" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                            <option value="electrical">Elétrica</option>
                            <option value="plumbing">Canalização</option>
                            <option value="hvac">Ar Condicionado</option>
                            <option value="furniture">Mobiliário</option>
                            <option value="appliance">Equipamentos</option>
                            <option value="structural">Estrutural</option>
                            <option value="other">Outro</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Descrição</label>
                    <textarea wire:model="description" rows="3" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600" placeholder="Descreva o problema..."></textarea>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Localização</label>
                        <input type="text" wire:model="location" placeholder="Ex: Banheiro" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Custo Estimado</label>
                        <input type="number" wire:model="estimatedCost" placeholder="Kz" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Tempo Estimado (min)</label>
                        <input type="number" wire:model="estimatedTime" placeholder="Minutos" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Agendamento</label>
                    <input type="datetime-local" wire:model="scheduledDate" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                </div>
            </div>
            <div class="px-6 py-4 border-t dark:border-gray-700 flex justify-end gap-2">
                <button wire:click="$set('showModal', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancelar</button>
                <button wire:click="save" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">Guardar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal View --}}
    @if($showViewModal && $viewingOrder)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center justify-between sticky top-0 bg-white dark:bg-gray-800">
                <div>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">{{ $viewingOrder->title }}</h3>
                    <p class="text-sm text-gray-500">{{ $viewingOrder->order_number }}</p>
                </div>
                <button wire:click="$set('showViewModal', false)" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3">
                        <p class="text-xs text-gray-500">Quarto</p>
                        <p class="font-bold">{{ $viewingOrder->room?->room_number ?? 'Área comum' }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3">
                        <p class="text-xs text-gray-500">Categoria</p>
                        <p class="font-bold">{{ $viewingOrder->getCategoryLabel() }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3">
                        <p class="text-xs text-gray-500">Prioridade</p>
                        <p class="font-bold text-{{ $viewingOrder->getPriorityColor() }}-600">{{ $viewingOrder->getPriorityLabel() }}</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3">
                        <p class="text-xs text-gray-500">Status</p>
                        <p class="font-bold text-{{ $viewingOrder->getStatusColor() }}-600">{{ $viewingOrder->getStatusLabel() }}</p>
                    </div>
                </div>

                @if($viewingOrder->description)
                <div class="mb-6">
                    <p class="text-sm text-gray-500 mb-1">Descrição</p>
                    <p class="text-gray-700 dark:text-gray-300">{{ $viewingOrder->description }}</p>
                </div>
                @endif

                @if($viewingOrder->status !== 'completed')
                <div class="mb-6">
                    <p class="text-sm text-gray-500 mb-2">Alterar Status</p>
                    <div class="flex flex-wrap gap-2">
                        @if($viewingOrder->status === 'pending')
                        <button wire:click="updateStatus({{ $viewingOrder->id }}, 'in_progress')" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-play mr-1"></i> Iniciar
                        </button>
                        @endif
                        @if($viewingOrder->status === 'in_progress')
                        <button wire:click="updateStatus({{ $viewingOrder->id }}, 'waiting_parts')" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                            <i class="fas fa-pause mr-1"></i> Aguarda Peças
                        </button>
                        @endif
                        @if(in_array($viewingOrder->status, ['in_progress', 'waiting_parts']))
                        <button wire:click="updateStatus({{ $viewingOrder->id }}, 'completed')" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                            <i class="fas fa-check mr-1"></i> Concluir
                        </button>
                        @endif
                    </div>
                </div>

                <div class="space-y-4 border-t pt-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Notas de Resolução</label>
                        <textarea wire:model="resolutionNotes" rows="3" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600" placeholder="Descreva o que foi feito..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Custo Real (Kz)</label>
                        <input type="number" wire:model="actualCost" class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                    </div>
                    <button wire:click="completeOrder" class="w-full py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-bold">
                        <i class="fas fa-check-circle mr-2"></i> Marcar como Concluída
                    </button>
                </div>
                @else
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                    <p class="text-green-800 dark:text-green-300 font-bold mb-2">
                        <i class="fas fa-check-circle mr-1"></i> Concluída em {{ $viewingOrder->completed_at?->format('d/m/Y H:i') }}
                    </p>
                    @if($viewingOrder->resolution_notes)
                    <p class="text-gray-600 dark:text-gray-400">{{ $viewingOrder->resolution_notes }}</p>
                    @endif
                    @if($viewingOrder->actual_cost)
                    <p class="mt-2 text-sm text-gray-500">Custo: {{ number_format($viewingOrder->actual_cost, 0, ',', '.') }} Kz</p>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
