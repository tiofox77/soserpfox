<div class="p-6 max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-amber-600 to-orange-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-sliders text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">Ajustes de Stock</h1>
                    <p class="text-amber-100 text-sm">
                        Seguimento do que foi mexido à mão — vendas e compras ficam de fora
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <button wire:click="exportarCsv" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-white/20 rounded-lg hover:bg-white/30 text-sm font-semibold disabled:opacity-50">
                    <i class="fas fa-file-csv mr-1"></i>
                    <span wire:loading.remove wire:target="exportarCsv">Exportar CSV</span>
                    <span wire:loading wire:target="exportarCsv">A preparar…</span>
                </button>
                <a href="{{ route('invoicing.reports.hub') }}"
                   class="px-4 py-2 bg-white/20 rounded-lg hover:bg-white/30 text-sm font-semibold">
                    <i class="fas fa-arrow-left mr-1"></i>Relatórios
                </a>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow p-5">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Período</label>
                <select wire:model.live="period" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="today">Hoje</option>
                    <option value="week">Esta semana</option>
                    <option value="month">Este mês</option>
                    <option value="quarter">Trimestre</option>
                    <option value="year">Ano</option>
                    <option value="custom">Personalizado</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">De</label>
                <input type="date" wire:model.live="dateFrom" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Até</label>
                <input type="date" wire:model.live="dateTo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Armazém</label>
                <select wire:model.live="warehouseFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    @foreach($armazens as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Tipo</label>
                <select wire:model.live="typeFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    <option value="in">Entrada</option>
                    <option value="out">Saída</option>
                    <option value="adjustment">Ajuste</option>
                    <option value="transfer">Transferência</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">Operador</label>
                <select wire:model.live="userFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    @foreach($operadores as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-col sm:flex-row gap-3 sm:items-center">
            <div class="relative flex-1">
                <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Produto, código, referência do lote ou nota…"
                       class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <button wire:click="limparFiltros"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-50">
                <i class="fas fa-eraser mr-1"></i>Limpar
            </button>
        </div>
    </div>

    {{-- Totais --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow p-4 border border-emerald-100">
            <p class="text-[11px] text-gray-500 uppercase font-bold">Entradas</p>
            <p class="text-2xl font-bold text-emerald-600">+{{ number_format($resumo['entradas_qtd'], 2, ',', '.') }}</p>
            <p class="text-[11px] text-gray-400">{{ $resumo['entradas_n'] }} movimento(s)</p>
        </div>
        <div class="bg-white rounded-2xl shadow p-4 border border-red-100">
            <p class="text-[11px] text-gray-500 uppercase font-bold">Saídas</p>
            <p class="text-2xl font-bold text-red-600">−{{ number_format($resumo['saidas_qtd'], 2, ',', '.') }}</p>
            <p class="text-[11px] text-gray-400">{{ $resumo['saidas_n'] }} movimento(s)</p>
        </div>
        <div class="bg-white rounded-2xl shadow p-4 border border-yellow-100">
            <p class="text-[11px] text-gray-500 uppercase font-bold">Ajustes</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $resumo['ajustes_n'] }}</p>
            <p class="text-[11px] text-gray-400">inventário corrigido</p>
        </div>
        <div class="bg-white rounded-2xl shadow p-4 border border-blue-100">
            <p class="text-[11px] text-gray-500 uppercase font-bold">Transferências</p>
            <p class="text-2xl font-bold text-blue-600">{{ $resumo['transferencias_n'] }}</p>
            <p class="text-[11px] text-gray-400">entre armazéns</p>
        </div>
        <div class="bg-white rounded-2xl shadow p-4 border border-purple-100">
            <p class="text-[11px] text-gray-500 uppercase font-bold">Valor das entradas</p>
            <p class="text-2xl font-bold text-purple-600">{{ number_format($resumo['entradas_valor'], 2, ',', '.') }}</p>
            <p class="text-[11px] text-gray-400">Kz · {{ $resumo['lotes_n'] }} documento(s)</p>
        </div>
    </div>

    <p class="-mt-3 mb-6 text-[11px] text-gray-400">
        <i class="fas fa-circle-info mr-1"></i>
        Ajustes e transferências contam-se pelo número de ocorrências e não entram nas quantidades:
        num ajuste a quantidade registada é o saldo final e não uma variação, e uma transferência
        muda o artigo de armazém sem alterar o que a empresa tem.
    </p>

    {{-- Quem mexeu --}}
    @if($porUtilizador->count() > 0)
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h3 class="font-bold text-gray-900"><i class="fas fa-user-shield mr-2 text-amber-600"></i>Por operador</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Operador</th>
                            <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Movimentos</th>
                            <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Qtd. entrada</th>
                            <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Qtd. saída</th>
                            <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Ajustes</th>
                            <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Valor entrado (Kz)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($porUtilizador as $u)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 font-medium text-gray-900">{{ $u->nome }}</td>
                                <td class="px-6 py-3 text-right font-semibold">{{ $u->n }}</td>
                                <td class="px-6 py-3 text-right text-emerald-600">{{ number_format((float) $u->qtd_entrada, 2, ',', '.') }}</td>
                                <td class="px-6 py-3 text-right text-red-600">{{ number_format((float) $u->qtd_saida, 2, ',', '.') }}</td>
                                <td class="px-6 py-3 text-right text-yellow-700">{{ $u->ajustes }}</td>
                                <td class="px-6 py-3 text-right text-gray-700">{{ number_format((float) $u->valor_entrada, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Detalhe --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50 flex items-center justify-between flex-wrap gap-2">
            <h3 class="font-bold text-gray-900">
                <i class="fas fa-list-check mr-2 text-amber-600"></i>Detalhe
                <span class="ml-2 text-xs font-normal text-gray-500">{{ $resumo['total_n'] }} movimento(s) no período</span>
            </h3>
            <select wire:model.live="perPage" class="px-2 py-1 border border-gray-300 rounded-lg text-xs">
                <option value="25">25 por página</option>
                <option value="50">50 por página</option>
                <option value="100">100 por página</option>
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Data</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Produto</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Armazém</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Qtd.</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Saldo após</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Valor (Kz)</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Documento</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Nota</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Operador</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($movimentos as $m)
                        @php
                            $saida = $m->type === 'out';

                            // As transferências gravam DUAS linhas: a perna que
                            // sai leva quantidade negativa. O sinal é dado aqui
                            // para não aparecer um "⇄ -5,00" a competir com o
                            // símbolo de transferência.
                            $qtd = (float) $m->quantity;

                            // Valor do movimento — só para entradas, saídas e
                            // transferências. Num AJUSTE a quantidade é o saldo
                            // final, portanto quantidade × custo é a valorização
                            // das existências e não o que se ajustou: mostrá-la
                            // nesta coluna lia-se como um ajuste de milhões.
                            $valor = $m->type === 'adjustment'
                                ? null
                                : abs($qtd) * (float) ($m->unit_cost ?? 0);
                        @endphp
                        <tr class="hover:bg-gray-50 {{ $saida ? 'bg-red-50/30' : '' }}">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                                {{ $m->created_at?->format('d/m/Y') }}
                                <span class="block text-[11px] text-gray-400">{{ $m->created_at?->format('H:i') }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($m->type === 'in')
                                    <span class="px-2 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full"><i class="fas fa-arrow-down mr-1"></i>Entrada</span>
                                @elseif($m->type === 'out')
                                    <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full"><i class="fas fa-arrow-up mr-1"></i>Saída</span>
                                @elseif($m->type === 'transfer')
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded-full"><i class="fas fa-exchange-alt mr-1"></i>Transferência</span>
                                @else
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full"><i class="fas fa-sliders mr-1"></i>Ajuste</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $m->product->name ?? '(artigo removido)' }}</p>
                                <p class="text-[11px] text-gray-400">{{ $m->product->code ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $m->warehouse->name ?? '—' }}
                                @if($m->type === 'transfer' && $m->toWarehouse)
                                    <span class="block text-[11px] text-blue-500">→ {{ $m->toWarehouse->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($m->type === 'adjustment')
                                    {{-- Saldo final, não variação: um "+" aqui lia-se como entrada. --}}
                                    <span class="font-bold text-yellow-700">= {{ number_format($qtd, 2, ',', '.') }}</span>
                                @elseif($m->type === 'transfer')
                                    <span class="font-bold {{ $qtd < 0 ? 'text-red-600' : 'text-blue-600' }}">
                                        {{ $qtd < 0 ? '⇄ −' : '⇄ +' }}{{ number_format(abs($qtd), 2, ',', '.') }}
                                    </span>
                                @else
                                    <span class="font-bold {{ $saida ? 'text-red-600' : 'text-emerald-600' }}">
                                        {{ $saida ? '−' : '+' }}{{ number_format(abs($qtd), 2, ',', '.') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">
                                {{ $m->balance_after !== null ? number_format((float) $m->balance_after, 2, ',', '.') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700 whitespace-nowrap">
                                @if($valor === null)
                                    <span class="text-gray-300" title="Num ajuste a quantidade é o saldo final, não uma variação">—</span>
                                @else
                                    {{ $valor > 0 ? number_format($valor, 2, ',', '.') : '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($m->batch_reference)
                                    <a href="{{ route('invoicing.stock.batch-pdf', ['reference' => $m->batch_reference]) }}"
                                       target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1 text-red-600 hover:text-red-700 hover:underline font-semibold"
                                       title="Abrir o documento desta movimentação">
                                        <i class="fas fa-file-pdf"></i>
                                        <span class="font-mono text-xs">{{ $m->batch_reference }}</span>
                                    </a>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="{{ $m->notes }}">
                                {{ $m->notes ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $m->user->name ?? '(sistema)' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-gray-400">
                                <i class="fas fa-inbox text-3xl mb-2"></i>
                                <p class="text-sm">Nenhum ajuste de stock no período e filtros escolhidos</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($movimentos->hasPages())
            <div class="px-6 py-4 border-t bg-gray-50">
                {{ $movimentos->links() }}
            </div>
        @endif
    </div>
</div>
