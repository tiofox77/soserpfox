<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Configurações WhatsApp</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">WhatsApp Notificações</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri-check-line me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ri-error-warning-line me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form wire:submit="save">
        <div class="row">
            {{-- Configurações Twilio --}}
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-settings-3-line me-2"></i>Credenciais Twilio
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Account SID</label>
                            <input type="text" class="form-control" wire:model="twilio_account_sid" 
                                   placeholder="AC...">
                            @error('twilio_account_sid') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Auth Token</label>
                            <input type="password" class="form-control" wire:model="twilio_auth_token" 
                                   placeholder="Auth Token">
                            @error('twilio_auth_token') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Número WhatsApp (From)</label>
                            <input type="text" class="form-control" wire:model="whatsapp_from_number" 
                                   placeholder="+15558740135">
                            <small class="text-muted">Exemplo: +15558740135 ou whatsapp:+15558740135</small>
                            @error('whatsapp_from_number') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">WhatsApp Business Account ID</label>
                            <input type="text" class="form-control" wire:model="whatsapp_business_account_id" 
                                   placeholder="2404220280010457">
                            @error('whatsapp_business_account_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" wire:model="is_enabled" id="isEnabled">
                                <label class="form-check-label" for="isEnabled">Ativar WhatsApp Notificações</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" wire:model="is_sandbox" id="isSandbox">
                                <label class="form-check-label" for="isSandbox">Modo Sandbox</label>
                            </div>
                            <small class="text-muted">Ative se estiver usando Twilio Sandbox</small>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" wire:click="testConnection" class="btn btn-info">
                                <i class="ri-radio-button-line me-1"></i>Testar Conexão
                            </button>
                            <button type="button" wire:click="fetchTemplates" class="btn btn-secondary">
                                <i class="ri-refresh-line me-1"></i>Buscar Templates
                            </button>
                        </div>

                        @if($connectionStatus)
                            <div class="alert alert-{{ $connectionStatus['success'] ? 'success' : 'danger' }} mt-3">
                                {{ $connectionStatus['message'] }}
                                @if(isset($connectionStatus['account_name']))
                                    <br><strong>Conta:</strong> {{ $connectionStatus['account_name'] }}
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Templates --}}
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-file-list-3-line me-2"></i>Templates Configurados
                        </h5>
                    </div>
                    <div class="card-body">
                        @if(count($templates) > 0)
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>SID</th>
                                            <th width="50"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($templates as $index => $template)
                                            <tr>
                                                <td>{{ $template['name'] }}</td>
                                                <td><small class="text-muted">{{ $template['sid'] }}</small></td>
                                                <td>
                                                    <button type="button" wire:click="removeTemplate({{ $index }})" 
                                                            class="btn btn-sm btn-danger">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted">Nenhum template configurado. Clique em "Buscar Templates" para importar.</p>
                        @endif

                        @if(count($availableTemplates) > 0)
                            <hr>
                            <h6 class="mb-3">Templates Disponíveis no Twilio:</h6>
                            <div class="list-group">
                                @foreach($availableTemplates as $template)
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ $template['name'] }}</strong>
                                            <br><small class="text-muted">{{ $template['sid'] }}</small>
                                        </div>
                                        <button type="button" wire:click="addTemplate({{ json_encode($template) }})" 
                                                class="btn btn-sm btn-primary">
                                            <i class="ri-add-line"></i> Adicionar
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Notificações Ativas --}}
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-notification-3-line me-2"></i>Tipos de Notificação
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           wire:model="notification_settings.salary_advance_approved" 
                                           id="notifSalaryAdvApproved">
                                    <label class="form-check-label" for="notifSalaryAdvApproved">
                                        Adiantamento Salarial Aprovado
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           wire:model="notification_settings.salary_advance_rejected" 
                                           id="notifSalaryAdvRejected">
                                    <label class="form-check-label" for="notifSalaryAdvRejected">
                                        Adiantamento Salarial Rejeitado
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           wire:model="notification_settings.vacation_approved" 
                                           id="notifVacationApproved">
                                    <label class="form-check-label" for="notifVacationApproved">
                                        Férias Aprovadas
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           wire:model="notification_settings.vacation_rejected" 
                                           id="notifVacationRejected">
                                    <label class="form-check-label" for="notifVacationRejected">
                                        Férias Rejeitadas
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           wire:model="notification_settings.payslip_ready" 
                                           id="notifPayslipReady">
                                    <label class="form-check-label" for="notifPayslipReady">
                                        Recibo de Pagamento Disponível
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" 
                                           wire:model="notification_settings.employee_created" 
                                           id="notifEmployeeCreated">
                                    <label class="form-check-label" for="notifEmployeeCreated">
                                        Funcionário Criado
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Teste de Envio --}}
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="ri-send-plane-line me-2"></i>Enviar Mensagem de Teste
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Número de Teste</label>
                            <input type="text" class="form-control" wire:model="testNumber" 
                                   placeholder="+244939729902">
                            @error('testNumber') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mensagem</label>
                            <textarea class="form-control" wire:model="testMessage" rows="3" 
                                      placeholder="Teste de mensagem WhatsApp..."></textarea>
                            @error('testMessage') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <button type="button" wire:click="sendTestMessage" class="btn btn-success">
                            <i class="ri-whatsapp-line me-1"></i>Enviar Teste
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Botões de Ação --}}
        <div class="row">
            <div class="col-12">
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i>Salvar Configurações
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
