<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-teal-600 to-cyan-700 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-broom text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Housekeeping</h2>
                    <p class="text-teal-100 text-sm">Gestao de limpeza e tarefas</p>
                </div>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex items-center gap-2 bg-white/20 rounded-xl p-1">
                    <button wire:click="$set('selectedDate', '{{ Carbon\Carbon::parse($selectedDate)->subDay()->toDateString() }}')" class="px-3 py-2 hover:bg-white/20 rounded-lg transition"><i class="fas fa-chevron-left"></i></button>
                    <input type="date" wire:model.live="selectedDate" class="bg-transparent border-0 text-white font-semibold text-center focus:ring-0 w-36">
                    <button wire:click="$set('selectedDate', '{{ Carbon\Carbon::parse($selectedDate)->addDay()->toDateString() }}')" class="px-3 py-2 hover:bg-white/20 rounded-lg transition"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="flex items-center gap-1 bg-white/20 rounded-xl p-1">
                    <button wire:click="$set('viewMode', 'board')" class="px-4 py-2 rounded-lg font-semibold transition {{ $viewMode === 'board' ? 'bg-white text-teal-600' : 'text-white hover:bg-white/20' }}"><i class="fas fa-columns mr-2"></i>Quadro</button>
                    <button wire:click="$set('viewMode', 'rooms')" class="px-4 py-2 rounded-lg font-semibold transition {{ $viewMode === 'rooms' ? 'bg-white text-teal-600' : 'text-white hover:bg-white/20' }}"><i class="fas fa-th mr-2"></i>Quartos</button>
                </div>
                <button wire:click="generateTasks" class="bg-white/20 hover:bg-white/30 px-4 py-3 rounded-xl font-semibold transition"><i class="fas fa-magic mr-2"></i>Auto Gerar</button>
                <button wire:click="openTaskModal" class="bg-white text-teal-600 hover:bg-teal-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg"><i class="fas fa-plus mr-2"></i>Nova Tarefa</button>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-yellow-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-yellow-500/30"><i class="fas fa-clock text-white"></i></div>
                <p class="text-xs text-yellow-600 font-semibold uppercase">Pendentes</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['pending'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-blue-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/30"><i class="fas fa-spinner text-white"></i></div>
                <p class="text-xs text-blue-600 font-semibold uppercase">Em Progresso</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['in_progress'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-green-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/30"><i class="fas fa-check text-white"></i></div>
                <p class="text-xs text-green-600 font-semibold uppercase">Concluidas</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['completed'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-red-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-red-500 to-rose-600 rounded-xl flex items-center justify-center shadow-lg shadow-red-500/30"><i class="fas fa-exclamation-triangle text-white"></i></div>
                <p class="text-xs text-red-600 font-semibold uppercase">Atrasadas</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['overdue'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5 border border-teal-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-lg shadow-teal-500/30"><i class="fas fa-percentage text-white"></i></div>
                <p class="text-xs text-teal-600 font-semibold uppercase">Quartos Limpos</p>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['clean_rate'] }}%</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-4 flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2"><i class="fas fa-filter text-teal-600"></i><span class="font-semibold text-gray-700">Filtros:</span></div>
        <select wire:model.live="filterPriority" class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
            <option value="">Todas prioridades</option>
            @foreach($priorities as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
        <select wire:model.live="filterUser" class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
            <option value="">Todos funcionarios</option>
            @foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
        </select>
        @if($viewMode === 'rooms')
        <select wire:model.live="filterFloor" class="px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-sm">
            <option value="">Todos andares</option>
            @foreach($floors as $floor)<option value="{{ $floor }}">{{ $floor }}o Andar</option>@endforeach
        </select>
        @endif
        <div class="ml-auto flex items-center gap-4 text-xs">
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-green-500"></span> Limpo</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-red-500"></span> Sujo</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-blue-500"></span> Em limpeza</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-yellow-500"></span> Inspecao</span>
        </div>
    </div>

    {{-- Vista Quadro --}}
    @if($viewMode === 'board')
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-yellow-50 rounded-2xl p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-yellow-700 flex items-center gap-2"><i class="fas fa-clock"></i> Pendentes</h3>
                <span class="bg-yellow-200 text-yellow-800 px-2 py-1 rounded-full text-xs font-bold">{{ $tasksByStatus['pending']->count() }}</span>
            </div>
            <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                @forelse($tasksByStatus['pending'] as $task)@include('livewire.hotel.housekeeping.partials.task-card', ['task' => $task])@empty<p class="text-center text-yellow-600 py-4 text-sm">Nenhuma tarefa pendente</p>@endforelse
            </div>
        </div>
        <div class="bg-blue-50 rounded-2xl p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-blue-700 flex items-center gap-2"><i class="fas fa-spinner"></i> Em Progresso</h3>
                <span class="bg-blue-200 text-blue-800 px-2 py-1 rounded-full text-xs font-bold">{{ $tasksByStatus['in_progress']->count() }}</span>
            </div>
            <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                @forelse($tasksByStatus['in_progress'] as $task)@include('livewire.hotel.housekeeping.partials.task-card', ['task' => $task])@empty<p class="text-center text-blue-600 py-4 text-sm">Nenhuma em progresso</p>@endforelse
            </div>
        </div>
        <div class="bg-green-50 rounded-2xl p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-green-700 flex items-center gap-2"><i class="fas fa-check"></i> Concluidas</h3>
                <span class="bg-green-200 text-green-800 px-2 py-1 rounded-full text-xs font-bold">{{ $tasksByStatus['completed']->count() + $tasksByStatus['verified']->count() }}</span>
            </div>
            <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                @forelse($tasksByStatus['completed']->merge($tasksByStatus['verified']) as $task)@include('livewire.hotel.housekeeping.partials.task-card', ['task' => $task])@empty<p class="text-center text-green-600 py-4 text-sm">Nenhuma concluida</p>@endforelse
            </div>
        </div>
        <div class="bg-red-50 rounded-2xl p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-red-700 flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i> Com Problemas</h3>
                <span class="bg-red-200 text-red-800 px-2 py-1 rounded-full text-xs font-bold">{{ $tasksByStatus['issue']->count() }}</span>
            </div>
            <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                @forelse($tasksByStatus['issue'] as $task)@include('livewire.hotel.housekeeping.partials.task-card', ['task' => $task])@empty<p class="text-center text-red-600 py-4 text-sm">Nenhum problema</p>@endforelse
            </div>
        </div>
    </div>
    @endif

    {{-- Vista Quartos --}}
    @if($viewMode === 'rooms')
    <div class="bg-white rounded-2xl shadow-lg p-6">
        @forelse($roomsByFloor as $floor => $floorRooms)
        <div class="mb-6 last:mb-0">
            <h3 class="font-bold text-gray-700 mb-3 flex items-center gap-2"><i class="fas fa-layer-group text-teal-600"></i>{{ $floor }}o Andar<span class="text-sm font-normal text-gray-500">({{ $floorRooms->count() }} quartos)</span></h3>
            <div class="grid grid-cols-4 md:grid-cols-8 lg:grid-cols-10 gap-3">
                @foreach($floorRooms as $room)
                @php
                $statusColors = ['clean'=>'bg-green-500','dirty'=>'bg-red-500','in_progress'=>'bg-blue-500','inspecting'=>'bg-yellow-500','out_of_order'=>'bg-gray-500'];
                $statusColor = $statusColors[$room->housekeeping_status] ?? 'bg-gray-300';
                $task = $room->housekeepingTasks->first();
                @endphp
                <div wire:click="{{ $task ? 'viewTask('.$task->id.')' : 'openTaskModal('.$room->id.')' }}" class="relative cursor-pointer group">
                    <div class="aspect-square rounded-xl {{ $statusColor }} text-white flex flex-col items-center justify-center shadow-lg hover:shadow-xl hover:scale-105 transition-all">
                        <span class="text-lg font-bold">{{ $room->number }}</span>
                        <span class="text-[10px] opacity-80">{{ $room->roomType->name ?? '' }}</span>
                    </div>
                    @if($task)
                    <div class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-white shadow flex items-center justify-center">
                        @if($task->priority === 'urgent')<i class="fas fa-exclamation text-red-500 text-xs"></i>@elseif($task->status === 'in_progress')<i class="fas fa-spinner fa-spin text-blue-500 text-xs"></i>@else<i class="fas fa-broom text-gray-400 text-xs"></i>@endif
                    </div>
                    @endif
                    @if($task && $task->assignedUser)<div class="absolute -bottom-1 left-1/2 -translate-x-1/2 bg-white px-1 rounded text-[8px] text-gray-600 shadow truncate max-w-full">{{ Str::limit($task->assignedUser->name, 8) }}</div>@endif
                </div>
                @endforeach
            </div>
        </div>
        @empty
        <div class="text-center py-12"><div class="w-16 h-16 mx-auto mb-4 bg-teal-100 rounded-full flex items-center justify-center"><i class="fas fa-door-open text-3xl text-teal-400"></i></div><h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum quarto encontrado</h3></div>
        @endforelse
    </div>
    @endif

    {{-- Modais --}}
    @include('livewire.hotel.housekeeping.partials.form-modal')
    @include('livewire.hotel.housekeeping.partials.view-modal')
    @include('livewire.hotel.housekeeping.partials.assign-modal')
    
    {{-- Toast --}}
    @include('livewire.hotel.housekeeping.partials.toast')
</div>
