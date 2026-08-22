{{-- Enviar SMS às empresas. O que este ecrã tem de fazer bem é mostrar, antes
     de qualquer clique irreversível, a quem vai e quantas mensagens custa. --}}
<div class="container mx-auto px-4 py-6">
    <div class="max-w-3xl mx-auto">

        <div class="mb-6 flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shrink-0">
                <i class="fas fa-comment-sms text-white text-lg"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('SMS às empresas') }}</h1>
                <p class="text-sm text-gray-500">Gateway padrão: {{ $gateway === 'telcosms' ? 'TelcoSMS Angola' : 'D7 Networks' }}</p>
            </div>
        </div>

        @unless($configurado)
            <div class="mb-6 rounded-xl border-l-4 border-amber-500 bg-amber-50 px-4 py-3">
                <p class="font-bold text-amber-900">{{ __('SMS não configurado') }}</p>
                <p class="text-sm text-amber-800">
                    {{ __('Não há configuração de SMS activa na plataforma. Configure-a antes de enviar.') }}
                    <a href="{{ route('superadmin.sms-settings') }}" class="underline font-semibold">{{ __('Abrir definições de SMS') }}</a>
                </p>
            </div>
        @endunless

        @if($resultado)
            <div class="mb-6 rounded-xl border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3">
                <p class="font-bold text-emerald-900">
                    {{ __('Enviado a :n empresa(s)', ['n' => $resultado['enviados']]) }}
                    <span class="font-normal text-emerald-700">
                        ({{ __(':n parte(s) de SMS', ['n' => $resultado['partes']]) }})
                    </span>
                </p>
                @if(!empty($resultado['falhados']))
                    {{-- Nomeadas uma a uma: "3 falharam" obriga a adivinhar quais. --}}
                    <p class="text-sm text-emerald-800 mt-1">
                        {{ __('Falharam:') }} {{ implode(', ', $resultado['falhados']) }}
                    </p>
                @endif
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="p-6 space-y-6">

                {{-- ---- Mensagem ---- --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Usar template existente') }}</label>
                    <select wire:model.live="template_id" class="mb-4 w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:outline-none">
                        <option value="">{{ __('Mensagem manual') }}</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                    <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Mensagem') }}</label>
                    {{-- O texto entre as etiquetas e não só o wire:model: num
                         textarea o valor não vem no HTML do servidor, e sem
                         isto a mensagem escrita desaparecia do ecrã sempre que
                         o componente se voltasse a desenhar. --}}
                    <textarea wire:model.live="mensagem" rows="4"
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-emerald-500 focus:outline-none"
                              placeholder="{{ __('Escreva o que quer dizer às empresas...') }}">{{ $mensagem }}</textarea>

                    <div class="flex items-center justify-between mt-2 text-xs">
                        <span class="text-gray-500">
                            {{ __(':n caracteres', ['n' => mb_strlen(trim($mensagem))]) }}
                        </span>
                        {{-- As partes e não os caracteres: é a parte que se paga,
                             e um acento faz cada parte encolher de 160 para 70. --}}
                        <span class="font-semibold {{ $this->partes() > 1 ? 'text-amber-600' : 'text-gray-500' }}">
                            {{ __(':n parte(s) por destinatário', ['n' => $this->partes()]) }}
                        </span>
                    </div>
                    @error('mensagem') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- ---- Quem recebe ---- --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Quem recebe') }}</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        @foreach(['todas' => __('Todas as empresas'), 'empresas' => __('Empresas escolhidas'), 'planos' => __('Por plano')] as $valor => $rotulo)
                            <label class="flex items-center gap-2 px-4 py-3 border-2 rounded-xl cursor-pointer transition
                                          {{ $publico === $valor ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:border-gray-300' }}">
                                <input type="radio" wire:model.live="publico" value="{{ $valor }}" class="text-emerald-600">
                                <span class="text-sm font-semibold text-gray-700">{{ $rotulo }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @if($publico === 'empresas')
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Empresas') }}</label>
                        <div class="max-h-64 overflow-y-auto border-2 border-gray-200 rounded-xl divide-y divide-gray-100">
                            @foreach($empresas as $e)
                                <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" wire:model.live="empresa_ids" value="{{ $e->id }}"
                                           @checked(in_array($e->id, $empresa_ids))
                                           class="rounded text-emerald-600">
                                    <span class="text-sm text-gray-800 flex-1 min-w-0 truncate">{{ $e->name }}</span>
                                    @if($e->phone)
                                        <span class="text-xs text-gray-400 shrink-0">{{ $e->phone }}</span>
                                    @else
                                        {{-- Dito aqui e não só no fim: escolher uma empresa
                                             sem telefone é escolher alguém que não vai receber. --}}
                                        <span class="text-xs text-amber-600 font-semibold shrink-0">{{ __('sem telefone') }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        @error('empresa_ids') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                @endif

                @if($publico === 'planos')
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Planos') }}</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($planos as $p)
                                <label class="flex items-center gap-2 px-4 py-2 border-2 rounded-xl cursor-pointer
                                              {{ in_array($p->id, $plano_ids) ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200' }}">
                                    <input type="checkbox" wire:model.live="plano_ids" value="{{ $p->id }}"
                                           @checked(in_array($p->id, $plano_ids)) class="rounded text-emerald-600">
                                    <span class="text-sm font-semibold text-gray-700">{{ $p->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('plano_ids') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                @endif

                {{-- ---- Contagem ---- --}}
                <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-3 flex items-center gap-6 text-sm">
                    <div>
                        <div class="text-gray-500 text-xs">{{ __('Empresas escolhidas') }}</div>
                        <div class="font-bold text-gray-900 text-lg">{{ $quantasAlvo }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500 text-xs">{{ __('Com telefone') }}</div>
                        <div class="font-bold text-emerald-600 text-lg">{{ $quantasPodem }}</div>
                    </div>
                    @if($quantasAlvo > $quantasPodem)
                        <div class="text-amber-700 text-xs leading-snug">
                            {{ __(':n não recebem por não terem telefone registado.', ['n' => $quantasAlvo - $quantasPodem]) }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- ---- Enviar ---- --}}
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                @if(!$porConfirmar)
                    <button wire:click="rever" wire:loading.attr="disabled"
                            class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold transition disabled:opacity-50">
                        <i class="fas fa-eye mr-2"></i>{{ __('Rever antes de enviar') }}
                    </button>
                @else
                    {{-- A confirmação diz o número, e não "tem a certeza?": o que
                         se está a confirmar é a quantidade, não a intenção. --}}
                    <div class="rounded-xl border-2 border-amber-300 bg-amber-50 p-4">
                        <p class="font-bold text-amber-900">
                            {{ __('Enviar a :empresas empresa(s), num total de :partes parte(s) de SMS?', [
                                'empresas' => $quantasPodem,
                                'partes'   => $quantasPodem * $this->partes(),
                            ]) }}
                        </p>
                        <p class="text-sm text-amber-800 mt-1">{{ __('Isto não se pode desfazer.') }}</p>

                        <div class="flex items-center gap-3 mt-4">
                            <button wire:click="enviar" wire:loading.attr="disabled"
                                    class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="enviar">
                                    <i class="fas fa-paper-plane mr-2"></i>{{ __('Enviar agora') }}
                                </span>
                                <span wire:loading wire:target="enviar">
                                    <i class="fas fa-circle-notch fa-spin mr-2"></i>{{ __('A enviar...') }}
                                </span>
                            </button>
                            <button wire:click="cancelar"
                                    class="px-5 py-3 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-100 transition">
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
