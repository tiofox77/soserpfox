@php
    $priorityColors = [
        'urgent' => 'border-l-red-500 bg-red-50',
        'high' => 'border-l-orange-500 bg-orange-50',
        'normal' => 'border-l-blue-500 bg-blue-50',
        'low' => 'border-l-gray-400 bg-gray-50',
    ];
    $cardColor = $priorityColors[$task->priority] ?? 'border-l-gray-400 bg-white';
@endphp

<div wire:click="viewTask({{ $task->id }})"
     class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all cursor-pointer border-l-4 {{ $cardColor }} p-4">
    
    {{-- Header --}}
    <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-2">
            <span class="text-lg font-bold text-gray-900">{{ $task->room->number }}</span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                {{ $task->room->roomType->name ?? '' }}
            </span>
        </div>
        @if($task->priority === 'urgent')
            <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold animate-pulse">
                <i class="fas fa-exclamation mr-1"></i>URGENTE
            </span>
        @endif
    </div>

    {{-- Tipo de tarefa --}}
    <div class="mb-2">
        <span class="text-sm text-gray-600">{{ $task->task_type_label }}</span>
    </div>

    {{-- Hóspede (se houver) --}}
    @if($task->reservation && $task->reservation->guest)
        <div class="mb-2 text-xs text-gray-500 flex items-center gap-1">
            <i class="fas fa-user"></i>
            {{ $task->reservation->guest->name }}
        </div>
    @endif

    {{-- Progresso --}}
    <div class="mb-2">
        <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
            <span>Checklist</span>
            <span>{{ $task->checklist_progress }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-1.5">
            <div class="bg-teal-500 h-1.5 rounded-full transition-all" style="width: {{ $task->checklist_progress }}%"></div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="flex items-center justify-between text-xs">
        @if($task->assignedUser)
            <div class="flex items-center gap-1 text-gray-500">
                <div class="w-5 h-5 rounded-full bg-teal-500 text-white flex items-center justify-center text-[10px] font-bold">
                    {{ strtoupper(substr($task->assignedUser->name, 0, 1)) }}
                </div>
                <span>{{ Str::limit($task->assignedUser->name, 10) }}</span>
            </div>
        @else
            <button wire:click.stop="openAssignModal({{ $task->id }})" 
                    class="text-teal-600 hover:underline">
                <i class="fas fa-user-plus mr-1"></i>Atribuir
            </button>
        @endif

        @if($task->scheduled_time)
            <span class="text-gray-400">
                <i class="fas fa-clock mr-1"></i>{{ Carbon\Carbon::parse($task->scheduled_time)->format('H:i') }}
            </span>
        @endif

        @if($task->is_overdue)
            <span class="text-red-500 font-bold">
                <i class="fas fa-exclamation-circle mr-1"></i>Atrasada
            </span>
        @endif
    </div>

    {{-- Ações rápidas --}}
    @if($task->status === 'pending')
        <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
            <button wire:click.stop="startTask({{ $task->id }})" 
                    class="flex-1 px-3 py-1.5 bg-blue-500 text-white rounded-lg text-xs font-semibold hover:bg-blue-600 transition">
                <i class="fas fa-play mr-1"></i>Iniciar
            </button>
        </div>
    @elseif($task->status === 'in_progress')
        <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
            <button wire:click.stop="completeTask({{ $task->id }})" 
                    class="flex-1 px-3 py-1.5 bg-green-500 text-white rounded-lg text-xs font-semibold hover:bg-green-600 transition">
                <i class="fas fa-check mr-1"></i>Concluir
            </button>
        </div>
    @elseif($task->status === 'completed')
        <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
            <button wire:click.stop="verifyTask({{ $task->id }})" 
                    class="flex-1 px-3 py-1.5 bg-emerald-500 text-white rounded-lg text-xs font-semibold hover:bg-emerald-600 transition">
                <i class="fas fa-check-double mr-1"></i>Verificar
            </button>
        </div>
    @elseif($task->status === 'verified')
        <div class="mt-3 pt-3 border-t border-gray-100">
            <span class="text-xs text-emerald-600 font-semibold">
                <i class="fas fa-check-circle mr-1"></i>Verificado
            </span>
        </div>
    @endif
</div>
