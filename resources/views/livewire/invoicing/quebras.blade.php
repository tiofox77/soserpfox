<div>
    <!-- Header — o desenho da página de Produtos -->
    <div class="mb-6 bg-gradient-to-r from-rose-600 to-orange-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-dumpster-fire text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Quebras de Stock</h2>
                    <p class="text-rose-100 text-sm">Expirado, estragado, partido ou perdido — registado, sai do stock e entra no relatório</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-rose-100 text-xs font-semibold uppercase">Perdas no período</p>
                <p class="text-2xl font-bold">{{ number_format($resumo['custo'], 0, ',', '.') }} Kz</p>
                <p class="text-rose-100 text-xs">{{ $resumo['registos'] }} registo(s)</p>
            </div>
        </div>
    </div>

    <!-- Registar: uma linha -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <label class="block text-sm font-semibold text-gray-700 mb-3">
            <i class="fas fa-plus text-rose-500 mr-2"></i>Registar quebra — o custo congela no momento e o stock desce sozinho.
        </label>
        <div class="flex flex-wrap gap-3">
            <div class="relative flex-1 min-w-56">
                <input wire:model.live.debounce.300ms="procurarProduto" type="text" placeholder="Procurar artigo por nome ou código..."
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-rose-500 focus:border-transparent transition-all {{ $produtoId ? 'border-emerald-400 bg-emerald-50 font-semibold' : '' }}">
                @if($produtoId)
                    <button wire:click="$set('produtoId', null); $set('procurarProduto', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500"><i class="fas fa-xmark"></i></button>
                @endif
                @if($sugestoes->isNotEmpty())
                    <div class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden">
                        @foreach($sugestoes as $s)
                            <button wire:click="escolherProduto({{ $s->id }})" class="w-full text-left px-4 py-2.5 hover:bg-rose-50 border-b border-gray-100 last:border-0">
                                <span class="font-semibold text-gray-900">{{ $s->name }}</span>
                                <span class="text-xs text-gray-500 ml-2">{{ $s->code }} · {{ $s->unit }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
            <input wire:model="quantidade" type="text" inputmode="decimal" placeholder="Qtd."
                   class="w-28 px-4 py-3 border border-gray-300 rounded-xl text-right font-bold focus:ring-2 focus:ring-rose-500 focus:border-transparent transition-all">
            <select wire:model="armazemId" class="px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-rose-500 transition-all">
                <option value="">Sem armazém</option>
                @foreach($armazens as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
            </select>
            <select wire:model="motivo" class="px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-rose-500 transition-all">
                @foreach(\App\Models\Invoicing\Waste::MOTIVOS as $codigo => $nome)
                    <option value="{{ $codigo }}">{{ $nome }}</option>
                @endforeach
            </select>
            <input wire:model="notas" type="text" placeholder="Nota (opcional)"
                   class="flex-1 min-w-40 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-rose-500 focus:border-transparent transition-all">
            <button wire:click="registar" wire:loading.attr="disabled"
                    class="bg-gradient-to-r from-rose-600 to-orange-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-rose-700 hover:to-orange-700 shadow-lg hover:shadow-xl transition disabled:opacity-50">
                <i class="fas fa-arrow-trend-down mr-2"></i>Registar
            </button>
        </div>
        @error('produtoId') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
        @error('quantidade') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
    </div>

    <!-- O relatório: quanto e porquê -->
    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 flex items-center"><i class="fas fa-chart-pie mr-2 text-rose-600"></i>Porquê</h3>
                <div class="flex gap-2 text-xs">
                    <input wire:model.live="de" type="date" class="border border-gray-300 rounded-lg px-2 py-1">
                    <input wire:model.live="ate" type="date" class="border border-gray-300 rounded-lg px-2 py-1">
                </div>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($porMotivo as $m)
                    <div class="flex items-center justify-between px-6 py-3">
                        <span class="font-semibold text-gray-700">{{ \App\Models\Invoicing\Waste::MOTIVOS[$m->reason] ?? $m->reason }}</span>
                        <span class="text-right">
                            <b class="tabular-nums text-gray-900">{{ number_format((float) $m->custo, 0, ',', '.') }} Kz</b>
                            <span class="text-xs text-gray-400 ml-1">({{ $m->registos }})</span>
                        </span>
                    </div>
                @empty
                    <p class="px-6 py-8 text-center text-sm text-gray-400">Sem quebras no período. Óptimo sinal.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg overflow-hidden lg:col-span-2">
            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center"><i class="fas fa-boxes-stacked mr-2 text-rose-600"></i>Os artigos que mais perdem</h3>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($porProduto as $p)
                    <div class="flex items-center justify-between px-6 py-3">
                        <span class="font-semibold text-gray-700 truncate">{{ $p->product?->name ?? '(apagado)' }}</span>
                        <span class="shrink-0 text-right">
                            <span class="text-xs text-gray-500 mr-3">{{ rtrim(rtrim(number_format((float) $p->quantidade, 2, ',', '.'), '0'), ',') }} un.</span>
                            <b class="tabular-nums text-gray-900">{{ number_format((float) $p->custo, 0, ',', '.') }} Kz</b>
                        </span>
                    </div>
                @empty
                    <p class="px-6 py-8 text-center text-sm text-gray-400">Nada a mostrar no período.</p>
                @endforelse
            </div>
            @if($resumo['custo'] == 0 && $resumo['registos'] > 0)
                <p class="px-6 py-3 text-xs text-amber-700 bg-amber-50 border-t border-amber-100">
                    <i class="fas fa-circle-info mr-1"></i>Há quebras sem custo: o custo vem da ficha do artigo — preencha-o em Produtos para o relatório valer dinheiro.
                </p>
            @endif
        </div>
    </div>

    <!-- Lista -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-list mr-2 text-rose-600"></i>Registos ({{ $quebras->total() }})
            </h3>
            <div class="flex flex-wrap gap-2">
                <button wire:click="$set('filtroMotivo', 'todos')"
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold transition {{ $filtroMotivo === 'todos' ? 'bg-rose-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Todos</button>
                @foreach(\App\Models\Invoicing\Waste::MOTIVOS as $codigo => $nome)
                    <button wire:click="$set('filtroMotivo', '{{ $codigo }}')"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold transition {{ $filtroMotivo === $codigo ? 'bg-rose-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ explode(' /', $nome)[0] }}</button>
                @endforeach
            </div>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse($quebras as $q)
                <div wire:key="q-{{ $q->id }}" class="group p-6 hover:bg-rose-50 transition-all duration-300 {{ $q->anulada() ? 'opacity-50' : '' }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-3 mb-2">
                                <span class="inline-flex items-center px-3 py-1 bg-rose-100 text-rose-700 rounded-lg text-xs font-bold">
                                    {{ strtoupper(explode(' /', $q->reason_label)[0]) }}
                                </span>
                                <h4 class="text-lg font-bold text-gray-900">{{ $q->product?->name ?? '(apagado)' }}</h4>
                                @if($q->anulada())
                                    <span class="inline-flex items-center px-3 py-1 bg-gray-200 text-gray-600 rounded-lg text-xs font-bold">ANULADA</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-3 text-sm">
                                <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-lg font-semibold">
                                    {{ rtrim(rtrim(number_format((float) $q->quantity, 2, ',', '.'), '0'), ',') }} {{ $q->product?->unit ?? 'un.' }}
                                </span>
                                <span class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-lg font-semibold">
                                    <i class="fas fa-money-bill-wave mr-1"></i>{{ number_format((float) $q->total_cost, 2, ',', '.') }} Kz
                                </span>
                                @if($q->warehouse)
                                    <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-600 rounded-lg font-semibold">
                                        <i class="fas fa-warehouse mr-1"></i>{{ $q->warehouse->name }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-600 rounded-lg font-semibold">
                                    <i class="fas fa-user mr-1"></i>{{ $q->user?->name ?? '—' }} · {{ $q->created_at->format('d/m/Y H:i') }}
                                </span>
                            </div>
                            @if($q->notes)<p class="mt-2 text-sm text-gray-500">{{ $q->notes }}</p>@endif
                        </div>

                        @if(!$q->anulada())
                            <div class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                                <button wire:click="anular({{ $q->id }})" wire:confirm="Anular esta quebra? O stock volta pelo movimento contrário — nada se apaga."
                                        class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-semibold transition shadow-md">
                                    <i class="fas fa-rotate-left mr-1"></i>Anular
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-dumpster-fire text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Sem quebras no período</h3>
                    <p class="text-gray-500 mb-4">Quando um artigo expirar, se estragar ou partir, registe-o acima — sai do stock e fica no relatório.</p>
                </div>
            @endforelse
        </div>

        @if($quebras->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $quebras->links() }}
            </div>
        @endif
    </div>
</div>
