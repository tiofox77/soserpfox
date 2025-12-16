{{-- Modal Detalhes/Checklist --}}
@if($showDetailsModal && $viewingTask)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeDetailsModal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 m-4 max-h-[90vh] overflow-y-auto">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-clipboard-list text-teal-600"></i>
                Tarefa - Quarto {{ $viewingTask->room->number }}
            </h3>
            <button wire:click="closeDetailsModal" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        {{-- Info Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 rounded-xl p-3 text-center">
                <p class="text-xs text-gray-500 mb-1">Tipo</p>
                <p class="font-bold text-gray-900">{{ $viewingTask->task_type_label }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3 text-center">
                <p class="text-xs text-gray-500 mb-1">Prioridade</p>
                <p class="font-bold text-{{ $viewingTask->priority_color }}-600">{{ $viewingTask->priority_label }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3 text-center">
                <p class="text-xs text-gray-500 mb-1">Status</p>
                <p class="font-bold text-{{ $viewingTask->status_color }}-600">{{ $viewingTask->status_label }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3 text-center">
                <p class="text-xs text-gray-500 mb-1">Progresso</p>
                <p class="font-bold text-teal-600">{{ $viewingTask->checklist_progress }}%</p>
            </div>
        </div>

        {{-- Atribuído --}}
        @if($viewingTask->assignedUser)
            <div class="mb-4 flex items-center gap-2 p-3 bg-purple-50 rounded-xl">
                <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
                    {{ substr($viewingTask->assignedUser->name, 0, 1) }}
                </div>
                <div>
                    <p class="text-xs text-purple-600">Atribuído a</p>
                    <p class="font-bold text-purple-700">{{ $viewingTask->assignedUser->name }}</p>
                </div>
            </div>
        @endif

        {{-- Hóspede --}}
        @if($viewingTask->reservation && $viewingTask->reservation->client)
            <div class="mb-4 p-3 bg-blue-50 rounded-xl">
                <div class="flex items-center gap-2">
                    <i class="fas fa-user-tie text-blue-500"></i>
                    <div>
                        <p class="text-xs text-blue-600">Hóspede</p>
                        <p class="font-bold text-blue-700">{{ $viewingTask->reservation->client->name }}</p>
                    </div>
                </div>
                <p class="text-blue-500 text-xs mt-1">Check-out: {{ $viewingTask->reservation->check_out_date->format('d/m/Y') }}</p>
            </div>
        @endif

        {{-- Checklist --}}
        <div class="mb-6">
            <h4 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
                <i class="fas fa-tasks text-teal-600"></i>Checklist
                <span class="text-sm font-normal text-gray-500">({{ $viewingTask->checklist_progress }}% concluído)</span>
            </h4>
            <div class="bg-gray-50 rounded-xl p-4 space-y-2 max-h-60 overflow-y-auto">
                @foreach($viewingTask->checklist ?? [] as $index => $item)
                    <label class="flex items-center gap-3 cursor-pointer hover:bg-white rounded-lg p-2 transition">
                        <input type="checkbox" 
                               wire:click="toggleChecklistItem({{ $viewingTask->id }}, {{ $index }})"
                               {{ $item['done'] ? 'checked' : '' }}
                               class="w-5 h-5 rounded border-gray-300 text-teal-600 focus:ring-teal-500"
                               {{ in_array($viewingTask->status, ['verified']) ? 'disabled' : '' }}>
                        <span class="{{ $item['done'] ? 'line-through text-gray-400' : 'text-gray-700' }}">
                            {{ $item['item'] }}
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Barra de progresso --}}
        <div class="mb-6">
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="bg-gradient-to-r from-teal-500 to-cyan-500 h-3 rounded-full transition-all duration-500" style="width: {{ $viewingTask->checklist_progress }}%"></div>
            </div>
        </div>

        {{-- Notas --}}
        @if($viewingTask->notes)
            <div class="mb-4 p-3 bg-yellow-50 rounded-xl flex items-start gap-2">
                <i class="fas fa-sticky-note text-yellow-500 mt-0.5"></i>
                <p class="text-sm text-yellow-700">{{ $viewingTask->notes }}</p>
            </div>
        @endif

        {{-- Issues --}}
        @if($viewingTask->issues)
            <div class="mb-4 p-3 bg-red-50 rounded-xl flex items-start gap-2">
                <i class="fas fa-exclamation-triangle text-red-500 mt-0.5"></i>
                <p class="text-sm text-red-700">{{ $viewingTask->issues }}</p>
            </div>
        @endif

        {{-- Ações --}}
        <div class="flex flex-wrap gap-2 pt-4 border-t">
            @if($viewingTask->status === 'pending')
                <button wire:click="startTask({{ $viewingTask->id }})" 
                        class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-play mr-2"></i>Iniciar
                </button>
                <button wire:click="openAssignModal({{ $viewingTask->id }})" 
                        class="px-4 py-2.5 bg-purple-100 text-purple-700 rounded-xl hover:bg-purple-200 transition font-medium">
                    <i class="fas fa-user-plus mr-2"></i>Atribuir
                </button>
            @endif

            @if($viewingTask->status === 'in_progress')
                <button wire:click="completeTask({{ $viewingTask->id }})" 
                        class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 transition font-semibold">
                    <i class="fas fa-check mr-2"></i>Concluir
                </button>
            @endif

            @if($viewingTask->status === 'completed')
                <button wire:click="verifyTask({{ $viewingTask->id }})" 
                        class="flex-1 px-4 py-2.5 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition font-semibold">
                    <i class="fas fa-check-double mr-2"></i>Verificar
                </button>
            @endif

            <button wire:click="editTask({{ $viewingTask->id }})" 
                    class="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">
                <i class="fas fa-edit mr-2"></i>Editar
            </button>

            <button wire:click="deleteTask({{ $viewingTask->id }})" 
                    wire:confirm="Tem certeza que deseja remover esta tarefa?"
                    class="px-4 py-2.5 bg-red-100 text-red-700 rounded-xl hover:bg-red-200 transition font-medium">
                <i class="fas fa-trash mr-2"></i>Remover
            </button>
        </div>
    </div>
</div>
@endif
