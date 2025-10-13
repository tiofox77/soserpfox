<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-gradient-success d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-white">
                        <i class="fas fa-money-check-alt me-2"></i>Gestão de Folha de Pagamento
                    </h5>
                    <button wire:click="$set('showCreateModal', true)" class="btn btn-light btn-sm">
                        <i class="fas fa-plus me-1"></i>Nova Folha
                    </button>
                </div>

                <div class="card-body">
                    <!-- Filtros -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <input type="text" wire:model.live="search" class="form-control" placeholder="🔍 Nº Folha...">
                        </div>
                        <div class="col-md-2">
                            <select wire:model.live="yearFilter" class="form-select">
                                <option value="">Todos Anos</option>
                                @foreach($years as $year)
                                    <option value="{{ $year }}">{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select wire:model.live="monthFilter" class="form-select">
                                <option value="">Todos Meses</option>
                                @foreach($months as $num => $name)
                                    <option value="{{ $num }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select wire:model.live="statusFilter" class="form-select">
                                <option value="">Todos Status</option>
                                <option value="draft">Rascunho</option>
                                <option value="processing">Processando</option>
                                <option value="approved">Aprovada</option>
                                <option value="paid">Paga</option>
                                <option value="cancelled">Cancelada</option>
                            </select>
                        </div>
                    </div>

                    <!-- Grid de Folhas -->
                    <div class="row">
                        @forelse($payrolls as $payroll)
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 shadow-sm hover-shadow">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="mb-0">{{ $months[$payroll->month] }} {{ $payroll->year }}</h6>
                                                <small class="text-muted">{{ $payroll->payroll_number }}</small>
                                            </div>
                                            @if($payroll->status === 'draft')
                                                <span class="badge bg-secondary">Rascunho</span>
                                            @elseif($payroll->status === 'processing')
                                                <span class="badge bg-warning">Processando</span>
                                            @elseif($payroll->status === 'approved')
                                                <span class="badge bg-info">Aprovada</span>
                                            @elseif($payroll->status === 'paid')
                                                <span class="badge bg-success">Paga</span>
                                            @else
                                                <span class="badge bg-danger">Cancelada</span>
                                            @endif
                                        </div>

                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <small class="text-muted">Funcionários:</small>
                                                <strong>{{ $payroll->processed_employees }}/{{ $payroll->total_employees }}</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <small class="text-muted">Total Bruto:</small>
                                                <strong class="text-primary">{{ number_format($payroll->total_gross_salary, 2, ',', '.') }} Kz</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <small class="text-muted">Total IRT:</small>
                                                <strong class="text-danger">{{ number_format($payroll->total_irt, 2, ',', '.') }} Kz</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <small class="text-muted">Total INSS:</small>
                                                <strong class="text-warning">{{ number_format($payroll->total_inss_employee, 2, ',', '.') }} Kz</strong>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between">
                                                <strong>Total Líquido:</strong>
                                                <strong class="text-success">{{ number_format($payroll->total_net_salary, 2, ',', '.') }} Kz</strong>
                                            </div>
                                        </div>

                                        <div class="d-grid gap-2">
                                            @if($payroll->status === 'draft')
                                                <button wire:click="processPayroll({{ $payroll->id }})" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-cogs me-1"></i>Processar
                                                </button>
                                            @elseif($payroll->status === 'processing')
                                                <button wire:click="approvePayroll({{ $payroll->id }})" class="btn btn-sm btn-info">
                                                    <i class="fas fa-check me-1"></i>Aprovar
                                                </button>
                                            @elseif($payroll->status === 'approved')
                                                <button wire:click="markAsPaid({{ $payroll->id }})" class="btn btn-sm btn-success">
                                                    <i class="fas fa-money-bill-wave me-1"></i>Marcar como Paga
                                                </button>
                                            @endif
                                            
                                            <button wire:click="viewDetails({{ $payroll->id }})" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i>Ver Detalhes
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-money-check-alt fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">Nenhuma folha de pagamento encontrada</h5>
                                    <p class="text-muted">Crie uma nova folha para começar.</p>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    <!-- Paginação -->
                    <div class="mt-3">
                        {{ $payrolls->links() }}
                    </div>
                </div>
            </div>
        </div>
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
</div>

<style>
.hover-shadow {
    transition: all 0.3s ease;
}
.hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
}
</style>
