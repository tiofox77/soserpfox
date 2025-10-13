<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            {{-- Header --}}
            <div class="card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0">
                                <i class="fas fa-cog text-primary me-2"></i>
                                Configurações do Software
                            </h3>
                            <p class="text-muted mb-0 mt-2">
                                Configure bloqueios e restrições do sistema por módulo
                            </p>
                        </div>
                        <div>
                            <span class="badge bg-primary px-3 py-2">
                                <i class="fas fa-shield-alt me-1"></i> Super Admin
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Mensagens de Feedback --}}
            @if (session()->has('message'))
                <div class="alert alert-{{ session('message-type') === 'success' ? 'success' : (session('message-type') === 'error' ? 'danger' : 'info') }} alert-dismissible fade show" role="alert">
                    <i class="fas fa-{{ session('message-type') === 'success' ? 'check-circle' : (session('message-type') === 'error' ? 'exclamation-triangle' : 'info-circle') }} me-2"></i>
                    {{ session('message') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="row">
                {{-- Sidebar de Módulos --}}
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-header bg-gradient-primary">
                            <h5 class="text-white mb-0">
                                <i class="fas fa-th-large me-2"></i>Módulos
                            </h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <a href="#" 
                               wire:click.prevent="switchModule('invoicing')" 
                               class="list-group-item list-group-item-action {{ $activeModule === 'invoicing' ? 'active' : '' }}">
                                <i class="fas fa-file-invoice me-2"></i>
                                <strong>Faturação</strong>
                                <br>
                                <small class="text-muted">Controle de documentos</small>
                            </a>
                            
                            <a href="#" 
                               class="list-group-item list-group-item-action disabled">
                                <i class="fas fa-boxes me-2"></i>
                                <strong>Inventário</strong>
                                <br>
                                <small class="text-muted">Em breve</small>
                            </a>
                            
                            <a href="#" 
                               class="list-group-item list-group-item-action disabled">
                                <i class="fas fa-calendar-check me-2"></i>
                                <strong>Eventos</strong>
                                <br>
                                <small class="text-muted">Em breve</small>
                            </a>
                            
                            <a href="#" 
                               class="list-group-item list-group-item-action disabled">
                                <i class="fas fa-users me-2"></i>
                                <strong>Utilizadores</strong>
                                <br>
                                <small class="text-muted">Em breve</small>
                            </a>
                        </div>
                    </div>

                    {{-- Info Card --}}
                    <div class="card mt-3">
                        <div class="card-body">
                            <h6 class="text-primary">
                                <i class="fas fa-info-circle me-2"></i>Informação
                            </h6>
                            <p class="text-sm text-muted mb-0">
                                As configurações aplicam-se a <strong>todos os tenants</strong> do sistema. 
                                Use com cuidado.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Área de Configurações --}}
                <div class="col-md-9">
                    @if($activeModule === 'invoicing')
                        {{-- Módulo de Faturação --}}
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-invoice text-primary me-2"></i>
                                    Configurações do Módulo de Faturação
                                </h5>
                            </div>
                            <div class="card-body">
                                <form wire:submit.prevent="saveSettings">
                                    {{-- Seção: Bloqueio de Eliminação --}}
                                    <div class="mb-4">
                                        <h6 class="text-primary border-bottom pb-2 mb-3">
                                            <i class="fas fa-lock me-2"></i>
                                            Bloqueio de Eliminação de Documentos
                                        </h6>
                                        <p class="text-muted small mb-4">
                                            Ao ativar o bloqueio, os utilizadores não poderão eliminar os documentos correspondentes. 
                                            Apenas anulações serão permitidas.
                                        </p>

                                        <div class="row g-3">
                                            {{-- Faturas de Venda --}}
                                            <div class="col-md-6">
                                                <div class="card h-100 border {{ $block_delete_sales_invoice ? 'border-danger' : 'border-light' }}">
                                                    <div class="card-body">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   id="block_delete_sales_invoice"
                                                                   wire:model="block_delete_sales_invoice">
                                                            <label class="form-check-label" for="block_delete_sales_invoice">
                                                                <strong>
                                                                    <i class="fas fa-file-invoice me-2"></i>
                                                                    Faturas de Venda
                                                                </strong>
                                                            </label>
                                                        </div>
                                                        <small class="text-muted d-block mt-2">
                                                            Bloquear eliminação de faturas de venda (Sales Invoices)
                                                        </small>
                                                        @if($block_delete_sales_invoice)
                                                            <span class="badge bg-danger mt-2">
                                                                <i class="fas fa-lock me-1"></i>Bloqueado
                                                            </span>
                                                        @else
                                                            <span class="badge bg-success mt-2">
                                                                <i class="fas fa-unlock me-1"></i>Permitido
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Proformas --}}
                                            <div class="col-md-6">
                                                <div class="card h-100 border {{ $block_delete_proforma ? 'border-danger' : 'border-light' }}">
                                                    <div class="card-body">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   id="block_delete_proforma"
                                                                   wire:model="block_delete_proforma">
                                                            <label class="form-check-label" for="block_delete_proforma">
                                                                <strong>
                                                                    <i class="fas fa-file-alt me-2"></i>
                                                                    Proformas
                                                                </strong>
                                                            </label>
                                                        </div>
                                                        <small class="text-muted d-block mt-2">
                                                            Bloquear eliminação de proformas de venda
                                                        </small>
                                                        @if($block_delete_proforma)
                                                            <span class="badge bg-danger mt-2">
                                                                <i class="fas fa-lock me-1"></i>Bloqueado
                                                            </span>
                                                        @else
                                                            <span class="badge bg-success mt-2">
                                                                <i class="fas fa-unlock me-1"></i>Permitido
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Recibos --}}
                                            <div class="col-md-6">
                                                <div class="card h-100 border {{ $block_delete_receipt ? 'border-danger' : 'border-light' }}">
                                                    <div class="card-body">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   id="block_delete_receipt"
                                                                   wire:model="block_delete_receipt">
                                                            <label class="form-check-label" for="block_delete_receipt">
                                                                <strong>
                                                                    <i class="fas fa-receipt me-2"></i>
                                                                    Recibos
                                                                </strong>
                                                            </label>
                                                        </div>
                                                        <small class="text-muted d-block mt-2">
                                                            Bloquear eliminação de recibos de pagamento
                                                        </small>
                                                        @if($block_delete_receipt)
                                                            <span class="badge bg-danger mt-2">
                                                                <i class="fas fa-lock me-1"></i>Bloqueado
                                                            </span>
                                                        @else
                                                            <span class="badge bg-success mt-2">
                                                                <i class="fas fa-unlock me-1"></i>Permitido
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Notas de Crédito --}}
                                            <div class="col-md-6">
                                                <div class="card h-100 border {{ $block_delete_credit_note ? 'border-danger' : 'border-light' }}">
                                                    <div class="card-body">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   id="block_delete_credit_note"
                                                                   wire:model="block_delete_credit_note">
                                                            <label class="form-check-label" for="block_delete_credit_note">
                                                                <strong>
                                                                    <i class="fas fa-file-invoice-dollar me-2"></i>
                                                                    Notas de Crédito
                                                                </strong>
                                                            </label>
                                                        </div>
                                                        <small class="text-muted d-block mt-2">
                                                            Bloquear eliminação de notas de crédito
                                                        </small>
                                                        @if($block_delete_credit_note)
                                                            <span class="badge bg-danger mt-2">
                                                                <i class="fas fa-lock me-1"></i>Bloqueado
                                                            </span>
                                                        @else
                                                            <span class="badge bg-success mt-2">
                                                                <i class="fas fa-unlock me-1"></i>Permitido
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Faturas Recibo --}}
                                            <div class="col-md-6">
                                                <div class="card h-100 border {{ $block_delete_invoice_receipt ? 'border-danger' : 'border-light' }}">
                                                    <div class="card-body">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   id="block_delete_invoice_receipt"
                                                                   wire:model="block_delete_invoice_receipt">
                                                            <label class="form-check-label" for="block_delete_invoice_receipt">
                                                                <strong>
                                                                    <i class="fas fa-file-contract me-2"></i>
                                                                    Faturas Recibo
                                                                </strong>
                                                            </label>
                                                        </div>
                                                        <small class="text-muted d-block mt-2">
                                                            Bloquear eliminação de faturas recibo
                                                        </small>
                                                        @if($block_delete_invoice_receipt)
                                                            <span class="badge bg-danger mt-2">
                                                                <i class="fas fa-lock me-1"></i>Bloqueado
                                                            </span>
                                                        @else
                                                            <span class="badge bg-success mt-2">
                                                                <i class="fas fa-unlock me-1"></i>Permitido
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Faturas POS --}}
                                            <div class="col-md-6">
                                                <div class="card h-100 border {{ $block_delete_pos_invoice ? 'border-danger' : 'border-light' }}">
                                                    <div class="card-body">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   id="block_delete_pos_invoice"
                                                                   wire:model="block_delete_pos_invoice">
                                                            <label class="form-check-label" for="block_delete_pos_invoice">
                                                                <strong>
                                                                    <i class="fas fa-cash-register me-2"></i>
                                                                    Faturas POS
                                                                </strong>
                                                            </label>
                                                        </div>
                                                        <small class="text-muted d-block mt-2">
                                                            Bloquear eliminação de faturas POS (Ponto de Venda)
                                                        </small>
                                                        @if($block_delete_pos_invoice)
                                                            <span class="badge bg-danger mt-2">
                                                                <i class="fas fa-lock me-1"></i>Bloqueado
                                                            </span>
                                                        @else
                                                            <span class="badge bg-success mt-2">
                                                                <i class="fas fa-unlock me-1"></i>Permitido
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Avisos --}}
                                    <div class="alert alert-warning" role="alert">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Atenção:</strong> Estas configurações afetam todos os tenants do sistema. 
                                        Documentos bloqueados só podem ser anulados, não eliminados.
                                    </div>

                                    {{-- Botões de Ação --}}
                                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                        <button type="button" 
                                                wire:click="resetSettings" 
                                                class="btn btn-outline-secondary">
                                            <i class="fas fa-undo me-2"></i>Resetar
                                        </button>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Salvar Configurações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
