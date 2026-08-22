<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header com Gradiente --}}
        <div class="mb-6 bg-gradient-to-r from-orange-500 to-red-500 rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center">
                        <i class="fas fa-file-signature mr-3"></i>
                        {{ __('Configurações AGT Angola') }}
                    </h1>
                    <p class="mt-1 text-orange-200 text-sm">{{ __('Decreto Presidencial n.º 71/25 — Sistema de Faturação Eletrónica') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    {{-- Sempre o ambiente ACTIVO: é a resposta a "os meus
                         documentos estão a ir para a AGT real?". O que se
                         está a ver noutro separador não muda isto. --}}
                    <span class="px-4 py-2 rounded-xl text-sm font-bold backdrop-blur-sm
                        {{ $this->ambienteActivo() === 'production' ? 'bg-red-700/60' : 'bg-yellow-500/40' }}">
                        <i class="fas fa-{{ $this->ambienteActivo() === 'production' ? 'shield-alt' : 'flask' }} mr-1"></i>
                        A emitir em {{ $this->ambienteActivo() === 'production' ? 'Produção' : 'Homologação' }}
                    </span>
                    <button wire:click="refreshReport" wire:loading.attr="disabled" wire:target="refreshReport"
                            class="px-3 py-2 bg-white/20 rounded-xl hover:bg-white/30 transition text-sm font-semibold">
                        <span wire:loading.remove wire:target="refreshReport"><i class="fas fa-sync-alt"></i></span>
                        <span wire:loading wire:target="refreshReport"><i class="fas fa-spinner fa-spin"></i></span>
                    </button>
                </div>
            </div>
        </div>

        @if($isAdmin)
        <div class="mb-6 bg-white border border-orange-200 rounded-2xl shadow-sm p-5">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center">
                    <div class="w-11 h-11 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center mr-3 shrink-0">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-orange-600 uppercase tracking-wide">{{ __('Contexto de operação') }}</p>
                        <h3 class="text-sm font-bold text-gray-900">
                            {{ $currentTenant?->name ?? 'Seleccione uma empresa' }}
                        </h3>
                        @if($currentTenant)
                            <p class="text-xs text-gray-500">NIF: {{ $currentTenant->nif ?? 'não definido' }} · ID {{ $currentTenant->id }}</p>
                        @endif
                    </div>
                </div>
                <div class="w-full lg:w-[420px]">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('Operar e testar como empresa') }}</label>
                    <select wire:change="selectTenant($event.target.value)"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:border-orange-500 focus:ring-2 focus:ring-orange-200">
                        <option value="">{{ __('Seleccione uma empresa') }}</option>
                        @foreach($availableTenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected((int) $currentTenantId === (int) $tenant->id)>
                                {{ $tenant->name }} — {{ $tenant->nif ?? 'sem NIF' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        @elseif($currentTenant)
        <div class="mb-6 flex items-center px-4 py-3 rounded-xl bg-white border border-gray-200 text-sm text-gray-700">
            <i class="fas fa-building text-orange-500 mr-2"></i>
            {{ __('Empresa activa:') }} <strong class="ml-1">{{ $currentTenant->name }}</strong>
        </div>
        @endif

        {{-- Aviso Admin sem Tenant --}}
        @if(!$hasTenant)
        <div class="mb-6 bg-blue-50 border border-blue-200 rounded-2xl p-5">
            <div class="flex items-start">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0 mr-4">
                    <i class="fas fa-building text-blue-500"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-blue-800">{{ __('Nenhuma empresa selecionada') }}</h3>
                    <p class="mt-1 text-sm text-blue-700 leading-relaxed">
                        {{ __('Para configurar e testar as opções AGT, seleccione uma empresa no selector acima. As credenciais da API são geridas globalmente pelo produtor. Seleccione uma empresa para configurar apenas as chaves pública e privada obtidas no Portal AGT dessa empresa.') }}
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
                <p class="text-xs text-gray-500">{{ __('Chaves RSA') }}</p>
                <p class="text-sm font-bold {{ $hasKeys ? 'text-green-700' : 'text-red-600' }}">
                    {{ $hasKeys ? 'Configuradas' : 'Não configuradas' }}
                </p>
            </div>

            {{-- Ambiente activo. Os restantes cartões referem-se ao ambiente
                 que se está a ver; este não, e por isso di-lo. --}}
            @php
                $envActivo = $this->ambienteActivo();
                $ehProducao = $envActivo === 'production';
            @endphp
            <div class="bg-white rounded-2xl shadow-lg p-5 border border-{{ $ehProducao ? 'purple' : 'yellow' }}-100">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $ehProducao ? 'bg-purple-100' : 'bg-yellow-100' }}">
                        <i class="fas fa-server {{ $ehProducao ? 'text-purple-600' : 'text-yellow-600' }}"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500">{{ __('Ambiente activo') }}</p>
                <p class="text-sm font-bold {{ $ehProducao ? 'text-purple-700' : 'text-yellow-700' }}">
                    {{ $ehProducao ? 'Produção' : 'Homologação (Testes)' }}
                </p>
                @unless($this->aVerOAmbienteActivo())
                    <p class="mt-1 text-[10px] font-semibold text-orange-600">
                        <i class="fas fa-eye mr-0.5"></i> {{ __('a ver o outro ambiente') }}
                    </p>
                @endunless
            </div>

            {{-- Séries Registadas --}}
            <div class="bg-white rounded-2xl shadow-lg p-5 border border-blue-100">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-hashtag text-blue-600"></i>
                    </div>
                    <span class="text-lg font-black text-blue-600">{{ $complianceReport['series']['registered'] ?? 0 }}</span>
                </div>
                <p class="text-xs text-gray-500">{{ __('Séries AGT') }}</p>
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
                <p class="text-xs text-gray-500">{{ __('Submissões') }}</p>
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
                        <i class="fas fa-cog text-xs"></i> {{ __('Configurações') }}
                    </button>
                    <button wire:click="setTab('series')"
                            class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition whitespace-nowrap
                            {{ $activeTab === 'series' ? 'text-orange-600 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300' }}">
                        <i class="fas fa-list-ol text-xs"></i> {{ __('Séries') }}
                    </button>
                    <button wire:click="setTab('submissions')"
                            class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition whitespace-nowrap
                            {{ $activeTab === 'submissions' ? 'text-orange-600 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300' }}">
                        <i class="fas fa-cloud-upload-alt text-xs"></i> Submissões
                        @php
                            $openSubmissionCount = collect($pendingSubmissions)
                                ->whereIn('status', ['pending', 'submitted'])
                                ->count();
                        @endphp
                        @if($openSubmissionCount > 0)
                            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700">{{ $openSubmissionCount }}</span>
                        @endif
                    </button>
                    <button wire:click="setTab('logs')"
                            class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition whitespace-nowrap
                            {{ $activeTab === 'logs' ? 'text-orange-600 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300' }}">
                        <i class="fas fa-history text-xs"></i> {{ __('Logs') }}
                    </button>
                    <button wire:click="setTab('api-tools')"
                            class="flex items-center gap-2 px-6 py-4 text-sm font-semibold border-b-2 transition whitespace-nowrap
                            {{ $activeTab === 'api-tools' ? 'text-orange-600 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300' }}">
                        <i class="fas fa-tools text-xs"></i> {{ __('Ferramentas API') }}
                    </button>
                </nav>
            </div>

            <div class="p-6">

                {{-- ═══════════════════════════════════ --}}
                {{-- Tab: CONFIGURAÇÕES --}}
                {{-- ═══════════════════════════════════ --}}
                @if($activeTab === 'config')
                <div class="space-y-6">
                    {{-- Os dois ambientes, configuráveis em separado.

                         Antes havia um só seletor, que era ao mesmo tempo "o
                         que estou a ver" e "o que emite". Instalar as chaves de
                         produção obrigava a pôr já a empresa em produção, e
                         espreitar a homologação seguido de um Guardar por outro
                         motivo qualquer deitava produção abaixo em silêncio. --}}
                    <div>
                        <h3 class="text-gray-800 font-bold text-sm flex items-center mb-1">
                            <i class="fas fa-server mr-2 text-orange-500"></i>
                            {{ __('Ambientes') }}
                        </h3>
                        <p class="text-gray-400 text-xs mb-4">
                            {{ __('Configure os dois. Escolher aqui só muda o que está a ver — a empresa continua a emitir pelo ambiente activo até o activar de propósito.') }}
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($this->estadoAmbientes() as $chave => $amb)
                                @php
                                    $prod = $chave === 'production';
                                    $cor  = $prod ? 'purple' : 'yellow';
                                @endphp
                                <button type="button" wire:click="$set('ambienteVista', '{{ $chave }}')"
                                        class="text-left p-4 rounded-xl border-2 transition
                                            {{ $amb['a_ver'] ? "border-{$cor}-400 bg-{$cor}-50" : 'border-gray-200 bg-white hover:border-gray-300' }}">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-bold text-sm text-gray-800">
                                            <i class="fas fa-{{ $prod ? 'shield-alt' : 'flask' }} mr-1 text-{{ $cor }}-500"></i>
                                            {{ $amb['rotulo'] }}
                                        </span>
                                        @if($amb['activo'])
                                            <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[10px] font-bold uppercase">
                                                <i class="fas fa-broadcast-tower mr-0.5"></i> {{ __('a emitir') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="space-y-1 text-[11px]">
                                        <p class="{{ $amb['chaves'] ? 'text-green-700' : 'text-red-600' }}">
                                            <i class="fas fa-{{ $amb['chaves'] ? 'check' : 'times' }}-circle mr-1"></i>
                                            Chaves da empresa: {{ $amb['chaves'] ? 'instaladas' : 'em falta' }}
                                        </p>
                                        <p class="{{ $amb['produtor'] ? 'text-green-700' : 'text-amber-600' }}">
                                            <i class="fas fa-{{ $amb['produtor'] ? 'check' : 'exclamation' }}-circle mr-1"></i>
                                            Produtor SOS ERP: {{ $amb['produtor'] ? 'configurado' : 'incompleto' }}
                                        </p>
                                    </div>
                                    @if($amb['a_ver'])
                                        <p class="mt-2 text-[10px] font-semibold text-{{ $cor }}-700 uppercase tracking-wide">
                                            <i class="fas fa-eye mr-0.5"></i> {{ __('a configurar') }}
                                        </p>
                                    @endif
                                </button>
                            @endforeach
                        </div>

                        {{-- Activar: acção deliberada, separada do Guardar. --}}
                        @unless($this->aVerOAmbienteActivo())
                            @php
                                $aVerProducao = $this->ambienteActual() === 'production';
                            @endphp
                            <div class="mt-4 rounded-xl border p-4 flex flex-col sm:flex-row sm:items-center gap-3
                                {{ $aVerProducao ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200' }}">
                                <div class="flex-1 text-xs {{ $aVerProducao ? 'text-red-800' : 'text-amber-800' }}">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    @if($aVerProducao)
                                        Activar <strong>{{ __('Produção') }}</strong> põe os documentos desta empresa a seguir
                                        para a AGT real, com valor fiscal. Confirme antes as chaves e o teste de ligação.
                                    @else
                                        Activar <strong>{{ __('Homologação') }}</strong> passa os documentos a ir para o ambiente
                                        de testes — deixam de ter valor fiscal.
                                    @endif
                                </div>
                                <button type="button" wire:click="activarAmbiente"
                                        wire:confirm="{{ $aVerProducao
                                            ? 'Passar esta empresa a emitir em PRODUCAO, na AGT real? Os documentos passam a ter valor fiscal.'
                                            : 'Passar esta empresa a emitir em HOMOLOGACAO? Os documentos deixam de ter valor fiscal.' }}"
                                        class="px-4 py-2 rounded-xl text-white text-xs font-bold whitespace-nowrap
                                            {{ $aVerProducao ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-600 hover:bg-amber-700' }}">
                                    <i class="fas fa-broadcast-tower mr-1"></i>
                                    {{ __('Passar a emitir aqui') }}
                                </button>
                            </div>
                        @endunless
                    </div>

                    {{-- Chaves do contribuinte no Portal AGT --}}
                    <div class="border-t border-gray-100 pt-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                            <div>
                                <h3 class="text-gray-800 font-bold text-sm flex items-center">
                                    <i class="fas fa-key mr-2 text-orange-500"></i>
                                    {{ __('Chaves do Portal AGT desta empresa') }}
                                </h3>
                                @php
                                    // Forma de bloco: a directiva de uma linha não suporta ternários
                                    $rotuloAmbiente = $ambienteVista === 'production' ? 'Produção' : 'Homologação';
                                @endphp
                                <p class="text-gray-400 text-xs mt-1">
                                    {!! __('Cole o par RSA fornecido no Portal do Contribuinte. As chaves são <strong>por empresa e por ambiente</strong> — o par abaixo é o de <strong>:ambiente</strong> e não afecta o outro ambiente.', ['ambiente' => e($rotuloAmbiente)]) !!}
                                </p>
                            </div>
                            {{-- O ambiente tem de constar do badge: "configurada" sem dizer qual
                                 levava a crer que produção estava pronta usando a chave de testes. --}}
                            <div class="flex flex-col items-end gap-1 text-[10px] font-bold">
                                <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 uppercase tracking-wide">{{ $rotuloAmbiente }}</span>
                                <div class="flex gap-2">
                                    <span class="px-2.5 py-1 rounded-full {{ $hasPublicKey ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">Pública: {{ $hasPublicKey ? 'configurada' : 'em falta' }}</span>
                                    <span class="px-2.5 py-1 rounded-full {{ $hasPrivateKey ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">Privada: {{ $hasPrivateKey ? 'configurada' : 'em falta' }}</span>
                                </div>
                            </div>
                        </div>

                        @if(!$hasKeys)
                            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 mb-4 text-xs text-amber-800">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                {!! __('Sem par RSA de <strong>:ambiente</strong> não é possível assinar nem registar séries neste ambiente. O par do outro ambiente <strong>não serve</strong> — a AGT recusa a assinatura.', ['ambiente' => e($rotuloAmbiente)]) !!}
                            </div>
                        @endif

                        <div class="rounded-xl bg-blue-50 border border-blue-200 p-4 mb-4 text-xs text-blue-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            {{ __('O username e a password Basic Auth não são configurados aqui; são credenciais do produtor SOS ERP, próprias de cada ambiente. Ao substituir as chaves, informe sempre o par completo.') }}
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('Chave pública RSA (PEM)') }}</label>
                                <textarea wire:model="contributorPublicKey" rows="8" autocomplete="off" spellcheck="false"
                                          class="w-full px-4 py-3 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-orange-400 focus:border-transparent bg-gray-50"
                                          placeholder="-----BEGIN PUBLIC KEY-----&#10;...&#10;-----END PUBLIC KEY-----"></textarea>
                                @error('contributorPublicKey') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">{{ __('Chave privada RSA (PEM)') }}</label>
                                <textarea wire:model="contributorPrivateKey" rows="8" autocomplete="new-password" spellcheck="false"
                                          class="w-full px-4 py-3 border border-gray-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-orange-400 focus:border-transparent bg-gray-50"
                                          placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"></textarea>
                                @error('contributorPrivateKey') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row justify-end gap-2 mt-4">
                            @if($hasPublicKey || $hasPrivateKey)
                                <button type="button" wire:click="removeContributorKeys"
                                        wire:confirm="Remover as chaves AGT desta empresa? A submissão ficará bloqueada."
                                        class="px-4 py-2.5 rounded-xl text-xs font-bold text-red-600 bg-red-50 border border-red-200 hover:bg-red-100">
                                    <i class="fas fa-trash-alt mr-1.5"></i>{{ __('Remover chaves') }}
                                </button>
                            @endif
                            <button type="button" wire:click="saveContributorKeys" wire:loading.attr="disabled"
                                    class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-slate-800 hover:bg-slate-900 disabled:opacity-50">
                                <span wire:loading.remove wire:target="saveContributorKeys"><i class="fas fa-lock mr-1.5"></i>{{ __('Validar e guardar par') }}</span>
                                <span wire:loading wire:target="saveContributorKeys"><i class="fas fa-circle-notch fa-spin mr-1.5"></i>{{ __('A validar...') }}</span>
                            </button>
                        </div>
                    </div>

                    {{-- Secção: Actividade económica (CAE) --}}
                    <div class="border-t border-gray-100 pt-6 mb-6">
                        <h3 class="text-gray-800 font-bold text-sm flex items-center mb-1">
                            <i class="fas fa-briefcase mr-2 text-teal-600"></i>
                            {{ __('Actividade económica (CAE)') }}
                        </h3>
                        <p class="text-gray-400 text-xs mb-3">
                            {!! __('Vai no campo <span class="font-mono">eacCode</span> de cada documento enviado à AGT.') !!}
                        </p>

                        <select wire:model="agt_eac_code"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm">
                            <option value="">{{ __('— não definida —') }}</option>
                            @foreach($caeCodes as $cae)
                                <option value="{{ $cae->code }}" @selected($agt_eac_code === $cae->code)>{{ $cae->code }} · {{ $cae->description }}</option>
                            @endforeach
                        </select>

                        @if(blank($agt_eac_code))
                            {{-- Sem CAE o mapper usa o marcador '00000', que não
                                 identifica actividade nenhuma. --}}
                            <div class="mt-3 rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                {{-- O eacCode 00000 fica dentro da frase mas é
                                     literal: é o valor que vai mesmo no
                                     payload da AGT, não texto para traduzir. --}}
                                {!! __('Sem CAE os documentos vão com <span class="font-mono">eacCode 00000</span>, que não identifica a actividade — a AGT pode recusá-los.') !!}
                            </div>
                        @endif
                    </div>

                    {{-- Secção: Opções --}}
                    <div class="border-t border-gray-100 pt-6">
                        <h3 class="text-gray-800 font-bold text-sm flex items-center mb-4">
                            <i class="fas fa-sliders-h mr-2 text-orange-500"></i>
                            {{ __('Opções de Submissão') }}
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
                                            <span class="font-semibold text-sm text-gray-800 block">{{ __('Submissão Automática') }}</span>
                                            <span class="text-[10px] text-gray-400">{{ __('Enviar documentos automaticamente à AGT') }}</span>
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
                                            <span class="font-semibold text-sm text-gray-800 block">{{ __('Validação Obrigatória') }}</span>
                                            <span class="text-[10px] text-gray-400">{{ __('Exigir validação AGT antes de imprimir') }}</span>
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
                                    <p class="text-sm font-bold text-red-800">{{ __('Falha na conexão') }}</p>
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
                            <span wire:loading.remove wire:target="testConnection"><i class="fas fa-plug mr-1"></i> {{ __('Testar Conexão') }}</span>
                            <span wire:loading wire:target="testConnection"><i class="fas fa-spinner fa-spin mr-1"></i> {{ __('A testar...') }}</span>
                        </button>
                        <button wire:click="save" wire:loading.attr="disabled" wire:target="save"
                                class="px-6 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-orange-500 to-red-500 rounded-xl hover:from-orange-600 hover:to-red-600 transition shadow-lg shadow-orange-200 flex items-center gap-2 disabled:opacity-50">
                            <span wire:loading.remove wire:target="save"><i class="fas fa-save mr-1"></i> {{ __('Guardar Configurações') }}</span>
                            <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin mr-1"></i> {{ __('A guardar...') }}</span>
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
                                {{ __('Séries de Documentos') }}
                            </h3>
                            <p class="text-gray-400 text-xs mt-0.5">
                                {{-- "FT A" e "FT7626S9153N" são exemplos de
                                     códigos reais da AGT: ficam dentro da
                                     frase, mas literais. --}}
                                {!! __('A sua série (ex.: <span class="font-mono">FT A</span>) ligada ao código que a AGT lhe atribuiu (ex.: <span class="font-mono">FT7626S9153N</span>). É esse código que aparece no separador <em>Séries de facturas</em> do portal — o nome que deu à série cá dentro não é enviado à AGT.') !!}
                            </p>
                        </div>
                        <button wire:click="syncSeries" wire:loading.attr="disabled" wire:target="syncSeries"
                                class="px-4 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-xl hover:from-blue-600 hover:to-indigo-600 transition text-sm font-bold shadow-lg shadow-blue-200 flex items-center gap-2 disabled:opacity-50">
                            <span wire:loading.remove wire:target="syncSeries"><i class="fas fa-sync-alt mr-1"></i> {{ __('Sincronizar com AGT') }}</span>
                            <span wire:loading wire:target="syncSeries"><i class="fas fa-spinner fa-spin mr-1"></i> {{ __('A sincronizar...') }}</span>
                        </button>
                    </div>

                    @if(!$hasGlobalCredentials || !$hasKeys)
                        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-exclamation-triangle text-amber-600 mt-0.5"></i>
                                <div>
                                    <p class="text-sm font-bold text-amber-900">{{ __('Sincronização ainda não disponível') }}</p>
                                    <p class="text-xs text-amber-800 mt-1">{{ __('O pedido só será enviado quando todos os requisitos estiverem configurados:') }}</p>
                                    <ul class="mt-2 space-y-1 text-xs">
                                        <li class="{{ $hasGlobalCredentials ? 'text-green-700' : 'text-red-700 font-semibold' }}"><i class="fas fa-{{ $hasGlobalCredentials ? 'check-circle' : 'times-circle' }} mr-1"></i>Credenciais globais do produtor {{ $hasGlobalCredentials ? 'configuradas' : 'em falta no servidor' }}</li>
                                        <li class="{{ $hasPublicKey ? 'text-green-700' : 'text-red-700 font-semibold' }}"><i class="fas fa-{{ $hasPublicKey ? 'check-circle' : 'times-circle' }} mr-1"></i>Chave pública do tenant {{ $hasPublicKey ? 'configurada' : 'em falta' }}</li>
                                        <li class="{{ $hasPrivateKey ? 'text-green-700' : 'text-red-700 font-semibold' }}"><i class="fas fa-{{ $hasPrivateKey ? 'check-circle' : 'times-circle' }} mr-1"></i>Chave privada do tenant {{ $hasPrivateKey ? 'configurada' : 'em falta' }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if(!empty($syncResult))
                    <div class="mb-5 rounded-xl border p-4
                        {{ ($syncResult['failed'] ?? 0) > 0 ? 'bg-red-50 border-red-200' : (($syncResult['total'] ?? 0) === 0 ? 'bg-blue-50 border-blue-200' : 'bg-green-50 border-green-200') }}">
                        <div class="flex items-start">
                            <i class="fas fa-{{ ($syncResult['failed'] ?? 0) > 0 ? 'exclamation-circle text-red-600' : (($syncResult['total'] ?? 0) === 0 ? 'info-circle text-blue-600' : 'check-circle text-green-600') }} text-lg mr-3 mt-0.5"></i>
                            <div>
                                <p class="text-sm font-bold text-gray-800">
                                    @if(($syncResult['total'] ?? 0) === 0 && empty($syncResult['error']))
                                        Todas as séries activas já estão sincronizadas ou ainda não existem séries.
                                    @else
                                        Sincronização concluída: {{ $syncResult['success'] ?? 0 }} sucesso(s), {{ $syncResult['failed'] ?? 0 }} falha(s).
                                    @endif
                                </p>
                                @if(!empty($syncResult['error']))
                                    <p class="text-xs text-red-700 mt-1">{{ $syncResult['error'] }}</p>
                                @endif
                                @foreach(($syncResult['details'] ?? []) as $detail)
                                    @if(!($detail['result']['success'] ?? false))
                                        <p class="text-xs text-red-700 mt-1">
                                            <strong>{{ $detail['series'] ?? 'Série' }}:</strong>
                                            {{ $detail['result']['error'] ?? 'Falha sem detalhe.' }}
                                        </p>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                    <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Série') }}</th>
                                    <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Tipo Documento') }}</th>
                                    {{-- "ID AGT" parecia um id interno; é o código que aparece no
                                         separador "Séries de facturas" do portal — é ESTE o elo. --}}
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Código no portal AGT') }}</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">ATCUD</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Estado') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($series as $s)
                                @php
                                    $eligible = $s->isAGTEligible();
                                    $agtErrors = data_get($s->agt_response, 'errorList', []);
                                    $agtError = $eligible ? collect(is_array($agtErrors) ? $agtErrors : [])
                                        ->map(fn ($error) => trim(($error['idError'] ?? '') . ' ' . ($error['descriptionError'] ?? $error['errorDescription'] ?? '')))
                                        ->filter()
                                        ->implode(' · ') : '';
                                @endphp
                                <tr class="hover:bg-orange-50/40 transition">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center mr-3 shrink-0">
                                                <i class="fas fa-hashtag text-blue-500 text-xs"></i>
                                            </div>
                                            {{-- O código já contém o prefixo (SOSFT): repeti-lo dava "FT SOSFT" --}}
                                            <div class="min-w-0">
                                                <span class="font-bold text-gray-800">{{ $s->series_code }}</span>
                                                @if($s->name && $s->name !== 'Série ' . $s->series_code)
                                                    <span class="block text-[10px] text-gray-400">{{ $s->name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-500">
                                        @php
                                            $rotulosTipo = [
                                                'invoice'     => 'Fatura',
                                                'pos'         => 'Fatura-Recibo',
                                                'proforma'    => 'Proforma',
                                                'receipt'     => 'Recibo',
                                                'credit_note' => 'Nota de Crédito',
                                                'debit_note'  => 'Nota de Débito',
                                                'advance'     => 'Adiantamento',
                                                'purchase'    => 'Fatura de Compra',
                                                'transport'   => 'Guia de Transporte',
                                            ];
                                            $rotuloTipo = $rotulosTipo[$s->document_type] ?? $s->document_type;
                                        @endphp
                                        <span class="text-gray-700">{{ $rotuloTipo }}</span>
                                        <span class="ml-1.5 font-mono text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">{{ $s->prefix }}</span>
                                        @if($agtError)
                                            <p class="mt-1 max-w-md text-[10px] leading-4 text-red-600" title="{{ $agtError }}">{{ $agtError }}</p>
                                        @elseif(!$eligible)
                                            <p class="mt-1 text-[10px] text-gray-400">{{ __('Documento não fiscal; não é enviado à AGT.') }}</p>
                                        @endif
                                    </td>
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
                                        @if(!$eligible)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600">
                                                <i class="fas fa-minus-circle mr-1"></i> {{ __('Não aplicável') }}
                                            </span>
                                        @elseif($s->agt_series_id)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-green-100 text-green-700">
                                                <i class="fas fa-check mr-1"></i> {{ __('Registada') }}
                                            </span>
                                        @elseif($agtError)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-red-100 text-red-700">
                                                <i class="fas fa-times-circle mr-1"></i> {{ __('Rejeitada') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-700">
                                                <i class="fas fa-clock mr-1"></i> {{ __('Pendente') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center">
                                        <div class="flex flex-col items-center text-gray-400">
                                            <i class="fas fa-inbox text-3xl mb-2"></i>
                                            <p class="text-sm font-medium">{{ __('Nenhuma série encontrada') }}</p>
                                            <p class="text-xs">{{ __('Crie séries em') }} <strong>{{ __('Faturação → Séries de Documentos') }}</strong></p>
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
                    <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-gray-800 font-bold text-sm flex items-center">
                                <i class="fas fa-cloud-upload-alt mr-2 text-indigo-500"></i>
                                {{ __('Histórico de Submissões FE') }}
                            </h3>
                            <p class="text-gray-400 text-xs mt-0.5">{{ __('Documentos enviados, validados ou rejeitados pela AGT') }}</p>
                        </div>
                        <button wire:click="refreshSubmissionStatuses"
                                wire:loading.attr="disabled" wire:target="refreshSubmissionStatuses"
                                class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-indigo-50 text-indigo-700 text-xs font-bold hover:bg-indigo-100 transition">
                            <i wire:loading.class="fa-spin" wire:target="refreshSubmissionStatuses"
                               class="fas fa-sync-alt mr-2"></i>
                            {{ __('Atualizar estados AGT') }}
                        </button>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                    <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Documento') }}</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Tipo') }}</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Estado') }}</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Tentativas') }}</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Data') }}</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Ações') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($pendingSubmissions as $sub)
                                @php
                                    $legalNumber = $sub['document_number'];
                                    $typeCode = strtoupper($sub['document_type_code'] ?: 'DOC');
                                    $sequence = null;
                                    // 2 a 4 letras: o número passou a começar por SOS (3)
                                    if (preg_match('/^[A-Z]{2,4}\s+[^\/]+\/(\d+)$/', $legalNumber, $parts)) {
                                        $sequence = $parts[1];
                                    }
                                    $friendlyNumber = 'SOS-' . $typeCode . ($sequence ? '-' . $sequence : '');
                                @endphp
                                <tr class="hover:bg-orange-50/40 transition">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 shrink-0 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center shadow-sm">
                                                <i class="fas fa-file-invoice"></i>
                                            </div>
                                            {{-- O número FISCAL é o único identificador real: é o que
                                                 está na AGT, no PDF e no portal. A etiqueta SOS-… é
                                                 apenas um apelido de ecrã, não existe em lado nenhum
                                                 — mostrá-la em destaque levava a procurar uma "série
                                                 SOS" que nunca existiu. --}}
                                            <div class="min-w-0">
                                                <div class="font-mono font-bold text-gray-900">{{ $legalNumber }}</div>
                                                <div class="text-[10px] text-gray-400 mt-0.5">
                                                    referência interna SOSERP: {{ $friendlyNumber }} · o número fiscal válido é o apresentado acima
                                                </div>
                                            </div>
                                        </div>
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
                                            $statusLabels = [
                                                'validated' => 'Validada',
                                                'rejected' => 'Rejeitada',
                                                'submitted' => 'Enviada',
                                                'pending' => 'Pendente',
                                            ];
                                            $color = $statusColors[$sub['status']] ?? 'bg-gray-100 text-gray-700';
                                            $icon = $statusIcons[$sub['status']] ?? 'fa-question-circle';
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold {{ $color }}">
                                            <i class="fas {{ $icon }} mr-1"></i> {{ $statusLabels[$sub['status']] ?? ucfirst($sub['status']) }}
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
                                            <span wire:loading.remove wire:target="retrySubmission({{ $sub['id'] }})"><i class="fas fa-redo mr-1"></i> {{ __('Reenviar') }}</span>
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
                                            <p class="text-sm font-medium">{{ __('Nenhuma submissão registada') }}</p>
                                            <p class="text-xs">{{ __('Os documentos enviados à AGT aparecerão aqui') }}</p>
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
                @if($activeTab === 'api-tools')
                <div>
                    <div class="mb-5">
                        <h3 class="text-gray-800 font-bold text-sm flex items-center">
                            <i class="fas fa-terminal mr-2 text-orange-500"></i>
                            {{ __('Operações REST de Homologação') }}
                        </h3>
                        <p class="text-gray-400 text-xs mt-1">{{ __('Consultas seguras aos endpoints oficiais. RegistarFactura é executado apenas no fluxo de emissão de uma factura real.') }}</p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
                        @foreach([
                            ['name' => 'RegistarFactura', 'path' => '/registarFactura', 'safe' => false],
                            ['name' => 'ObterEstado', 'path' => '/obterEstado', 'safe' => true],
                            ['name' => 'ConsultarFactura', 'path' => '/consultarFactura', 'safe' => true],
                            ['name' => 'ListarFacturas', 'path' => '/listarFacturas', 'safe' => true],
                        ] as $endpoint)
                            @php
                                // Forma de bloco: a directiva de uma linha não suporta
                                // match() — as vírgulas partem o parser do Blade.
                                $notas = [
                                    'ListarFacturas'   => 'Lista os documentos RECEBIDOS (empresa como adquirente). Não devolve os emitidos.',
                                    'ConsultarFactura' => 'Exige o número fiscal completo, ex.: NC NC7626S7057N/000003.',
                                    'ObterEstado'      => 'Estado de uma submissão pelo Request ID.',
                                ];
                                $nota = $notas[$endpoint['name']] ?? 'Executado apenas no fluxo de emissão real.';
                            @endphp
                            <div class="p-4 rounded-xl border {{ $endpoint['safe'] ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50' }}">
                                <div class="flex items-center justify-between">
                                    <strong class="text-xs text-gray-800">{{ $endpoint['name'] }}</strong>
                                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-full {{ $endpoint['safe'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $endpoint['safe'] ? 'TESTÁVEL' : 'EMISSÃO' }}
                                    </span>
                                </div>
                                <code class="block text-[10px] text-gray-500 mt-2 break-all">{{ $ambienteVista === 'production' ? \App\Services\AGT\AGTClient::PRODUCTION_URL : \App\Services\AGT\AGTClient::SANDBOX_URL }}{{ $endpoint['path'] }}</code>
                                <p class="text-[10px] text-gray-600 mt-2 leading-snug">{{ $nota }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-2">{{ __('Operação') }}</label>
                                <select wire:model.live="apiOperation" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm">
                                    {{-- O nome do endpoint sozinho leva a crer que lista os
                                         documentos EMITIDOS. Lista os RECEBIDOS. --}}
                                    <option value="listarFacturas" @selected($apiOperation === 'listarFacturas')>{{ __('ListarFacturas — documentos RECEBIDOS (empresa como adquirente)') }}</option>
                                    <option value="consultarFactura" @selected($apiOperation === 'consultarFactura')>{{ __('ConsultarFactura — consultar um documento emitido') }}</option>
                                    <option value="obterEstado" @selected($apiOperation === 'obterEstado')>{{ __('ObterEstado — estado de uma submissão') }}</option>
                                </select>
                            </div>
                            @if($apiOperation === 'consultarFactura')
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-2">{{ __('Número do documento') }}</label>
                                    <input type="text" wire:model="apiDocumentNo" placeholder="{{ __('Ex.: FT A/2026/000001') }}" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm">
                                    @error('apiDocumentNo') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                </div>
                            @elseif($apiOperation === 'obterEstado')
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-2">{{ __('Request ID') }}</label>
                                    <input type="text" wire:model="apiRequestId" placeholder="{{ __('Request ID devolvido pela AGT') }}" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm">
                                    @error('apiRequestId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                </div>
                            @else
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-2">{{ __('Data inicial') }}</label>
                                        <input type="date" wire:model="apiDateFrom" class="w-full px-3 py-3 rounded-xl border border-gray-200 bg-white text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-2">{{ __('Data final') }}</label>
                                        <input type="date" wire:model="apiDateTo" class="w-full px-3 py-3 rounded-xl border border-gray-200 bg-white text-sm">
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="flex justify-end mt-4">
                            <button type="button" wire:click="runApiOperation" wire:loading.attr="disabled"
                                    class="px-5 py-2.5 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 disabled:opacity-50">
                                <span wire:loading.remove wire:target="runApiOperation"><i class="fas fa-play mr-1.5"></i> {{ __('Executar consulta') }}</span>
                                <span wire:loading wire:target="runApiOperation"><i class="fas fa-circle-notch fa-spin mr-1.5"></i> {{ __('A comunicar...') }}</span>
                            </button>
                        </div>
                    </div>

                    @if(!empty($apiOperationResult))
                        <div class="mt-5 rounded-xl border p-4 {{ ($apiOperationResult['success'] ?? false) ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                            <p class="text-sm font-bold {{ ($apiOperationResult['success'] ?? false) ? 'text-green-800' : 'text-red-800' }}">
                                {{ ($apiOperationResult['success'] ?? false) ? 'Operação concluída' : 'Operação recusada ou com erro' }}
                            </p>
                            <p class="text-xs mt-1 text-gray-700">{{ $apiOperationResult['error'] ?? $apiOperationResult['message'] ?? 'A AGT devolveu uma resposta válida.' }}</p>
                            <div class="text-[10px] text-gray-500 mt-2">{{ $apiOperationResult['tested_at'] ?? '' }} · {{ $apiOperationResult['elapsed_ms'] ?? 0 }} ms · HTTP {{ $apiOperationResult['status'] ?? 'N/A' }}</div>
                            @if(isset($apiOperationResult['data']))
                                <pre class="mt-3 p-3 rounded-lg bg-slate-900 text-green-300 text-[10px] overflow-auto max-h-72">{{ json_encode($apiOperationResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            @endif
                        </div>
                    @endif
                </div>
                @endif

                {{-- Tab: LOGS --}}
                {{-- ═══════════════════════════════════ --}}
                @if($activeTab === 'logs')
                <div>
                    <div class="mb-5">
                        <h3 class="text-gray-800 font-bold text-sm flex items-center">
                            <i class="fas fa-history mr-2 text-purple-500"></i>
                            {{ __('Logs de Comunicação') }}
                        </h3>
                        <p class="text-gray-400 text-xs mt-0.5">{{ __('Últimas 20 comunicações com a API AGT') }}</p>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                    <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Data/Hora') }}</th>
                                    <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Serviço') }}</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Método') }}</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Status') }}</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Tempo') }}</th>
                                    <th class="text-center px-5 py-3.5 text-xs font-bold text-gray-500 uppercase">{{ __('Resultado') }}</th>
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
                                                <i class="fas fa-times mr-1"></i> {{ __('Erro') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center">
                                        <div class="flex flex-col items-center text-gray-400">
                                            <i class="fas fa-list-alt text-3xl mb-2"></i>
                                            <p class="text-sm font-medium">{{ __('Nenhum log de comunicação') }}</p>
                                            <p class="text-xs">{{ __('Os logs aparecerão após interação com a API AGT') }}</p>
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
                    <h3 class="text-sm font-bold text-amber-800">{{ __('Chaves RSA não configuradas') }}</h3>
                    <p class="mt-1 text-sm text-amber-700 leading-relaxed">
                        {!! __('As chaves pública e privada do Portal AGT são necessárias para assinar e submeter documentos desta empresa. Configure o par no separador <strong>Configurações</strong> acima.') !!}
                    </p>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
