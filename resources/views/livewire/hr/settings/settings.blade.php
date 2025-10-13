<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-white">
                        <i class="fas fa-cog me-2"></i>Configurações do Módulo RH
                    </h5>
                    <div>
                        <button wire:click="resetToDefaults" 
                                class="btn btn-warning btn-sm me-2"
                                onclick="confirm('Tem certeza que deseja restaurar todas as configurações para os valores padrão?') || event.stopImmediatePropagation()">
                            <i class="fas fa-undo me-1"></i>Restaurar Padrões
                        </button>
                        <button wire:click="save" class="btn btn-light btn-sm">
                            <i class="fas fa-save me-1"></i>Salvar Alterações
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Filtro de Categoria -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Filtrar por Categoria</label>
                            <select wire:model.live="categoryFilter" class="form-select">
                                <option value="all">Todas as Categorias</option>
                                <option value="general">Geral</option>
                                <option value="payroll">Folha de Pagamento</option>
                                <option value="vacation">Férias</option>
                                <option value="overtime">Horas Extras</option>
                                <option value="leave">Licenças</option>
                            </select>
                        </div>
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <!-- Configurações por Categoria -->
                    @foreach($settings as $category => $categorySettings)
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    @if($category === 'general')
                                        <i class="fas fa-info-circle text-primary me-2"></i>Configurações Gerais
                                    @elseif($category === 'payroll')
                                        <i class="fas fa-money-bill-wave text-success me-2"></i>Folha de Pagamento
                                    @elseif($category === 'vacation')
                                        <i class="fas fa-umbrella-beach text-info me-2"></i>Férias
                                    @elseif($category === 'overtime')
                                        <i class="fas fa-clock text-warning me-2"></i>Horas Extras
                                    @elseif($category === 'leave')
                                        <i class="fas fa-calendar-times text-danger me-2"></i>Licenças
                                    @endif
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    @foreach($categorySettings as $setting)
                                        <div class="col-md-6">
                                            <div class="setting-item p-3 border rounded">
                                                <label class="form-label fw-bold">
                                                    {{ $setting->label }}
                                                    <span class="badge bg-secondary ms-2">{{ $setting->value_type_name }}</span>
                                                </label>
                                                
                                                @if($setting->description)
                                                    <p class="text-muted small mb-2">{{ $setting->description }}</p>
                                                @endif

                                                @if($setting->value_type === 'boolean')
                                                    <div class="form-check form-switch">
                                                        <input type="checkbox" 
                                                               class="form-check-input" 
                                                               wire:model="editingSettings.{{ $setting->key }}"
                                                               id="setting_{{ $setting->key }}">
                                                        <label class="form-check-label" for="setting_{{ $setting->key }}">
                                                            {{ $setting->casted_value ? 'Sim' : 'Não' }}
                                                        </label>
                                                    </div>
                                                @elseif($setting->value_type === 'integer')
                                                    <input type="number" 
                                                           class="form-control" 
                                                           wire:model="editingSettings.{{ $setting->key }}"
                                                           value="{{ $setting->value }}"
                                                           step="1">
                                                @elseif($setting->value_type === 'decimal')
                                                    <input type="number" 
                                                           class="form-control" 
                                                           wire:model="editingSettings.{{ $setting->key }}"
                                                           value="{{ $setting->value }}"
                                                           step="0.01">
                                                @elseif($setting->value_type === 'percentage')
                                                    <div class="input-group">
                                                        <input type="number" 
                                                               class="form-control" 
                                                               wire:model="editingSettings.{{ $setting->key }}"
                                                               value="{{ $setting->value }}"
                                                               step="0.1"
                                                               min="0"
                                                               max="100">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                @else
                                                    <input type="text" 
                                                           class="form-control" 
                                                           wire:model="editingSettings.{{ $setting->key }}"
                                                           value="{{ $setting->value }}">
                                                @endif

                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <strong>Valor Atual:</strong> 
                                                        @if($setting->value_type === 'boolean')
                                                            {{ $setting->casted_value ? 'Sim' : 'Não' }}
                                                        @elseif($setting->value_type === 'percentage')
                                                            {{ $setting->value }}%
                                                        @else
                                                            {{ $setting->value }}
                                                        @endif
                                                        | 
                                                        <strong>Padrão:</strong> 
                                                        @if($setting->value_type === 'boolean')
                                                            {{ $setting->default_value ? 'Sim' : 'Não' }}
                                                        @elseif($setting->value_type === 'percentage')
                                                            {{ $setting->default_value }}%
                                                        @else
                                                            {{ $setting->default_value }}
                                                        @endif
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <!-- Informação sobre Legislação Angolana -->
                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Informação sobre Legislação Angolana</h6>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Férias:</h6>
                                <ul class="mb-2">
                                    <li>22 dias úteis por ano completo de trabalho</li>
                                    <li>Subsídio de férias (14º mês): mínimo 50% do salário base</li>
                                    <li>Cálculo proporcional aos meses trabalhados</li>
                                </ul>

                                <h6>Horas Extras:</h6>
                                <ul class="mb-2">
                                    <li>Dias úteis: 50% adicional sobre valor/hora normal</li>
                                    <li>Fins de semana: 100% adicional</li>
                                    <li>Feriados: 100% adicional</li>
                                    <li>Trabalho noturno: 25% adicional</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Subsídios Obrigatórios:</h6>
                                <ul class="mb-2">
                                    <li>Subsídio de Natal (13º mês): 100% do salário base</li>
                                    <li>Subsídio de Férias (14º mês): mínimo 50% do salário</li>
                                </ul>

                                <h6>Licenças Previstas em Lei:</h6>
                                <ul class="mb-0">
                                    <li>Maternidade: 90 dias (3 meses)</li>
                                    <li>Paternidade: 3 dias</li>
                                    <li>Casamento: 10 dias</li>
                                    <li>Luto (familiar direto): 5 dias</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <div class="d-flex justify-content-end">
                        <button wire:click="save" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Salvar Todas as Alterações
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
