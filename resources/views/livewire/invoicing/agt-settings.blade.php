<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header com Gradiente --}}
        <div class="mb-6 bg-gradient-to-r from-orange-500 to-red-500 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center">
                        <i class="fas fa-file-signature mr-3"></i>
                        Configurações AGT Angola
                    </h1>
                    <p class="mt-1 text-orange-200 text-sm">Decreto Presidencial n.º 71/25 — Sistema de Faturação Eletrónica</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-4 py-2 rounded-xl text-sm font-bold backdrop-blur-sm
                        {{ $agt_environment === 'production' ? 'bg-red-700/60' : 'bg-yellow-500/40' }}">
                        <i class="fas fa-{{ $agt_environment === 'production' ? 'shield-alt' : 'flask' }} mr-1"></i>
                        {{ $agt_environment === 'production' ? 'Produção' : 'Sandbox' }}
                    </span>
                    <button wire:click="refreshReport" wire:loading.attr="disabled" wire:target="refreshReport"
                            class="px-3 py-2 bg-white/20 rounded-xl hover:bg-white/30 transition text-sm font-semibold">
                        <span wire:loading.remove wire:target="refreshReport"><i class="fas fa-sync-alt"></i></span>
                        <span wire:loading wire:target="refreshReport"><i class="fas fa-spinner fa-spin"></i></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Aviso Admin sem Tenant --}}
        @if(!$hasTenant)
        <div class="mb-6 bg-blue-50 border border-blue-200 rounded-2xl p-5">
            <div class="flex items-start">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0 mr-4">
                    <i class="fas fa-building text-blue-500"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-blue-800">Nenhuma empresa selecionada</h3>
                    <p class="mt-1 text-sm text-blue-700 leading-relaxed">
                        Para configurar as opções AGT, selecione uma empresa no menu de troca de empresas (canto superior).
                        As chaves RSA são configurações globais e podem ser geridas em
                        <a href="{{ route('superadmin.saft') }}" class="font-bold underline hover:text-blue-900">SuperAdmin → SAFT</a>.
                    </p>
                </div>
            </div>
        </div>
        @endif

        {{-- Status Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            {{-- Chaves SAFT --}}
            <div class="bg-white rounded-2xl shadow-lg p-5 border border-{{ $hasKeys ? 'green' : 'red' }}-100">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $hasKeys ? 'bg-green-100' : 'bg-red-100' }}">
                        <i class="fas fa-key {{ $hasKeys ? 'text-green-600' : 'text-red-500' }}"></i>
                    </div>
                    @if($hasKeys)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">OK</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700">!</span>
                    @endif
                </div>
                <p class="text-xs text-gray-500">Chaves RSA</p>
                <p class="text-sm font-bold {{ $hasKeys ? 'text-green-700' : 'text-red-600' }}">
                    {{ $hasKeys ? 'Configuradas' : 'Não configuradas' }}
                </p>
            </div>

            {{-- Ambiente --}}
            <div class="bg-white rounded-2xl shadow-lg p-5 border border-{{ $agt_environment === 'production' ? 'purple' : 'yellow' }}-100">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $agt_environment === 'production' ? 'bg-purple-100' : 'bg-yellow-100' }}">
                        <i class="fas fa-server {{ $agt_environment === 'production' ? 'text-purple-600' : 'text-yellow-600' }}"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500">Ambiente</p>
                <p class="text-sm font-bold {{ $agt_environment === 'production' ? 'text-purple-700' : 'text-yellow-700' }}">
                    {{ $agt_environment === 'production' ? 'Produção' : 'Sandbox (Testes)' }}
                </p>
            </div>

            {{-- Séries Registadas --}}
            <div class="bg-white rounded-2xl shadow-lg p-5 border border-blue-100">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-hashtag text-blue-600"></i>
                    </div>
                    <span class="text-lg font-black text-blue-600">{{ $complianceReport['series']['registered'] ?? 0 }}</span>
                </div>
                <p class="text-xs text-gray-500">Séries AGT</p>
                <p class="text-sm font-bold text-blue-700">
                    {{ $complianceReport['series']['registered'] ?? 0 }} / {{ $complianceReport['series']['total'] ?? 0 }} registadas
                </p>
            </div>

            {{-- Submissões --}}
            <div class="bg-white rounded-2xl shadow-lg p-5 border border-indigo-100">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                        <i class="fas fa-cloud-upload-alt text-indigo-600"></i>
                    </div>
                    <span class="text-lg font-black text-indigo-600">{{ $complianceReport['submissions']['validated'] ?? 0 }}</span>
                </div>
                <p class="text-xs text-gray-500">Submissões</p>
                <p class="text-sm font-bold text-indigo-700">
                    {{ $complianceReport['submissions']['validated'] ?? 0 }} validadas
                </p>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                <nav class="flex -mb-px overflow-x-auto">
                    <button wire:click="setTab('config')"
                            class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition whitespace-nowrap
                            {{ $activeTab === 'config' ? 'text-orange-600 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300' }}">
                        <i class="fas fa-cog text-xs"></i> Configurações
                    </button>
                    <button wire:click="setTab('series')"
                            class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition whitespace-nowrap
                            {{ $activeTab === 'series' ? 'text-orange-600 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300' }}">
                        <i class="fas fa-list-ol text-xs"></i> Séries
                    </button>
                    <button wire:click="setTab('submissions')"
                            class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition whitespace-nowrap
                            {{ $activeTab === 'submissions' ? 'text-orange-600 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300' }}">
                        <i class="fas fa-cloud-upload-alt text-xs"></i> Submissões
                        @if(count($pendingSubmissions) > 0)
                            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700">{{ count($pendingSubmissions) }}</span>
                        @endif
                    </button>
                    <button wire:click="setTab('logs')"
                            class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition whitespace-nowrap
                            {{ $activeTab === 'logs' ? 'text-orange-600 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300' }}">
                        <i class="fas fa-history text-xs"></i> Logs
                    </button>
                </nav>
            </div>

            <div class="p-6">

                {{-- ═══════════════════════════════════ --}}
                {{-- Tab: CONFIGURAÇÕES --}}
                {{-- ═══════════════════════════════════ --}}
                @if($activeTab === 'config')
                <div class="space-y-6">
                    {{-- Secção: Credenciais API --}}
                    <div>
                        <h3 class="text-gray-800 font-bold text-sm flex items-center mb-1">
                            <i class="fas fa-plug mr-2 text-orange-500"></i>
                            Credenciais da API AGT
                        </h3>
                        <p class="text-gray-400 text-xs mb-5">Dados fornecidos pela Administração Geral Tributária de Angola</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {{-- Ambiente --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <i class="fas fa-server mr-1 text-gray-400"></i> Ambiente *
                                </label>
                                <select wire:model="agt_environment"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-orange-400 focus:border-transparent bg-gray-50 transition">
                                    <option value="sandbox">Sandbox (Testes)</option>
                                    <option value="production">Produção</option>
                                </select>
                                <p class="mt-1 text-[10px] text-gray-400">Use Sandbox para testes antes de ir para produção</p>
                            </div>

                            {{-- Certificado Software --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <i class="fas fa-certificate mr-1 text-gray-400"></i> Nº Certificado Software AGT
                                </label>
                                <input type="text" wire:model="agt_software_certificate"
                                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-orange-400 focus:border-transparent bg-gray-50 transition"
                                       placeholder="Ex: 1234/AGT">
                            </div>

                            {{-- Client ID --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <i class="fas fa-id-badge mr-1 text-gray-400"></i> OAuth Client ID
                                </label>
                                <div class="relative">
                                    <input type="password" wire:model="agt_client_id"
                                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-orange-400 focus:border-transparent bg-gray-50 transition pr-10"
                                           placeholder="Fornecido pela AGT">
                                    <i class="fas fa-lock absolute right-3 top-3 text-gray-300 text-xs"></i>
                                </div>
                            </div>

                            {{-- Client Secret --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <i class="fas fa-user-secret mr-1 text-gray-400"></i> OAuth Client Secret
                                </label>
                                <div class="relative">
                                    <input type="password" wire:model="agt_client_secret"
                                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-orange-400 focus:border-transparent bg-gray-50 transition pr-10"
                                           placeholder="Fornecido pela AGT">
                                    <i class="fas fa-lock absolute right-3 top-3 text-gray-300 text-xs"></i>
                                </div>
                            </div>

                            {{-- URL Base (opcional) --}}
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <i class="fas fa-link mr-1 text-gray-400"></i> URL Base API (opcional)
                                </label>
                                <input type="url" wire:model="agt_api_base_url"
                                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-orange-400 focus:border-transparent bg-gray-50 transition"
                                       placeholder="Deixe vazio para usar URL padrão da AGT">
                            </div>
                        </div>
                    </div>

                    {{-- Secção: Opções --}}
                    <div class="border-t border-gray-100 pt-6">
                        <h3 class="text-gray-800 font-bold text-sm flex items-center mb-4">
                            <i class="fas fa-sliders-h mr-2 text-orange-500"></i>
                            Opções de Submissão
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Auto Submit --}}
                            <div class="border rounded-xl p-4 transition hover:shadow-md
                                {{ $agt_auto_submit ? 'border-green-200 bg-green-50/50' : 'border-gray-200 bg-white' }}">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0
                                            {{ $agt_auto_submit ? 'bg-green-100' : 'bg-gray-100' }}">
                                            <i class="fas fa-paper-plane {{ $agt_auto_submit ? 'text-green-500' : 'text-gray-400' }}"></i>
                                        </div>
                                        <div class="ml-3">
                                            <span class="font-semibold text-sm text-gray-800 block">Submissão Automática</span>
                                            <span class="text-[10px] text-gray-400">Enviar documentos automaticamente à AGT</span>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model="agt_auto_submit" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                                    </label>
                                </div>
                            </div>

                            {{-- Require Validation --}}
                            <div class="border rounded-xl p-4 transition hover:shadow-md
                                {{ $agt_require_validation ? 'border-blue-200 bg-blue-50/50' : 'border-gray-200 bg-white' }}">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0
                                            {{ $agt_require_validation ? 'bg-blue-100' : 'bg-gray-100' }}">
                                            <i class="fas fa-check-double {{ $agt_require_validation ? 'text-blue-500' : 'text-gray-400' }}"></i>
                                        </div>
                                        <div class="ml-3">
                                            <span class="font-semibold text-sm text-gray-800 block">Validação Obrigatória</span>
                                            <span class="text-[10px] text-gray-400">Exigir validação AGT antes de imprimir</span>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model="agt_require_validation" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-500"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Resultado Teste Conexão --}}
                    @if(!empty($connectionTest))
                    <div class="rounded-2xl p-4 {{ $connectionTest['success'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                        <div class="flex items-center">
                            @if($connectionTest['success'])
                                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center mr-4 shrink-0">
                                    <i class="fas fa-check-circle text-green-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-green-800">{{ $connectionTest['message'] ?? 'Conexão estabelecida com sucesso' }}</p>
                                    <p class="text-xs text-green-600">Ambiente: {{ $connectionTest['environment'] ?? 'N/A' }}</p>
                                </div>
                            @else
                                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center mr-4 shrink-0">
                                    <i class="fas fa-times-circle text-red-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-red-800">Falha na conexão</p>
                                    <p class="text-xs text-red-600">{{ $connectionTest['error'] ?? 'Verifique as credenciais' }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- Botões --}}
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <button wire:click="testConnection" wire:loading.attr="disabled" wire:target="testConnection"
                                class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition flex items-center gap-2 disabled:opacity-50">
                            <span wire:loading.remove wire:target="testConnection"><i class="fas fa-plug mr-1"></i> Testar Conexão</span>
                            <span wire:loading wire:target="testConnection"><i class="fas fa-spinner fa-spin mr-1"></i> A testar...</span>
                        </button>
                        <button wire:click="save" wire:loading.attr="disabled" wire:target="save"
                                class="px-6 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-orange-500 to-red-500 rounded-xl hover:from-orange-600 hover:to-red-600 transition shadow-lg shadow-orange-200 flex items-center gap-2 disabled:opacity-50">
                            <span wire:loading.remove wire:target="save"><i class="fas fa-save mr-1"></i> Guardar Configurações</span>
                            <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin mr-1"></i> A guardar...</span>
                        </button>
                    </div>
                </div>
                @endif

                {{-- ═══════════════════════════════════ --}}
                {{-- Tab: SÉRIES --}}
                {{-- ═══════════════════════════════════ --}}
                @if($activeTab === 'series')
                <div>
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-gray-800 font-bold text-sm flex items-center">
                                <i class="fas fa-list-ol mr-2 text-blue-500"></i>
                                Séries de Documentos
                            </h3>
                            <p class="text-gray-400 text-xs mt-0.5">Séries registadas na AGT para emissão de documentos</p>
                        </div>
                        <button wire:click="syncSeries" wire:loading.attr="disabled" wire:target="syncSeries"
                                class="px-4 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-xl hover:from-blue-600 hover:to-indigo-600 transition text-sm font-bold shadow-lg shadow-blue-200 flex items-center gap-2 disabled:opacity-50">
                            <span wire:loading.remove wire:target="syncSeries"><i class="fas fa-sync-alt mr-1"></i> Sincronizar com AGT</span>
                            <span wire:loading wire:target="syncSeries"><i class="fas fa-spinner fa-spin mr-1"></i> A sincronizar...</span>
                        </button>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                    <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Série</th>
                                    <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Tipo Documento</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">ID AGT</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">ATCUD</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($series as $s)
                                <tr class="hover:bg-orange-50/40 transition">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center mr-3 shrink-0">
                                                <i class="fas fa-hashtag text-blue-500 text-xs"></i>
                                            </div>
                                            <span class="font-bold text-gray-800">{{ $s->prefix }} {{ $s->series_code }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-500">{{ $s->document_type }}</td>
                                    <td class="px-5 py-3.5 text-center">
                                        @if($s->agt_series_id)
                                            <span class="font-mono text-xs text-gray-700 bg-gray-100 px-2 py-1 rounded-lg">{{ $s->agt_series_id }}</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        @if($s->atcud_validation_code)
                                            <span class="font-mono text-xs text-gray-700 bg-gray-100 px-2 py-1 rounded-lg">{{ $s->atcud_validation_code }}</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        @if($s->agt_series_id)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-green-100 text-green-700">
                                                <i class="fas fa-check mr-1"></i> Registada
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-700">
                                                <i class="fas fa-clock mr-1"></i> Pendente
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center">
                                        <div class="flex flex-col items-center text-gray-400">
                                            <i class="fas fa-inbox text-3xl mb-2"></i>
                                            <p class="text-sm font-medium">Nenhuma série encontrada</p>
                                            <p class="text-xs">Crie séries em <strong>Faturação → Séries de Documentos</strong></p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- ═══════════════════════════════════ --}}
                {{-- Tab: SUBMISSÕES --}}
                {{-- ═══════════════════════════════════ --}}
                @if($activeTab === 'submissions')
                <div>
                    <div class="mb-5">
                        <h3 class="text-gray-800 font-bold text-sm flex items-center">
                            <i class="fas fa-cloud-upload-alt mr-2 text-indigo-500"></i>
                            Submissões Pendentes
                        </h3>
                        <p class="text-gray-400 text-xs mt-0.5">Documentos enviados ou a enviar à AGT</p>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                    <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Documento</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Tipo</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Estado</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Tentativas</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Data</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($pendingSubmissions as $sub)
                                <tr class="hover:bg-orange-50/40 transition">
                                    <td class="px-5 py-3.5">
                                        <span class="font-bold text-gray-800">{{ $sub['document_number'] }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="font-mono text-xs text-gray-600 bg-gray-100 px-2 py-1 rounded-lg">{{ $sub['document_type_code'] }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        @php
                                            $statusColors = [
                                                'validated' => 'bg-green-100 text-green-700',
                                                'rejected' => 'bg-red-100 text-red-700',
                                                'submitted' => 'bg-blue-100 text-blue-700',
                                                'pending' => 'bg-yellow-100 text-yellow-700',
                                            ];
                                            $statusIcons = [
                                                'validated' => 'fa-check-circle',
                                                'rejected' => 'fa-times-circle',
                                                'submitted' => 'fa-paper-plane',
                                                'pending' => 'fa-clock',
                                            ];
                                            $color = $statusColors[$sub['status']] ?? 'bg-gray-100 text-gray-700';
                                            $icon = $statusIcons[$sub['status']] ?? 'fa-question-circle';
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold {{ $color }}">
                                            <i class="fas {{ $icon }} mr-1"></i> {{ ucfirst($sub['status']) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="text-xs font-bold text-gray-600">{{ $sub['retry_count'] }}/3</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($sub['created_at'])->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        @if($sub['status'] !== 'validated' && $sub['retry_count'] < 3)
                                        <button wire:click="retrySubmission({{ $sub['id'] }})"
                                                wire:loading.attr="disabled" wire:target="retrySubmission({{ $sub['id'] }})"
                                                class="px-3 py-1.5 text-xs font-bold text-orange-600 bg-orange-50 rounded-lg hover:bg-orange-100 transition">
                                            <span wire:loading.remove wire:target="retrySubmission({{ $sub['id'] }})"><i class="fas fa-redo mr-1"></i> Reenviar</span>
                                            <span wire:loading wire:target="retrySubmission({{ $sub['id'] }})"><i class="fas fa-spinner fa-spin"></i></span>
                                        </button>
                                        @else
                                            <span class="text-gray-300 text-xs">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center">
                                        <div class="flex flex-col items-center text-gray-400">
                                            <i class="fas fa-check-circle text-3xl mb-2 text-green-300"></i>
                                            <p class="text-sm font-medium">Nenhuma submissão pendente</p>
                                            <p class="text-xs">Todos os documentos foram processados</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- ═══════════════════════════════════ --}}
                {{-- Tab: LOGS --}}
                {{-- ═══════════════════════════════════ --}}
                @if($activeTab === 'logs')
                <div>
                    <div class="mb-5">
                        <h3 class="text-gray-800 font-bold text-sm flex items-center">
                            <i class="fas fa-history mr-2 text-purple-500"></i>
                            Logs de Comunicação
                        </h3>
                        <p class="text-gray-400 text-xs mt-0.5">Últimas 20 comunicações com a API AGT</p>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                    <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Data/Hora</th>
                                    <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Serviço</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Método</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Status</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Tempo</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">Resultado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($recentLogs as $log)
                                <tr class="hover:bg-orange-50/40 transition">
                                    <td class="px-5 py-3.5 text-xs text-gray-500 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($log['created_at'])->format('d/m/Y H:i:s') }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <span class="font-bold text-gray-800 text-xs">{{ $log['service'] }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                        <span class="font-mono text-xs text-gray-600 bg-gray-100 px-2 py-1 rounded-lg">{{ $log['method'] }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                        @php
                                            $httpOk = isset($log['response_status']) && $log['response_status'] >= 200 && $log['response_status'] < 300;
                                        @endphp
                                        <span class="font-mono text-xs font-bold px-2 py-1 rounded-lg {{ $httpOk ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $log['response_status'] ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center whitespace-nowrap text-xs text-gray-500">
                                        {{ $log['response_time'] ? round($log['response_time']) . 'ms' : '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        @if($log['success'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">
                                                <i class="fas fa-check mr-1"></i> OK
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700" title="{{ $log['error_message'] ?? '' }}">
                                                <i class="fas fa-times mr-1"></i> Erro
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center">
                                        <div class="flex flex-col items-center text-gray-400">
                                            <i class="fas fa-list-alt text-3xl mb-2"></i>
                                            <p class="text-sm font-medium">Nenhum log de comunicação</p>
                                            <p class="text-xs">Os logs aparecerão após interação com a API AGT</p>
                                        </div>
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

        {{-- Aviso Chaves RSA --}}
        @if(!$hasKeys)
        <div class="mt-6 bg-amber-50 border border-amber-200 rounded-2xl p-5">
            <div class="flex items-start">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0 mr-4">
                    <i class="fas fa-exclamation-triangle text-amber-500"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-amber-800">Chaves RSA não configuradas</h3>
                    <p class="mt-1 text-sm text-amber-700 leading-relaxed">
                        As chaves RSA são necessárias para assinar documentos SAFT-AO.
                        <a href="{{ route('superadmin.saft') }}" class="font-bold underline hover:text-amber-900">Configure em SuperAdmin → SAFT</a>
                    </p>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
