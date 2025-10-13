<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-gradient-info">
                    <h5 class="mb-0 text-white">
                        <i class="fas fa-sitemap me-2"></i>Departamentos e Cargos
                    </h5>
                </div>

                <div class="card-body">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-4" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'departments' ? 'active' : '' }}" 
                               wire:click="$set('activeTab', 'departments')" href="#">
                                <i class="fas fa-building me-1"></i>Departamentos ({{ $departments->count() }})
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'positions' ? 'active' : '' }}" 
                               wire:click="$set('activeTab', 'positions')" href="#">
                                <i class="fas fa-briefcase me-1"></i>Cargos ({{ $positions->count() }})
                            </a>
                        </li>
                    </ul>

                    <!-- Departamentos Tab -->
                    @if($activeTab === 'departments')
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Lista de Departamentos</h6>
                            <button wire:click="createDept" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>Novo Departamento
                            </button>
                        </div>

                        <div class="row">
                            @forelse($departments as $dept)
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 {{ $dept->is_active ? 'border-success' : 'border-secondary' }}">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0">{{ $dept->name }}</h6>
                                                @if($dept->is_active)
                                                    <span class="badge bg-success">Ativo</span>
                                                @else
                                                    <span class="badge bg-secondary">Inativo</span>
                                                @endif
                                            </div>
                                            
                                            @if($dept->code)
                                                <small class="text-muted d-block mb-2">Código: {{ $dept->code }}</small>
                                            @endif
                                            
                                            @if($dept->description)
                                                <p class="text-muted small mb-2">{{ $dept->description }}</p>
                                            @endif
                                            
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-users me-1"></i>{{ $dept->employees_count }} funcionários
                                                </small>
                                                <div>
                                                    <button wire:click="editDept({{ $dept->id }})" class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button wire:click="deleteDept({{ $dept->id }})" 
                                                            onclick="confirm('Remover departamento?') || event.stopImmediatePropagation()"
                                                            class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="text-center py-5">
                                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Nenhum departamento cadastrado.</p>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    @endif

                    <!-- Cargos Tab -->
                    @if($activeTab === 'positions')
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Lista de Cargos</h6>
                            <button wire:click="createPos" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>Novo Cargo
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Cargo</th>
                                        <th>Código</th>
                                        <th>Departamento</th>
                                        <th>Funcionários</th>
                                        <th>Status</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($positions as $pos)
                                        <tr>
                                            <td>
                                                <strong>{{ $pos->title }}</strong>
                                                @if($pos->description)
                                                    <br><small class="text-muted">{{ $pos->description }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $pos->code ?? '-' }}</td>
                                            <td>{{ $pos->department->name ?? '-' }}</td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $pos->employees_count }}</span>
                                            </td>
                                            <td>
                                                @if($pos->is_active)
                                                    <span class="badge bg-success">Ativo</span>
                                                @else
                                                    <span class="badge bg-secondary">Inativo</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <button wire:click="editPos({{ $pos->id }})" class="btn btn-sm btn-info">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button wire:click="deletePos({{ $pos->id }})" 
                                                        onclick="confirm('Remover cargo?') || event.stopImmediatePropagation()"
                                                        class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">Nenhum cargo cadastrado.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Departamento -->
    @if($showDeptModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-gradient-info text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-building me-2"></i>
                            {{ $editDeptMode ? 'Editar Departamento' : 'Novo Departamento' }}
                        </h5>
                        <button type="button" wire:click="$set('showDeptModal', false)" class="btn-close btn-close-white"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" wire:model="dept_name" class="form-control @error('dept_name') is-invalid @enderror">
                            @error('dept_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" wire:model="dept_code" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea wire:model="dept_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" wire:model="dept_is_active" class="form-check-input" id="deptActive">
                            <label class="form-check-label" for="deptActive">Ativo</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="$set('showDeptModal', false)" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>
                        <button type="button" wire:click="saveDept" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Salvar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Cargo -->
    @if($showPosModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-gradient-info text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-briefcase me-2"></i>
                            {{ $editPosMode ? 'Editar Cargo' : 'Novo Cargo' }}
                        </h5>
                        <button type="button" wire:click="$set('showPosModal', false)" class="btn-close btn-close-white"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Título *</label>
                            <input type="text" wire:model="pos_title" class="form-control @error('pos_title') is-invalid @enderror">
                            @error('pos_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" wire:model="pos_code" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Departamento</label>
                            <select wire:model="pos_department_id" class="form-select">
                                <option value="">Selecione</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea wire:model="pos_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" wire:model="pos_is_active" class="form-check-input" id="posActive">
                            <label class="form-check-label" for="posActive">Ativo</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" wire:click="$set('showPosModal', false)" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>
                        <button type="button" wire:click="savePos" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Salvar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
