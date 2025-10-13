<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-cash-register text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">POS - Ponto de Venda</h2>
                    <p class="text-purple-100 text-sm">Sistema de Caixa e Turnos</p>
                </div>
            </div>
            @if(!$currentShift)
                <button wire:click="openShiftModal" class="bg-white text-purple-600 px-6 py-3 rounded-lg font-bold hover:bg-purple-50 transition">
                    <i class="fas fa-play mr-2"></i>Abrir Turno
                </button>
            @else
                <button wire:click="closeShiftModal" class="bg-red-500 hover:bg-red-600 px-6 py-3 rounded-lg font-bold transition">
                    <i class="fas fa-stop mr-2"></i>Fechar Turno
                </button>
            @endif
        </div>
    </div>

    @if(!$currentShift)
        {{-- Sem turno aberto --}}
        <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
            <i class="fas fa-cash-register text-gray-300 text-6xl mb-4"></i>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">Nenhum Turno Aberto</h3>
            <p class="text-gray-600 mb-6">Abra um turno para começar a vender no POS</p>
            <button wire:click="openShiftModal" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl transition">
                <i class="fas fa-play mr-2"></i>Abrir Novo Turno
            </button>
        </div>
    @else
        {{-- Turno aberto --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- Info do Turno --}}
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-info-circle text-purple-600 mr-2"></i>
                    Informações do Turno
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm text-gray-600">Número</span>
                        <span class="font-bold text-purple-600">{{ $currentShift->shift_number }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm text-gray-600">Status</span>
                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-bold">
                            <i class="fas fa-circle text-xs mr-1"></i>{{ $currentShift->status_label }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm text-gray-600">Aberto em</span>
                        <span class="font-bold text-gray-900">{{ $currentShift->opened_at->format('H:i') }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm text-gray-600">Duração</span>
                        <span class="font-bold text-gray-900">{{ $currentShift->duration }}h</span>
                    </div>
                </div>
            </div>

            {{-- Valores --}}
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-euro-sign text-green-600 mr-2"></i>
                    Valores
                </h3>
                <div class="space-y-3">
                    <div class="p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl">
                        <p class="text-xs text-blue-600 font-semibold mb-1">Saldo Inicial</p>
                        <p class="text-2xl font-bold text-blue-900">{{ number_format($currentShift->opening_balance, 2, ',', '.') }} Kz</p>
                    </div>
                    <div class="p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-xl">
                        <p class="text-xs text-green-600 font-semibold mb-1">Total de Vendas</p>
                        <p class="text-2xl font-bold text-green-900">{{ number_format($currentShift->total_sales, 2, ',', '.') }} Kz</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 mb-1">Dinheiro</p>
                            <p class="text-lg font-bold text-gray-900">{{ number_format($currentShift->cash_sales, 2, ',', '.') }} Kz</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500 mb-1">Cartão</p>
                            <p class="text-lg font-bold text-gray-900">{{ number_format($currentShift->card_sales, 2, ',', '.') }} Kz</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Estatísticas --}}
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-chart-bar text-indigo-600 mr-2"></i>
                    Estatísticas
                </h3>
                <div class="space-y-3">
                    <div class="p-4 bg-gradient-to-r from-purple-50 to-purple-100 rounded-xl">
                        <p class="text-xs text-purple-600 font-semibold mb-1">Total de Faturas</p>
                        <p class="text-3xl font-bold text-purple-900">{{ $currentShift->total_invoices }}</p>
                    </div>
                    <div class="p-4 bg-gradient-to-r from-orange-50 to-orange-100 rounded-xl">
                        <p class="text-xs text-orange-600 font-semibold mb-1">Total de Recibos</p>
                        <p class="text-3xl font-bold text-orange-900">{{ $currentShift->total_receipts }}</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-500 mb-1">Valor Médio</p>
                        <p class="text-lg font-bold text-gray-900">
                            {{ $currentShift->total_invoices > 0 ? number_format($currentShift->total_sales / $currentShift->total_invoices, 2, ',', '.') : '0,00' }} Kz
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Transações Recentes --}}
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-list text-purple-600 mr-2"></i>
                Transações Recentes
            </h3>
            @if($currentShift->transactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="text-left p-3 text-sm font-semibold text-gray-700">Tipo</th>
                                <th class="text-left p-3 text-sm font-semibold text-gray-700">Referência</th>
                                <th class="text-left p-3 text-sm font-semibold text-gray-700">Método</th>
                                <th class="text-right p-3 text-sm font-semibold text-gray-700">Valor</th>
                                <th class="text-right p-3 text-sm font-semibold text-gray-700">Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($currentShift->transactions->sortByDesc('created_at')->take(10) as $transaction)
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="p-3">
                                        <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs font-bold">
                                            {{ $transaction->type_label }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-sm text-gray-700">{{ $transaction->reference_number ?? '-' }}</td>
                                    <td class="p-3 text-sm text-gray-700">{{ $transaction->payment_method_label }}</td>
                                    <td class="p-3 text-right font-bold text-gray-900">{{ number_format($transaction->amount, 2, ',', '.') }} Kz</td>
                                    <td class="p-3 text-right text-sm text-gray-500">{{ $transaction->created_at->format('H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8">
                    <i class="fas fa-inbox text-gray-300 text-4xl mb-2"></i>
                    <p class="text-gray-500">Nenhuma transação registrada ainda</p>
                </div>
            @endif
        </div>
    @endif

    {{-- Modal: Abrir Turno --}}
    @if($showOpenShiftModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 rounded-t-2xl">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-play mr-2"></i>
                    Abrir Novo Turno
                </h3>
            </div>
            
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Saldo Inicial (Kz) *</label>
                    <input type="number" wire:model="opening_balance" step="0.01" min="0" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 @error('opening_balance') border-red-500 @enderror">
                    @error('opening_balance') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Observações (opcional)</label>
                    <textarea wire:model="opening_notes" rows="3" 
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                              placeholder="Notas sobre a abertura do turno..."></textarea>
                </div>

                <div class="flex space-x-3 pt-4 border-t">
                    <button wire:click="openShift" 
                            wire:loading.attr="disabled"
                            class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="openShift">
                            <i class="fas fa-check mr-2"></i>Abrir Turno
                        </span>
                        <span wire:loading wire:target="openShift">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Abrindo...
                        </span>
                    </button>
                    <button wire:click="$set('showOpenShiftModal', false)" 
                            class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal: Fechar Turno --}}
    @if($showCloseShiftModal && $currentShift)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
            <div class="bg-gradient-to-r from-red-600 to-orange-600 px-6 py-4 rounded-t-2xl">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-stop mr-2"></i>
                    Fechar Turno
                </h3>
            </div>
            
            <div class="p-6 space-y-4">
                {{-- Resumo --}}
                <div class="bg-gradient-to-r from-purple-50 to-indigo-50 border-2 border-purple-200 rounded-xl p-4">
                    <h4 class="font-bold text-purple-900 mb-3">Resumo do Turno</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-purple-600 mb-1">Saldo Inicial</p>
                            <p class="text-lg font-bold text-purple-900">{{ number_format($currentShift->opening_balance, 2, ',', '.') }} Kz</p>
                        </div>
                        <div>
                            <p class="text-xs text-purple-600 mb-1">Vendas em Dinheiro</p>
                            <p class="text-lg font-bold text-purple-900">{{ number_format($currentShift->cash_sales, 2, ',', '.') }} Kz</p>
                        </div>
                        <div>
                            <p class="text-xs text-purple-600 mb-1">Dinheiro Esperado</p>
                            <p class="text-2xl font-bold text-green-600">{{ number_format($currentShift->opening_balance + $currentShift->cash_sales, 2, ',', '.') }} Kz</p>
                        </div>
                        <div>
                            <p class="text-xs text-purple-600 mb-1">Total de Vendas</p>
                            <p class="text-2xl font-bold text-purple-900">{{ number_format($currentShift->total_sales, 2, ',', '.') }} Kz</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Dinheiro Contado (Kz) *</label>
                    <input type="number" wire:model.live="actual_cash" step="0.01" min="0" 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 text-lg font-bold @error('actual_cash') border-red-500 @enderror">
                    @error('actual_cash') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    
                    @php
                        $expected = $currentShift->opening_balance + $currentShift->cash_sales;
                        $difference = $actual_cash - $expected;
                    @endphp
                    
                    @if($actual_cash > 0 && $difference != 0)
                        <div class="mt-2 p-3 rounded-lg {{ $difference > 0 ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                            <p class="text-sm font-bold {{ $difference > 0 ? 'text-green-900' : 'text-red-900' }}">
                                Diferença: {{ $difference > 0 ? '+' : '' }}{{ number_format($difference, 2, ',', '.') }} Kz
                                @if($difference > 0)
                                    (Sobra)
                                @else
                                    (Falta)
                                @endif
                            </p>
                        </div>
                    @endif
                </div>

                @if($actual_cash > 0 && abs($difference) > 0.01)
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Motivo da Diferença</label>
                        <textarea wire:model="difference_reason" rows="2" 
                                  class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                                  placeholder="Explique o motivo da diferença..."></textarea>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Observações de Fechamento (opcional)</label>
                    <textarea wire:model="closing_notes" rows="3" 
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                              placeholder="Notas sobre o fechamento..."></textarea>
                </div>

                <div class="flex space-x-3 pt-4 border-t">
                    <button wire:click="closeShift" 
                            wire:loading.attr="disabled"
                            class="flex-1 bg-gradient-to-r from-red-600 to-orange-600 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="closeShift">
                            <i class="fas fa-lock mr-2"></i>Fechar Turno
                        </span>
                        <span wire:loading wire:target="closeShift">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Fechando...
                        </span>
                    </button>
                    <button wire:click="$set('showCloseShiftModal', false)" 
                            class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
