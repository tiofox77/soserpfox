<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-money-check-alt text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Folha de Pagamento</h2>
                    <p class="text-green-100 text-sm">Gestão completa de folhas de pagamento - Lei Angolana</p>
                </div>
            </div>
            <button wire:click="$set('showCreateModal', true)" 
                    class="px-6 py-3 bg-white text-green-600 hover:bg-green-50 rounded-xl font-bold transition-all shadow-lg hover:shadow-xl transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Nova Folha
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6 stagger-animation">
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-blue-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/50 icon-float">
                    <i class="fas fa-file-invoice text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-blue-600 font-semibold mb-2">Total de Folhas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['total'] }}</p>
            <p class="text-xs text-gray-500">Todas as folhas criadas</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-gray-500 to-gray-600 rounded-2xl flex items-center justify-center shadow-lg shadow-gray-500/50 icon-float">
                    <i class="fas fa-edit text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-600 font-semibold mb-2">Rascunho</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['draft'] }}</p>
            <p class="text-xs text-gray-500">Aguardando processamento</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-yellow-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-500/50 icon-float">
                    <i class="fas fa-cogs text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-yellow-600 font-semibold mb-2">Processando</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['processing'] }}</p>
            <p class="text-xs text-gray-500">Em cálculo de IRT/INSS</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-green-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/50 icon-float">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-green-600 font-semibold mb-2">Aprovadas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['approved'] }}</p>
            <p class="text-xs text-gray-500">Prontas para pagamento</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 border border-emerald-100 overflow-hidden card-hover card-3d">
            <div class="flex items-center justify-between mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-500/50 icon-float">
                    <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                </div>
            </div>
            <p class="text-sm text-emerald-600 font-semibold mb-2">Pagas</p>
            <p class="text-4xl font-bold text-gray-900 mb-1">{{ $stats['paid'] }}</p>
            <p class="text-xs text-gray-500">Pagamentos concluídos</p>
        </div>
    </div>

    {{-- Filtros Avançados --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-filter mr-2 text-green-600"></i>
                Filtros Avançados
            </h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-search mr-1"></i>Pesquisar
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live="search" type="text" placeholder="Número da folha..." 
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar mr-1"></i>Ano
                </label>
                <select wire:model.live="yearFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 appearance-none bg-white text-sm">
                    <option value="">Todos</option>
                    @foreach($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-calendar-alt mr-1"></i>Mês
                </label>
                <select wire:model.live="monthFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 appearance-none bg-white text-sm">
                    <option value="">Todos</option>
                    @foreach($months as $num => $name)
                        <option value="{{ $num }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">
                    <i class="fas fa-list mr-1"></i>Status
                </label>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 appearance-none bg-white text-sm">
                    <option value="all">Todos</option>
                    <option value="draft">Rascunho</option>
                    <option value="processing">Processando</option>
                    <option value="approved">Aprovada</option>
                    <option value="paid">Paga</option>
                    <option value="cancelled">Cancelada</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Grid de Folhas --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($payrolls as $payroll)
            <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 overflow-hidden">
                <!-- Header do Card -->
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 p-4 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-bold">{{ $months[$payroll->month] }} {{ $payroll->year }}</h3>
                            <p class="text-sm text-green-100">{{ $payroll->payroll_number }}</p>
                        </div>
                        @if($payroll->status === 'draft')
                            <span class="px-3 py-1 bg-gray-500 rounded-full text-xs font-semibold">Rascunho</span>
                        @elseif($payroll->status === 'processing')
                            <span class="px-3 py-1 bg-yellow-500 rounded-full text-xs font-semibold animate-pulse">Processando</span>
                        @elseif($payroll->status === 'approved')
                            <span class="px-3 py-1 bg-blue-500 rounded-full text-xs font-semibold">Aprovada</span>
                        @elseif($payroll->status === 'paid')
                            <span class="px-3 py-1 bg-emerald-500 rounded-full text-xs font-semibold">✓ Paga</span>
                        @else
                            <span class="px-3 py-1 bg-red-500 rounded-full text-xs font-semibold">Cancelada</span>
                        @endif
                    </div>
                </div>

                <!-- Corpo do Card -->
                <div class="p-5">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b">
                            <span class="text-sm text-gray-600 flex items-center">
                                <i class="fas fa-users mr-2 text-blue-600"></i> Funcionários:
                            </span>
                            <span class="font-bold text-gray-800">{{ $payroll->processed_employees }}/{{ $payroll->total_employees }}</span>
                        </div>
                        <div class="flex items-center justify-between pb-2 border-b">
                            <span class="text-sm text-gray-600 flex items-center">
                                <i class="fas fa-money-bill-wave mr-2 text-green-600"></i> Total Bruto:
                            </span>
                            <span class="font-bold text-green-600">{{ number_format($payroll->total_gross_salary, 2, ',', '.') }} Kz</span>
                        </div>
                        <div class="flex items-center justify-between pb-2 border-b">
                            <span class="text-sm text-gray-600 flex items-center">
                                <i class="fas fa-receipt mr-2 text-red-600"></i> Total IRT:
                            </span>
                            <span class="font-semibold text-red-600">{{ number_format($payroll->total_irt, 2, ',', '.') }} Kz</span>
                        </div>
                        <div class="flex items-center justify-between pb-2 border-b">
                            <span class="text-sm text-gray-600 flex items-center">
                                <i class="fas fa-shield-alt mr-2 text-orange-600"></i> Total INSS:
                            </span>
                            <span class="font-semibold text-orange-600">{{ number_format($payroll->total_inss_employee, 2, ',', '.') }} Kz</span>
                        </div>
                        <div class="flex items-center justify-between pt-2 bg-green-50 p-3 rounded-lg">
                            <span class="font-bold text-gray-800">Total Líquido:</span>
                            <span class="text-xl font-bold text-green-600">{{ number_format($payroll->total_net_salary, 2, ',', '.') }} Kz</span>
                        </div>
                    </div>

                    <!-- Ações -->
                    <div class="p-5 bg-gray-50 border-t border-gray-200 space-y-2">
                        @if($payroll->status === 'draft')
                            <button wire:click="processPayroll({{ $payroll->id }})" 
                                    class="w-full px-4 py-2.5 bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                                <i class="fas fa-cogs mr-2"></i>Processar Folha
                            </button>
                        @elseif($payroll->status === 'processing')
                            <button wire:click="approvePayroll({{ $payroll->id }})" 
                                    class="w-full px-4 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                                <i class="fas fa-check-circle mr-2"></i>Aprovar Folha
                            </button>
                        @elseif($payroll->status === 'approved')
                            <button wire:click="markAsPaid({{ $payroll->id }})" 
                                    class="w-full px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg transform hover:scale-105">
                                <i class="fas fa-money-bill-wave mr-2"></i>Marcar como Paga
                            </button>
                        @endif
                        
                        <div class="grid grid-cols-2 gap-2">
                            <button wire:click="viewDetails({{ $payroll->id }})" 
                                    class="px-4 py-2.5 bg-white border-2 border-green-600 text-green-600 hover:bg-green-50 rounded-xl font-semibold transition-all">
                                <i class="fas fa-eye mr-1"></i>Detalhes
                            </button>
                            
                            @if($payroll->status !== 'paid')
                                <button wire:click="deletePayroll({{ $payroll->id }})" 
                                        class="px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                                    <i class="fas fa-trash mr-1"></i>Excluir
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-3">
                <div class="text-center py-12 bg-white rounded-xl shadow-md">
                    <i class="fas fa-money-check-alt text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-600 mb-2">Nenhuma folha encontrada</h3>
                    <p class="text-gray-500 mb-4">Crie uma nova folha de pagamento para começar</p>
                    <button wire:click="$set('showCreateModal', true)" 
                            class="px-6 py-2 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-lg font-semibold transition">
                        <i class="fas fa-plus mr-2"></i>Nova Folha
                    </button>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Paginação --}}
    @if($payrolls->hasPages())
        <div class="mt-6">
            {{ $payrolls->links() }}
        </div>
    @endif

    {{-- Modals --}}
    @if($showCreateModal)
        @include('livewire.hr.payroll.partials.create-modal')
    @endif

    @if($showDetailsModal && $selectedPayroll)
        @include('livewire.hr.payroll.partials.details-modal')
    @endif

    @if($showEditItemModal && $editingItem)
        @include('livewire.hr.payroll.partials.edit-item-modal')
    @endif

    @if($showDeleteModal && $deletingPayroll)
        @include('livewire.hr.payroll.partials.delete-modal')
    @endif
</div>
