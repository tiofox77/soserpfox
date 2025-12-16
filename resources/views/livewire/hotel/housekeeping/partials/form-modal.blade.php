{{-- Modal Nova/Editar Tarefa --}}
@if($showTaskModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeTaskModal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 m-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-broom text-teal-600"></i>
                {{ $editingTask ? 'Editar Tarefa' : 'Nova Tarefa' }}
            </h3>
            <button wire:click="closeTaskModal" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="space-y-4">
            {{-- Quarto --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">
                    <i class="fas fa-door-open mr-1 text-teal-500"></i>Quarto *
                </label>
                <select wire:model="taskRoomId" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
                    <option value="">Selecione...</option>
                    @foreach($rooms as $room)
                        <option value="{{ $room->id }}">{{ $room->number }} - {{ $room->roomType->name ?? '' }}</option>
                    @endforeach
                </select>
                @error('taskRoomId') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Tipo e Prioridade --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-tag mr-1 text-blue-500"></i>Tipo *
                    </label>
                    <select wire:model="taskType" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
                        @foreach($taskTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-flag mr-1 text-red-500"></i>Prioridade *
                    </label>
                    <select wire:model="taskPriority" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
                        @foreach($priorities as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Atribuir a --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">
                    <i class="fas fa-user mr-1 text-purple-500"></i>Atribuir a
                </label>
                <select wire:model="taskAssignedTo" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
                    <option value="">Não atribuído</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Data e Hora --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-calendar mr-1 text-green-500"></i>Data
                    </label>
                    <input type="date" wire:model="taskScheduledDate" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">
                        <i class="fas fa-clock mr-1 text-orange-500"></i>Hora
                    </label>
                    <input type="time" wire:model="taskScheduledTime" 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
                </div>
            </div>

            {{-- Notas --}}
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">
                    <i class="fas fa-sticky-note mr-1 text-yellow-500"></i>Notas
                </label>
                <textarea wire:model="taskNotes" rows="2"
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm"
                          placeholder="Instruções especiais..."></textarea>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button wire:click="closeTaskModal" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">
                Cancelar
            </button>
            <button wire:click="saveTask" wire:loading.attr="disabled" wire:target="saveTask"
                    class="px-6 py-2 bg-teal-600 text-white rounded-xl hover:bg-teal-700 transition font-semibold disabled:opacity-50">
                <span wire:loading.remove wire:target="saveTask">
                    <i class="fas fa-save mr-2"></i>{{ $editingTask ? 'Atualizar' : 'Criar' }}
                </span>
                <span wire:loading wire:target="saveTask">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Salvando...
                </span>
            </button>
        </div>
    </div>
</div>
@endif
