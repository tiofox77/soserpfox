<div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-gradient-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>Detalhes da Solicitação de Férias
                </h5>
                <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
            </div>

            <div class="modal-body">
                @if($selectedVacation)
                    <div class="row g-4">
                        <!-- Informações do Funcionário -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>Informações do Funcionário</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <th width="40%">Nome:</th>
                                            <td>{{ $selectedVacation->employee->full_name }}</td>
                                        </tr>
                                        <tr>
                                            <th>Nº Funcionário:</th>
                                            <td><span class="badge bg-secondary">{{ $selectedVacation->employee->employee_number }}</span></td>
                                        </tr>
                                        <tr>
                                            <th>Departamento:</th>
                                            <td>{{ $selectedVacation->employee->department->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Cargo:</th>
                                            <td>{{ $selectedVacation->employee->position->title ?? '-' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Informações das Férias -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-umbrella-beach me-2"></i>Informações das Férias</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <th width="40%">Nº Férias:</th>
                                            <td><span class="badge bg-primary">{{ $selectedVacation->vacation_number }}</span></td>
                                        </tr>
                                        <tr>
                                            <th>Ano Referência:</th>
                                            <td>{{ $selectedVacation->reference_year }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tipo:</th>
                                            <td>
                                                @if($selectedVacation->vacation_type === 'normal')
                                                    <span class="badge bg-primary">Normal</span>
                                                @elseif($selectedVacation->vacation_type === 'accumulated')
                                                    <span class="badge bg-info">Acumuladas</span>
                                                @elseif($selectedVacation->vacation_type === 'advance')
                                                    <span class="badge bg-warning">Antecipadas</span>
                                                @else
                                                    <span class="badge bg-purple">Coletivas</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Período Aquisitivo:</th>
                                            <td>{{ $selectedVacation->period_start->format('d/m/Y') }} a {{ $selectedVacation->period_end->format('d/m/Y') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Status:</th>
                                            <td>
                                                @if($selectedVacation->status === 'pending')
                                                    <span class="badge bg-warning">Pendente</span>
                                                @elseif($selectedVacation->status === 'approved')
                                                    <span class="badge bg-info">Aprovada</span>
                                                @elseif($selectedVacation->status === 'rejected')
                                                    <span class="badge bg-danger">Rejeitada</span>
                                                @elseif($selectedVacation->status === 'in_progress')
                                                    <span class="badge bg-primary">Em Andamento</span>
                                                @elseif($selectedVacation->status === 'completed')
                                                    <span class="badge bg-success">Concluída</span>
                                                @else
                                                    <span class="badge bg-secondary">Cancelada</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Período de Férias -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Período de Férias</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <th width="40%">Data de Início:</th>
                                            <td><i class="fas fa-calendar-alt text-primary"></i> {{ $selectedVacation->start_date->format('d/m/Y') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Data de Término:</th>
                                            <td><i class="fas fa-calendar-check text-success"></i> {{ $selectedVacation->end_date->format('d/m/Y') }}</td>
                                        </tr>
                                        @if($selectedVacation->expected_return_date)
                                        <tr>
                                            <th>Retorno Previsto:</th>
                                            <td><i class="fas fa-calendar-day text-info"></i> {{ $selectedVacation->expected_return_date->format('d/m/Y') }}</td>
                                        </tr>
                                        @endif
                                        <tr>
                                            <th>Dias Corridos:</th>
                                            <td><strong>{{ $selectedVacation->requested_days }}</strong> dias</td>
                                        </tr>
                                        <tr>
                                            <th>Dias Úteis:</th>
                                            <td><strong class="text-primary">{{ $selectedVacation->working_days }}</strong> dias úteis</td>
                                        </tr>
                                        @if($selectedVacation->split_number)
                                        <tr>
                                            <th>Divisão:</th>
                                            <td><span class="badge bg-info">{{ $selectedVacation->split_number }}ª parcela de {{ $selectedVacation->total_splits }}</span></td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Direito e Valores -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Cálculos Financeiros</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <th width="40%">Dias com Direito:</th>
                                            <td>{{ $selectedVacation->entitled_days }} dias ({{ $selectedVacation->working_months }} meses)</td>
                                        </tr>
                                        @if($selectedVacation->previous_balance > 0)
                                        <tr>
                                            <th>Saldo Anterior:</th>
                                            <td><span class="badge bg-warning">+{{ $selectedVacation->previous_balance }} dias</span></td>
                                        </tr>
                                        @endif
                                        @if($selectedVacation->accumulated_days > 0)
                                        <tr>
                                            <th>Dias Acumulados:</th>
                                            <td><span class="badge bg-info">{{ $selectedVacation->accumulated_days }} dias</span></td>
                                        </tr>
                                        @endif
                                        @if($selectedVacation->days_remaining > 0)
                                        <tr>
                                            <th>Dias Restantes:</th>
                                            <td><span class="badge bg-secondary">{{ $selectedVacation->days_remaining }} dias</span></td>
                                        </tr>
                                        @endif
                                        <tr>
                                            <th>Taxa Diária:</th>
                                            <td>{{ number_format($selectedVacation->daily_rate, 2, ',', '.') }} Kz</td>
                                        </tr>
                                        <tr>
                                            <th>Pagamento Férias:</th>
                                            <td class="text-success"><strong>{{ number_format($selectedVacation->vacation_pay, 2, ',', '.') }} Kz</strong></td>
                                        </tr>
                                        <tr>
                                            <th>Subsídio (14º):</th>
                                            <td class="text-success"><strong>{{ number_format($selectedVacation->subsidy_amount, 2, ',', '.') }} Kz</strong></td>
                                        </tr>
                                        <tr class="table-active">
                                            <th>Total a Receber:</th>
                                            <td><strong class="text-success fs-5">{{ number_format($selectedVacation->total_amount, 2, ',', '.') }} Kz</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Aprovação/Rejeição -->
                        @if($selectedVacation->status === 'approved' || $selectedVacation->status === 'rejected')
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            @if($selectedVacation->status === 'approved')
                                                <i class="fas fa-check-circle text-success me-2"></i>Aprovação
                                            @else
                                                <i class="fas fa-times-circle text-danger me-2"></i>Rejeição
                                            @endif
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>{{ $selectedVacation->status === 'approved' ? 'Aprovado por:' : 'Rejeitado por:' }}</strong>
                                                {{ $selectedVacation->status === 'approved' ? ($selectedVacation->approvedBy->name ?? '-') : ($selectedVacation->rejectedBy->name ?? '-') }}
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Data:</strong>
                                                {{ $selectedVacation->status === 'approved' ? $selectedVacation->approved_at->format('d/m/Y H:i') : $selectedVacation->rejected_at->format('d/m/Y H:i') }}
                                            </div>
                                            @if($selectedVacation->rejection_reason)
                                                <div class="col-12 mt-2">
                                                    <strong>Motivo da Rejeição:</strong>
                                                    <p class="mb-0 text-muted">{{ $selectedVacation->rejection_reason }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Adiantamento (Subsídio de Férias) -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-hand-holding-usd me-2"></i>Adiantamento (14º)</h6>
                                </div>
                                <div class="card-body">
                                    @if($selectedVacation->advance_paid)
                                        <div class="alert alert-success mb-0">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <strong>Pago em:</strong> {{ $selectedVacation->advance_paid_date->format('d/m/Y') }}
                                            <br>
                                            <strong>Autorizado por:</strong> {{ $selectedVacation->advancePaidBy->name ?? '-' }}
                                        </div>
                                    @else
                                        <div class="alert alert-warning mb-0">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Adiantamento não pago
                                            @if($selectedVacation->advance_payment_date)
                                                <br><small>Data limite: {{ $selectedVacation->advance_payment_date->format('d/m/Y') }}</small>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Pagamento Final -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-money-check me-2"></i>Pagamento Final</h6>
                                </div>
                                <div class="card-body">
                                    @if($selectedVacation->paid)
                                        <div class="alert alert-success mb-0">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <strong>Pago em:</strong> {{ $selectedVacation->paid_date->format('d/m/Y') }}
                                            <br>
                                            <strong>Pago por:</strong> {{ $selectedVacation->paidBy->name ?? '-' }}
                                            @if($selectedVacation->processed_in_payroll)
                                                <br><span class="badge bg-success mt-1">Processado em folha</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="alert alert-warning mb-0">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Pagamento ainda não realizado
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Controle de Retorno -->
                        @if($selectedVacation->actual_return_date || $selectedVacation->status === 'completed')
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Retorno ao Trabalho</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        @if($selectedVacation->actual_return_date)
                                        <div class="col-md-6">
                                            <strong>Data de Retorno:</strong> {{ $selectedVacation->actual_return_date->format('d/m/Y') }}
                                            @if($selectedVacation->returned_on_time !== null)
                                                @if($selectedVacation->returned_on_time)
                                                    <span class="badge bg-success ms-2">No Prazo</span>
                                                @else
                                                    <span class="badge bg-danger ms-2">Com Atraso</span>
                                                @endif
                                            @endif
                                        </div>
                                        @endif
                                        @if($selectedVacation->return_notes)
                                        <div class="col-12 mt-2">
                                            <strong>Observações do Retorno:</strong>
                                            <p class="mb-0 text-muted">{{ $selectedVacation->return_notes }}</p>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Substituição -->
                        @if($selectedVacation->replacementEmployee)
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-user-friends me-2"></i>Funcionário Substituto</h6>
                                    </div>
                                    <div class="card-body">
                                        <strong>{{ $selectedVacation->replacementEmployee->full_name }}</strong>
                                        ({{ $selectedVacation->replacementEmployee->employee_number }})
                                        - {{ $selectedVacation->replacementEmployee->position->title ?? '-' }}
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Férias Coletivas -->
                        @if($selectedVacation->is_collective)
                            <div class="col-12">
                                <div class="card border-purple">
                                    <div class="card-header bg-purple text-white">
                                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Férias Coletivas</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Esta é uma solicitação de <strong>férias coletivas</strong>
                                            @if($selectedVacation->collective_group)
                                                para: <strong>{{ $selectedVacation->collective_group }}</strong>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Documento Anexo -->
                        @if($selectedVacation->attachment_path)
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-paperclip me-2"></i>Documento Anexo</h6>
                                    </div>
                                    <div class="card-body">
                                        <a href="{{ asset('storage/' . $selectedVacation->attachment_path) }}" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download me-1"></i>
                                            Baixar Documento
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Observações -->
                        @if($selectedVacation->notes)
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Observações</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0">{{ $selectedVacation->notes }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" wire:click="closeModal">
                    <i class="fas fa-times me-1"></i>Fechar
                </button>
            </div>
        </div>
    </div>
</div>
