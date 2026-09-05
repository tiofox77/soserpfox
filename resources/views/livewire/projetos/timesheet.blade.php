<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-violet-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">{{ __('Folha de Horas') }}</h2>
                    <p class="text-violet-100 text-sm">
                        {{ $inicio->format('d/m') }} — {{ $fim->format('d/m/Y') }}
                    </p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-3xl font-bold">{{ number_format($totalSemana, 2, ',', '.') }}h</p>
                <p class="text-violet-100 text-xs">
                    {{ number_format($facturavelSemana, 2, ',', '.') }}h {{ __('facturáveis') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Navegação da semana -->
    <div class="bg-white rounded-2xl shadow-lg p-4 mb-6 flex items-center justify-between gap-3">
        <button wire:click="semanaAnterior"
                class="px-4 py-2.5 text-gray-600 font-semibold hover:text-violet-700 hover:bg-violet-50 rounded-xl">
            <i class="fas fa-chevron-left mr-1"></i>{{ __('Semana anterior') }}
        </button>
        <button wire:click="semanaActual"
                class="px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50 rounded-xl">
            {{ __('Esta semana') }}
        </button>
        <button wire:click="semanaSeguinte"
                class="px-4 py-2.5 text-gray-600 font-semibold hover:text-violet-700 hover:bg-violet-50 rounded-xl">
            {{ __('Semana seguinte') }}<i class="fas fa-chevron-right ml-1"></i>
        </button>
    </div>

    <!-- Dias -->
    <div class="space-y-3">
        @foreach($porDia as $dia)
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden {{ $dia['hoje'] ? 'ring-2 ring-violet-400' : '' }}">
                <div class="px-6 py-3 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-baseline gap-2">
                        <span class="font-bold text-gray-900">{{ $dia['data']->translatedFormat('l') }}</span>
                        <span class="text-sm text-gray-400">{{ $dia['data']->format('d/m') }}</span>
                        @if($dia['hoje'])
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-100 text-violet-700">{{ __('hoje') }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        @if($dia['total'] > 0)
                            <span class="font-bold text-gray-900">{{ number_format($dia['total'], 2, ',', '.') }}h</span>
                        @endif
                        <button wire:click="lancarEm('{{ $dia['data']->format('Y-m-d') }}')"
                                class="px-3 py-1.5 text-xs font-semibold text-violet-700 bg-violet-50 hover:bg-violet-100 rounded-lg">
                            <i class="fas fa-plus mr-1"></i>{{ __('Lançar') }}
                        </button>
                    </div>
                </div>

                @forelse($dia['linhas'] as $l)
                    <div class="group px-6 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50 flex flex-wrap items-center gap-3">
                        <div class="flex-1 min-w-[200px]">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $l->projeto?->nome }}
                                @if($l->tarefa)
                                    <span class="text-gray-400">·</span>
                                    <span class="text-gray-600">{{ $l->tarefa->titulo }}</span>
                                @endif
                            </p>
                            @if($l->descricao)
                                <p class="text-xs text-gray-500">{{ $l->descricao }}</p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            @if(!$l->facturavel)
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">
                                    {{ __('não facturável') }}
                                </span>
                            @endif
                            @if($l->jaFacturada())
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700"
                                      title="{{ __('Já sustenta uma factura emitida') }}">
                                    <i class="fas fa-lock mr-1"></i>{{ __('facturada') }}
                                </span>
                            @endif
                            <span class="font-bold text-gray-900 w-16 text-right">
                                {{ number_format((float) $l->horas, 2, ',', '.') }}h
                            </span>
                        </div>

                        <div class="flex items-center gap-1 lg:opacity-0 lg:group-hover:opacity-100 transition">
                            @if(!$l->jaFacturada())
                                <button wire:click="editar({{ $l->id }})"
                                        class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg">
                                    <i class="fas fa-pen text-sm"></i>
                                </button>
                                <button wire:click="$set('confirmarApagarId', {{ $l->id }})"
                                        class="p-2 text-gray-300 hover:text-red-600 hover:bg-red-50 rounded-lg">
                                    <i class="fas fa-trash text-sm"></i>
                                </button>
                            @else
                                <span class="p-2 text-gray-200"><i class="fas fa-lock text-sm"></i></span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-4 text-sm text-gray-300">{{ __('Sem horas neste dia.') }}</div>
                @endforelse
            </div>
        @endforeach
    </div>

    <!-- ─── Modal: lançar/corrigir ──────────────────────────────────────── -->
    @if($showForm)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg my-8">
                <div class="px-6 py-4 bg-gradient-to-r from-violet-600 to-purple-600 rounded-t-2xl text-white flex items-center justify-between">
                    <h3 class="text-lg font-bold">
                        <i class="fas fa-clock mr-2"></i>
                        {{ $editandoId ? __('Corrigir lançamento') : __('Lançar horas') }}
                    </h3>
                    <button wire:click="$set('showForm', false)" class="text-white/80 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            {{ __('Projeto') }} <span class="text-red-500">*</span>
                        </label>
                        <select wire:model.live="projetoId" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                            <option value="">{{ __('— escolher —') }}</option>
                            @foreach($projetos as $pr)
                                <option value="{{ $pr->id }}">{{ $pr->codigo }} · {{ $pr->nome }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($projetoId && $this->tarefasDoProjeto->isNotEmpty())
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Tarefa') }}</label>
                            <select wire:model="tarefaId" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                                <option value="">{{ __('— sem tarefa concreta —') }}</option>
                                @foreach($this->tarefasDoProjeto as $t)
                                    <option value="{{ $t->id }}">{{ $t->titulo }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Dia') }}</label>
                            <input type="date" wire:model="data" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                {{ __('Horas') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="number" step="0.25" min="0" max="24" wire:model="horas"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('O que fez') }}</label>
                        <input type="text" wire:model="descricao" placeholder="{{ __('Em duas palavras') }}"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="facturavel" class="rounded text-violet-600 focus:ring-violet-500">
                        <span class="text-sm font-medium text-gray-700">{{ __('Facturável ao cliente') }}</span>
                    </label>
                </div>

                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                    <button wire:click="$set('showForm', false)"
                            class="px-5 py-2.5 text-gray-600 font-semibold hover:text-gray-900">{{ __('Cancelar') }}</button>
                    <button wire:click="guardar" wire:loading.attr="disabled"
                            class="px-6 py-2.5 bg-gradient-to-r from-violet-600 to-purple-600 text-white rounded-xl font-semibold hover:from-violet-700 hover:to-purple-700 shadow-lg">
                        <i class="fas fa-save mr-2"></i>{{ __('Guardar') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ─── Modal: apagar ───────────────────────────────────────────────── -->
    @if($confirmarApagarId)
        <div class="fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-trash text-2xl text-red-600"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">{{ __('Apagar este lançamento?') }}</h3>
                <p class="text-sm text-gray-500 mt-2">
                    {{ __('Só se apaga o que ainda não foi facturado.') }}
                </p>
                <div class="flex justify-center gap-3 mt-6">
                    <button wire:click="$set('confirmarApagarId', null)"
                            class="px-5 py-2.5 text-gray-600 font-semibold hover:text-gray-900">{{ __('Voltar') }}</button>
                    <button wire:click="apagar"
                            class="px-6 py-2.5 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700">
                        {{ __('Apagar') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
