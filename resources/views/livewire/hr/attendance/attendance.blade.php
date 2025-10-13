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

    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-6 bg-gradient-to-r from-red-500 to-pink-600 rounded-2xl shadow-lg p-4 text-white flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-2xl mr-3"></i>
                <span class="font-semibold">{{ session('error') }}</span>
            </div>
            <button @click="show = false" class="text-white hover:text-red-100 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-user-clock text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Gestão de Presenças</h2>
                    <p class="text-green-100 text-sm">Controle de ponto e assiduidade dos funcionários</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <button wire:click="openImportModal" 
                        class="bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white px-5 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl border border-white/30">
                    <i class="fas fa-file-excel mr-2"></i>Importar Excel
                </button>
                <button wire:click="create" 
                        class="bg-white text-green-600 hover:bg-green-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-plus mr-2"></i>Registrar Presença
                </button>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 stagger-animation">
        <!-- Total Presenças Hoje -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-user-check text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Presenças Hoje</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ \App\Models\HR\Attendance::where('tenant_id', auth()->user()->activeTenantId())
                    ->whereDate('date', today())
                    ->where('status', 'present')
                    ->count() }}
            </p>
            <p class="text-xs text-gray-500">Funcionários presentes</p>
        </div>

        <!-- Atrasos Hoje -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-500/50 icon-float">
                    <i class="fas fa-clock text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-yellow-600 font-semibold mb-2">Atrasos Hoje</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ \App\Models\HR\Attendance::where('tenant_id', auth()->user()->activeTenantId())
                    ->whereDate('date', today())
                    ->where('status', 'late')
                    ->count() }}
            </p>
            <p class="text-xs text-gray-500">Chegaram atrasados</p>
        </div>

        <!-- Horas Trabalhadas (Média) -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-hourglass-half text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Média de Horas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ number_format(\App\Models\HR\Attendance::where('tenant_id', auth()->user()->activeTenantId())
                    ->whereDate('date', today())
                    ->avg('hours_worked') ?? 0, 1) }}h
            </p>
            <p class="text-xs text-gray-500">Horas trabalhadas hoje</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-green-600"></i>
                Filtros Avançados
            </h3>
            <div class="flex items-center space-x-2">
                {{-- Toggle View --}}
                <div class="inline-flex rounded-xl border-2 border-green-200 p-1 bg-green-50">
                    <button wire:click="changeView('list')" 
                            class="px-4 py-2 rounded-lg font-semibold text-sm transition-all {{ $view === 'list' ? 'bg-green-600 text-white shadow-lg' : 'text-gray-600 hover:text-green-600' }}">
                        <i class="fas fa-list mr-1"></i>Lista
                    </button>
                    <button wire:click="changeView('calendar')" 
                            class="px-4 py-2 rounded-lg font-semibold text-sm transition-all {{ $view === 'calendar' ? 'bg-green-600 text-white shadow-lg' : 'text-gray-600 hover:text-green-600' }}">
                        <i class="fas fa-calendar mr-1"></i>Calendário
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Search --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live="search" type="text" placeholder="Nome, número funcionário..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            {{-- Date Filter --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar mr-1"></i>Data
                </label>
                <input wire:model.live="dateFilter" type="date" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 appearance-none bg-white text-sm">
            </div>

            {{-- Employee Filter --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-user mr-1"></i>Funcionário
                </label>
                <select wire:model.live="employeeFilter" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 appearance-none bg-white text-sm">
                    <option value="">Todos</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Status Filter --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-info-circle mr-1"></i>Status
                </label>
                <select wire:model.live="statusFilter" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 appearance-none bg-white text-sm">
                    <option value="">Todos</option>
                    <option value="present">Presente</option>
                    <option value="absent">Ausente</option>
                    <option value="late">Atrasado</option>
                    <option value="half_day">Meio Período</option>
                    <option value="sick">Doente</option>
                    <option value="vacation">Férias</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Content --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden" 
         x-data="{ currentView: @entangle('view') }"
         x-effect="if (currentView === 'calendar') { 
             setTimeout(() => { 
                 window.dispatchEvent(new CustomEvent('render-attendance-calendar'));
                 console.log('🔄 Alpine detectou mudança para calendário de presenças');
             }, 200);
         }">
        
        {{-- List View --}}
        <div x-show="currentView === 'list'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100">
            {{-- Header --}}
            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-list mr-2 text-green-600"></i>
                        Lista de Presenças
                    </h3>
                    <span class="text-sm text-gray-600 font-semibold">
                        <i class="fas fa-user-check mr-1"></i>{{ $attendances->total() }} Registros
                    </span>
                </div>
            </div>
            
            {{-- Table Header --}}
            <div class="grid grid-cols-12 gap-4 px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-600 uppercase">
                <div class="col-span-2 flex items-center">
                    <i class="fas fa-calendar mr-2 text-green-500"></i>Data
                </div>
                <div class="col-span-3 flex items-center">
                    <i class="fas fa-user mr-2 text-purple-500"></i>Funcionário
                </div>
                <div class="col-span-1 flex items-center">
                    <i class="fas fa-sign-in-alt mr-2 text-blue-500"></i>Entrada
                </div>
                <div class="col-span-2 flex items-center">
                    <i class="fas fa-sign-out-alt mr-2 text-red-500"></i>Saída
                </div>
                <div class="col-span-1 flex items-center">
                    <i class="fas fa-hourglass-half mr-2 text-cyan-500"></i>Horas
                </div>
                <div class="col-span-2 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-orange-500"></i>Status
                </div>
                <div class="col-span-1 flex items-center justify-end">
                    <i class="fas fa-cog mr-2 text-gray-500"></i>Ações
                </div>
            </div>
            
            {{-- Table Body --}}
            <div class="divide-y divide-gray-100">
                @forelse($attendances as $attendance)
                    <div class="group grid grid-cols-12 gap-4 px-6 py-4 hover:bg-green-50 transition-all duration-300 items-center">
                        {{-- Data --}}
                        <div class="col-span-2">
                            <span class="text-sm font-semibold text-gray-900">{{ $attendance->date->format('d/m/Y') }}</span>
                            <br>
                            <span class="text-xs text-gray-500">{{ $attendance->date->locale('pt')->dayName }}</span>
                        </div>

                        {{-- Funcionário --}}
                        <div class="col-span-3 flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white font-bold shadow-lg flex-shrink-0">
                                {{ strtoupper(substr($attendance->employee->first_name, 0, 1) . substr($attendance->employee->last_name, 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-gray-900 truncate">{{ $attendance->employee->full_name }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $attendance->employee->employee_number }}</p>
                            </div>
                        </div>

                        {{-- Entrada --}}
                        <div class="col-span-1">
                            @if($attendance->check_in)
                                <span class="inline-flex items-center px-2.5 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold">
                                    <i class="fas fa-sign-in-alt mr-1"></i>
                                    {{ substr($attendance->check_in, 0, 5) }}
                                </span>
                            @else
                                <span class="text-gray-400 text-xs">-</span>
                            @endif
                        </div>

                        {{-- Saída --}}
                        <div class="col-span-2">
                            @if($attendance->check_out)
                                <span class="inline-flex items-center px-2.5 py-1 bg-red-100 text-red-700 rounded-lg text-xs font-semibold">
                                    <i class="fas fa-sign-out-alt mr-1"></i>
                                    {{ substr($attendance->check_out, 0, 5) }}
                                </span>
                            @else
                                <button wire:click="checkOut({{ $attendance->id }})" 
                                        class="px-3 py-1.5 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-lg text-xs font-semibold hover:shadow-lg transition-all">
                                    <i class="fas fa-sign-out-alt mr-1"></i>Registrar Saída
                                </button>
                            @endif
                        </div>

                        {{-- Horas --}}
                        <div class="col-span-1">
                            @if($attendance->hours_worked)
                                <span class="inline-flex items-center px-2.5 py-1 bg-cyan-100 text-cyan-700 rounded-lg text-xs font-semibold">
                                    {{ number_format($attendance->hours_worked, 1) }}h
                                </span>
                            @else
                                <span class="text-gray-400 text-xs">-</span>
                            @endif
                        </div>

                        {{-- Status --}}
                        <div class="col-span-2">
                            @if($attendance->status === 'present')
                                <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                    <i class="fas fa-check-circle mr-1"></i>Presente
                                </span>
                            @elseif($attendance->status === 'absent')
                                <span class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">
                                    <i class="fas fa-times-circle mr-1"></i>Ausente
                                </span>
                            @elseif($attendance->status === 'late')
                                <span class="inline-flex items-center px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">
                                    <i class="fas fa-clock mr-1"></i>Atrasado
                                </span>
                            @elseif($attendance->status === 'half_day')
                                <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                                    <i class="fas fa-adjust mr-1"></i>Meio Período
                                </span>
                            @elseif($attendance->status === 'sick')
                                <span class="inline-flex items-center px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">
                                    <i class="fas fa-briefcase-medical mr-1"></i>Doente
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-semibold">
                                    <i class="fas fa-umbrella-beach mr-1"></i>Férias
                                </span>
                            @endif
                        </div>

                        {{-- Ações --}}
                        <div class="col-span-1 flex items-center justify-end space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button wire:click="viewDetails({{ $attendance->id }})" 
                                    class="w-8 h-8 flex items-center justify-center bg-green-500 hover:bg-green-600 text-white rounded-lg transition shadow-md hover:shadow-lg" 
                                    title="Visualizar">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                            <button wire:click="edit({{ $attendance->id }})" 
                                    class="w-8 h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition shadow-md hover:shadow-lg" 
                                    title="Editar">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <button wire:click="delete({{ $attendance->id }})" 
                                    onclick="return confirm('Tem certeza que deseja remover este registro?')" 
                                    class="w-8 h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition shadow-md hover:shadow-lg" 
                                    title="Excluir">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user-clock text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum registro de presença encontrado</h3>
                        <p class="text-gray-500 mb-4">Registre a primeira presença para começar</p>
                        <button wire:click="create" class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl font-semibold hover:shadow-lg transition-all">
                            <i class="fas fa-plus mr-2"></i>Registrar Presença
                        </button>
                    </div>
                @endforelse
            </div>

            @if($attendances->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $attendances->links() }}
                </div>
            @endif
        </div>
        
        {{-- Calendar View --}}
        <div x-show="currentView === 'calendar'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100">
            @include('livewire.hr.attendance.partials.calendar')
        </div>
    </div>

    {{-- Modals --}}
    @if($showModal)
        @include('livewire.hr.attendance.partials.form-modal')
    @endif

    @if($showDetailsModal)
        @include('livewire.hr.attendance.partials.details-modal')
    @endif

    @if($showImportModal)
        @include('livewire.hr.attendance.partials.import-modal')
    @endif
</div>
