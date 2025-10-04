{{-- View Invoice Modal --}}
@if($showViewModal && $selectedInvoice)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full my-8 animate-scale-in">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-file-invoice mr-2"></i>
                Fatura {{ $selectedInvoice->invoice_number }}
            </h3>
            <button wire:click="closeViewModal" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        {{-- Body --}}
        <div class="p-6 max-h-[calc(100vh-200px)] overflow-y-auto">
            {{-- Informações do Fornecedor --}}
            <div class="mb-6">
                <h4 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-truck mr-2 text-orange-600"></i>
                    Informações do Fornecedor
                </h4>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="font-bold text-gray-900">{{ $selectedInvoice->supplier->name }}</p>
                    @if($selectedInvoice->supplier->nif)
                    <p class="text-sm text-gray-600">NIF: {{ $selectedInvoice->supplier->nif }}</p>
                    @endif
                    @if($selectedInvoice->supplier->email)
                    <p class="text-sm text-gray-600">Email: {{ $selectedInvoice->supplier->email }}</p>
                    @endif
                    @if($selectedInvoice->supplier->phone)
                    <p class="text-sm text-gray-600">Tel: {{ $selectedInvoice->supplier->phone }}</p>
                    @endif
                </div>
            </div>
            
            {{-- Datas e Status --}}
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Data da Fatura:</p>
                    <p class="font-bold text-gray-900">{{ $selectedInvoice->invoice_date->format('d/m/Y') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 mb-1">Vencimento:</p>
                    <p class="font-bold text-gray-900">
                        {{ $selectedInvoice->due_date ? $selectedInvoice->due_date->format('d/m/Y') : '-' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 mb-1">Status:</p>
                    @if($selectedInvoice->status === 'draft')
                        <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-bold rounded-full">
                            Rascunho
                        </span>
                    @elseif($selectedInvoice->status === 'sent')
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded-full">
                            Enviada
                        </span>
                    @elseif($selectedInvoice->status === 'accepted')
                        <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">
                            Aceite
                        </span>
                    @else
                        <span class="px-3 py-1 bg-purple-100 text-purple-800 text-xs font-bold rounded-full">
                            Convertida
                        </span>
                    @endif
                </div>
            </div>
            
            {{-- Produtos --}}
            <div class="mb-6">
                <h4 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-box mr-2 text-orange-600"></i>
                    Produtos
                </h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-700">Produto</th>
                                <th class="px-4 py-2 text-center text-xs font-bold text-gray-700">Qtd</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-700">Preço</th>
                                <th class="px-4 py-2 text-center text-xs font-bold text-gray-700">Desc%</th>
                                <th class="px-4 py-2 text-center text-xs font-bold text-gray-700">IVA</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-700">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($selectedInvoice->items as $item)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900">{{ $item->description }}</p>
                                    <p class="text-xs text-gray-500">{{ $item->unit }}</p>
                                </td>
                                <td class="px-4 py-3 text-center">{{ $item->quantity }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($item->unit_price, 2) }} Kz</td>
                                <td class="px-4 py-3 text-center">{{ $item->discount_percent }}%</td>
                                <td class="px-4 py-3 text-center">{{ $item->tax_rate }}%</td>
                                <td class="px-4 py-3 text-right font-bold">{{ number_format($item->total, 2) }} Kz</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            
            {{-- Totais --}}
            <div class="bg-gray-50 rounded-lg p-4">
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-semibold">{{ number_format($selectedInvoice->subtotal, 2) }} Kz</span>
                    </div>
                    @if($selectedInvoice->discount_commercial > 0 || $selectedInvoice->discount_amount > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Desconto Comercial:</span>
                        <span class="font-semibold">{{ number_format($selectedInvoice->discount_commercial + $selectedInvoice->discount_amount, 2) }} Kz</span>
                    </div>
                    @endif
                    @if($selectedInvoice->discount_financial > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Desconto Financeiro:</span>
                        <span class="font-semibold">{{ number_format($selectedInvoice->discount_financial, 2) }} Kz</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">IVA:</span>
                        <span class="font-semibold">{{ number_format($selectedInvoice->tax_amount, 2) }} Kz</span>
                    </div>
                    @if($selectedInvoice->irt_amount > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Retenção (6,5%):</span>
                        <span class="font-semibold">{{ number_format($selectedInvoice->irt_amount, 2) }} Kz</span>
                    </div>
                    @endif
                    <div class="flex justify-between pt-2 border-t-2 border-gray-300">
                        <span class="text-lg font-bold text-gray-900">TOTAL:</span>
                        <span class="text-2xl font-bold text-green-600">{{ number_format($selectedInvoice->total, 2) }} Kz</span>
                    </div>
                </div>
            </div>
            
            {{-- Notas --}}
            @if($selectedInvoice->notes)
            <div class="mt-6">
                <h4 class="text-sm font-bold text-gray-700 mb-2">Notas:</h4>
                <p class="text-sm text-gray-600">{{ $selectedInvoice->notes }}</p>
            </div>
            @endif
        </div>
        
        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end gap-3">
            <button wire:click="closeViewModal" 
                    class="px-4 py-2 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                Fechar
            </button>
            <a href="{{ route('invoicing.purchases.invoices.preview', $selectedInvoice->id) }}" target="_blank"
               class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition">
                <i class="fas fa-file-pdf mr-2"></i>Preview
            </a>
        </div>
    </div>
</div>
@endif
