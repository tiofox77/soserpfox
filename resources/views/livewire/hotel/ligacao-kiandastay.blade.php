<div class="p-3 sm:p-6">

    {{-- Cabeçalho --}}
    <div class="bg-gradient-to-r from-indigo-600 to-violet-600 rounded-2xl shadow-lg p-6 mb-6 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold flex items-center">
                    <i class="fas fa-link mr-3"></i>{{ __('Ligação ao KiandaStay') }}
                </h2>
                <p class="text-indigo-100 text-sm mt-1">
                    {{ __('As reservas feitas no site entram sozinhas na recepção, prontas para check-in e factura.') }}
                </p>
            </div>

            <div class="text-right">
                @if($ligacao->aReceber())
                    <span class="px-4 py-2 bg-white/20 rounded-xl font-bold">
                        <i class="fas fa-circle text-green-300 text-xs mr-2"></i>{{ __('A receber') }}
                    </span>
                @else
                    <span class="px-4 py-2 bg-white/20 rounded-xl font-bold">
                        <i class="fas fa-circle text-yellow-300 text-xs mr-2"></i>{{ __('Por ligar') }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- O CAMINHO FÁCIL À FRENTE.

         Ninguém devia ter de copiar uma chave de API para ligar o seu hotel — e
         a chave do KiandaStay é UMA para o site inteiro, o que fazia dela uma
         coisa que não se entrega a ninguém. Aqui o hoteleiro entra com a conta
         dele, escolhe a casa e volta ligado, com um acesso que vale só para
         essa casa. --}}
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 border-2 border-indigo-100">
        <div class="flex flex-col md:flex-row md:items-center gap-4">
            <div class="flex-1">
                <h3 class="font-black text-slate-800 text-lg">{{ __('Ligar numa volta') }}</h3>
                <p class="text-sm text-slate-600 mt-1">
                    {{ __('Entra no KiandaStay com a conta da casa, escolhe o hotel e volta ligado. Não é preciso copiar chave nenhuma.') }}
                </p>
            </div>

            <div class="md:w-96">
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase tracking-wider">{{ __('Endereço do site') }}</label>
                <div class="flex gap-2">
                    {{-- name/autocomplete afastados de tudo o que o browser
                         reconheça: com "endereço" ao lado de uma "palavra-passe",
                         a autofill enchia isto com o email do utilizador. --}}
                    <input wire:model="base_url" type="url" name="endereco_do_site_kianda"
                           autocomplete="off" data-lpignore="true" data-1p-ignore
                           placeholder="https://kiandastay.vip"
                           class="flex-1 px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">

                    <button wire:click="entrarComKiandaStay"
                            class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl font-semibold hover:bg-indigo-700 transition whitespace-nowrap">
                        <i class="fas fa-right-to-bracket mr-2"></i>{{ __('Entrar com o KiandaStay') }}
                    </button>
                </div>
                @error('base_url') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-2xl bg-green-50 border border-green-200 text-green-800 text-sm">
            <i class="fas fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 text-sm">
            <i class="fas fa-circle-exclamation mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Coluna dos passos ─────────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Passo 1 --}}
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="font-black text-slate-800 mb-1">
                    <span class="inline-flex w-6 h-6 rounded-full bg-slate-400 text-white text-xs items-center justify-center mr-2">1</span>
                    {{ __('Onde fica o site') }}
                    <span class="ml-2 text-xs font-normal text-slate-400">{{ __('— caminho manual') }}</span>
                </h3>
                <p class="text-xs text-slate-500 mb-4">
                    {{ __('Só é preciso se preferir usar uma chave da API em vez de entrar com a conta. A chave gera-se no KiandaStay, em Admin → Definições → API Key.') }}
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Endereço do site') }}</label>
                        <input wire:model="base_url" type="url" name="endereco_manual_kianda"
                               autocomplete="off" data-lpignore="true" data-1p-ignore
                               placeholder="https://kiandastay.vip"
                               class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                        @error('base_url') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            {{ __('Chave da API') }}
                            @if($ligacao->api_key)
                                <span class="text-xs font-normal text-green-600">({{ __('guardada') }})</span>
                            @endif
                        </label>
                        {{-- `new-password` é o que faz o gestor de palavras-passe
                             deixar este campo em paz. --}}
                        <input wire:model="api_key" type="password" name="chave_api_kianda"
                               autocomplete="new-password" data-lpignore="true" data-1p-ignore
                               placeholder="okb_..."
                               class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                        <p class="text-xs text-slate-400 mt-1">{{ __('Só escreva aqui para substituir a que está guardada.') }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mt-4">
                    <button wire:click="guardarCredenciais" class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl font-semibold hover:bg-indigo-700 transition">
                        <i class="fas fa-save mr-2"></i>{{ __('Guardar') }}
                    </button>
                    <button wire:click="testar" class="px-5 py-2.5 border-2 border-indigo-200 text-indigo-700 rounded-xl font-semibold hover:bg-indigo-50 transition">
                        <i class="fas fa-satellite-dish mr-2"></i>{{ __('Testar ligação') }}
                    </button>
                </div>

                @if($diagnostico)
                    <div class="mt-4 p-3 rounded-xl text-sm {{ $diagnostico['ok'] ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800' }}">
                        {{ $diagnostico['ok'] ? __('O site respondeu.') : ($diagnostico['erro'] ?? '') }}
                    </div>
                @endif
            </div>

            {{-- Passo 2 --}}
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="font-black text-slate-800 mb-1">
                    <span class="inline-flex w-6 h-6 rounded-full bg-indigo-600 text-white text-xs items-center justify-center mr-2">2</span>
                    {{ __('Qual dos hotéis do site é esta casa') }}
                </h3>
                <p class="text-xs text-slate-500 mb-4">
                    {{ __('Sem isto, entrariam aqui as reservas de todos os hotéis do site.') }}
                </p>

                @if(empty($hoteisDoSite))
                    <p class="text-sm text-slate-400">{{ __('Guarde primeiro o endereço e a chave — a lista vem do próprio site.') }}</p>
                @else
                    <select wire:model.live="property_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl">
                        <option value="">{{ __('Escolha o hotel…') }}</option>
                        @foreach($hoteisDoSite as $h)
                            <option value="{{ $h['id'] }}">{{ $h['name'] ?? ('#' . $h['id']) }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            {{-- Passo 3 --}}
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="font-black text-slate-800 mb-1">
                    <span class="inline-flex w-6 h-6 rounded-full bg-indigo-600 text-white text-xs items-center justify-center mr-2">3</span>
                    {{ __('Ligar') }}
                </h3>
                <p class="text-xs text-slate-500 mb-4">
                    {{ __('O sistema regista-se no site para receber cada reserva no momento em que é feita.') }}
                </p>

                <button wire:click="ligar" class="px-5 py-2.5 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 transition">
                    <i class="fas fa-plug mr-2"></i>{{ $ligacao->webhook_secret ? __('Voltar a ligar') : __('Ligar agora') }}
                </button>

                @if($ligacao->webhook_secret)
                    <p class="text-xs text-green-700 mt-3">
                        <i class="fas fa-check-circle mr-1"></i>{{ __('Registado. O site entrega em :url', ['url' => $ligacao->urlDoWebhook()]) }}
                    </p>
                @endif
            </div>

            {{-- Mapa dos tipos de quarto --}}
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="font-black text-slate-800 mb-1">{{ __('Tipos de quarto') }}</h3>
                <p class="text-xs text-slate-500 mb-4">
                    {{ __('Sem correspondência, a reserva entra no primeiro tipo desta casa e fica dito na nota interna.') }}
                </p>

                @if(empty($tiposDoSite))
                    <p class="text-sm text-slate-400">{{ __('Escolha primeiro o hotel no passo 2.') }}</p>
                @else
                    <div class="space-y-3">
                        @foreach($tiposDoSite as $t)
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                <div class="sm:w-1/2 text-sm font-semibold text-slate-700">
                                    {{ $t['name'] ?? ('#' . ($t['id'] ?? '?')) }}
                                    <span class="text-xs font-normal text-slate-400">({{ __('no site') }})</span>
                                </div>
                                <div class="sm:w-1/2">
                                    <select wire:model="mapa_tipos.{{ $t['id'] ?? 0 }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                        <option value="">{{ __('— sem correspondência —') }}</option>
                                        @foreach($tiposLocal as $tl)
                                            <option value="{{ $tl->id }}">{{ $tl->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Coluna do estado ──────────────────────────────────────── --}}
        <div class="space-y-6">

            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="font-black text-slate-800 mb-4">{{ __('Como as reservas entram') }}</h3>

                <label class="flex items-center gap-3 mb-4">
                    <input wire:model="activa" type="checkbox" class="rounded text-indigo-600">
                    <span class="text-sm font-semibold text-slate-700">{{ __('Receber reservas do site') }}</span>
                </label>

                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Entram como') }}</label>
                <select wire:model="estado_inicial" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm mb-1">
                    <option value="pending">{{ __('Por confirmar') }}</option>
                    <option value="confirmed">{{ __('Confirmadas') }}</option>
                </select>
                <p class="text-xs text-slate-400 mb-4">{{ __('Escolha «confirmadas» se confia na disponibilidade do site.') }}</p>

                <label class="flex items-center gap-3 mb-4">
                    <input wire:model="criar_hospede" type="checkbox" class="rounded text-indigo-600">
                    <span class="text-sm text-slate-700">{{ __('Criar ficha de hóspede') }}</span>
                </label>

                <button wire:click="guardarOpcoes" class="w-full px-4 py-2.5 bg-slate-800 text-white rounded-xl font-semibold hover:bg-slate-900 transition">
                    {{ __('Guardar') }}
                </button>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="font-black text-slate-800 mb-4">{{ __('Estado') }}</h3>

                <dl class="text-sm space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">{{ __('Eventos recebidos') }}</dt>
                        <dd class="font-bold text-slate-800">{{ $ligacao->eventos_recebidos }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">{{ __('Último') }}</dt>
                        <dd class="font-bold text-slate-800">
                            {{ $ligacao->ultimo_evento_em?->diffForHumans() ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">{{ __('Hotel no site') }}</dt>
                        <dd class="font-bold text-slate-800">{{ $ligacao->property_name ?? '—' }}</dd>
                    </div>
                </dl>

                @if($ligacao->ultimo_erro)
                    <div class="mt-4 p-3 bg-red-50 text-red-800 rounded-xl text-xs">
                        {{ $ligacao->ultimo_erro }}
                    </div>
                @endif

                {{-- O que está do outro lado, dito sem rodeios: o site entrega
                     uma vez só e desliga o webhook ao fim de dez falhas. --}}
                <div class="mt-4 p-3 bg-amber-50 text-amber-900 rounded-xl text-xs">
                    <i class="fas fa-triangle-exclamation mr-1"></i>
                    {{ __('O site entrega cada reserva uma única vez e desliga a ligação ao fim de dez falhas seguidas. Depois de uma paragem longa, verifique no painel do KiandaStay se falta alguma reserva e volte a ligar aqui.') }}
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="font-black text-slate-800 mb-4">{{ __('Últimas do site') }}</h3>

                @forelse($ultimas as $r)
                    <div class="py-2 border-b border-slate-100 last:border-0">
                        <p class="text-sm font-bold text-slate-800">{{ $r->confirmation_code }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $r->guest?->name ?? __('Hóspede por confirmar') }} ·
                            {{ $r->check_in_date?->format('d/m') }} → {{ $r->check_out_date?->format('d/m') }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">{{ __('Ainda não entrou nenhuma.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
