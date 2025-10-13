<div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-gradient-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-times-circle me-2"></i>Rejeitar Solicitação de Férias
                </h5>
                <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
            </div>

            <form wire:submit.prevent="reject">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção!</strong> Você está prestes a rejeitar esta solicitação de férias. 
                        Por favor, informe o motivo da rejeição.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Motivo da Rejeição <span class="text-danger">*</span></label>
                        <textarea wire:model="rejection_reason" 
                                  class="form-control @error('rejection_reason') is-invalid @enderror" 
                                  rows="4" 
                                  placeholder="Descreva o motivo da rejeição (mínimo 10 caracteres)..."
                                  required></textarea>
                        @error('rejection_reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Este motivo será visível para o funcionário.</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">
                        <i class="fas fa-arrow-left me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times-circle me-1"></i>Confirmar Rejeição
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
