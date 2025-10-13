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
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-umbrella-beach text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Gestão de Férias</h2>
                    <p class="text-purple-100 text-sm">Controle de férias e períodos de descanso</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-purple-600 hover:bg-purple-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Nova Solicitação
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 stagger-animation">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-500/50 icon-float">
                    <i class="fas fa-clock text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-yellow-600 font-semibold mb-2">Pendentes</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $vacations->where('status', 'pending')->count() }}
            </p>
            <p class="text-xs text-gray-500">Aguardando aprovação</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Aprovadas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $vacations->where('status', 'approved')->count() }}
            </p>
            <p class="text-xs text-gray-500">Férias confirmadas</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-plane-departure text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Em Andamento</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $vacations->where('status', 'in_progress')->count() }}
            </p>
            <p class="text-xs text-gray-500">Funcionários de férias</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/50 icon-float">
                    <i class="fas fa-check-double text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-purple-600 font-semibold mb-2">Concluídas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $vacations->where('status', 'completed')->count() }}
            </p>
            <p class="text-xs text-gray-500">Finalizadas este ano</p>
        </div>
    </div>

    {{-- View Toggle & Filters --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0 mb-6">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-purple-600"></i>
                Filtros & Visualização
            </h3>
            
            {{-- View Toggle --}}
            <div class="flex items-center bg-gray-100 rounded-xl p-1">
                <button wire:click="changeView('list')" 
                        class="px-4 py-2 rounded-lg font-semibold text-sm transition-all {{ $view === 'list' ? 'bg-purple-600 text-white shadow-lg' : 'text-gray-600 hover:text-purple-600' }}">
                    <i class="fas fa-list mr-1"></i>Lista
                </button>
                <button wire:click="changeView('calendar')" 
                        class="px-4 py-2 rounded-lg font-semibold text-sm transition-all {{ $view === 'calendar' ? 'bg-purple-600 text-white shadow-lg' : 'text-gray-600 hover:text-purple-600' }}">
                    <i class="fas fa-calendar mr-1"></i>Calendário
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Search --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <input wire:model.live="search" type="text"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm"
                       placeholder="Número, funcionário...">
            </div>

            {{-- Year Filter --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar-alt mr-1"></i>Ano
                </label>
                <select wire:model.live="yearFilter"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="">Todos os Anos</option>
                    @for($year = date('Y') - 2; $year <= date('Y') + 1; $year++)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endfor
                </select>
            </div>

            {{-- Employee Filter --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-user mr-1"></i>Funcionário
                </label>
                <select wire:model.live="employeeFilter"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="">Todos Funcionários</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Status Filter --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-flag mr-1"></i>Status
                </label>
                <select wire:model.live="statusFilter"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="">Todos Status</option>
                    <option value="pending">Pendente</option>
                    <option value="approved">Aprovada</option>
                    <option value="rejected">Rejeitada</option>
                    <option value="in_progress">Em Andamento</option>
                    <option value="completed">Concluída</option>
                    <option value="cancelled">Cancelada</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Content with List/Calendar Views --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden" 
         x-data="{ currentView: @entangle('view') }"
         x-effect="if (currentView === 'calendar') { 
             setTimeout(() => { 
                 window.dispatchEvent(new CustomEvent('render-vacation-calendar'));
             }, 200);
         }">
        
        {{-- List View --}}
        <div x-show="currentView === 'list'">
            <div class="px-6 py-4 bg-purple-50 border-b border-purple-100">
                <h3 class="text-lg font-bold text-purple-900 flex items-center">
                    <i class="fas fa-list mr-2"></i>
                    Listagem de Férias ({{ $vacations->total() }})
                </h3>
            </div>

            @if($vacations->count() > 0)
                {{-- Grid View (modernizado) --}}
                <div class="p-6 space-y-4">
                    @foreach($vacations as $vacation)
                        <div class="bg-gradient-to-r from-white to-purple-50 border border-purple-200 rounded-xl p-6 hover:shadow-lg transition-all">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-3">
                                        <span class="px-3 py-1 bg-purple-600 text-white rounded-full text-xs font-bold">
                                            {{ $vacation->vacation_number }}
                                        </span>
                                        <h4 class="font-bold text-gray-900 text-lg">{{ $vacation->employee->full_name }}</h4>
                                        @if($vacation->status === 'pending')
                                            <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">Pendente</span>
                                        @elseif($vacation->status === 'approved')
                                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">Aprovada</span>
                                        @elseif($vacation->status === 'in_progress')
                                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Em Andamento</span>
                                        @elseif($vacation->status === 'completed')
                                            <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">Concluída</span>
                                        @elseif($vacation->status === 'rejected')
                                            <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">Rejeitada</span>
                                        @else
                                            <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">Cancelada</span>
                                        @endif
                                    </div>
                                    
                                    <div class="grid grid-cols-4 gap-4 text-sm">
                                        <div>
                                            <p class="text-gray-500 text-xs mb-1">Período</p>
                                            <p class="font-semibold text-purple-600">
                                                <i class="fas fa-calendar mr-1"></i>{{ $vacation->start_date->format('d/m/Y') }} - {{ $vacation->end_date->format('d/m/Y') }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 text-xs mb-1">Dias</p>
                                            <p class="font-bold text-gray-900">{{ $vacation->working_days }} úteis</p>
                                            <p class="text-xs text-gray-500">({{ $vacation->requested_days }} corridos)</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 text-xs mb-1">Ano Referência</p>
                                            <p class="font-semibold text-gray-900">{{ $vacation->reference_year }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 text-xs mb-1">Valor Total</p>
                                            <p class="font-bold text-green-600">{{ number_format($vacation->total_amount, 2, ',', '.') }} Kz</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <button wire:click="viewDetails({{ $vacation->id }})" 
                                            class="w-9 h-9 flex items-center justify-center bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition-colors"
                                            title="Ver Detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    @if($vacation->status === 'pending')
                                        <button wire:click="openApprovalModal({{ $vacation->id }}, 'approve')" 
                                                class="w-9 h-9 flex items-center justify-center bg-green-100 text-green-600 rounded-lg hover:bg-green-200 transition-colors"
                                                title="Aprovar">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button wire:click="openApprovalModal({{ $vacation->id }}, 'reject')" 
                                                class="w-9 h-9 flex items-center justify-center bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors"
                                                title="Rejeitar">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $vacations->links() }}
                </div>
            @else
                {{-- Empty State --}}
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-umbrella-beach text-purple-600 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Nenhuma férias encontrada</h3>
                    <p class="text-gray-600 mb-6">Comece criando uma solicitação de férias</p>
                    <button wire:click="create" 
                            class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-md inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i>Nova Solicitação
                    </button>
                </div>
            @endif
        </div>
        
        {{-- Calendar View --}}
        <div x-show="currentView === 'calendar'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100">
            @include('livewire.hr.vacations.partials.calendar')
        </div>
    </div>

    <!-- Modal de Formulário -->
    @if($showModal)
        @include('livewire.hr.vacations.partials.form-modal')
    @endif

    <!-- Modal de Detalhes -->
    @if($showDetailsModal)
        @include('livewire.hr.vacations.partials.details-modal')
    @endif

    <!-- Modal de Rejeição -->
    @if($showApprovalModal)
        @include('livewire.hr.vacations.partials.rejection-modal')
    @endif
</div>

