<div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-umbrella-beach me-2"></i>
                    {{ $editMode ? 'Editar Solicitação de Férias' : 'Nova Solicitação de Férias' }}
                </h5>
                <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
            </div>

            <form wire:submit.prevent="save">
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Funcionário -->
                        <div class="col-md-8">
                            <label class="form-label">Funcionário <span class="text-danger">*</span></label>
                            <select wire:model.live="employee_id" class="form-select @error('employee_id') is-invalid @enderror">
                                <option value="">Selecione o funcionário</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">
                                        {{ $employee->full_name }} ({{ $employee->employee_number }})
                                    </option>
                                @endforeach
                            </select>
                            @error('employee_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Ano de Referência -->
                        <div class="col-md-4">
                            <label class="form-label">Ano de Referência <span class="text-danger">*</span></label>
                            <select wire:model.live="reference_year" class="form-select @error('reference_year') is-invalid @enderror">
                                @for($year = date('Y') - 1; $year <= date('Y') + 1; $year++)
                                    <option value="{{ $year }}">{{ $year }}</option>
                                @endfor
                            </select>
                            @error('reference_year')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Tipo de Férias -->
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Férias <span class="text-danger">*</span></label>
                            <select wire:model="vacation_type" class="form-select @error('vacation_type') is-invalid @enderror">
                                <option value="normal">Normal (Regulares)</option>
                                <option value="accumulated">Acumuladas (2 anos juntos)</option>
                                <option value="advance">Antecipadas</option>
                                <option value="collective">Coletivas (Empresa)</option>
                            </select>
                            @error('vacation_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Permitir Divisão -->
                        <div class="col-md-6">
                            <label class="form-label d-block">Opções</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" wire:model="can_split" id="canSplitSwitch">
                                <label class="form-check-label" for="canSplitSwitch">
                                    Permitir dividir férias em períodos (até 3x)
                                </label>
                            </div>
                        </div>

                        <!-- Dias Disponíveis (Informativo) -->
                        @if($employee_id)
                            <div class="col-12">
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Dias de férias disponíveis:</strong> {{ $availableDays }} dias úteis
                                </div>
                            </div>
                        @endif

                        <!-- Data de Início -->
                        <div class="col-md-6">
                            <label class="form-label">Data de Início <span class="text-danger">*</span></label>
                            <input type="date" 
                                   wire:model.live="start_date" 
                                   class="form-control @error('start_date') is-invalid @enderror"
                                   min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Data de Término -->
                        <div class="col-md-6">
                            <label class="form-label">Data de Término <span class="text-danger">*</span></label>
                            <input type="date" 
                                   wire:model.live="end_date" 
                                   class="form-control @error('end_date') is-invalid @enderror"
                                   min="{{ $start_date ? date('Y-m-d', strtotime($start_date . '+1 day')) : date('Y-m-d', strtotime('+2 days')) }}">
                            @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Resumo de Dias (Informativo) -->
                        @if($start_date && $end_date)
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <h6 class="text-muted mb-1">Dias Corridos</h6>
                                                <h4 class="mb-0">{{ $requestedDays }}</h4>
                                            </div>
                                            <div class="col-4">
                                                <h6 class="text-muted mb-1">Dias Úteis</h6>
                                                <h4 class="mb-0 text-primary">{{ $workingDays }}</h4>
                                            </div>
                                            <div class="col-4">
                                                <h6 class="text-muted mb-1">Valor Total</h6>
                                                <h4 class="mb-0 text-success">{{ number_format($totalAmount, 2, ',', '.') }} Kz</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Funcionário Substituto -->
                        <div class="col-12">
                            <label class="form-label">Funcionário Substituto (Opcional)</label>
                            <select wire:model="replacement_employee_id" class="form-select @error('replacement_employee_id') is-invalid @enderror">
                                <option value="">Nenhum</option>
                                @foreach($employees as $employee)
                                    @if($employee->id != $employee_id)
                                        <option value="{{ $employee->id }}">
                                            {{ $employee->full_name }} ({{ $employee->employee_number }})
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            @error('replacement_employee_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Observações -->
                        <div class="col-md-8">
                            <label class="form-label">Observações</label>
                            <textarea wire:model="notes" 
                                      class="form-control @error('notes') is-invalid @enderror" 
                                      rows="3" 
                                      placeholder="Observações sobre as férias..."></textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Upload de Anexo -->
                        <div class="col-md-4">
                            <label class="form-label">Documento Anexo</label>
                            <input type="file" 
                                   wire:model="attachment" 
                                   class="form-control @error('attachment') is-invalid @enderror"
                                   accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">PDF, JPG ou PNG (máx. 2MB)</small>
                            @error('attachment')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>{{ $editMode ? 'Atualizar' : 'Criar Solicitação' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
