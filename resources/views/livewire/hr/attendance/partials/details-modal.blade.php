<div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-gradient-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>Detalhes do Registro de Presença
                </h5>
                <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
            </div>

            <div class="modal-body">
                @if($selectedAttendance)
                    <div class="row g-4">
                        {{-- Informações do Funcionário --}}
                        <div class="col-md-6">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-uppercase text-muted mb-3">
                                        <i class="fas fa-user me-2"></i>Funcionário
                                    </h6>
                                    <h5 class="mb-2">{{ $selectedAttendance->employee->full_name }}</h5>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-id-badge me-2"></i>
                                        {{ $selectedAttendance->employee->employee_number }}
                                    </p>
                                    @if($selectedAttendance->employee->department)
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-building me-2"></i>
                                            {{ $selectedAttendance->employee->department->name }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Data e Status --}}
                        <div class="col-md-6">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-uppercase text-muted mb-3">
                                        <i class="fas fa-calendar me-2"></i>Data e Status
                                    </h6>
                                    <p class="mb-2">
                                        <strong>Data:</strong>
                                        <span class="badge bg-primary ms-2">
                                            {{ $selectedAttendance->date->format('d/m/Y') }}
                                        </span>
                                    </p>
                                    <p class="mb-0">
                                        <strong>Status:</strong>
                                        @if($selectedAttendance->status === 'present')
                                            <span class="badge bg-success ms-2">Presente</span>
                                        @elseif($selectedAttendance->status === 'absent')
                                            <span class="badge bg-danger ms-2">Ausente</span>
                                        @elseif($selectedAttendance->status === 'late')
                                            <span class="badge bg-warning ms-2">Atrasado</span>
                                        @elseif($selectedAttendance->status === 'half_day')
                                            <span class="badge bg-info ms-2">Meio Período</span>
                                        @elseif($selectedAttendance->status === 'sick')
                                            <span class="badge bg-secondary ms-2">Doente</span>
                                        @else
                                            <span class="badge bg-primary ms-2">Férias</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Horários --}}
                        <div class="col-12">
                            <div class="card border-0">
                                <div class="card-body">
                                    <h6 class="text-uppercase text-muted mb-4">
                                        <i class="fas fa-clock me-2"></i>Controle de Horário
                                    </h6>
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <div class="p-3 bg-success bg-opacity-10 rounded">
                                                <i class="fas fa-sign-in-alt fa-2x text-success mb-2"></i>
                                                <p class="text-muted mb-1 small">Entrada</p>
                                                <h4 class="mb-0">
                                                    {{ $selectedAttendance->check_in ? substr($selectedAttendance->check_in, 0, 5) : '-' }}
                                                </h4>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-3 bg-danger bg-opacity-10 rounded">
                                                <i class="fas fa-sign-out-alt fa-2x text-danger mb-2"></i>
                                                <p class="text-muted mb-1 small">Saída</p>
                                                <h4 class="mb-0">
                                                    {{ $selectedAttendance->check_out ? substr($selectedAttendance->check_out, 0, 5) : '-' }}
                                                </h4>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-3 bg-info bg-opacity-10 rounded">
                                                <i class="fas fa-hourglass-half fa-2x text-info mb-2"></i>
                                                <p class="text-muted mb-1 small">Horas Trabalhadas</p>
                                                <h4 class="mb-0">
                                                    {{ $selectedAttendance->hours_worked ? number_format($selectedAttendance->hours_worked, 2) . 'h' : '-' }}
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Observações --}}
                        @if($selectedAttendance->notes)
                            <div class="col-12">
                                <div class="card border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="text-uppercase text-muted mb-3">
                                            <i class="fas fa-sticky-note me-2"></i>Observações
                                        </h6>
                                        <p class="mb-0">{{ $selectedAttendance->notes }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Informações de Registro --}}
                        <div class="col-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-plus me-1"></i>
                                                Registrado em: {{ $selectedAttendance->created_at->format('d/m/Y H:i') }}
                                            </small>
                                        </div>
                                        @if($selectedAttendance->updated_at != $selectedAttendance->created_at)
                                            <div class="col-md-6 text-end">
                                                <small class="text-muted">
                                                    <i class="fas fa-edit me-1"></i>
                                                    Atualizado em: {{ $selectedAttendance->updated_at->format('d/m/Y H:i') }}
                                                </small>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
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
