<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Gestão de Turnos</h2>
                    <p class="text-purple-100 text-sm">Configuração de turnos de trabalho</p>
                </div>
            </div>
            <button wire:click="create" 
                    class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl flex items-center">
                <i class="fas fa-plus mr-2"></i>Novo Turno
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 stagger-animation">
        {{-- Total Turnos --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold uppercase">Total de Turnos</p>
                    <p class="text-3xl font-bold text-purple-600 mt-2">{{ $stats['total'] }}</p>
                </div>
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl shadow-lg flex items-center justify-center icon-float">
                    <i class="fas fa-clock text-white text-2xl"></i>
                </div>
            </div>
        </div>

        {{-- Turnos Ativos --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold uppercase">Turnos Ativos</p>
                    <p class="text-3xl font-bold text-green-600 mt-2">{{ $stats['active'] }}</p>
                </div>
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl shadow-lg flex items-center justify-center icon-float">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
        </div>

        {{-- Turnos Noturnos --}}
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-indigo-100 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-semibold uppercase">Turnos Noturnos</p>
                    <p class="text-3xl font-bold text-indigo-600 mt-2">{{ $stats['night_shifts'] }}</p>
                </div>
                <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl shadow-lg flex items-center justify-center icon-float">
                    <i class="fas fa-moon text-white text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Search & Filters --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <input type="text" wire:model.live="search" 
                       placeholder="Nome ou código do turno..."
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">
                    <i class="fas fa-filter mr-1"></i>Status
                </label>
                <select wire:model.live="statusFilter" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                    <option value="all">Todos</option>
                    <option value="active">Ativos</option>
                    <option value="inactive">Inativos</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">
                    <i class="fas fa-list mr-1"></i>Por Página
                </label>
                <select wire:model.live="perPage" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Shifts List --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        @if($shifts->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gradient-to-r from-purple-50 to-pink-50 border-b border-purple-100">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-purple-700 uppercase tracking-wider">
                                <i class="fas fa-clock mr-2"></i>Turno
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-purple-700 uppercase tracking-wider">
                                <i class="fas fa-hourglass-half mr-2"></i>Horário
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-purple-700 uppercase tracking-wider">
                                <i class="fas fa-calendar-week mr-2"></i>Dias
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-purple-700 uppercase tracking-wider">
                                <i class="fas fa-users mr-2"></i>Funcionários
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-purple-700 uppercase tracking-wider">
                                <i class="fas fa-toggle-on mr-2"></i>Status
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-purple-700 uppercase tracking-wider">
                                <i class="fas fa-cog mr-2"></i>Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($shifts as $shift)
                        <tr class="hover:bg-purple-50 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3" 
                                         style="background-color: {{ $shift->color }}20; border: 2px solid {{ $shift->color }};">
                                        <i class="fas {{ $shift->is_night_shift ? 'fa-moon' : 'fa-sun' }}" 
                                           style="color: {{ $shift->color }};"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-800">{{ $shift->name }}</p>
                                        @if($shift->code)
                                            <p class="text-xs text-gray-500">Código: {{ $shift->code }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm">
                                    <p class="font-semibold text-gray-700">
                                        {{ $shift->start_time ? $shift->start_time->format('H:i') : '—' }} - 
                                        {{ $shift->end_time ? $shift->end_time->format('H:i') : '—' }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $shift->hours_per_day }}h por dia</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-700">{{ $shift->work_days_formatted }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                                    <i class="fas fa-users mr-1"></i>{{ $shift->employees_count ?? 0 }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($shift->is_active)
                                    <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                        <i class="fas fa-check-circle mr-1"></i>Ativo
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">
                                        <i class="fas fa-times-circle mr-1"></i>Inativo
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click="openAssignModal({{ $shift->id }})" 
                                            class="w-8 h-8 flex items-center justify-center bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition shadow-md hover:shadow-lg"
                                            title="Atribuir Funcionários">
                                        <i class="fas fa-users text-xs"></i>
                                    </button>
                                    <button wire:click="view({{ $shift->id }})" 
                                            class="w-8 h-8 flex items-center justify-center bg-cyan-500 hover:bg-cyan-600 text-white rounded-lg transition shadow-md hover:shadow-lg"
                                            title="Visualizar">
                                        <i class="fas fa-eye text-xs"></i>
                                    </button>
                                    <button wire:click="edit({{ $shift->id }})" 
                                            class="w-8 h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition shadow-md hover:shadow-lg"
                                            title="Editar">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    <button wire:click="delete({{ $shift->id }})" 
                                            onclick="return confirm('Tem certeza que deseja excluir este turno?')"
                                            class="w-8 h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition shadow-md hover:shadow-lg"
                                            title="Excluir">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $shifts->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <i class="fas fa-clock text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg font-semibold">Nenhum turno encontrado</p>
                <p class="text-gray-400 text-sm mt-2">Clique em "Novo Turno" para começar</p>
            </div>
        @endif
    </div>

    {{-- Modal Form --}}
    @if($showModal)
        @include('livewire.hr.shifts.partials.form-modal')
    @endif

    {{-- Modal View --}}
    @include('livewire.hr.shifts.partials.view-modal')

    {{-- Modal Assign Employees --}}
    @if($showAssignModal)
        @include('livewire.hr.shifts.partials.assign-modal')
    @endif
</div>
