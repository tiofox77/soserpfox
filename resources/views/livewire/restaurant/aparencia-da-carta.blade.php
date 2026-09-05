<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-orange-600 to-amber-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-palette text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">{{ __('Aparência da Carta') }}</h2>
                    <p class="text-orange-100 text-sm">{{ __('O que faz a carta parecer a sua casa') }}</p>
                </div>
            </div>
            @if($urlDaCarta)
                <a href="{{ $urlDaCarta }}" target="_blank" rel="noopener"
                   class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-5 py-2.5 rounded-xl font-semibold transition">
                    <i class="fas fa-arrow-up-right-from-square mr-2"></i>{{ __('Abrir a carta') }}
                </a>
            @endif
        </div>
    </div>

    @unless($urlDaCarta)
        <div class="mb-6 bg-amber-50 border border-amber-200 rounded-2xl p-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-semibold text-amber-900">
                    <i class="fas fa-circle-info mr-1"></i>{{ __('A carta ainda não está publicada') }}
                </p>
                <p class="text-sm text-amber-700 mt-1">
                    {{ __('Pode preparar a aparência à vontade — mas só depois de a publicar é que há endereço para mostrar ao cliente (e pré-visualização aqui).') }}
                </p>
            </div>
            <a href="{{ route('restaurant.settings') }}"
               class="px-5 py-2.5 bg-amber-600 text-white rounded-xl font-semibold hover:bg-amber-700 shrink-0">
                {{ __('Publicar a carta') }}
            </a>
        </div>
    @endunless

    <div class="grid lg:grid-cols-5 gap-6">
        <!-- ── Controlos ────────────────────────────────────────────────── -->
        <div class="lg:col-span-3 space-y-6">
            <!-- Identidade -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                    <h3 class="font-bold text-gray-900"><i class="fas fa-signature text-orange-500 mr-2"></i>{{ __('Identidade') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Título') }}</label>
                        <input type="text" wire:model="titulo" placeholder="{{ __('A nossa carta') }}"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500">
                        @error('titulo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Uma linha sobre a casa') }}</label>
                        <textarea wire:model="descricao" rows="2"
                                  placeholder="{{ __('Cozinha angolana, à lenha, desde 1998.') }}"
                                  class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500"></textarea>
                        @error('descricao') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- Imagens -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                    <h3 class="font-bold text-gray-900"><i class="fas fa-image text-orange-500 mr-2"></i>{{ __('Capa e logótipo') }}</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('Capa') }}</label>

                        @if($capaNova)
                            <img src="{{ $capaNova->temporaryUrl() }}" alt=""
                                 class="w-full h-40 object-cover rounded-xl mb-3 ring-2 ring-orange-300">
                        @elseif($capa)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($capa) }}" alt=""
                                 class="w-full h-40 object-cover rounded-xl mb-3">
                        @else
                            <div class="w-full h-40 rounded-xl mb-3 bg-gray-50 border-2 border-dashed border-gray-200 flex items-center justify-center">
                                <p class="text-sm text-gray-400">
                                    <i class="fas fa-image mr-1"></i>{{ __('Sem capa — a carta abre só com o título') }}
                                </p>
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center gap-3">
                            <input type="file" wire:model="capaNova" accept="image/*"
                                   class="text-sm text-gray-600 file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-orange-50 file:text-orange-700 file:font-semibold hover:file:bg-orange-100">
                            @if($capa)
                                <button wire:click="removerCapa"
                                        class="text-sm font-semibold text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash mr-1"></i>{{ __('Remover') }}
                                </button>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-2">{{ __('Uma fotografia larga da sala ou do prato da casa. Até 4 MB.') }}</p>
                        @error('capaNova') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="capaNova" class="text-xs text-orange-600 mt-1">
                            <i class="fas fa-spinner fa-spin mr-1"></i>{{ __('a carregar...') }}
                        </div>
                    </div>

                    <div class="flex items-center gap-4 pt-2 border-t border-gray-100">
                        @if($logoNovo)
                            <img src="{{ $logoNovo->temporaryUrl() }}" alt=""
                                 class="w-16 h-16 object-contain rounded-xl bg-gray-50 ring-2 ring-orange-300">
                        @elseif($logo)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($logo) }}" alt=""
                                 class="w-16 h-16 object-contain rounded-xl bg-gray-50">
                        @else
                            <div class="w-16 h-16 rounded-xl bg-gray-50 border-2 border-dashed border-gray-200 flex items-center justify-center">
                                <i class="fas fa-utensils text-gray-300"></i>
                            </div>
                        @endif
                        <div class="flex-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Logótipo') }}</label>
                            <input type="file" wire:model="logoNovo" accept="image/*"
                                   class="text-sm text-gray-600 file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-orange-50 file:text-orange-700 file:font-semibold hover:file:bg-orange-100">
                            @error('logoNovo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cores e tema -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                    <h3 class="font-bold text-gray-900"><i class="fas fa-swatchbook text-orange-500 mr-2"></i>{{ __('Cores e tema') }}</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Cor principal') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model.live="cor" class="h-11 w-14 rounded-lg border border-gray-200 cursor-pointer">
                                <input type="text" wire:model.live="cor"
                                       class="flex-1 px-3 py-2.5 border border-gray-200 rounded-xl font-mono text-sm uppercase">
                            </div>
                            @error('cor') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Cor de acento') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model.live="corAcento" class="h-11 w-14 rounded-lg border border-gray-200 cursor-pointer">
                                <input type="text" wire:model.live="corAcento"
                                       class="flex-1 px-3 py-2.5 border border-gray-200 rounded-xl font-mono text-sm uppercase">
                            </div>
                            <p class="text-xs text-gray-400 mt-1">{{ __('Usada nos destaques e no botão de pedir.') }}</p>
                            @error('corAcento') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('Tema') }}</label>
                        <div class="flex gap-3">
                            @foreach(\App\Livewire\Restaurant\AparenciaDaCarta::TEMAS as $chave => $rotulo)
                                <button type="button" wire:click="$set('tema', '{{ $chave }}')"
                                        class="flex-1 px-4 py-3 rounded-xl border-2 font-semibold transition
                                               {{ $tema === $chave ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-200 text-gray-600 hover:border-gray-300' }}">
                                    <i class="fas fa-{{ $chave === 'escuro' ? 'moon' : 'sun' }} mr-2"></i>{{ __($rotulo) }}
                                </button>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-400 mt-2">
                            {{ __('Uma carta de jantar pede fundo escuro; uma pastelaria pede luz.') }}
                        </p>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer pt-2 border-t border-gray-100">
                        <input type="checkbox" wire:model="mostrarPrecos" class="rounded text-orange-600 focus:ring-orange-500">
                        <span class="text-sm font-medium text-gray-700">{{ __('Mostrar preços na carta') }}</span>
                    </label>
                </div>
            </div>

            <!-- Destaques -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900"><i class="fas fa-star text-orange-500 mr-2"></i>{{ __('Pratos em destaque') }}</h3>
                    <span class="text-xs font-semibold text-gray-400">
                        {{ $this->destaques->count() }}/{{ \App\Models\Restaurant\MenuDestaque::MAXIMO }}
                    </span>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Título da fila') }}</label>
                        <input type="text" wire:model="tituloDestaques" placeholder="{{ __('Sugestões da casa') }}"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500">
                    </div>

                    @if($this->destaques->isNotEmpty())
                        <div class="border border-gray-200 rounded-xl divide-y divide-gray-100">
                            @foreach($this->destaques as $i => $d)
                                <div class="flex items-center gap-3 px-4 py-2.5">
                                    <div class="flex flex-col">
                                        <button wire:click="mover({{ $d->id }}, 'cima')" @disabled($i === 0)
                                                class="text-gray-300 hover:text-orange-600 disabled:opacity-30 disabled:hover:text-gray-300">
                                            <i class="fas fa-caret-up"></i>
                                        </button>
                                        <button wire:click="mover({{ $d->id }}, 'baixo')" @disabled($i === $this->destaques->count() - 1)
                                                class="text-gray-300 hover:text-orange-600 disabled:opacity-30 disabled:hover:text-gray-300">
                                            <i class="fas fa-caret-down"></i>
                                        </button>
                                    </div>
                                    @if($d->produto?->image_url)
                                        <img src="{{ $d->produto->image_url }}" alt="" class="w-10 h-10 rounded-lg object-cover">
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $d->produto?->name }}</p>
                                        <p class="text-xs text-gray-400">{{ number_format((float) $d->produto?->price, 2, ',', '.') }}</p>
                                    </div>
                                    <button wire:click="retirarDestaque({{ $d->id }})"
                                            class="p-2 text-gray-300 hover:text-red-600 hover:bg-red-50 rounded-lg">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 text-center py-4">
                            {{ __('Sem destaques — a carta abre logo pelas categorias.') }}
                        </p>
                    @endif

                    <div class="bg-gray-50 rounded-xl p-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('Acrescentar um prato') }}</label>
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.300ms="procurar"
                                   placeholder="{{ __('Nome ou código do prato...') }}"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                            @if($this->candidatos->isNotEmpty())
                                <div class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-xl max-h-64 overflow-y-auto">
                                    @foreach($this->candidatos as $c)
                                        <button type="button" wire:click="destacar({{ $c->id }})"
                                                class="w-full text-left px-4 py-2.5 hover:bg-orange-50 border-b border-gray-50 last:border-0 flex items-center justify-between">
                                            <span class="font-medium text-gray-900">{{ $c->name }}</span>
                                            <span class="text-xs text-gray-400">{{ number_format((float) $c->price, 2, ',', '.') }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button wire:click="guardar" wire:loading.attr="disabled"
                        class="px-8 py-3 bg-gradient-to-r from-orange-600 to-amber-600 text-white rounded-xl font-semibold hover:from-orange-700 hover:to-amber-700 shadow-lg">
                    <i class="fas fa-save mr-2"></i>{{ __('Guardar aparência') }}
                </button>
            </div>
        </div>

        <!-- ── Pré-visualização ─────────────────────────────────────────── -->
        <div class="lg:col-span-2">
            <div class="lg:sticky lg:top-6 space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-bold text-gray-700">
                        <i class="fas fa-mobile-screen text-orange-500 mr-1"></i>{{ __('Como o cliente vê') }}
                    </p>
                    @if($urlDaCarta)
                        <span class="text-xs text-gray-400">{{ __('actualiza ao guardar') }}</span>
                    @endif
                </div>

                @if($urlDaCarta)
                    {{-- A carta VERDADEIRA num iframe. Uma imitação divergiria do
                         original ao primeiro retoque, e o dono decidiria por uma
                         coisa que não é a que o cliente vai ver. --}}
                    <div class="mx-auto w-full max-w-[380px] rounded-[2rem] bg-gray-900 p-2.5 shadow-2xl">
                        <div class="rounded-[1.6rem] overflow-hidden bg-white">
                            <iframe src="{{ $urlDaCarta }}?v={{ $versaoPreview }}"
                                    title="{{ __('Pré-visualização da carta') }}"
                                    class="w-full border-0" style="height: 640px;"
                                    loading="lazy"></iframe>
                        </div>
                    </div>
                @else
                    <div class="mx-auto w-full max-w-[380px] rounded-[2rem] bg-gray-100 border-2 border-dashed border-gray-300 p-8 text-center"
                         style="height: 400px;">
                        <div class="h-full flex flex-col items-center justify-center">
                            <i class="fas fa-mobile-screen text-4xl text-gray-300 mb-3"></i>
                            <p class="text-sm text-gray-500 font-semibold">{{ __('Sem pré-visualização') }}</p>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ __('A carta ainda não está publicada.') }}
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
