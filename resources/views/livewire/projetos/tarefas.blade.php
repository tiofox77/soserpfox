<div>
    @php
        $coresEstado = [
            'por_fazer' => 'bg-gray-100 text-gray-700',
            'em_curso' => 'bg-blue-100 text-blue-800',
            'bloqueada' => 'bg-red-100 text-red-800',
            'concluida' => 'bg-green-100 text-green-800',
            'cancelada' => 'bg-gray-200 text-gray-500',
        ];
        $coresPrioridade = [
            'baixa' => 'text-gray-400',
            'normal' => 'text-gray-500',
            'alta' => 'text-amber-600',
            'urgente' => 'text-red-600',
        ];
    @endphp

    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-violet-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-list-check text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">{{ __('Tarefas') }}</h2>
                    <p class="text-violet-100 text-sm">{{ __('Urgente primeiro, depois o prazo mais próximo') }}</p>
                </div>
            </div>
            <button wire:click="novaTarefa"
                    class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-6 py-3 rounded-xl font-semibold transition">
                <i class="fas fa-plus mr-2"></i>{{ __('Nova tarefa') }}
            </button>
        </div>
    </div>

    <!-- Resumo -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Abertas') }}</p>
            <p class="text-3xl font-bold text-blue-600">{{ $resumo['abertas'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Atrasadas') }}</p>
            <p class="text-3xl font-bold {{ $resumo['atrasadas'] > 0 ? 'text-red-600' : 'text-gray-300' }}">{{ $resumo['atrasadas'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Minhas') }}</p>
            <p class="text-3xl font-bold text-violet-600">{{ $resumo['minhas'] }}</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl shadow-lg p-4 mb-6 flex flex-wrap gap-3 items-center">
        <div class="flex-1 min-w-[200px] relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" wire:model.live.debounce.400ms="procurar"
                   placeholder="{{ __('Título ou descrição...') }}"
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500">
        </div>
        <select wire:model.live="projetoId" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500">
            <option value="">{{ __('Todos os projetos') }}</option>
            @foreach($projetos as $pr)
                <option value="{{ $pr->id }}">{{ $pr->nome }}</option>
            @endforeach
        </select>
        <select wire:model.live="estado" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500">
            <option value="abertas">{{ __('Abertas') }}</option>
            <option value="todas">{{ __('Todas') }}</option>
            @foreach(\App\Models\Projetos\Tarefa::ESTADOS as $chave => $rotulo)
                <option value="{{ $chave }}">{{ __($rotulo) }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 px-3 py-2.5 cursor-pointer">
            <input type="checkbox" wire:model.live="soMinhas" class="rounded text-violet-600 focus:ring-violet-500">
            <span class="text-sm font-medium text-gray-700">{{ __('Só as minhas') }}</span>
        </label>
    </div>

    <!-- Lista -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        @forelse($tarefas as $t)
            <div class="group px-6 py-4 border-b border-gray-100 hover:bg-gray-50 transition flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[240px]">
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($t->prioridade !== 'normal')
                            <i class="fas fa-flag text-xs {{ $coresPrioridade[$t->prioridade] }}"
                               title="{{ __($t->prioridadeRotulo()) }}"></i>
                        @endif
                        <span class="font-semibold text-gray-900 {{ $t->estado === 'concluida' ? 'line-through text-gray-400' : '' }}">
                            {{ $t->titulo }}
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $coresEstado[$t->estado] ?? 'bg-gray-100' }}">
                            {{ __($t->estadoRotulo()) }}
                        </span>
                        @if($t->estaAtrasada())
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                <i class="fas fa-clock mr-1"></i>{{ __('Atrasada') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        <span class="font-mono text-xs">{{ $t->projeto?->codigo }}</span>
                        · {{ $t->projeto?->nome }}
                        @if($t->responsavel) · {{ $t->responsavel->name }} @endif
                        @if($t->prazo) · {{ __('prazo') }} {{ $t->prazo->format('d/m/Y') }} @endif
                        @if($t->horas_estimadas) · {{ rtrim(rtrim(number_format((float) $t->horas_estimadas, 2, ',', '.'), '0'), ',') }}h {{ __('estimadas') }} @endif
                    </p>
                </div>

                <div class="flex items-center gap-2 lg:opacity-0 lg:group-hover:opacity-100 transition">
                    <button wire:click="editar({{ $t->id }})"
                            class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg" title="{{ __('Editar') }}">
                        <i class="fas fa-pen"></i>
                    </button>
                    @if($t->estado === 'por_fazer')
                        <button wire:click="mudarEstado({{ $t->id }}, 'em_curso')"
                                class="px-3 py-2 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg">
                            <i class="fas fa-play mr-1"></i>{{ __('Começar') }}
                        </button>
                    @endif
                    @if(in_array($t->estado, ['por_fazer', 'em_curso', 'bloqueada']))
                        <button wire:click="mudarEstado({{ $t->id }}, 'concluida')"
                                class="px-3 py-2 text-xs font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg">
                            <i class="fas fa-check mr-1"></i>{{ __('Concluir') }}
                        </button>
                    @endif
                    @if($t->estado === 'em_curso')
                        <button wire:click="mudarEstado({{ $t->id }}, 'bloqueada')"
                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg" title="{{ __('Bloquear') }}">
                            <i class="fas fa-hand"></i>
                        </button>
                    @endif
                    @if($t->estado === 'concluida')
                        <button wire:click="mudarEstado({{ $t->id }}, 'por_fazer')"
                                class="p-2 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg" title="{{ __('Reabrir') }}">
                            <i class="fas fa-rotate-left"></i>
                        </button>
                    @endif
                    @if(!in_array($t->estado, ['cancelada', 'concluida']))
                        <button wire:click="mudarEstado({{ $t->id }}, 'cancelada')"
                                class="p-2 text-gray-300 hover:text-gray-600 hover:bg-gray-100 rounded-lg" title="{{ __('Cancelar') }}">
                            <i class="fas fa-ban"></i>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <i class="fas fa-list-check text-5xl text-gray-200 mb-4"></i>
                <p class="text-gray-500 font-semibold">{{ __('Sem tarefas por aqui') }}</p>
                <p class="text-sm text-gray-400 mt-1">{{ __('Ou está tudo feito, ou o filtro está apertado demais.') }}</p>
            </div>
        @endforelse

        @if($tarefas->hasPages())
            <div class="px-6 py-4">{{ $tarefas->links() }}</div>
        @endif
    </div>

    <!-- ─── Modal: criar/editar ─────────────────────────────────────────── -->
    @if($showForm)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-8">
                <div class="px-6 py-4 bg-gradient-to-r from-violet-600 to-purple-600 rounded-t-2xl text-white flex items-center justify-between">
                    <h3 class="text-lg font-bold">
                        <i class="fas fa-list-check mr-2"></i>
                        {{ $editandoId ? __('Editar tarefa') : __('Nova tarefa') }}
                    </h3>
                    <button wire:click="$set('showForm', false)" class="text-white/80 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            {{ __('Projeto') }} <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="formProjetoId" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                            <option value="">{{ __('— escolher —') }}</option>
                            @foreach($projetos as $pr)
                                <option value="{{ $pr->id }}">{{ $pr->codigo }} · {{ $pr->nome }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            {{ __('Título') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" wire:model="titulo" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                    </div>

                    <div class="grid md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Responsável') }}</label>
                            <select wire:model="formResponsavelId" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                                <option value="">{{ __('— ninguém —') }}</option>
                                @foreach($utilizadores as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Prioridade') }}</label>
                            <select wire:model="prioridade" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                                @foreach(\App\Models\Projetos\Tarefa::PRIORIDADES as $chave => $rotulo)
                                    <option value="{{ $chave }}">{{ __($rotulo) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Prazo') }}</label>
                            <input type="date" wire:model="prazo" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Horas estimadas') }}</label>
                            <input type="number" step="0.25" min="0" wire:model="horasEstimadas"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Descrição') }}</label>
                        <textarea wire:model="formDescricao" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl"></textarea>
                    </div>
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
</div>
