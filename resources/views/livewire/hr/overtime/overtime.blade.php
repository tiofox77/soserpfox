<div class="p-6">
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
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Gestão de Horas Extras</h2>
                    <p class="text-indigo-100 text-sm">Controle de horas extraordinárias e compensação</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-indigo-600 hover:bg-indigo-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Registrar Horas
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-clock text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-yellow-600 font-semibold mb-2">Pendentes</p>
            <p class="text-4xl font-bold text-gray-900">
                {{ $overtimes->where('status', 'pending')->count() }}
            </p>
            <p class="text-xs text-gray-500">Aguardando aprovação</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Aprovadas</p>
            <p class="text-4xl font-bold text-gray-900">
                {{ $overtimes->where('status', 'approved')->count() }}
            </p>
            <p class="text-xs text-gray-500">Horas confirmadas</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-indigo-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-business-time text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-indigo-600 font-semibold mb-2">Total Horas</p>
            <p class="text-4xl font-bold text-gray-900">
                {{ number_format($overtimes->where('status', 'approved')->sum('total_hours'), 1) }}
            </p>
            <p class="text-xs text-gray-500">Horas aprovadas</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">A Pagar</p>
            <p class="text-4xl font-bold text-gray-900">
                {{ number_format($overtimes->where('status', 'approved')->where('paid', false)->sum('total_amount'), 0) }}
            </p>
            <p class="text-xs text-gray-500">Kz pendentes</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-filter mr-2 text-indigo-600"></i>
            Filtros
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Search --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <input wire:model.live="search" type="text"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 appearance-none bg-white text-sm"
                       placeholder="Número, funcionário...">
            </div>

            {{-- Month Filter --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar-alt mr-1"></i>Mês
                </label>
                <select wire:model.live="monthFilter"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 appearance-none bg-white text-sm">
                    <option value="">Todos os Meses</option>
                    @for($month = 1; $month <= 12; $month++)
                        <option value="{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}">{{ DateTime::createFromFormat('!m', $month)->format('F') }}</option>
                    @endfor
                </select>
            </div>

            {{-- Employee Filter --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-user mr-1"></i>Funcionário
                </label>
                <select wire:model.live="employeeFilter"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 appearance-none bg-white text-sm">
                    <option value="">Todos Funcionários</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Type Filter --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-tags mr-1"></i>Tipo
                </label>
                <select wire:model.live="typeFilter"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 appearance-none bg-white text-sm">
                    <option value="">Todos os Tipos</option>
                    <option value="regular">Regular (50%)</option>
                    <option value="holiday">Feriado (100%)</option>
                    <option value="night">Noturno (75%)</option>
                    <option value="weekend">Fim de Semana (100%)</option>
                </select>
            </div>
        </div>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-100">
            <h3 class="text-lg font-bold text-indigo-900 flex items-center">
                <i class="fas fa-list mr-2"></i>
                Horas Extras Registradas ({{ $overtimes->total() }})
            </h3>
        </div>

        @if($overtimes->count() > 0)
            <div class="p-6 space-y-4">
                @foreach($overtimes as $overtime)
                    <div class="bg-gradient-to-r from-white to-indigo-50 border border-indigo-200 rounded-xl p-6 hover:shadow-lg transition-all">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-3">
                                    <span class="px-3 py-1 bg-indigo-600 text-white rounded-full text-xs font-bold">
                                        {{ $overtime->overtime_number }}
                                    </span>
                                    <h4 class="font-bold text-gray-900 text-lg">{{ $overtime->employee->full_name }}</h4>
                                    
                                    @if($overtime->status === 'pending')
                                        <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">Pendente</span>
                                    @elseif($overtime->status === 'approved')
                                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Aprovada</span>
                                    @elseif($overtime->status === 'rejected')
                                        <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">Rejeitada</span>
                                    @endif

                                    @if($overtime->paid)
                                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                            <i class="fas fa-check mr-1"></i>Pago
                                        </span>
                                    @endif
                                </div>
                                
                                <div class="grid grid-cols-5 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500 text-xs mb-1">Data</p>
                                        <p class="font-semibold text-indigo-600">
                                            <i class="fas fa-calendar mr-1"></i>{{ $overtime->date->format('d/m/Y') }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs mb-1">Horário</p>
                                        <p class="font-bold text-gray-900">{{ $overtime->start_time }} - {{ $overtime->end_time }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs mb-1">Horas</p>
                                        <p class="font-bold text-indigo-600">{{ number_format($overtime->total_hours, 1) }}h</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs mb-1">Tipo</p>
                                        <p class="font-semibold text-gray-900">
                                            @if($overtime->overtime_type === 'regular')
                                                <i class="fas fa-clock text-blue-600 mr-1"></i>Regular (50%)
                                            @elseif($overtime->overtime_type === 'holiday')
                                                <i class="fas fa-calendar-day text-red-600 mr-1"></i>Feriado (100%)
                                            @elseif($overtime->overtime_type === 'night')
                                                <i class="fas fa-moon text-purple-600 mr-1"></i>Noturno (75%)
                                            @else
                                                <i class="fas fa-calendar-week text-green-600 mr-1"></i>Fim de Semana (100%)
                                            @endif
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs mb-1">Valor</p>
                                        <p class="font-bold text-green-600">{{ number_format($overtime->total_amount, 2, ',', '.') }} Kz</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <button wire:click="viewDetails({{ $overtime->id }})" 
                                        class="w-9 h-9 flex items-center justify-center bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition-colors"
                                        title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                @if($overtime->status === 'pending')
                                    <button wire:click="approve({{ $overtime->id }})" 
                                            class="w-9 h-9 flex items-center justify-center bg-green-100 text-green-600 rounded-lg hover:bg-green-200 transition-colors"
                                            title="Aprovar">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button wire:click="openRejectionModal({{ $overtime->id }})" 
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
                {{ $overtimes->links() }}
            </div>
        @else
            {{-- Empty State --}}
            <div class="p-12 text-center">
                <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-clock text-indigo-600 text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Nenhuma hora extra registrada</h3>
                <p class="text-gray-600 mb-6">Comece registrando horas extras trabalhadas</p>
                <button wire:click="create" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-md inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i>Registrar Horas
                </button>
            </div>
        @endif
    </div>

    {{-- Modals --}}
    @if($showModal)
        @include('livewire.hr.overtime.partials.form-modal')
    @endif

    @if($showDetailsModal)
        @include('livewire.hr.overtime.partials.details-modal')
    @endif

    @if($showRejectionModal)
        @include('livewire.hr.overtime.partials.rejection-modal')
    @endif
</div>
