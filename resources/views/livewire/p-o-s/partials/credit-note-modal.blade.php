{{-- Modal Nota de Crédito POS --}}
<div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:keydown.escape.window="closeModals">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-file-circle-minus mr-2"></i>Nota de Crédito
            </h3>
            <button wire:click="closeModals" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <div class="p-6">
            {{-- Info da Fatura Original --}}
            <div class="bg-orange-50 border-2 border-orange-200 rounded-xl p-4 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-orange-600 font-semibold">Fatura Original</p>
                        <p class="text-lg font-bold text-gray-900">{{ $creditNoteInvoice->invoice_number }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-orange-600 font-semibold">Total</p>
                        <p class="text-lg font-bold text-gray-900">{{ number_format($creditNoteInvoice->total, 2) }} Kz</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-orange-600 font-semibold">Cliente</p>
                        <p class="text-lg font-bold text-gray-900">{{ $creditNoteInvoice->client->name }}</p>
                    </div>
                </div>
            </div>

            {{-- Motivo e Tipo --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                        <i class="fas fa-tag mr-1 text-orange-600"></i>Motivo
                    </label>
                    <select wire:model="creditNoteReason" class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition text-sm">
                        <option value="return">Devolução</option>
                        <option value="discount">Desconto</option>
                        <option value="correction">Correção</option>
                        <option value="other">Outro</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                        <i class="fas fa-layer-group mr-1 text-orange-600"></i>Tipo
                    </label>
                    <select wire:model="creditNoteType" class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition text-sm">
                        <option value="total">Total (Anulação)</option>
                        <option value="partial">Parcial (Rectificação)</option>
                    </select>
                </div>
            </div>

            {{-- Observações --}}
            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                    <i class="fas fa-comment mr-1 text-orange-600"></i>Observações (opcional)
                </label>
                <input type="text" wire:model="creditNoteNotes" placeholder="Motivo adicional..."
                       class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition text-sm">
            </div>

            {{-- Itens --}}
            <div class="mb-6">
                <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-shopping-basket mr-2 text-orange-600"></i>
                    Itens a Creditar
                </h4>
                <div class="bg-gray-50 rounded-xl overflow-hidden border border-gray-200">
                    <table class="w-full">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 w-10"></th>
                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-700">Produto</th>
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-700 w-24">Qtd</th>
                                <th class="px-3 py-2 text-right text-xs font-bold text-gray-700">Preço</th>
                                <th class="px-3 py-2 text-right text-xs font-bold text-gray-700">IVA</th>
                                <th class="px-3 py-2 text-right text-xs font-bold text-gray-700">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($creditNoteItems as $index => $item)
                            <tr class="{{ $item['selected'] ? 'bg-white' : 'bg-gray-100 opacity-60' }}">
                                <td class="px-3 py-2 text-center">
                                    <input type="checkbox" 
                                           wire:click="toggleCreditNoteItem({{ $index }})"
                                           {{ $item['selected'] ? 'checked' : '' }}
                                           class="w-4 h-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                                </td>
                                <td class="px-3 py-2 text-sm font-semibold text-gray-900">
                                    {{ $item['product_name'] }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if($item['selected'])
                                    <input type="number" 
                                           wire:change="updateCreditNoteQty({{ $index }}, $event.target.value)"
                                           value="{{ (float) $item['quantity'] + 0 }}"
                                           min="0.001" max="{{ (float) $item['max_quantity'] + 0 }}" step="any"
                                           class="w-20 px-2 py-1 border border-gray-300 rounded-lg text-center text-sm focus:ring-2 focus:ring-orange-500">
                                    @else
                                    <span class="text-sm text-gray-500">{{ (float) $item['quantity'] + 0 }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right text-sm">
                                    {{ number_format($item['unit_price'], 2) }} Kz
                                </td>
                                <td class="px-3 py-2 text-right text-sm">
                                    {{ number_format($item['unit_price'] * $item['quantity'] * ($item['tax_rate'] / 100), 2) }} Kz
                                </td>
                                <td class="px-3 py-2 text-right text-sm font-bold">
                                    @if($item['selected'])
                                    {{ number_format($item['unit_price'] * $item['quantity'] * (1 + $item['tax_rate'] / 100), 2) }} Kz
                                    @else
                                    <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Resumo --}}
            @php
                $cnSubtotal = 0;
                $cnTax = 0;
                foreach ($creditNoteItems as $item) {
                    if ($item['selected']) {
                        $lineSubtotal = $item['unit_price'] * $item['quantity'];
                        $cnSubtotal += $lineSubtotal;
                        $cnTax += $lineSubtotal * ($item['tax_rate'] / 100);
                    }
                }
                $cnTotal = $cnSubtotal + $cnTax;
            @endphp
            <div class="bg-gradient-to-br from-orange-50 to-red-50 rounded-xl p-4 border-2 border-orange-200">
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">Subtotal:</span>
                        <span class="font-bold text-gray-900">{{ number_format($cnSubtotal, 2) }} Kz</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">IVA:</span>
                        <span class="font-bold text-gray-900">{{ number_format($cnTax, 2) }} Kz</span>
                    </div>
                    <div class="border-t-2 border-orange-300 pt-2 flex justify-between">
                        <span class="text-lg font-bold text-gray-900">TOTAL NC:</span>
                        <span class="text-2xl font-bold text-orange-600">{{ number_format($cnTotal, 2) }} Kz</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Botões --}}
        <div class="px-6 pb-6 flex gap-3">
            <button wire:click="closeModals" 
                    class="flex-1 px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-bold transition">
                <i class="fas fa-times mr-2"></i>Cancelar
            </button>
            <button wire:click="saveCreditNote" 
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-70 cursor-wait"
                    class="flex-1 px-6 py-3 bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 text-white rounded-xl font-bold shadow-lg transition">
                <span wire:loading.remove wire:target="saveCreditNote">
                    <i class="fas fa-file-circle-minus mr-2"></i>Emitir Nota de Crédito
                </span>
                <span wire:loading wire:target="saveCreditNote">
                    <i class="fas fa-spinner fa-spin mr-2"></i>A processar...
                </span>
            </button>
        </div>
    </div>
</div>
