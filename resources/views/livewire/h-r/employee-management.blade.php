<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-white">
                        <i class="fas fa-users me-2"></i>Gestão de Funcionários
                    </h5>
                    <button wire:click="create" class="btn btn-light btn-sm">
                        <i class="fas fa-plus me-1"></i>Novo Funcionário
                    </button>
                </div>

                <div class="card-body">
                    <!-- Filtros -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <input type="text" wire:model.live="search" class="form-control" placeholder="🔍 Pesquisar funcionário...">
                        </div>
                        <div class="col-md-3">
                            <select wire:model.live="departmentFilter" class="form-select">
                                <option value="">Todos Departamentos</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select wire:model.live="statusFilter" class="form-select">
                                <option value="">Todos Status</option>
                                <option value="active">Ativo</option>
                                <option value="suspended">Suspenso</option>
                                <option value="terminated">Desligado</option>
                                <option value="on_leave">Em Licença</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tabela -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Nº</th>
                                    <th>Nome</th>
                                    <th>Departamento</th>
                                    <th>Cargo</th>
                                    <th>Contato</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($employees as $employee)
                                    <tr>
                                        <td><span class="badge bg-secondary">{{ $employee->employee_number }}</span></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm bg-gradient-primary rounded-circle text-white me-2">
                                                    {{ substr($employee->first_name, 0, 1) }}{{ substr($employee->last_name, 0, 1) }}
                                                </div>
                                                <div>
                                                    <strong>{{ $employee->full_name }}</strong>
                                                    @if($employee->nif)
                                                        <br><small class="text-muted">NIF: {{ $employee->nif }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $employee->department->name ?? '-' }}</td>
                                        <td>{{ $employee->position->title ?? '-' }}</td>
                                        <td>
                                            @if($employee->email)
                                                <small><i class="fas fa-envelope text-primary"></i> {{ $employee->email }}</small><br>
                                            @endif
                                            @if($employee->mobile)
                                                <small><i class="fas fa-phone text-success"></i> {{ $employee->mobile }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($employee->status === 'active')
                                                <span class="badge bg-success">Ativo</span>
                                            @elseif($employee->status === 'suspended')
                                                <span class="badge bg-warning">Suspenso</span>
                                            @elseif($employee->status === 'terminated')
                                                <span class="badge bg-danger">Desligado</span>
                                            @else
                                                <span class="badge bg-info">Em Licença</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <button wire:click="edit({{ $employee->id }})" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button wire:click="delete({{ $employee->id }})" 
                                                    onclick="confirm('Tem certeza que deseja remover este funcionário?') || event.stopImmediatePropagation()" 
                                                    class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">Nenhum funcionário encontrado.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <div class="mt-3">
                        {{ $employees->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    @if($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-gradient-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-user me-2"></i>
                            {{ $editMode ? 'Editar Funcionário' : 'Novo Funcionário' }}
                        </h5>
                        <button type="button" wire:click="closeModal" class="btn-close btn-close-white"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="save">
                            <!-- Dados Pessoais -->
                            <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-user-circle me-2"></i>Dados Pessoais</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Primeiro Nome *</label>
                                    <input type="text" wire:model="first_name" class="form-control @error('first_name') is-invalid @enderror">
                                    @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Último Nome *</label>
                                    <input type="text" wire:model="last_name" class="form-control @error('last_name') is-invalid @enderror">
                                    @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Data de Nascimento</label>
                                    <input type="date" wire:model="birth_date" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Gênero</label>
                                    <select wire:model="gender" class="form-select">
                                        <option value="">Selecione</option>
                                        <option value="M">Masculino</option>
                                        <option value="F">Feminino</option>
                                        <option value="Outro">Outro</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">NIF</label>
                                    <input type="text" wire:model="nif" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nº BI</label>
                                    <input type="text" wire:model="bi_number" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Validade BI</label>
                                    <input type="date" wire:model="bi_expiry_date" class="form-control">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Nº Segurança Social (INSS)</label>
                                    <input type="text" wire:model="social_security_number" class="form-control">
                                </div>
                            </div>

                            <!-- Contato -->
                            <h6 class="border-bottom pb-2 mb-3 mt-4"><i class="fas fa-address-book me-2"></i>Contato</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" wire:model="email" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" wire:model="phone" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Celular</label>
                                    <input type="text" wire:model="mobile" class="form-control">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Endereço</label>
                                    <textarea wire:model="address" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Cidade</label>
                                    <input type="text" wire:model="city" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Província</label>
                                    <input type="text" wire:model="province" class="form-control">
                                </div>
                            </div>

                            <!-- Dados Profissionais -->
                            <h6 class="border-bottom pb-2 mb-3 mt-4"><i class="fas fa-briefcase me-2"></i>Dados Profissionais</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Departamento</label>
                                    <select wire:model="department_id" class="form-select">
                                        <option value="">Selecione</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Cargo</label>
                                    <select wire:model="position_id" class="form-select">
                                        <option value="">Selecione</option>
                                        @foreach($positions as $pos)
                                            <option value="{{ $pos->id }}">{{ $pos->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Data de Admissão</label>
                                    <input type="date" wire:model="hire_date" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tipo de Emprego *</label>
                                    <select wire:model="employment_type" class="form-select">
                                        <option value="Contrato">Contrato</option>
                                        <option value="Freelancer">Freelancer</option>
                                        <option value="Estágio">Estágio</option>
                                        <option value="Temporário">Temporário</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status *</label>
                                    <select wire:model="status" class="form-select">
                                        <option value="active">Ativo</option>
                                        <option value="suspended">Suspenso</option>
                                        <option value="terminated">Desligado</option>
                                        <option value="on_leave">Em Licença</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Dados Bancários -->
                            <h6 class="border-bottom pb-2 mb-3 mt-4"><i class="fas fa-university me-2"></i>Dados Bancários</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Banco</label>
                                    <input type="text" wire:model="bank_name" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Conta Bancária</label>
                                    <input type="text" wire:model="bank_account" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">IBAN</label>
                                    <input type="text" wire:model="iban" class="form-control">
                                </div>
                            </div>

                            <!-- Observações -->
                            <div class="mt-4">
                                <label class="form-label">Observações</label>
                                <textarea wire:model="notes" class="form-control" rows="3"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="closeModal" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>
                        <button type="button" wire:click="save" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Salvar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
