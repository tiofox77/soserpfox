<div class="p-6 max-w-5xl mx-auto">

    {{-- Cabeçalho --}}
    <div class="mb-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-green-200">
                    <i class="fas fa-shield-alt text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ __('Facturação Electrónica AGT') }}</h1>
                    <p class="text-sm text-gray-500">{{ __('Configure os dados do contribuinte para comunicar documentos fiscais à AGT.') }}</p>
                </div>
            </div>
            @if(auth()->user()->isSuperAdmin())
            <div class="flex items-center gap-2">
                <a href="{{ route('invoicing.agt-settings') }}" class="text-xs px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 inline-flex items-center gap-2">
                    <i class="fas fa-cogs"></i> {{ __('Operação AGT (séries · submissões · logs)') }}
                </a>
            </div>
            @endif
        </div>
    </div>

    {{-- Resumo do estado --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        {{-- Acesso API (global — do produtor) --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-200 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg {{ $hasGlobalCredentials ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500' }} flex items-center justify-center">
                <i class="fas fa-user-shield"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase font-bold">{{ __('Acesso API') }}</p>
                <p class="text-sm font-semibold {{ $hasGlobalCredentials ? 'text-green-700' : 'text-red-600' }}">
                    {{ $hasGlobalCredentials ? 'Produtor OK' : 'Não configurado' }}
                </p>
            </div>
        </div>
        {{-- Chave RSA do contribuinte --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-200 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg {{ $hasPrivateKey ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center">
                <i class="fas fa-key"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase font-bold">{{ __('Chave RSA') }}</p>
                <p class="text-sm font-semibold {{ $hasPrivateKey ? 'text-green-700' : 'text-gray-500' }}">
                    {{ $hasPrivateKey ? 'Instalada' : 'Não instalada' }}
                </p>
            </div>
        </div>
        {{-- Ambiente --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-200 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg {{ $agt_environment === 'production' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600' }} flex items-center justify-center">
                <i class="fas {{ $agt_environment === 'production' ? 'fa-globe' : 'fa-flask' }}"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase font-bold">{{ __('Ambiente') }}</p>
                <p class="text-sm font-semibold {{ $agt_environment === 'production' ? 'text-red-700' : 'text-amber-700' }}">
                    {{ $agt_environment === 'production' ? 'Produção' : 'Homologação' }}
                </p>
            </div>
        </div>
        {{-- Auto-submissão --}}
        <div class="bg-white rounded-2xl p-4 border border-gray-200 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg {{ $agt_auto_submit ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center">
                <i class="fas fa-paper-plane"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase font-bold">{{ __('Auto-submissão') }}</p>
                <p class="text-sm font-semibold {{ $agt_auto_submit ? 'text-blue-700' : 'text-gray-500' }}">
                    {{ $agt_auto_submit ? 'Activa' : 'Desactivada' }}
                </p>
            </div>
        </div>
    </div>

    {{-- O que está a acontecer NA PRÁTICA.
         Os cartões acima dizem "Auto-submissão: Activa" e "Chave RSA: Não
         instalada" lado a lado, e cabe a quem lê juntar as duas coisas. Este
         aviso junta-as: com o interruptor ligado e a configuração incompleta,
         os documentos não estão a ser comunicados — e diz quantos. --}}
    <x-agt.aviso-comunicacao />

    {{-- Aviso se credenciais globais não estiverem configuradas --}}
    @if(!$hasGlobalCredentials)
    <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <i class="fas fa-exclamation-triangle text-red-500 mt-0.5"></i>
            <div class="text-xs text-red-800 space-y-1">
                <p>{!! __('<strong>Credenciais do produtor não configuradas.</strong> O administrador do sistema precisa configurar as credenciais Basic Auth (username/password) em <strong>Operação AGT → Configurações</strong> para que a comunicação com a API AGT funcione.') !!}</p>
                <p>{!! __('Estas credenciais são globais do <strong>produtor de software</strong> — não do contribuinte.') !!}</p>
            </div>
        </div>
    </div>
    @endif

    {{-- Info Box --}}
    <div class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
            <div class="text-xs text-blue-800 space-y-1">
                {{-- jwsDocumentSignature e jwsSignature são nomes de campos
                     do payload da AGT: literais dentro da frase. --}}
                <p>{!! __('<strong>O que configurar aqui:</strong> O NIF do contribuinte, o código do estabelecimento e a <strong>chave privada RSA</strong> emitida pela AGT no <a href=":portal" target="_blank" class="underline font-bold">Portal do Contribuinte</a>.', ['portal' => 'https://portaldoparceiro.minfin.gov.ao/']) !!}</p>
                <p>{!! __('A chave RSA é usada para assinar cada documento (<code>jwsDocumentSignature</code>) e cada requisição (<code>jwsSignature</code>). Os dados do software e as credenciais de acesso à API são <strong>globais</strong> e geridos pelo administrador.') !!}</p>
            </div>
        </div>
    </div>

    <form wire:submit.prevent="save" class="bg-white rounded-2xl shadow border border-gray-200 p-6 space-y-6">

        {{-- Bloco 1: Dados do Contribuinte --}}
        <div>
            <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fas fa-building text-emerald-500"></i> {{ __('Dados do Contribuinte') }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('NIF (taxRegistrationNumber) *') }}</label>
                    <input type="text" wire:model="tax_registration_number" placeholder="{{ __('Ex.: 5001636863') }}" maxlength="15"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 outline-none font-mono text-sm">
                    <p class="text-[11px] text-gray-400 mt-1">{{ __('Número fiscal do contribuinte (máx. 15 dígitos). Deve estar registado e ter aderido à facturação electrónica no portal AGT.') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('Estabelecimento *') }}</label>
                    <input type="text" wire:model="agt_establishment_number" placeholder="SEDE"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 outline-none text-sm">
                    <p class="text-[11px] text-gray-400 mt-1">{{ __('Código do estabelecimento registado no Portal do Contribuinte. Use "SEDE" se tiver apenas uma localização.') }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                        {{ __('Código CAE — Actividade Económica') }}
                        <span class="text-[10px] font-normal text-gray-400">{{ __('(DS.120 §4.1)') }}</span>
                    </label>
                    <select wire:model.defer="agt_eac_code"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 outline-none text-sm">
                        <option value="">{{ __('— Seleccione a actividade principal —') }}</option>
                        @php
                            $caeByLevel = ($caeCodes ?? collect())->groupBy('level');
                            $levelLabels = [
                                'section'  => 'Secções (alto nível)',
                                'division' => 'Divisões',
                                'group'    => 'Grupos',
                                'class'    => 'Classes',
                                'subclass' => 'Subclasses',
                            ];
                        @endphp
                        @foreach($caeByLevel as $lvl => $codes)
                            <optgroup label="{{ $levelLabels[$lvl] ?? ucfirst($lvl) }}">
                                @foreach($codes as $cae)
                                    <option value="{{ $cae->code }}">
                                        {{ $cae->code }} — {{ \Illuminate\Support\Str::limit($cae->description, 80) }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">
                        {{ __('Obrigatório nas submissões à AGT. Lista oficial de classificação de actividades.') }}
                    </p>
                    @error('agt_eac_code')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('Ambiente AGT') }}</label>
                    <select wire:model.live="agt_environment" class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 outline-none">
                        <option value="sandbox">{{ __('Homologação (sandbox)') }}</option>
                        <option value="production">{{ __('Produção') }}</option>
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">
                        @if($agt_environment === 'sandbox')
                            URL: https://sifphml.minfin.gov.ao/sigt/fe/v1/
                        @else
                            URL: https://sifp.minfin.gov.ao/sigt/fe/v1/
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100"></div>

        {{-- Bloco 2: Chave Privada RSA do Contribuinte --}}
        <div>
            <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fas fa-lock text-emerald-500"></i> Chave Privada RSA do Contribuinte
                @if($hasPrivateKey)
                    <span class="text-[10px] text-green-700 bg-green-100 px-2 py-0.5 rounded-full">{{ __('instalada') }}</span>
                @endif
            </h3>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                <p class="text-xs text-amber-800">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    {{-- jwsDocumentSignature e jwsSignature são os nomes dos
                         campos que vão no payload da AGT: ficam literais
                         dentro da frase. O endereço do portal também. --}}
                    {!! __('Esta chave é <strong>gerada e emitida pela AGT</strong> e está disponível no <a href=":portal" target="_blank" class="underline font-bold">Portal do Contribuinte</a>. É utilizada para assinar digitalmente cada documento (<code>jwsDocumentSignature</code>) e cada requisição (<code>jwsSignature</code>). <strong>Nunca partilhe esta chave.</strong>', [
                        'portal' => 'https://portaldoparceiro.minfin.gov.ao/',
                    ]) !!}
                </p>
            </div>

            @if($hasPrivateKey)
                <div class="flex items-center gap-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <i class="fas fa-check-circle text-green-600"></i>
                    <span class="text-sm text-green-800 font-medium">{{ __('Chave privada RSA instalada com sucesso.') }}</span>
                    <button type="button" wire:click="clearPrivateKey" wire:confirm="Tem a certeza? A chave será removida permanentemente."
                        class="ml-auto text-xs px-3 py-1.5 rounded-lg bg-white border border-red-200 text-red-600 hover:bg-red-50">
                        <i class="fas fa-trash mr-1"></i> {{ __('Remover') }}
                    </button>
                </div>
            @else
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('Cole a chave privada PEM aqui:') }}</label>
                    <textarea wire:model="contributor_private_key" rows="6"
                        placeholder="-----BEGIN PRIVATE KEY-----&#10;MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBK...&#10;-----END PRIVATE KEY-----"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-200 outline-none font-mono text-xs leading-relaxed resize-y"></textarea>
                    <p class="text-[11px] text-gray-400 mt-1">{{ __('Formato PEM, RSA mínimo 2048 bits. Armazenada localmente no servidor (não na BD).') }}</p>
                </div>
            @endif
        </div>

        <div class="border-t border-gray-100"></div>

        {{-- Bloco 3: Comportamento --}}
        <div>
            <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fas fa-sliders-h text-emerald-500"></i> {{ __('Comportamento') }}
            </h3>
            <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="agt_auto_submit" class="mt-1 w-4 h-4 rounded border-gray-300 text-emerald-500 focus:ring-emerald-200">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">{{ __('Submissão automática à AGT') }}</p>
                        <p class="text-xs text-gray-500">{{ __('Cada documento fiscal criado (FT/FR/NC/ND) é enviado automaticamente para a AGT após gravação.') }}</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="agt_require_validation" class="mt-1 w-4 h-4 rounded border-gray-300 text-emerald-500 focus:ring-emerald-200">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">{{ __('Exigir validação prévia') }}</p>
                        <p class="text-xs text-gray-500">{{ __('Bloqueia emissão se o documento não cumprir as regras de conformidade AGT v1.2.') }}</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Notificações por email (Sprint 4 — DS.120) --}}
        <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-2xl p-5 border border-amber-200">
            <h3 class="font-bold text-amber-900 mb-3 flex items-center gap-2">
                <i class="fas fa-envelope-open-text text-amber-600"></i> {{ __('Notificações de erros AGT') }}
            </h3>
            <p class="text-xs text-amber-800/80 mb-3">
                {{-- resultCode 1 e 2 são os códigos da própria AGT, e
                     invoicing.agt.view é o nome interno da permissão: nenhum
                     deles se traduz. --}}
                {!! __('Lista de emails (separados por vírgula) que recebem notificação quando uma submissão à AGT é <strong>rejeitada</strong> (resultCode 2) ou contém <strong>erros parciais</strong> (resultCode 1). Se vazio, é usado o fallback dos utilizadores com permissão <code class="bg-amber-100 px-1 rounded">invoicing.agt.view</code>.') !!}
            </p>
            <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('Emails (CSV)') }}</label>
            <input type="text" wire:model.defer="agt_notification_emails"
                placeholder="financeiro@empresa.ao, fiscal@empresa.ao"
                class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:border-amber-500 focus:ring-1 focus:ring-amber-200 outline-none text-sm font-mono">
            @error('agt_notification_emails')
                <p class="text-red-600 text-xs mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</p>
            @enderror
        </div>

        {{-- Resultado teste de conexão --}}
        @if(!empty($connectionTest))
        <div class="rounded-xl p-4 {{ ($connectionTest['success'] ?? false) ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' }}">
            <div class="flex items-start gap-3">
                <i class="fas fa-{{ ($connectionTest['success'] ?? false) ? 'check-circle' : 'exclamation-triangle' }} mt-0.5"></i>
                <div class="text-sm">
                    @if($connectionTest['success'] ?? false)
                        <strong>{{ __('Conexão OK.') }}</strong> {{ $connectionTest['message'] ?? 'Credenciais e configuração validadas.' }}
                    @else
                        <strong>{{ __('Falhou.') }}</strong> {{ $connectionTest['error'] ?? 'Erro desconhecido' }}
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- Acções --}}
        <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-gray-100">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white font-semibold hover:shadow-lg transition flex items-center gap-2">
                <i class="fas fa-save"></i> {{ __('Guardar configuração') }}
            </button>
            <button type="button" wire:click="testConnection"
                    class="px-5 py-2.5 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition flex items-center gap-2">
                <i class="fas fa-plug"></i> {{ __('Testar conexão') }}
            </button>
        </div>
    </form>

    {{-- Referência rápida: Arquitectura de chaves --}}
    <div class="mt-6 bg-gray-50 rounded-2xl border border-gray-200 p-5">
        <h3 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-sitemap text-gray-500"></i> {{ __('Arquitectura de Chaves e Assinaturas AGT') }}
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="text-left px-3 py-2 font-bold text-gray-600">{{ __('Assinatura') }}</th>
                        <th class="text-left px-3 py-2 font-bold text-gray-600">{{ __('Chave utilizada') }}</th>
                        <th class="text-left px-3 py-2 font-bold text-gray-600">{{ __('Quem fornece') }}</th>
                        <th class="text-center px-3 py-2 font-bold text-gray-600">{{ __('Escopo') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr>
                        <td class="px-3 py-2 font-mono text-gray-700">jwsSoftwareSignature</td>
                        <td class="px-3 py-2 text-gray-600">{!! __('Chave RSA do <strong>produtor</strong>') !!}</td>
                        <td class="px-3 py-2 text-gray-600">{{ __('SOS ERP (administrador)') }}</td>
                        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 font-bold">{{ __('Global') }}</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-mono text-gray-700">jwsDocumentSignature</td>
                        <td class="px-3 py-2 text-gray-600">{!! __('Chave RSA do <strong>contribuinte</strong>') !!}</td>
                        <td class="px-3 py-2 text-gray-600">{{ __('Portal do Contribuinte AGT') }}</td>
                        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold">{{ __('Por tenant') }}</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-mono text-gray-700">jwsSignature</td>
                        <td class="px-3 py-2 text-gray-600">{!! __('Chave RSA do <strong>contribuinte</strong>') !!}</td>
                        <td class="px-3 py-2 text-gray-600">{{ __('Portal do Contribuinte AGT') }}</td>
                        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold">{{ __('Por tenant') }}</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 font-mono text-gray-700">{{ __('Basic Auth') }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ __('Username + Password') }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ __('AGT → Produtor de Software') }}</td>
                        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 font-bold">{{ __('Global') }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
