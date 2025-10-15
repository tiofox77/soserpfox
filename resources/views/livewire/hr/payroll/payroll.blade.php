<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-money-check-alt mr-3 text-green-600"></i>
                    Folha de Pagamento
                </h2>
                <p class="text-gray-600 mt-1">Gestão de folhas de pagamento mensal</p>
            </div>
            <button wire:click="$set('showCreateModal', true)" 
                    class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-bold transition shadow-lg transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Nova Folha
            </button>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="mb-4 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded-lg animate-fade-in">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg animate-fade-in">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-200 text-xs font-medium">Total Folhas</p>
                    <p class="text-2xl font-bold mt-1">{{ $payrolls->total() }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-file-invoice text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-gray-500 to-gray-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-200 text-xs font-medium">Rascunho</p>
                    <p class="text-2xl font-bold mt-1">{{ $payrolls->where('status', 'draft')->count() }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-edit text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-200 text-xs font-medium">Processando</p>
                    <p class="text-2xl font-bold mt-1">{{ $payrolls->where('status', 'processing')->count() }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-spinner text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">Aprovadas</p>
                    <p class="text-2xl font-bold mt-1">{{ $payrolls->where('status', 'approved')->count() }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl shadow-lg p-4 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-emerald-200 text-xs font-medium">Pagas</p>
                    <p class="text-2xl font-bold mt-1">{{ $payrolls->where('status', 'paid')->count() }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-money-bill-wave text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-md p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <input type="text" wire:model.live.debounce.300ms="search" 
                       placeholder="🔍 Pesquisar nº folha..." 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <select wire:model.live="yearFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    <option value="">📅 Todos Anos</option>
                    @foreach($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select wire:model.live="monthFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    <option value="">📆 Todos Meses</option>
                    @foreach($months as $num => $name)
                        <option value="{{ $num }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    <option value="">🏷️ Todos Status</option>
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
                    <div class="mt-5 space-y-2">
                        @if($payroll->status === 'draft')
                            <button wire:click="processPayroll({{ $payroll->id }})" 
                                    class="w-full px-4 py-2 bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white rounded-lg font-semibold transition transform hover:scale-105 shadow">
                                <i class="fas fa-cogs mr-2"></i>Processar Folha
                            </button>
                        @elseif($payroll->status === 'processing')
                            <button wire:click="approvePayroll({{ $payroll->id }})" 
                                    class="w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white rounded-lg font-semibold transition transform hover:scale-105 shadow">
                                <i class="fas fa-check-circle mr-2"></i>Aprovar Folha
                            </button>
                        @elseif($payroll->status === 'approved')
                            <button wire:click="markAsPaid({{ $payroll->id }})" 
                                    class="w-full px-4 py-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white rounded-lg font-semibold transition transform hover:scale-105 shadow">
                                <i class="fas fa-money-bill-wave mr-2"></i>Marcar como Paga
                            </button>
                        @endif
                        
                        <button wire:click="viewDetails({{ $payroll->id }})" 
                                class="w-full px-4 py-2 bg-white border-2 border-green-600 text-green-600 hover:bg-green-50 rounded-lg font-semibold transition">
                            <i class="fas fa-eye mr-2"></i>Ver Detalhes
                        </button>
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
    <div class="mt-6">
        {{ $payrolls->links() }}
    </div>

    <!-- Modal Criar Folha -->
    @if($showCreateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-gradient-success text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-plus me-2"></i>Nova Folha de Pagamento
                        </h5>
                        <button type="button" wire:click="closeModals" class="btn-close btn-close-white"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Ano *</label>
                                <select wire:model="createYear" class="form-select">
                                    @foreach($years as $year)
                                        <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mês *</label>
                                <select wire:model="createMonth" class="form-select">
                                    @foreach($months as $num => $name)
                                        <option value="{{ $num }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>A folha será criada para todos os funcionários ativos com contratos vigentes.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="closeModals" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>
                        <button type="button" wire:click="createPayroll" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Criar Folha
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Detalhes -->
    @if($showDetailsModal && $selectedPayroll)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-gradient-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-file-invoice-dollar me-2"></i>
                            Folha: {{ $months[$selectedPayroll->month] }} {{ $selectedPayroll->year }}
                        </h5>
                        <button type="button" wire:click="closeModals" class="btn-close btn-close-white"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Resumo -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <small class="text-muted">Total Bruto</small>
                                        <h5 class="mb-0 text-primary">{{ number_format($selectedPayroll->total_gross_salary, 2, ',', '.') }} Kz</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <small class="text-muted">Total IRT</small>
                                        <h5 class="mb-0 text-danger">{{ number_format($selectedPayroll->total_irt, 2, ',', '.') }} Kz</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <small class="text-muted">Total INSS (3%)</small>
                                        <h5 class="mb-0 text-warning">{{ number_format($selectedPayroll->total_inss_employee, 2, ',', '.') }} Kz</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <small class="text-muted">Total Líquido</small>
                                        <h5 class="mb-0 text-success">{{ number_format($selectedPayroll->total_net_salary, 2, ',', '.') }} Kz</h5>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabela de Funcionários -->
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Funcionário</th>
                                        <th class="text-end">Bruto</th>
                                        <th class="text-end">INSS (3%)</th>
                                        <th class="text-end">IRT</th>
                                        <th class="text-end">Deduções</th>
                                        <th class="text-end">Líquido</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($selectedPayroll->items as $item)
                                        <tr>
                                            <td>
                                                <strong>{{ $item->employee->full_name }}</strong><br>
                                                <small class="text-muted">{{ $item->employee->employee_number }}</small>
                                            </td>
                                            <td class="text-end">{{ number_format($item->gross_salary, 2, ',', '.') }}</td>
                                            <td class="text-end text-warning">{{ number_format($item->inss_employee, 2, ',', '.') }}</td>
                                            <td class="text-end text-danger">{{ number_format($item->irt_amount, 2, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format($item->total_deductions, 2, ',', '.') }}</td>
                                            <td class="text-end">
                                                <strong class="text-success">{{ number_format($item->net_salary, 2, ',', '.') }}</strong>
                                            </td>
                                            <td>
                                                @if($item->status === 'paid')
                                                    <span class="badge bg-success">Pago</span>
                                                @elseif($item->status === 'approved')
                                                    <span class="badge bg-info">Aprovado</span>
                                                @elseif($item->status === 'calculated')
                                                    <span class="badge bg-warning">Calculado</span>
                                                @else
                                                    <span class="badge bg-secondary">Pendente</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="closeModals" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Fechar
                        </button>
                        <button type="button" class="btn btn-primary">
                            <i class="fas fa-download me-1"></i>Exportar PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
    
    {{-- Animações e Estilos --}}
    <style>
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in {
            animation: fade-in 0.5s ease-out;
        }
        
        @keyframes slide-up {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .grid > div {
            animation: slide-up 0.4s ease-out;
            animation-fill-mode: both;
        }
        
        .grid > div:nth-child(1) { animation-delay: 0.1s; }
        .grid > div:nth-child(2) { animation-delay: 0.2s; }
        .grid > div:nth-child(3) { animation-delay: 0.3s; }
        .grid > div:nth-child(4) { animation-delay: 0.4s; }
        .grid > div:nth-child(5) { animation-delay: 0.5s; }
        .grid > div:nth-child(6) { animation-delay: 0.6s; }
    </style>
</div>
