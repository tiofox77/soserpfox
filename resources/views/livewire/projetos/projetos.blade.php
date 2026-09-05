<div>
    @php
        $coresEstado = [
            'rascunho' => 'bg-gray-100 text-gray-700',
            'activo' => 'bg-green-100 text-green-800',
            'em_pausa' => 'bg-amber-100 text-amber-800',
            'concluido' => 'bg-blue-100 text-blue-800',
            'cancelado' => 'bg-gray-200 text-gray-600',
        ];
    @endphp

    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-violet-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-diagram-project text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">{{ __('Projetos') }}</h2>
                    <p class="text-violet-100 text-sm">{{ __('O trabalho, o orçamento, e as horas que lá foram') }}</p>
                </div>
            </div>
            <button wire:click="novoProjeto"
                    class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-6 py-3 rounded-xl font-semibold transition">
                <i class="fas fa-plus mr-2"></i>{{ __('Novo projeto') }}
            </button>
        </div>
    </div>

    <!-- Resumo -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Activos') }}</p>
            <p class="text-3xl font-bold text-green-600">{{ $resumo['activos'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Concluídos') }}</p>
            <p class="text-3xl font-bold text-blue-600">{{ $resumo['concluidos'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase">{{ __('Total') }}</p>
            <p class="text-3xl font-bold text-gray-900">{{ $resumo['total'] }}</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl shadow-lg p-4 mb-6 flex flex-wrap gap-3">
        <div class="flex-1 min-w-[220px] relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" wire:model.live.debounce.400ms="procurar"
                   placeholder="{{ __('Nome ou código...') }}"
                   class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
        </div>
        <select wire:model.live="estado" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-violet-500">
            <option value="todos">{{ __('Todos os estados') }}</option>
            @foreach(\App\Models\Projetos\Projeto::ESTADOS as $chave => $rotulo)
                <option value="{{ $chave }}">{{ __($rotulo) }}</option>
            @endforeach
        </select>
    </div>

    <!-- Lista -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        @forelse($projetos as $p)
            <div class="group px-6 py-4 border-b border-gray-100 hover:bg-gray-50 transition flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[240px]">
                    <div class="flex items-center gap-2 flex-wrap">
                        <button wire:click="$set('verId', {{ $p->id }})" class="font-bold text-gray-900 hover:text-violet-700">
                            {{ $p->nome }}
                        </button>
                        <span class="text-xs text-gray-400 font-mono">{{ $p->codigo }}</span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $coresEstado[$p->estado] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ __($p->estadoRotulo()) }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $p->cliente?->name ?? __('Projeto interno') }}
                        @if($p->responsavel) · {{ $p->responsavel->name }} @endif
                        @if($p->data_fim_prevista) · {{ __('até') }} {{ $p->data_fim_prevista->format('d/m/Y') }} @endif
                    </p>
                </div>

                <div class="text-right min-w-[120px]">
                    @if($p->orcamento)
                        <p class="font-bold text-gray-900">{{ valorProtegido((float) $p->orcamento, 'projetos.facturar', 'projetos.gerir') }}</p>
                        <p class="text-xs text-gray-400">{{ __('orçamento') }}</p>
                    @else
                        <p class="text-sm text-gray-300">{{ __('sem orçamento') }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-2 lg:opacity-0 lg:group-hover:opacity-100 transition">
                    <button wire:click="$set('verId', {{ $p->id }})"
                            class="p-2 text-gray-400 hover:text-violet-600 hover:bg-violet-50 rounded-lg" title="{{ __('Ver') }}">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button wire:click="editar({{ $p->id }})"
                            class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg" title="{{ __('Editar') }}">
                        <i class="fas fa-pen"></i>
                    </button>
                    @if($p->estado === 'rascunho')
                        <button wire:click="mudarEstado({{ $p->id }}, 'activo')"
                                class="px-3 py-2 text-xs font-semibold text-white bg-green-600 hover:bg-green-700 rounded-lg">
                            <i class="fas fa-play mr-1"></i>{{ __('Arrancar') }}
                        </button>
                    @endif
                    @if($p->estado === 'activo')
                        <button wire:click="mudarEstado({{ $p->id }}, 'em_pausa')"
                                class="px-3 py-2 text-xs font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-lg">
                            <i class="fas fa-pause mr-1"></i>{{ __('Pausar') }}
                        </button>
                        <button wire:click="mudarEstado({{ $p->id }}, 'concluido')"
                                class="px-3 py-2 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg">
                            <i class="fas fa-flag-checkered mr-1"></i>{{ __('Concluir') }}
                        </button>
                    @endif
                    @if($p->estado === 'em_pausa')
                        <button wire:click="mudarEstado({{ $p->id }}, 'activo')"
                                class="px-3 py-2 text-xs font-semibold text-green-700 bg-green-50 hover:bg-green-100 rounded-lg">
                            <i class="fas fa-play mr-1"></i>{{ __('Retomar') }}
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <i class="fas fa-diagram-project text-5xl text-gray-200 mb-4"></i>
                <p class="text-gray-500 font-semibold">{{ __('Ainda não há projetos') }}</p>
                <p class="text-sm text-gray-400 mt-1">{{ __('Crie o primeiro — as tarefas e as horas vêm depois.') }}</p>
            </div>
        @endforelse

        @if($projetos->hasPages())
            <div class="px-6 py-4">{{ $projetos->links() }}</div>
        @endif
    </div>

    <!-- ─── Modal: criar/editar ─────────────────────────────────────────── -->
    @if($showForm)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-8">
                <div class="px-6 py-4 bg-gradient-to-r from-violet-600 to-purple-600 rounded-t-2xl text-white flex items-center justify-between">
                    <h3 class="text-lg font-bold">
                        <i class="fas fa-diagram-project mr-2"></i>
                        {{ $editandoId ? __('Editar projeto') : __('Novo projeto') }}
                    </h3>
                    <button wire:click="$set('showForm', false)" class="text-white/80 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            {{ __('Nome do projeto') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" wire:model="nome" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Cliente') }}</label>
                            <select wire:model="clientId" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                                <option value="">{{ __('— projeto interno —') }}</option>
                                @foreach($clientes as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">{{ __('Sem cliente não há a quem facturar horas.') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Responsável') }}</label>
                            <select wire:model="responsavelId" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                                <option value="">{{ __('— ninguém —') }}</option>
                                @foreach($utilizadores as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Início') }}</label>
                            <input type="date" wire:model="dataInicio" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Fim previsto') }}</label>
                            <input type="date" wire:model="dataFimPrevista" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Orçamento') }}</label>
                            <input type="number" step="0.01" min="0" wire:model="orcamento"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Preço por hora') }}</label>
                            <input type="number" step="0.01" min="0" wire:model="valorHora"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl">
                            <p class="text-xs text-gray-400 mt-1">
                                {{ __('Cada hora lançada congela este valor. Mudar aqui só afecta as próximas.') }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ __('Descrição') }}</label>
                        <textarea wire:model="descricao" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl"></textarea>
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

    <!-- ─── Modal: detalhe ──────────────────────────────────────────────── -->
    @if($verId && $this->detalhe)
        @php
            $d = $this->detalhe;
            $p = $d['projeto'];
            $pf = $d['porFacturar'];
        @endphp
        <div class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-8">
                <div class="px-6 py-4 bg-gradient-to-r from-violet-600 to-purple-600 rounded-t-2xl text-white flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold">{{ $p->nome }}</h3>
                        <p class="text-violet-100 text-sm">
                            {{ $p->codigo }} · {{ __($p->estadoRotulo()) }}
                            @if($p->cliente) · {{ $p->cliente->name }} @endif
                        </p>
                    </div>
                    <button wire:click="$set('verId', null)" class="text-white/80 hover:text-white">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="p-6 space-y-5">
                    <!-- Orçamento -->
                    <div class="bg-gray-50 rounded-xl p-5">
                        <div class="flex items-baseline justify-between mb-3">
                            <p class="text-sm font-semibold text-gray-700">{{ __('Orçamento') }}</p>
                            @if($d['percentagem'] !== null)
                                <p class="text-sm font-bold {{ $d['percentagem'] > 100 ? 'text-red-600' : 'text-gray-900' }}">
                                    {{ number_format($d['percentagem'], 1, ',', '.') }}%
                                </p>
                            @endif
                        </div>
                        @if($p->orcamento)
                            <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                                <div class="h-2.5 rounded-full {{ $d['percentagem'] > 100 ? 'bg-red-500' : 'bg-violet-500' }}"
                                     style="width: {{ min(100, $d['percentagem'] ?? 0) }}%"></div>
                            </div>
                            <p class="text-sm text-gray-600">
                                {{ valorProtegido($d['consumido'], 'projetos.facturar', 'projetos.gerir') }}
                                {{ __('de') }} {{ valorProtegido((float) $p->orcamento, 'projetos.facturar', 'projetos.gerir') }}
                            </p>
                        @else
                            <p class="text-sm text-gray-400">
                                {{ __('Sem orçamento definido — consumido até agora:') }}
                                <strong class="text-gray-700">{{ valorProtegido($d['consumido'], 'projetos.facturar', 'projetos.gerir') }}</strong>
                            </p>
                        @endif
                    </div>

                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div class="bg-white border border-gray-200 rounded-xl p-4">
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($d['horas'], 2, ',', '.') }}</p>
                            <p class="text-xs text-gray-500 uppercase font-semibold">{{ __('horas') }}</p>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-xl p-4">
                            <p class="text-2xl font-bold text-gray-900">{{ $d['tarefas']['abertas'] }}<span class="text-gray-300 text-lg">/{{ $d['tarefas']['total'] }}</span></p>
                            <p class="text-xs text-gray-500 uppercase font-semibold">{{ __('tarefas abertas') }}</p>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-xl p-4">
                            <p class="text-2xl font-bold {{ $pf['valor'] > 0 ? 'text-amber-600' : 'text-gray-300' }}">
                                {{ valorProtegido($pf['valor'], 'projetos.facturar', 'projetos.gerir') }}
                            </p>
                            <p class="text-xs text-gray-500 uppercase font-semibold">{{ __('por facturar') }}</p>
                        </div>
                    </div>

                    {{-- O laço fechado: o que já saiu para cobrança, e em que
                         documentos. Sem isto, facturava-se e o projeto ficava
                         sem resposta para «o que já cobrei daqui?». --}}
                    @if($d['facturado'] > 0 || $d['facturas']->isNotEmpty())
                        <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                                <p class="font-semibold text-emerald-900">
                                    <i class="fas fa-circle-check mr-1"></i>{{ __('Já facturado') }}
                                </p>
                                <p class="text-emerald-900">
                                    <strong class="text-lg">{{ valorProtegido($d['facturado'], 'projetos.facturar', 'projetos.gerir') }}</strong>
                                    <span class="text-xs text-emerald-700 ml-1">
                                        · {{ number_format($d['horasFacturadas'], 2, ',', '.') }} {{ __('horas') }}
                                        · {{ $d['facturas']->count() }} {{ trans_choice('documento|documentos', $d['facturas']->count()) }}
                                    </span>
                                </p>
                            </div>

                            @if($d['facturas']->isNotEmpty())
                                <div class="space-y-1.5">
                                    @foreach($d['facturas'] as $factura)
                                        <div class="flex flex-wrap items-center justify-between gap-2 bg-white rounded-lg px-3 py-2 border border-emerald-100">
                                            <span class="font-mono text-sm text-gray-800">{{ $factura->invoice_number }}</span>
                                            <span class="text-xs text-gray-500">
                                                {{ $factura->invoice_date?->format('d/m/Y') }}
                                                @if($factura->status)
                                                    · {{ __(ucfirst($factura->status)) }}
                                                @endif
                                            </span>
                                            <span class="text-sm font-semibold text-gray-900">
                                                {{ valorProtegido((float) $factura->total, 'projetos.facturar', 'projetos.gerir') }} Kz
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($p->descricao)
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">{{ __('Descrição') }}</p>
                            <p class="text-gray-800 whitespace-pre-line">{{ $p->descricao }}</p>
                        </div>
                    @endif

                    @if($pf['linhas'] > 0)
                        <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-amber-900">
                                    {{ number_format($pf['horas'], 2, ',', '.') }} {{ __('horas por facturar') }}
                                    ({{ $pf['linhas'] }} {{ __('lançamentos') }})
                                </p>
                                <p class="text-xs text-amber-700">
                                    @if($p->client_id)
                                        {{ __('A factura nasce em rascunho, para conferir antes de assumir.') }}
                                    @else
                                        {{ __('Este projeto não tem cliente — atribua um para poder facturar.') }}
                                    @endif
                                </p>
                            </div>
                            @if($p->client_id && auth()->user()->can('projetos.facturar'))
                                <button wire:click="$set('confirmarFacturarId', {{ $p->id }})"
                                        class="px-5 py-2.5 bg-amber-600 text-white rounded-xl font-semibold hover:bg-amber-700">
                                    <i class="fas fa-file-invoice mr-2"></i>{{ __('Facturar horas') }}
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="px-6 py-4 bg-gray-50 rounded-b-2xl flex justify-end gap-3">
                    <a href="{{ route('projetos.tarefas') }}"
                       class="px-5 py-2.5 text-violet-700 font-semibold hover:text-violet-900">
                        <i class="fas fa-list-check mr-1"></i>{{ __('Ver tarefas') }}
                    </a>
                    <button wire:click="$set('verId', null)"
                            class="px-5 py-2.5 text-gray-600 font-semibold hover:text-gray-900">{{ __('Fechar') }}</button>
                </div>
            </div>
        </div>
    @endif

    <!-- ─── Modal: confirmar facturação ─────────────────────────────────── -->
    @if($confirmarFacturarId)
        <div class="fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
                <div class="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-file-invoice text-2xl text-amber-600"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900">{{ __('Facturar as horas deste projeto?') }}</h3>
                <p class="text-sm text-gray-500 mt-2">
                    {{ __('Emite uma factura em RASCUNHO com uma linha por tarefa. As horas levadas ficam marcadas e não voltam a ser facturadas.') }}
                </p>
                <div class="flex justify-center gap-3 mt-6">
                    <button wire:click="$set('confirmarFacturarId', null)"
                            class="px-5 py-2.5 text-gray-600 font-semibold hover:text-gray-900">{{ __('Voltar') }}</button>
                    <button wire:click="facturar" wire:loading.attr="disabled"
                            class="px-6 py-2.5 bg-amber-600 text-white rounded-xl font-semibold hover:bg-amber-700">
                        {{ __('Facturar') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
