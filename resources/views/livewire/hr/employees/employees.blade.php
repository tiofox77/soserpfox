<div>
    {{-- Toastr Notifications --}}
    @if (session()->has('success'))
        <div x-data="{ show: true }" 
             x-show="show" 
             x-transition:enter="transform transition ease-out duration-300"
             x-transition:enter-start="translate-y-2 opacity-0 scale-90"
             x-transition:enter-end="translate-y-0 opacity-100 scale-100"
             x-transition:leave="transform transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 opacity-100 scale-100"
             x-transition:leave-end="translate-y-2 opacity-0 scale-90"
             x-init="setTimeout(() => show = false, 5000)" 
             class="fixed top-4 right-4 z-50 max-w-md bg-white rounded-2xl shadow-2xl border-l-4 border-green-500 overflow-hidden toastr-slide-in">
            <div class="p-4 flex items-start">
                <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mr-3 toastr-icon-bounce">
                    <i class="fas fa-check-circle text-white text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-bold text-gray-900">Sucesso!</p>
                    <p class="text-sm text-gray-600 mt-1">{{ session('success') }}</p>
                </div>
                <button @click="show = false" class="ml-3 text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="h-1 bg-gray-100">
                <div class="h-full bg-gradient-to-r from-green-500 to-emerald-600 toastr-progress"></div>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }" 
             x-show="show" 
             x-transition:enter="transform transition ease-out duration-300"
             x-transition:enter-start="translate-y-2 opacity-0 scale-90"
             x-transition:enter-end="translate-y-0 opacity-100 scale-100"
             x-transition:leave="transform transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 opacity-100 scale-100"
             x-transition:leave-end="translate-y-2 opacity-0 scale-90"
             x-init="setTimeout(() => show = false, 5000)" 
             class="fixed top-4 right-4 z-50 max-w-md bg-white rounded-2xl shadow-2xl border-l-4 border-red-500 overflow-hidden toastr-slide-in">
            <div class="p-4 flex items-start">
                <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-red-500 to-pink-600 rounded-xl flex items-center justify-center mr-3 toastr-icon-bounce">
                    <i class="fas fa-exclamation-circle text-white text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-bold text-gray-900">Erro!</p>
                    <p class="text-sm text-gray-600 mt-1">{{ session('error') }}</p>
                </div>
                <button @click="show = false" class="ml-3 text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="h-1 bg-gray-100">
                <div class="h-full bg-gradient-to-r from-red-500 to-pink-600 toastr-progress"></div>
            </div>
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Gestão de Funcionários</h2>
                    <p class="text-blue-100 text-sm">Controle completo de colaboradores e equipe</p>
                </div>
            </div>
            <button wire:click="create" class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Novo Funcionário
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 stagger-animation">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-user-check text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Funcionários Ativos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $employees->where('status', 'active')->count() }}
            </p>
            <p class="text-xs text-gray-500">Colaboradores em atividade</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-zoom">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-sitemap text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Departamentos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $departments->count() }}</p>
            <p class="text-xs text-gray-500">Setores organizados</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100 overflow-hidden card-hover card-glow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-500/50 icon-float">
                    <i class="fas fa-clock text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-yellow-600 font-semibold mb-2">Suspensões</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $employees->where('status', 'suspended')->count() }}
            </p>
            <p class="text-xs text-gray-500">Temporariamente afastados</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-red-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/50 icon-float">
                    <i class="fas fa-user-times text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-red-600 font-semibold mb-2">Desligamentos</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">
                {{ $employees->where('status', 'terminated')->count() }}
            </p>
            <p class="text-xs text-gray-500">Contratos encerrados</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-blue-600"></i>
                Filtros Avançados
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-search mr-1 text-blue-600"></i>Pesquisar
                </label>
                <input type="text" wire:model.live="search" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                       placeholder="Nome, NIF, número...">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-building mr-1 text-green-600"></i>Departamento
                </label>
                <select wire:model.live="departmentFilter" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    <option value="">Todos Departamentos</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">
                    <i class="fas fa-info-circle mr-1 text-purple-600"></i>Status
                </label>
                <select wire:model.live="statusFilter" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    <option value="">Todos Status</option>
                    <option value="active">✅ Ativo</option>
                    <option value="suspended">⏸️ Suspenso</option>
                    <option value="terminated">❌ Desligado</option>
                    <option value="on_leave">🏖️ Em Licença</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Lista de Funcionários --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        {{-- Header --}}
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2 text-blue-600"></i>
                    Lista de Funcionários
                </h3>
                <span class="text-sm text-gray-600 font-semibold">
                    <i class="fas fa-users mr-1"></i>{{ $employees->total() }} Funcionários
                </span>
            </div>
        </div>

        {{-- Grid Header --}}
        <div class="hidden md:grid grid-cols-12 gap-4 px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-700 uppercase">
            <div class="col-span-1">Nº</div>
            <div class="col-span-2">Funcionário</div>
            <div class="col-span-2">Departamento</div>
            <div class="col-span-2">Cargo</div>
            <div class="col-span-2">Remuneração</div>
            <div class="col-span-2">Status</div>
            <div class="col-span-1 text-center">Ações</div>
        </div>

        {{-- Grid Body --}}
        <div class="divide-y divide-gray-200">
            @forelse($employees as $employee)
                <div class="px-6 py-4 hover:bg-gray-50 transition-colors group">
                    <div class="grid grid-cols-12 gap-4 items-center">
                        {{-- Número --}}
                        <div class="col-span-12 md:col-span-1">
                            <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold">
                                {{ $employee->employee_number }}
                            </span>
                        </div>

                        {{-- Funcionário --}}
                        <div class="col-span-12 md:col-span-2">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold shadow-lg mr-3">
                                    {{ strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <p class="font-semibold text-gray-900">{{ $employee->full_name }}</p>
                                        @if($employee->hasExpiredDocuments())
                                            <span class="inline-flex items-center px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-semibold" title="Documentos vencidos">
                                                <i class="fas fa-exclamation-circle mr-1"></i>{{ count($employee->expired_documents) }}
                                            </span>
                                        @elseif($employee->hasExpiringDocuments())
                                            <span class="inline-flex items-center px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold" title="Documentos próximos ao vencimento">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>{{ count($employee->expiring_documents) }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($employee->email)
                                        <p class="text-xs text-gray-500">
                                            <i class="fas fa-envelope mr-1"></i>{{ $employee->email }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Departamento --}}
                        <div class="col-span-12 md:col-span-2">
                            @if($employee->department)
                                <span class="inline-flex items-center px-2 py-1 bg-purple-100 text-purple-700 rounded-lg text-xs font-semibold">
                                    <i class="fas fa-building mr-1"></i>
                                    {{ $employee->department->name }}
                                </span>
                            @else
                                <span class="text-gray-400 text-xs">-</span>
                            @endif
                        </div>

                        {{-- Cargo --}}
                        <div class="col-span-12 md:col-span-2">
                            @if($employee->position)
                                <span class="inline-flex items-center px-2 py-1 bg-cyan-100 text-cyan-700 rounded-lg text-xs font-semibold">
                                    <i class="fas fa-briefcase mr-1"></i>
                                    {{ $employee->position->title }}
                                </span>
                            @else
                                <span class="text-gray-400 text-xs">-</span>
                            @endif
                        </div>

                        {{-- Remuneração --}}
                        <div class="col-span-12 md:col-span-2">
                            @if($employee->salary)
                                <div class="bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                                    <p class="text-xs text-gray-600 mb-1">
                                        <i class="fas fa-money-bill-wave text-green-600 mr-1"></i>Salário
                                    </p>
                                    <p class="font-bold text-green-700 text-sm">
                                        {{ number_format($employee->salary, 2, ',', '.') }} Kz
                                    </p>
                                    @if($employee->total_compensation > $employee->salary)
                                        <p class="text-xs text-gray-500 mt-1">
                                            Total: {{ number_format($employee->total_compensation, 2, ',', '.') }} Kz
                                        </p>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-400 text-xs">Não definido</span>
                            @endif
                        </div>

                        {{-- Status --}}
                        <div class="col-span-12 md:col-span-2">
                            @if($employee->status === 'active')
                                <span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">
                                    <i class="fas fa-check-circle mr-1"></i>Ativo
                                </span>
                            @elseif($employee->status === 'suspended')
                                <span class="inline-flex items-center px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">
                                    <i class="fas fa-pause-circle mr-1"></i>Suspenso
                                </span>
                            @elseif($employee->status === 'terminated')
                                <span class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">
                                    <i class="fas fa-times-circle mr-1"></i>Desligado
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                                    <i class="fas fa-calendar-alt mr-1"></i>Licença
                                </span>
                            @endif
                        </div>

                        {{-- Ações --}}
                        <div class="col-span-12 md:col-span-1">
                            <div class="flex items-center justify-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="edit({{ $employee->id }})" 
                                        class="w-8 h-8 flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition shadow-md hover:shadow-lg"
                                        title="Editar">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                                <button @click="$dispatch('open-delete-modal', { id: {{ $employee->id }}, name: '{{ $employee->full_name }}' })" 
                                        class="w-8 h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white rounded-lg transition shadow-md hover:shadow-lg"
                                        title="Excluir">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                {{-- Empty State --}}
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Nenhum funcionário encontrado</h3>
                    <p class="text-gray-500 mb-4">Comece cadastrando o primeiro colaborador</p>
                    <button wire:click="create" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-semibold hover:shadow-lg">
                        <i class="fas fa-plus mr-2"></i>Cadastrar Primeiro Funcionário
                    </button>
                </div>
            @endforelse
        </div>

        {{-- Paginação --}}
        @if($employees->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $employees->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Form --}}
    @if($showModal)
        @include('livewire.hr.employees.partials.form-modal')
    @endif

    {{-- Delete Confirmation Modal --}}
    <div x-data="{ 
        show: false, 
        employeeId: null, 
        employeeName: '' 
    }"
         @open-delete-modal.window="show = true; employeeId = $event.detail.id; employeeName = $event.detail.name"
         x-show="show"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        
        {{-- Backdrop --}}
        <div x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm"
             @click="show = false"></div>

        {{-- Modal --}}
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative inline-block w-full max-w-lg p-6 my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-3xl">
                
                {{-- Icon Warning --}}
                <div class="flex items-center justify-center mb-6">
                    <div class="w-20 h-20 bg-gradient-to-br from-red-500 to-pink-600 rounded-full flex items-center justify-center shadow-lg shadow-red-500/50 animate-pulse">
                        <i class="fas fa-exclamation-triangle text-white text-3xl"></i>
                    </div>
                </div>

                {{-- Title --}}
                <h3 class="text-2xl font-bold text-center text-gray-900 mb-3">
                    Confirmar Exclusão
                </h3>

                {{-- Description --}}
                <div class="text-center mb-6">
                    <p class="text-gray-600 mb-2">
                        Tem certeza que deseja remover o funcionário:
                    </p>
                    <p class="text-lg font-bold text-red-600" x-text="employeeName"></p>
                    <p class="text-sm text-gray-500 mt-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        Esta ação não pode ser desfeita!
                    </p>
                </div>

                {{-- Actions --}}
                <div class="flex space-x-3">
                    <button @click="show = false"
                            type="button"
                            class="flex-1 px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition-all duration-200 shadow-md hover:shadow-lg">
                        <i class="fas fa-times mr-2"></i>
                        Cancelar
                    </button>
                    <button @click="$wire.delete(employeeId); show = false"
                            type="button"
                            class="flex-1 px-6 py-3 bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl">
                        <i class="fas fa-trash mr-2"></i>
                        Sim, Excluir
                    </button>
                </div>

                {{-- Close Button --}}
                <button @click="show = false"
                        class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    /* Card Animations */
    .card-hover {
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .card-hover:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }
    
    .card-3d:hover {
        transform: translateY(-10px) rotateX(5deg) scale(1.03);
    }
    
    .card-zoom:hover {
        transform: scale(1.05);
    }
    
    .card-glow:hover {
        box-shadow: 0 0 30px rgba(168, 85, 247, 0.4);
    }
    
    .card-hover:hover .icon-float {
        transform: translateY(-5px) scale(1.1);
    }
    
    .stagger-animation > * {
        animation: fadeInUp 0.6s ease forwards;
        opacity: 0;
    }
    .stagger-animation > *:nth-child(1) { animation-delay: 0.1s; }
    .stagger-animation > *:nth-child(2) { animation-delay: 0.2s; }
    .stagger-animation > *:nth-child(3) { animation-delay: 0.3s; }
    .stagger-animation > *:nth-child(4) { animation-delay: 0.4s; }
    
    /* Toastr Animations */
    .toastr-slide-in {
        animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .toastr-icon-bounce {
        animation: iconBounce 0.6s ease-out;
    }
    
    .toastr-progress {
        animation: progressBar 5s linear;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes iconBounce {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.2);
        }
    }
    
    @keyframes progressBar {
        from {
            width: 100%;
        }
        to {
            width: 0%;
        }
    }
    
    /* Alpine.js x-cloak */
    [x-cloak] {
        display: none !important;
    }
</style>
@endpush
