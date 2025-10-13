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
    <div class="mb-6 bg-gradient-to-r from-orange-600 to-red-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-file-medical text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Gestão de Licenças</h2>
                    <p class="text-orange-100 text-sm">Controle de licenças médicas, faltas e ausências</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-orange-600 hover:bg-orange-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Nova Solicitação
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
                {{ $leaves->where('status', 'pending')->count() }}
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
                {{ $leaves->where('status', 'approved')->count() }}
            </p>
            <p class="text-xs text-gray-500">Licenças confirmadas</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-heartbeat text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Médicas</p>
            <p class="text-4xl font-bold text-gray-900">
                {{ $leaves->where('leave_type', 'sick')->count() }}
            </p>
            <p class="text-xs text-gray-500">Licenças médicas</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-red-100">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-times-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-red-600 font-semibold mb-2">Rejeitadas</p>
            <p class="text-4xl font-bold text-gray-900">
                {{ $leaves->where('status', 'rejected')->count() }}
            </p>
            <p class="text-xs text-gray-500">Não aprovadas</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-filter mr-2 text-orange-600"></i>
            Filtros
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Search --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <input wire:model.live="search" type="text"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 appearance-none bg-white text-sm"
                       placeholder="Número, funcionário...">
            </div>

            {{-- Year Filter --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar-alt mr-1"></i>Ano
                </label>
                <select wire:model.live="yearFilter"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 appearance-none bg-white text-sm">
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
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 appearance-none bg-white text-sm">
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
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 appearance-none bg-white text-sm">
                    <option value="">Todos os Tipos</option>
                    <option value="sick">Doença</option>
                    <option value="personal">Pessoal</option>
                    <option value="bereavement">Luto</option>
                    <option value="maternity">Maternidade</option>
                    <option value="paternity">Paternidade</option>
                    <option value="unpaid">Sem Vencimento</option>
                    <option value="other">Outro</option>
                </select>
            </div>
        </div>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-orange-50 border-b border-orange-100">
            <h3 class="text-lg font-bold text-orange-900 flex items-center">
                <i class="fas fa-list mr-2"></i>
                Listagem de Licenças ({{ $leaves->total() }})
            </h3>
        </div>

        @if($leaves->count() > 0)
            <div class="p-6 space-y-4">
                @foreach($leaves as $leave)
                    <div class="bg-gradient-to-r from-white to-orange-50 border border-orange-200 rounded-xl p-6 hover:shadow-lg transition-all">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-3">
                                    <span class="px-3 py-1 bg-orange-600 text-white rounded-full text-xs font-bold">
                                        {{ $leave->leave_number }}
                                    </span>
                                    <h4 class="font-bold text-gray-900 text-lg">{{ $leave->employee->full_name }}</h4>
                                    
                                    @if($leave->status === 'pending')
                                        <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">Pendente</span>
                                    @elseif($leave->status === 'approved')
                                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Aprovada</span>
                                    @elseif($leave->status === 'rejected')
                                        <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">Rejeitada</span>
                                    @else
                                        <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">Cancelada</span>
                                    @endif
                                </div>
                                
                                <div class="grid grid-cols-4 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500 text-xs mb-1">Período</p>
                                        <p class="font-semibold text-orange-600">
                                            <i class="fas fa-calendar mr-1"></i>{{ $leave->start_date->format('d/m/Y') }} - {{ $leave->end_date->format('d/m/Y') }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs mb-1">Dias</p>
                                        <p class="font-bold text-gray-900">{{ $leave->working_days }} úteis</p>
                                        <p class="text-xs text-gray-500">({{ $leave->total_days }} corridos)</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs mb-1">Tipo</p>
                                        <p class="font-semibold text-gray-900">
                                            @if($leave->leave_type === 'sick')
                                                <i class="fas fa-heartbeat text-red-600 mr-1"></i>Doença
                                            @elseif($leave->leave_type === 'personal')
                                                <i class="fas fa-user text-blue-600 mr-1"></i>Pessoal
                                            @elseif($leave->leave_type === 'bereavement')
                                                <i class="fas fa-cross text-gray-600 mr-1"></i>Luto
                                            @elseif($leave->leave_type === 'maternity')
                                                <i class="fas fa-baby text-pink-600 mr-1"></i>Maternidade
                                            @elseif($leave->leave_type === 'paternity')
                                                <i class="fas fa-baby-carriage text-blue-600 mr-1"></i>Paternidade
                                            @else
                                                <i class="fas fa-file-alt text-gray-600 mr-1"></i>{{ ucfirst($leave->leave_type) }}
                                            @endif
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs mb-1">Pago</p>
                                        @if($leave->paid)
                                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                                <i class="fas fa-check mr-1"></i>Sim
                                            </span>
                                        @else
                                            <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-semibold">
                                                <i class="fas fa-times mr-1"></i>Não
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <button wire:click="viewDetails({{ $leave->id }})" 
                                        class="w-9 h-9 flex items-center justify-center bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition-colors"
                                        title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                @if($leave->status === 'pending')
                                    <button wire:click="openApprovalModal({{ $leave->id }}, 'approve')" 
                                            class="w-9 h-9 flex items-center justify-center bg-green-100 text-green-600 rounded-lg hover:bg-green-200 transition-colors"
                                            title="Aprovar">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button wire:click="openApprovalModal({{ $leave->id }}, 'reject')" 
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
                {{ $leaves->links() }}
            </div>
        @else
            {{-- Empty State --}}
            <div class="p-12 text-center">
                <div class="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-file-medical text-orange-600 text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Nenhuma licença encontrada</h3>
                <p class="text-gray-600 mb-6">Comece criando uma solicitação de licença</p>
                <button wire:click="create" 
                        class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-md inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i>Nova Solicitação
                </button>
            </div>
        @endif
    </div>

    {{-- Modals --}}
    @if($showModal)
        @include('livewire.hr.leaves.partials.form-modal')
    @endif

    @if($showDetailsModal)
        @include('livewire.hr.leaves.partials.details-modal')
    @endif

    @if($showApprovalModal)
        @include('livewire.hr.leaves.partials.approval-modal')
    @endif
</div>
