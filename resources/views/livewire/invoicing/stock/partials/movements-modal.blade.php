<!-- Movements Modal -->
@if($showMovementsModal)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-5xl w-[calc(100%-1rem)] sm:w-full max-h-[94vh] overflow-y-auto transform transition-all animate-scale-in">
        <!-- Header -->
        <div class="sticky top-0 bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-history mr-2"></i>
                Histórico de Movimentos
            </h3>
            <button wire:click="$set('showMovementsModal', false)" type="button"
                    class="btn-press text-white hover:text-gray-200 transition w-9 h-9 inline-flex items-center justify-center rounded-lg hover:bg-white/10">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6">
            <!-- Product Info -->
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-4 mb-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-600 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-box text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600">Produto</p>
                        <p class="text-lg font-bold text-gray-900">{{ $movementsProductName }}</p>
                    </div>
                </div>
            </div>

            <!-- Movements Table -->
            @php
                $movements = \App\Models\Invoicing\StockMovement::where('tenant_id', activeTenantId())
                    ->where('product_id', $movementsProductId)
                    ->with(['warehouse', 'user'])
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get();

                // O sinal está no `type`, NUNCA na quantidade — `quantity` é
                // sempre positiva. Somar por `quantity > 0` dava todas as linhas
                // como entrada: o total de saídas ficava eternamente a zero e as
                // saídas apareciam a verde com um "+" à frente.
                //
                // Só `in` e `out` entram nos totais:
                //  · uma TRANSFERÊNCIA muda o artigo de armazém e não altera a
                //    quantidade que a empresa tem — contá-la como entrada
                //    inflacionava a variação líquida;
                //  · num AJUSTE, `quantity` é o valor final do stock e não uma
                //    variação (ver StockMovement::createAdjustment), por isso
                //    somá-la não significa nada.
                $entradas = $movements->where('type', 'in');
                $saidas   = $movements->where('type', 'out');
                $outros   = $movements->whereIn('type', ['transfer', 'adjustment']);

                $totalIn   = (float) $entradas->sum('quantity');
                $totalOut  = (float) $saidas->sum('quantity');
                $netChange = $totalIn - $totalOut;
            @endphp

            @if($movements->count() > 0)
                <div class="border-2 border-gray-200 rounded-xl overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Data</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Armazém</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Quantidade</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Saldo anterior</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Saldo actual</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Documento</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Observações</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Utilizador</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($movements as $movement)
                                @php $saida = $movement->type === 'out'; @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                        {{ $movement->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($movement->type == 'in')
                                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">
                                                <i class="fas fa-arrow-down mr-1"></i>Entrada
                                            </span>
                                        @elseif($movement->type == 'out')
                                            <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">
                                                <i class="fas fa-arrow-up mr-1"></i>Saída
                                            </span>
                                        @elseif($movement->type == 'transfer')
                                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded-full">
                                                <i class="fas fa-exchange-alt mr-1"></i>Transferência
                                            </span>
                                        @else
                                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full">
                                                <i class="fas fa-adjust mr-1"></i>Ajuste
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        {{ $movement->warehouse->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        @php
                                            // Num ajuste a quantidade gravada é o valor FINAL, não uma
                                            // variação — "= 18" não dizia se subiu ou desceu, nem quanto.
                                            // Com os dois saldos mostra-se a variação verdadeira.
                                            $temSaldos = $movement->balance_before !== null && $movement->balance_after !== null;
                                            $variacao = $temSaldos
                                                ? (float) $movement->balance_after - (float) $movement->balance_before
                                                : null;
                                        @endphp
                                        @if($movement->type === 'adjustment')
                                            @if($variacao !== null)
                                                <span class="text-lg font-bold {{ $variacao < 0 ? 'text-red-600' : ($variacao > 0 ? 'text-green-600' : 'text-gray-500') }}">
                                                    {{ $variacao > 0 ? '+' : ($variacao < 0 ? '−' : '') }}{{ number_format(abs($variacao), 2) }}
                                                </span>
                                                <span class="block text-[10px] text-yellow-700 font-semibold uppercase tracking-wide">ajustada</span>
                                            @else
                                                <span class="text-lg font-bold text-yellow-700">
                                                    = {{ number_format($movement->quantity, 2) }}
                                                </span>
                                                <span class="block text-[10px] text-gray-400">valor final</span>
                                            @endif
                                        @elseif($movement->type === 'transfer')
                                            <span class="text-lg font-bold text-blue-600">
                                                ⇄ {{ number_format($movement->quantity, 2) }}
                                            </span>
                                        @else
                                            <span class="text-lg font-bold {{ $saida ? 'text-red-600' : 'text-green-600' }}">
                                                {{ $saida ? '−' : '+' }}{{ number_format($movement->quantity, 2) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm whitespace-nowrap">
                                        @if($movement->balance_before !== null)
                                            <span class="font-mono {{ (float) $movement->balance_before < 0 ? 'text-red-600 font-bold' : 'text-gray-600' }}">
                                                {{ number_format($movement->balance_before, 2) }}
                                            </span>
                                        @else
                                            {{-- Movimentos anteriores a esta coluna existir. Melhor
                                                 vazio do que um número reconstruído a fingir de registo. --}}
                                            <span class="text-gray-300" title="Movimento anterior ao registo de saldos">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm whitespace-nowrap">
                                        @if($movement->balance_after !== null)
                                            <span class="font-mono font-bold {{ (float) $movement->balance_after < 0 ? 'text-red-600' : 'text-gray-800' }}">
                                                {{ number_format($movement->balance_after, 2) }}
                                            </span>
                                        @else
                                            <span class="text-gray-300" title="Movimento anterior ao registo de saldos">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm whitespace-nowrap">
                                        @if($movement->batch_reference)
                                            {{-- Reimpressão do documento de conferência do lote. --}}
                                            <a href="{{ route('invoicing.stock.batch-pdf', ['reference' => $movement->batch_reference]) }}"
                                               target="_blank" rel="noopener"
                                               class="inline-flex items-center gap-1 text-red-600 hover:text-red-700 hover:underline font-semibold"
                                               title="Abrir o documento desta movimentação">
                                                <i class="fas fa-file-pdf"></i>
                                                <span class="font-mono text-xs">{{ $movement->batch_reference }}</span>
                                            </a>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $movement->notes ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        {{ $movement->user->name ?? 'Sistema' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Summary -->
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-green-50 border-2 border-green-200 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-600 mb-1">Total Entradas</p>
                        <p class="text-2xl font-bold text-green-600">+{{ number_format($totalIn, 2) }}</p>
                    </div>

                    <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-600 mb-1">Total Saídas</p>
                        <p class="text-2xl font-bold text-red-600">−{{ number_format($totalOut, 2) }}</p>
                    </div>

                    <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 text-center">
                        <p class="text-xs text-gray-600 mb-1">Variação Líquida</p>
                        <p class="text-2xl font-bold {{ $netChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $netChange >= 0 ? '+' : '−' }}{{ number_format(abs($netChange), 2) }}
                        </p>
                    </div>
                </div>

                <p class="mt-3 text-xs text-gray-400 text-center">
                    <i class="fas fa-circle-info mr-1"></i>
                    Os totais contam apenas entradas e saídas.
                    @if($outros->count() > 0)
                        {{ $outros->count() }} movimento(s) de transferência ou ajuste ficam de fora:
                        uma transferência muda o artigo de armazém sem alterar o que a empresa tem,
                        e num ajuste a quantidade é o saldo final, não uma variação.
                    @endif
                    @if($movements->count() >= 50)
                        A lista mostra os 50 mais recentes.
                    @endif
                </p>
            @else
                <div class="text-center py-12">
                    <i class="fas fa-history text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Nenhum movimento registado para este produto</p>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end border-t border-gray-200">
            <button
                type="button"
                wire:click="$set('showMovementsModal', false)"
                class="btn-press px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold transition">
                <i class="fas fa-times mr-2"></i>Fechar
            </button>
        </div>
    </div>
</div>
@endif
