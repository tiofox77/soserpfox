{{-- Modal de Detalhes da Venda --}}
<div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-receipt mr-2"></i>Detalhes da Venda
            </h3>
            <button wire:click="closeModals" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6">
            {{-- Informações da Fatura --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-file-invoice mr-2 text-indigo-600"></i>
                        Informações da Fatura
                    </h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Número:</span>
                            <span class="font-bold text-gray-900">{{ $selectedInvoice->invoice_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Data:</span>
                            <span class="font-semibold">{{ $selectedInvoice->invoice_date->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status:</span>
                            <span>
                                @if($selectedInvoice->status === 'paid')
                                <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">
                                    <i class="fas fa-check-circle mr-1"></i>Pago
                                </span>
                                @elseif($selectedInvoice->status === 'pending')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-bold">
                                    <i class="fas fa-clock mr-1"></i>Pendente
                                </span>
                                @elseif($selectedInvoice->status === 'partially_paid')
                                <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">
                                    <i class="fas fa-percent mr-1"></i>Parcialmente Pago
                                </span>
                                @else
                                <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">
                                    <i class="fas fa-times-circle mr-1"></i>Cancelado
                                </span>
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Pagamento:</span>
                            <span class="font-semibold uppercase">{{ $selectedInvoice->payment_method ?? 'Dinheiro' }}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-user mr-2 text-indigo-600"></i>
                        Cliente
                    </h4>
                    <div class="space-y-2 text-sm">
                        <div>
                            <span class="text-gray-600">Nome:</span>
                            <p class="font-bold text-gray-900">{{ $selectedInvoice->client->name }}</p>
                        </div>
                        <div>
                            <span class="text-gray-600">NIF:</span>
                            <p class="font-semibold">{{ $selectedInvoice->client->nif }}</p>
                        </div>
                        @if($selectedInvoice->client->phone)
                        <div>
                            <span class="text-gray-600">Telefone:</span>
                            <p class="font-semibold">{{ $selectedInvoice->client->phone }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Itens da Venda --}}
            <div class="mb-6">
                <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-shopping-basket mr-2 text-indigo-600"></i>
                    Produtos
                </h4>
                <div class="bg-gray-50 rounded-xl overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-700">Produto</th>
                                <th class="px-4 py-2 text-center text-xs font-bold text-gray-700">Qtd</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-700">Preço</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-700">IVA</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-700">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($selectedInvoice->items as $item)
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    <p class="font-semibold text-gray-900">{{ $item->product_name }}</p>
                                    @if($item->description)
                                    <p class="text-xs text-gray-500">{{ $item->description }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-sm font-semibold">
                                    {{ number_format($item->quantity, 0) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    {{ number_format($item->unit_price, 2) }} Kz
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    {{ number_format($item->tax_amount, 2) }} Kz
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-bold">
                                    {{ number_format($item->total, 2) }} Kz
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Totais --}}
            <div class="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-xl p-6 border-2 border-indigo-200">
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">Subtotal:</span>
                        <span class="font-bold text-gray-900">{{ number_format($selectedInvoice->subtotal, 2) }} Kz</span>
                    </div>
                    @if($selectedInvoice->discount_amount > 0)
                    <div class="flex justify-between text-sm text-orange-600">
                        <span>Desconto:</span>
                        <span class="font-bold">-{{ number_format($selectedInvoice->discount_amount, 2) }} Kz</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700">IVA (14%):</span>
                        <span class="font-bold text-gray-900">{{ number_format($selectedInvoice->tax_amount, 2) }} Kz</span>
                    </div>
                    <div class="border-t-2 border-indigo-300 pt-3 flex justify-between">
                        <span class="text-lg font-bold text-gray-900">TOTAL:</span>
                        <span class="text-2xl font-bold text-indigo-600">{{ number_format($selectedInvoice->total, 2) }} Kz</span>
                    </div>
                </div>
            </div>

            {{-- Observações --}}
            @if($selectedInvoice->notes)
            <div class="mt-6 bg-yellow-50 border-2 border-yellow-200 rounded-xl p-4">
                <h4 class="font-bold text-yellow-800 mb-2 flex items-center">
                    <i class="fas fa-sticky-note mr-2"></i>
                    Observações
                </h4>
                <p class="text-sm text-yellow-900">{{ $selectedInvoice->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Botões --}}
        <div class="px-6 pb-6 flex gap-3">
            <button wire:click="closeModals" 
                    class="flex-1 px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-bold transition">
                <i class="fas fa-times mr-2"></i>Fechar
            </button>
            <button wire:click="printInvoice({{ $selectedInvoice->id }})" 
                    class="flex-1 px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-bold shadow-lg transition">
                <i class="fas fa-print mr-2"></i>Imprimir
            </button>
        </div>
    </div>
</div>
