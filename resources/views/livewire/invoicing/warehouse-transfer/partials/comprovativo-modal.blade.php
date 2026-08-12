{{--
    Comprovativo da movimentação acabada de registar.

    Serve as duas operações do ecrã — transferência e ajuste — e distingue-se
    pelo formato do resumo: o da transferência traz os dois lados, o do ajuste
    traz um só.

    Substitui o fecho imediato do modal, que deixava o utilizador sem saber com
    que número tinha ficado a movimentação. O documento abre-se por clique e não
    por JS: aberto automaticamente seria bloqueado como popup, por não vir de um
    gesto directo.
--}}
@if($batchReference)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-[70] flex items-center justify-center p-4">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[92vh] overflow-y-auto">

        @php
            $ehTransferencia = isset($batchResumo[0]['destino']);

            // Quantidades de stock são inteiras na esmagadora maioria dos casos:
            // "12" em vez de "12,00" tira ruído de uma tabela com seis colunas
            // de números.
            $qtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
        @endphp

        <div class="sticky top-0 bg-gradient-to-r from-emerald-600 to-green-600 px-6 py-4 rounded-t-2xl flex items-center justify-between z-10">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-circle-check mr-2"></i>
                {{ $ehTransferencia ? 'Transferência registada' : 'Ajuste registado' }}
            </h3>
            <button wire:click="fecharPainelLote" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <div class="p-6 space-y-5">

            <div class="text-center">
                <p class="text-sm text-gray-600">Referência do documento</p>
                <p class="mt-1 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-50 border-2 border-indigo-200 font-mono text-lg font-extrabold text-indigo-800">
                    <i class="fas fa-hashtag text-indigo-400 text-sm"></i>{{ $batchReference }}
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    Registado por <strong>{{ auth()->user()->name }}</strong> em {{ now()->format('d/m/Y H:i') }}
                </p>
            </div>

            {{-- Os saldos dos dois lados, produto a produto. É o que faltava:
                 sem eles ninguém sabia com quanto cada armazém tinha ficado. --}}
            <div class="border-2 border-gray-200 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            @if($ehTransferencia)
                                <tr>
                                    <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 uppercase align-bottom">Produto</th>
                                    <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 uppercase align-bottom">Transf.</th>
                                    <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-red-700 uppercase border-l">Origem</th>
                                    <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-green-700 uppercase border-l">Destino</th>
                                </tr>
                                <tr class="text-[11px] text-gray-500 uppercase">
                                    <th class="px-3 py-1 text-right font-semibold border-l">Antes</th>
                                    <th class="px-3 py-1 text-right font-semibold">Ficou</th>
                                    <th class="px-3 py-1 text-right font-semibold border-l">Antes</th>
                                    <th class="px-3 py-1 text-right font-semibold">Ficou</th>
                                </tr>
                            @else
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-700 uppercase">Produto</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-700 uppercase">Armazém</th>
                                    <th class="px-3 py-2 text-right text-xs font-bold text-gray-700 uppercase">Ajustado</th>
                                    <th class="px-3 py-2 text-right text-xs font-bold text-gray-700 uppercase border-l">Antes</th>
                                    <th class="px-3 py-2 text-right text-xs font-bold text-gray-700 uppercase">Ficou</th>
                                </tr>
                            @endif
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($batchResumo as $linha)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2">
                                        <p class="font-semibold text-gray-900">{{ $linha['produto'] }}</p>
                                        @if(!empty($linha['codigo']))
                                            <p class="text-xs text-gray-500">{{ $linha['codigo'] }}</p>
                                        @endif
                                    </td>

                                    @if($ehTransferencia)
                                        <td class="px-3 py-2 text-right font-bold text-indigo-600">
                                            {{ $qtd($linha['quantidade']) }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-500 border-l">{{ $qtd($linha['origem_antes']) }}</td>
                                        <td class="px-3 py-2 text-right font-bold {{ $linha['origem_depois'] <= 0 ? 'text-red-600' : 'text-gray-900' }}">
                                            {{ $qtd($linha['origem_depois']) }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-500 border-l">{{ $qtd($linha['destino_antes']) }}</td>
                                        <td class="px-3 py-2 text-right font-bold text-green-700">{{ $qtd($linha['destino_depois']) }}</td>
                                    @else
                                        <td class="px-3 py-2 text-sm text-gray-600">{{ $linha['armazem'] }}</td>
                                        <td class="px-3 py-2 text-right font-bold text-indigo-600">
                                            {{ $qtd($linha['quantidade']) }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-500 border-l">{{ $qtd($linha['antes']) }}</td>
                                        <td class="px-3 py-2 text-right font-bold {{ $linha['depois'] <= 0 ? 'text-red-600' : 'text-gray-900' }}">
                                            {{ $qtd($linha['depois']) }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if($ehTransferencia && count($batchResumo) > 0)
                <div class="flex items-center justify-center gap-3 text-sm">
                    <span class="px-3 py-1.5 rounded-lg bg-red-50 border border-red-200 font-semibold text-red-700">
                        <i class="fas fa-warehouse mr-1"></i>{{ $batchResumo[0]['origem'] }}
                    </span>
                    <i class="fas fa-arrow-right text-indigo-500"></i>
                    <span class="px-3 py-1.5 rounded-lg bg-green-50 border border-green-200 font-semibold text-green-700">
                        <i class="fas fa-warehouse mr-1"></i>{{ $batchResumo[0]['destino'] }}
                    </span>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="{{ route('invoicing.stock.batch-preview', ['reference' => $batchReference]) }}"
                   target="_blank" rel="noopener"
                   class="flex items-center justify-center gap-2 px-5 py-3 bg-white border-2 border-gray-300 hover:bg-gray-50 text-gray-700 rounded-xl font-bold transition">
                    <i class="fas fa-eye"></i> Pré-visualizar
                </a>
                <a href="{{ route('invoicing.stock.batch-pdf', ['reference' => $batchReference]) }}"
                   target="_blank" rel="noopener"
                   class="flex items-center justify-center gap-2 px-5 py-3 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white rounded-xl font-bold shadow transition">
                    <i class="fas fa-file-pdf text-lg"></i> Abrir documento em PDF
                </a>
            </div>

            <p class="text-[11px] text-gray-400 text-center">
                Documento interno de conferência de armazém — não é documento fiscal e não substitui
                guia de transporte. Pode reimprimi-lo a qualquer momento pela referência {{ $batchReference }}.
            </p>
        </div>

        <div class="sticky bottom-0 bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end border-t">
            <button type="button" wire:click="fecharPainelLote"
                    class="px-6 py-3 bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white rounded-xl font-bold shadow transition">
                Concluir
            </button>
        </div>
    </div>
</div>
@endif
