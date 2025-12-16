<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configurações AGT Angola</h1>
            <p class="mt-1 text-sm text-gray-500">Decreto Presidencial n.º 71/25 - Sistema de Faturação Eletrónica</p>
        </div>

        {{-- Status Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            {{-- Chaves SAFT --}}
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 {{ $hasKeys ? 'border-green-500' : 'border-red-500' }}">
                <div class="flex items-center">
                    <div class="p-2 rounded-full {{ $hasKeys ? 'bg-green-100' : 'bg-red-100' }}">
                        @if($hasKeys)
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @else
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        @endif
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">Chaves RSA</p>
                        <p class="text-xs {{ $hasKeys ? 'text-green-600' : 'text-red-600' }}">
                            {{ $hasKeys ? 'Configuradas' : 'Não configuradas' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Ambiente --}}
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 {{ $agt_environment === 'production' ? 'border-purple-500' : 'border-yellow-500' }}">
                <div class="flex items-center">
                    <div class="p-2 rounded-full {{ $agt_environment === 'production' ? 'bg-purple-100' : 'bg-yellow-100' }}">
                        <svg class="w-6 h-6 {{ $agt_environment === 'production' ? 'text-purple-600' : 'text-yellow-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">Ambiente</p>
                        <p class="text-xs {{ $agt_environment === 'production' ? 'text-purple-600' : 'text-yellow-600' }}">
                            {{ $agt_environment === 'production' ? 'Produção' : 'Sandbox (Testes)' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Séries Registadas --}}
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-blue-100">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">Séries AGT</p>
                        <p class="text-xs text-blue-600">
                            {{ $complianceReport['series']['registered'] ?? 0 }} / {{ $complianceReport['series']['total'] ?? 0 }} registadas
                        </p>
                    </div>
                </div>
            </div>

            {{-- Submissões --}}
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-indigo-500">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-indigo-100">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">Submissões</p>
                        <p class="text-xs text-indigo-600">
                            {{ $complianceReport['submissions']['validated'] ?? 0 }} validadas
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="bg-white rounded-xl shadow-sm mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button wire:click="setTab('config')" class="px-6 py-3 text-sm font-medium {{ $activeTab === 'config' ? 'text-purple-600 border-b-2 border-purple-600' : 'text-gray-500 hover:text-gray-700' }}">
                        Configurações
                    </button>
                    <button wire:click="setTab('series')" class="px-6 py-3 text-sm font-medium {{ $activeTab === 'series' ? 'text-purple-600 border-b-2 border-purple-600' : 'text-gray-500 hover:text-gray-700' }}">
                        Séries
                    </button>
                    <button wire:click="setTab('submissions')" class="px-6 py-3 text-sm font-medium {{ $activeTab === 'submissions' ? 'text-purple-600 border-b-2 border-purple-600' : 'text-gray-500 hover:text-gray-700' }}">
                        Submissões
                    </button>
                    <button wire:click="setTab('logs')" class="px-6 py-3 text-sm font-medium {{ $activeTab === 'logs' ? 'text-purple-600 border-b-2 border-purple-600' : 'text-gray-500 hover:text-gray-700' }}">
                        Logs
                    </button>
                </nav>
            </div>

            <div class="p-6">
                {{-- Tab: Configurações --}}
                @if($activeTab === 'config')
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Ambiente --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Ambiente *</label>
                            <select wire:model="agt_environment" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="sandbox">Sandbox (Testes)</option>
                                <option value="production">Produção</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Use Sandbox para testes antes de ir para produção</p>
                        </div>

                        {{-- Certificado Software --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nº Certificado Software AGT</label>
                            <input type="text" wire:model="agt_software_certificate" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                   placeholder="Ex: 1234/AGT">
                        </div>

                        {{-- Client ID --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">OAuth Client ID</label>
                            <input type="password" wire:model="agt_client_id" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                   placeholder="Fornecido pela AGT">
                        </div>

                        {{-- Client Secret --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">OAuth Client Secret</label>
                            <input type="password" wire:model="agt_client_secret" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                   placeholder="Fornecido pela AGT">
                        </div>

                        {{-- URL Base (opcional) --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">URL Base API (opcional)</label>
                            <input type="url" wire:model="agt_api_base_url" 
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                   placeholder="Deixe vazio para usar URL padrão">
                        </div>
                    </div>

                    {{-- Opções --}}
                    <div class="border-t pt-6">
                        <h3 class="text-sm font-medium text-gray-700 mb-4">Opções</h3>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="agt_auto_submit" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                <span class="ml-3 text-sm text-gray-700">Submeter documentos automaticamente à AGT</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="agt_require_validation" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                <span class="ml-3 text-sm text-gray-700">Exigir validação AGT antes de imprimir</span>
                            </label>
                        </div>
                    </div>

                    {{-- Botões --}}
                    <div class="flex justify-between border-t pt-6">
                        <button wire:click="testConnection" wire:loading.attr="disabled"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition flex items-center gap-2">
                            <svg wire:loading wire:target="testConnection" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Testar Conexão
                        </button>
                        <button wire:click="save" wire:loading.attr="disabled"
                                class="px-6 py-2 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition flex items-center gap-2">
                            <svg wire:loading wire:target="save" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Guardar Configurações
                        </button>
                    </div>

                    {{-- Resultado Teste Conexão --}}
                    @if(!empty($connectionTest))
                    <div class="mt-4 p-4 rounded-xl {{ $connectionTest['success'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                        <p class="text-sm {{ $connectionTest['success'] ? 'text-green-800' : 'text-red-800' }}">
                            @if($connectionTest['success'])
                                ✓ {{ $connectionTest['message'] ?? 'Conexão estabelecida' }}
                                <br><span class="text-xs">Ambiente: {{ $connectionTest['environment'] ?? 'N/A' }}</span>
                            @else
                                ✗ {{ $connectionTest['error'] ?? 'Falha na conexão' }}
                            @endif
                        </p>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Tab: Séries --}}
                @if($activeTab === 'series')
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Séries de Documentos</h3>
                        <button wire:click="syncSeries" wire:loading.attr="disabled"
                                class="px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition flex items-center gap-2">
                            <svg wire:loading wire:target="syncSeries" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Sincronizar com AGT
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Série</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID AGT</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ATCUD</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($series as $s)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="font-medium text-gray-900">{{ $s->prefix }} {{ $s->series_code }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ $s->document_type }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ $s->agt_series_id ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ $s->atcud_validation_code ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($s->agt_series_id)
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Registada</span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">Pendente</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        Nenhuma série encontrada. Crie séries em Faturação > Séries.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- Tab: Submissões --}}
                @if($activeTab === 'submissions')
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Submissões Pendentes</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Documento</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tentativas</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($pendingSubmissions as $sub)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900">
                                        {{ $sub['document_number'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ $sub['document_type_code'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            {{ $sub['status'] === 'validated' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $sub['status'] === 'rejected' ? 'bg-red-100 text-red-800' : '' }}
                                            {{ $sub['status'] === 'submitted' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $sub['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                            {{ ucfirst($sub['status']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ $sub['retry_count'] }}/3
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($sub['created_at'])->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($sub['status'] !== 'validated' && $sub['retry_count'] < 3)
                                        <button wire:click="retrySubmission({{ $sub['id'] }})" 
                                                class="text-blue-600 hover:text-blue-800 text-sm">
                                            Reenviar
                                        </button>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        Nenhuma submissão pendente.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- Tab: Logs --}}
                @if($activeTab === 'logs')
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Logs de Comunicação</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data/Hora</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Serviço</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Método</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tempo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resultado</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($recentLogs as $log)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($log['created_at'])->format('d/m/Y H:i:s') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $log['service'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ $log['method'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <span class="font-mono {{ $log['response_status'] >= 200 && $log['response_status'] < 300 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $log['response_status'] ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ $log['response_time'] ? round($log['response_time']) . 'ms' : '-' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($log['success'])
                                            <span class="text-green-600">✓</span>
                                        @else
                                            <span class="text-red-600" title="{{ $log['error_message'] ?? '' }}">✗</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        Nenhum log de comunicação registado.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Aviso Chaves --}}
        @if(!$hasKeys)
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mt-6">
            <div class="flex">
                <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Chaves RSA não configuradas</h3>
                    <p class="mt-1 text-sm text-yellow-700">
                        As chaves RSA são necessárias para assinar documentos. 
                        <a href="{{ route('superadmin.saft') }}" class="font-medium underline">Configure em SuperAdmin > SAFT</a>
                    </p>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
