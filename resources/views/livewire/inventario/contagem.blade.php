<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-amber-600 to-yellow-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-clipboard-check text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Contagem Física</h2>
                    <p class="text-amber-100 text-sm">Contar a prateleira, comparar com o sistema, acertar por movimentos verdadeiros</p>
                </div>
            </div>
            @if($contagem && $contagem->aberta() && $progresso)
                <div class="text-right">
                    <p class="text-amber-100 text-xs font-semibold uppercase">{{ $contagem->warehouse?->name }}</p>
                    <p class="text-2xl font-bold">{{ $progresso['contados'] }} / {{ $progresso['total'] }}</p>
                    <p class="text-amber-100 text-xs">{{ $progresso['diferencas'] }} diferença(s) até agora</p>
                </div>
            @endif
        </div>
    </div>

    @if(!$contagem || !$contagem->aberta())
        <!-- Abrir contagem: o modal explica o congelamento antes do clique -->
        <div class="mb-6 bg-white rounded-2xl shadow-lg p-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="font-bold text-gray-900"><i class="fas fa-play text-amber-600 mr-2"></i>Pronto para contar?</p>
                <p class="text-sm text-gray-500">O esperado congela-se no momento em que abre — as vendas podem continuar.</p>
            </div>
            <button wire:click="$set('showAbrir', true)"
                    class="bg-gradient-to-r from-amber-600 to-yellow-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-amber-700 hover:to-yellow-700 shadow-lg hover:shadow-xl transition">
                <i class="fas fa-clipboard-list mr-2"></i>Abrir contagem
            </button>
        </div>

        <!-- Histórico -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center">
                    <i class="fas fa-clock-rotate-left mr-2 text-amber-600"></i>Contagens anteriores
                </h3>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($historico as $h)
                    <button wire:click="$set('verContagemId', {{ $h->id }})" wire:key="hist-{{ $h->id }}"
                            class="w-full text-left flex flex-wrap items-center justify-between gap-3 px-6 py-4 hover:bg-amber-50 transition group">
                        <div>
                            <p class="font-bold text-gray-900">{{ $h->warehouse?->name ?? '—' }}
                                @if($h->status === 'cancelled')<span class="ml-2 rounded bg-gray-200 px-2 py-0.5 text-[10px] font-bold text-gray-600">CANCELADA</span>@endif
                            </p>
                            <p class="text-xs text-gray-500">{{ $h->opener?->name ?? '—' }} · {{ ($h->closed_at ?? $h->updated_at)->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            @if($h->status === 'closed')
                                <div class="text-right text-sm">
                                    <p class="font-bold text-gray-900">{{ $h->items_adjusted }} acerto(s) em {{ $h->items_counted }} contado(s)</p>
                                    <p class="text-xs {{ (float) $h->adjustment_cost > 0 ? 'text-rose-600 font-semibold' : 'text-gray-400' }}">
                                        {{ number_format((float) $h->adjustment_cost, 2, ',', '.') }} Kz de diferença
                                    </p>
                                </div>
                            @endif
                            <i class="fas fa-chevron-right text-gray-300 group-hover:text-amber-500 transition"></i>
                        </div>
                    </button>
                @empty
                    <div class="p-12 text-center">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-clipboard-check text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Nunca se contou</h3>
                        <p class="text-gray-500">Abra a primeira contagem acima — o sistema só diz a verdade depois de alguém a conferir.</p>
                    </div>
                @endforelse
            </div>
        </div>
    @else
        <!-- A contagem aberta: a lista de artigos -->
        <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-56">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
                    <input wire:model.live.debounce.300ms="procurar" type="text" placeholder="Procurar artigo por nome ou código..."
                           class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all">
                </div>
                @foreach(['todos' => 'Todos', 'por_contar' => 'Por contar', 'com_diferenca' => 'Com diferença'] as $codigo => $nome)
                    <button wire:click="$set('filtro', '{{ $codigo }}')"
                            class="px-4 py-2 rounded-xl text-sm font-semibold transition {{ $filtro === $codigo ? 'bg-amber-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        {{ $nome }}
                    </button>
                @endforeach
                <button wire:click="$set('showFechar', true)"
                        class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-5 py-2.5 rounded-xl font-semibold shadow-lg hover:shadow-xl transition">
                    <i class="fas fa-check mr-2"></i>Fechar e acertar
                </button>
                <button wire:click="$set('showCancelar', true)"
                        class="px-4 py-2.5 border-2 border-gray-300 rounded-xl text-gray-600 font-semibold hover:bg-gray-50 transition">
                    Cancelar
                </button>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="hidden grid-cols-[minmax(0,1fr)_120px_150px_120px] gap-2 border-b border-gray-100 bg-gray-50 px-6 py-2 text-xs font-black uppercase tracking-wide text-gray-400 sm:grid">
                <span>Artigo</span><span class="text-right">Sistema</span><span class="text-right">Contado</span><span class="text-right">Diferença</span>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($linhas as $linha)
                    @php($dif = $linha->diferenca())
                    <div wire:key="linha-{{ $linha->id }}" class="grid grid-cols-1 gap-2 px-6 py-3 sm:grid-cols-[minmax(0,1fr)_120px_150px_120px] sm:items-center {{ $dif !== null && abs($dif) > 0.0001 ? 'bg-amber-50/60' : '' }}">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-gray-900">{{ $linha->product?->name }}</p>
                            <p class="text-xs text-gray-400">{{ $linha->product?->code }} · {{ $linha->product?->unit }}</p>
                        </div>
                        <span class="text-right tabular-nums text-gray-600">{{ rtrim(rtrim(number_format((float) $linha->expected_quantity, 2, ',', '.'), '0'), ',') }}</span>
                        <input type="text" inputmode="decimal"
                               value="{{ $linha->counted_quantity !== null ? rtrim(rtrim(number_format((float) $linha->counted_quantity, 2, ',', '.'), '0'), ',') : '' }}"
                               wire:change="contar({{ $linha->product_id }}, $event.target.value)"
                               placeholder="—"
                               class="rounded-lg border-gray-200 py-1.5 text-right font-bold tabular-nums focus:border-amber-400 focus:ring-amber-400">
                        <span class="text-right font-bold tabular-nums {{ $dif === null ? 'text-gray-300' : (abs($dif) < 0.0001 ? 'text-emerald-600' : ($dif > 0 ? 'text-blue-600' : 'text-red-600')) }}">
                            {{ $dif === null ? '—' : ($dif > 0 ? '+' : '').rtrim(rtrim(number_format($dif, 2, ',', '.'), '0'), ',') }}
                        </span>
                    </div>
                @empty
                    <p class="px-6 py-10 text-center text-sm text-gray-400">Nada com esse filtro.</p>
                @endforelse
            </div>
            @if($linhas && $linhas->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">{{ $linhas->links() }}</div>
            @endif
        </div>

        <p class="mt-3 text-xs text-gray-400">
            <i class="fas fa-circle-info mr-1"></i>Um artigo deixado em branco não é um zero — é «não fui lá ver», e fica de fora do acerto.
            Zero escreve-se: significa que a prateleira está mesmo vazia.
        </p>
    @endif

    <!-- Modal: abrir contagem -->
    @if($showAbrir)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-gradient-to-r from-amber-600 to-yellow-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center"><i class="fas fa-clipboard-list mr-3"></i>Abrir contagem</h3>
                            <button wire:click="$set('showAbrir', false)" class="text-white hover:text-gray-200 transition"><i class="fas fa-times text-2xl"></i></button>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900 mb-4">
                            <i class="fas fa-snowflake mr-1.5 text-amber-600"></i>
                            O que o sistema espera <b>congela-se agora</b> — as vendas podem continuar enquanto se conta, sem estragar a comparação.
                        </div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2"><i class="fas fa-warehouse text-amber-600 mr-2"></i>Armazém</label>
                        <select wire:model="armazemId" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 transition">
                            @foreach($armazens as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                        </select>
                        <label class="block text-sm font-semibold text-gray-700 mb-2 mt-4"><i class="fas fa-align-left text-gray-500 mr-2"></i>Nota (opcional)</label>
                        <input wire:model="notaDeAbertura" type="text" placeholder="Ex.: contagem de fim de mês"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button wire:click="$set('showAbrir', false)" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition"><i class="fas fa-times mr-2"></i>Cancelar</button>
                            <button wire:click="abrir" wire:loading.attr="disabled"
                                    class="px-6 py-2.5 bg-gradient-to-r from-amber-600 to-yellow-600 text-white rounded-xl font-semibold hover:from-amber-700 hover:to-yellow-700 shadow-lg hover:shadow-xl transition disabled:opacity-50">
                                <i class="fas fa-play mr-2"></i>Abrir e congelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: fechar, com o resumo antes do botão -->
    @if($showFechar && $this->resumoDoFecho)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center"><i class="fas fa-check mr-3"></i>Fechar e acertar</h3>
                            <button wire:click="$set('showFechar', false)" class="text-white hover:text-gray-200 transition"><i class="fas fa-times text-2xl"></i></button>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-gray-600 mb-4">Isto é o que vai acontecer — cada diferença vira um movimento verdadeiro de stock. O que ficou em branco fica de fora.</p>
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-xl bg-gray-50 border border-gray-200 p-4">
                                <p class="text-2xl font-bold text-gray-900">{{ $this->resumoDoFecho['contados'] }}</p>
                                <p class="text-xs font-semibold text-gray-500 uppercase">Contados</p>
                            </div>
                            <div class="rounded-xl {{ $this->resumoDoFecho['acertos'] > 0 ? 'bg-amber-50 border-amber-200' : 'bg-gray-50 border-gray-200' }} border p-4">
                                <p class="text-2xl font-bold {{ $this->resumoDoFecho['acertos'] > 0 ? 'text-amber-700' : 'text-gray-900' }}">{{ $this->resumoDoFecho['acertos'] }}</p>
                                <p class="text-xs font-semibold text-gray-500 uppercase">Acertos</p>
                            </div>
                            <div class="rounded-xl {{ $this->resumoDoFecho['custo'] > 0 ? 'bg-rose-50 border-rose-200' : 'bg-gray-50 border-gray-200' }} border p-4">
                                <p class="text-xl font-bold {{ $this->resumoDoFecho['custo'] > 0 ? 'text-rose-600' : 'text-gray-900' }}">{{ number_format($this->resumoDoFecho['custo'], 0, ',', '.') }}</p>
                                <p class="text-xs font-semibold text-gray-500 uppercase">Kz de diferença</p>
                            </div>
                        </div>
                        @if($this->resumoDoFecho['contados'] === 0)
                            <p class="mt-4 rounded-xl bg-red-50 border border-red-200 p-3 text-sm font-semibold text-red-600"><i class="fas fa-circle-exclamation mr-1.5"></i>Nada foi contado ainda — não há nada para fechar.</p>
                        @endif
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button wire:click="$set('showFechar', false)" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition"><i class="fas fa-times mr-2"></i>Voltar a contar</button>
                            <button wire:click="fechar" wire:loading.attr="disabled" @disabled($this->resumoDoFecho['contados'] === 0)
                                    class="px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-xl font-semibold hover:from-emerald-700 hover:to-teal-700 shadow-lg hover:shadow-xl transition disabled:opacity-40">
                                <i class="fas fa-check mr-2"></i>Fechar e acertar o stock
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: cancelar -->
    @if($showCancelar)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                    <div class="bg-gradient-to-r from-gray-600 to-gray-700 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center"><i class="fas fa-ban mr-3"></i>Cancelar contagem</h3>
                            <button wire:click="$set('showCancelar', false)" class="text-white hover:text-gray-200 transition"><i class="fas fa-times text-2xl"></i></button>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-gray-600">O que já se contou fica registado na contagem cancelada, mas <b>nada é mexido no stock</b>. Pode abrir outra a qualquer momento.</p>
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button wire:click="$set('showCancelar', false)" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">Continuar a contar</button>
                            <button wire:click="cancelar" class="px-6 py-2.5 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl font-semibold shadow-lg transition"><i class="fas fa-ban mr-2"></i>Cancelar contagem</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: detalhe de uma contagem antiga -->
    @if($verContagemId && $this->detalhe)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-gradient-to-r from-amber-600 to-yellow-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-bold text-white flex items-center">
                                <i class="fas fa-clipboard-check mr-3"></i>{{ $this->detalhe['contagem']->warehouse?->name }}
                            </h3>
                            <button wire:click="$set('verContagemId', null)" class="text-white hover:text-gray-200 transition"><i class="fas fa-times text-2xl"></i></button>
                        </div>
                        <p class="text-amber-100 text-sm mt-1">
                            {{ $this->detalhe['contagem']->opener?->name ?? '—' }} ·
                            {{ ($this->detalhe['contagem']->closed_at ?? $this->detalhe['contagem']->updated_at)->format('d/m/Y H:i') }}
                            @if($this->detalhe['contagem']->notes) · {{ $this->detalhe['contagem']->notes }} @endif
                        </p>
                    </div>
                    <div class="p-6">
                        @if($this->detalhe['contagem']->status === 'cancelled')
                            <p class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-sm text-gray-600"><i class="fas fa-ban mr-1.5"></i>Contagem cancelada — nada foi mexido no stock.</p>
                        @elseif($this->detalhe['ajustadas']->isEmpty())
                            <p class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-700"><i class="fas fa-check mr-1.5"></i>Tudo bateu certo — nenhum acerto foi preciso. É o melhor resultado possível.</p>
                        @else
                            <div class="hidden sm:grid grid-cols-[minmax(0,1fr)_110px_110px_110px] gap-2 border-b border-gray-100 pb-2 text-xs font-black uppercase tracking-wide text-gray-400">
                                <span>Artigo</span><span class="text-right">Esperado</span><span class="text-right">Contado</span><span class="text-right">Diferença</span>
                            </div>
                            <div class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
                                @foreach($this->detalhe['ajustadas'] as $l)
                                    @php($d = $l->diferenca())
                                    <div class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_110px_110px_110px] gap-2 py-2.5 sm:items-center">
                                        <span class="font-semibold text-gray-800 truncate">{{ $l->product?->name }}</span>
                                        <span class="text-right tabular-nums text-gray-500">{{ rtrim(rtrim(number_format((float) $l->expected_quantity, 2, ',', '.'), '0'), ',') }}</span>
                                        <span class="text-right tabular-nums font-bold text-gray-900">{{ rtrim(rtrim(number_format((float) $l->counted_quantity, 2, ',', '.'), '0'), ',') }}</span>
                                        <span class="text-right tabular-nums font-bold {{ $d > 0 ? 'text-blue-600' : 'text-red-600' }}">{{ $d > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($d, 2, ',', '.'), '0'), ',') }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4 flex items-center justify-between rounded-xl bg-gray-50 border border-gray-200 p-4">
                                <span class="text-sm font-semibold text-gray-600">{{ $this->detalhe['contagem']->items_adjusted }} acerto(s) em {{ $this->detalhe['contagem']->items_counted }} contado(s)</span>
                                <b class="text-rose-600">{{ number_format((float) $this->detalhe['contagem']->adjustment_cost, 2, ',', '.') }} Kz</b>
                            </div>
                        @endif
                        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end">
                            <button wire:click="$set('verContagemId', null)" class="px-6 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
