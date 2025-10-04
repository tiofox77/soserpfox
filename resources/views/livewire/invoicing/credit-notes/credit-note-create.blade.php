<div>
    {{-- Header Verde --}}
    <div class="mb-6 bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4 icon-float">
                    <i class="fas fa-file-circle-minus text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-bold">Nova Nota de Crédito</h2>
                    <p class="text-green-100 text-sm mt-1">Devolução, desconto ou correção</p>
                </div>
            </div>
            <a href="{{ route('invoicing.credit-notes.index') }}" 
               class="bg-white text-green-600 hover:bg-green-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-arrow-left mr-2"></i>Voltar
            </a>
        </div>
    </div>

    <form wire:submit.prevent="save">
        {{-- Informações Gerais --}}
        <div class="bg-white rounded-2xl shadow-lg border border-green-100 overflow-hidden card-hover mb-6">
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-info-circle text-white text-xl"></i>
                    </div>
                    <h3 class="text-white font-bold text-lg">Informações Gerais</h3>
                </div>
            </div>
            <div class="p-6 space-y-6">
                {{-- Cliente --}}
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                        <i class="fas fa-user mr-1 text-green-600"></i>Cliente *
                    </label>
                    @if($client_id && !$searchClient)
                        @php $selectedClient = $clients->where('id', $client_id)->first(); @endphp
                        @if($selectedClient)
                        <div class="p-4 bg-green-50 border-2 border-green-300 rounded-xl">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-lg">{{ $selectedClient->name }}</div>
                                    <div class="text-sm text-gray-600">NIF: {{ $selectedClient->nif }}</div>
                                </div>
                                <button type="button" wire:click="$set('client_id', '')" class="text-red-600 hover:text-red-700">
                                    <i class="fas fa-times-circle text-2xl"></i>
                                </button>
                            </div>
                        </div>
                        @endif
                    @else
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" wire:model.live="searchClient" placeholder="Pesquisar cliente..."
                                   class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        </div>
                        @if($searchClient && $clients->count() > 0)
                        <div class="mt-2 border-2 border-gray-200 rounded-xl max-h-60 overflow-y-auto">
                            @foreach($clients as $client)
                            <div wire:click="selectClient({{ $client->id }})" 
                                 class="p-3 hover:bg-green-50 cursor-pointer border-b transition-colors">
                                <div class="font-bold">{{ $client->name }}</div>
                                <div class="text-sm text-gray-600">NIF: {{ $client->nif }}</div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    @endif
                    @error('client_id') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                            <i class="fas fa-calendar mr-1 text-green-600"></i>Data de Emissão *
                        </label>
                        <input type="date" wire:model="issue_date" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                            <i class="fas fa-file-invoice mr-1 text-green-600"></i>Fatura Original (opcional)
                        </label>
                        <select wire:model.live="invoice_id" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                            <option value="">Sem fatura</option>
                            @foreach($invoices as $invoice)
                            <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} - {{ number_format($invoice->total, 2) }} AOA</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-600 mt-1"><i class="fas fa-info-circle mr-1"></i>Os produtos da fatura serão carregados automaticamente</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                            <i class="fas fa-tag mr-1 text-green-600"></i>Motivo *
                        </label>
                        <select wire:model="reason" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                            <option value="return">↩️ Devolução</option>
                            <option value="discount">💰 Desconto</option>
                            <option value="correction">✏️ Correção</option>
                            <option value="other">❓ Outro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                            <i class="fas fa-layer-group mr-1 text-green-600"></i>Tipo *
                        </label>
                        <select wire:model="type" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                            <option value="partial">📝 Parcial</option>
                            <option value="total">📋 Total</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                        <i class="fas fa-comment mr-1 text-green-600"></i>Observações
                    </label>
                    <textarea wire:model="notes" rows="2" 
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500"
                              placeholder="Observações adicionais..."></textarea>
                </div>
            </div>
        </div>

        {{-- Produtos --}}
        <div class="bg-white rounded-2xl shadow-lg border border-green-100 overflow-hidden card-hover mb-6">
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                            <i class="fas fa-shopping-cart text-white text-xl"></i>
                        </div>
                        <h3 class="text-white font-bold text-lg">Produtos/Serviços ({{ $cartItems->count() }})</h3>
                    </div>
                    <button type="button" wire:click="$set('showProductModal', true)" 
                            class="bg-white text-green-600 hover:bg-green-50 px-4 py-2 rounded-xl font-semibold transition-all shadow-lg">
                        <i class="fas fa-plus mr-2"></i>Adicionar
                    </button>
                </div>
            </div>
            <div class="p-6">
                @if($cartItems->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-green-50 to-emerald-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Produto/Descrição</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Quantidade</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Preço Unit.</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Taxa</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Total</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($cartItems as $item)
                                <tr class="hover:bg-green-50 transition" wire:key="cart-item-{{ $item->id }}">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="text-sm font-bold text-gray-900">{{ $item->name }}</div>
                                            <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">
                                                <i class="fas fa-box mr-1"></i>Produto
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="1" min="1"
                                               wire:change="updateQuantity('{{ $item->id }}', $event.target.value)"
                                               value="{{ number_format($item->quantity, 0, '', '') }}"
                                               class="w-20 px-2 py-1 text-center border border-gray-300 rounded focus:ring-2 focus:ring-green-500 font-bold">
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-sm font-semibold text-gray-900">{{ number_format($item->price, 2) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded inline-flex items-center">
                                            <i class="fas fa-percent mr-1"></i>
                                            IVA {{ $item->attributes['tax_rate'] ?? 14 }}%
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @php
                                            $itemSubtotal = $item->price * $item->quantity;
                                            $itemTax = $itemSubtotal * (($item->attributes['tax_rate'] ?? 14) / 100);
                                            $itemTotal = $itemSubtotal + $itemTax;
                                        @endphp
                                        <span class="text-lg font-bold text-gray-900">{{ number_format($itemTotal, 2) }}</span>
                                        <div class="text-xs text-gray-500">AOA</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" wire:click="removeItem('{{ $item->id }}')"
                                                class="p-2 bg-red-100 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-12 text-center">
                        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg font-semibold">Nenhum produto adicionado</p>
                        <p class="text-gray-400 text-sm mt-2">Clique em "Adicionar" ou selecione uma fatura para começar</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Resumo --}}
        <div class="bg-white rounded-2xl shadow-lg border border-green-100 overflow-hidden">
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <i class="fas fa-calculator mr-2"></i>Resumo
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Subtotal:</span>
                    <span class="font-bold">{{ number_format($subtotal_original, 2) }} AOA</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">IVA (14%):</span>
                    <span class="font-bold">{{ number_format($tax_amount, 2) }} AOA</span>
                </div>
                <div class="border-t-2 border-gray-200 pt-4 flex justify-between">
                    <span class="font-bold text-lg">Total:</span>
                    <span class="font-bold text-2xl text-green-600">{{ number_format($total, 2) }} AOA</span>
                </div>

                <div class="flex gap-4 pt-4 border-t-2">
                    <a href="{{ route('invoicing.credit-notes.index') }}" 
                       class="flex-1 px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-bold hover:bg-gray-200 transition-all text-center">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                    <button type="submit" 
                            class="flex-1 px-6 py-4 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-bold transition-all shadow-lg">
                        <i class="fas fa-save mr-2"></i>Criar Nota de Crédito
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Modal Produtos --}}
    @if($showProductModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4 text-white">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-xl">Adicionar Produto</h3>
                    <button type="button" wire:click="$set('showProductModal', false)" class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <input type="text" wire:model.live="searchProduct" placeholder="Pesquisar produto..." 
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl mb-4">
                <div class="max-h-96 overflow-y-auto space-y-2">
                    @foreach($products as $product)
                    <div wire:click="addProduct({{ $product->id }})" 
                         class="p-4 border-2 border-gray-200 rounded-xl hover:border-green-500 hover:bg-green-50 cursor-pointer transition-all">
                        <div class="font-bold">{{ $product->name }}</div>
                        <div class="text-sm text-gray-600">{{ $product->code }} - {{ number_format($product->price, 2) }} AOA</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    <style>
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-5px); } }
        .icon-float { animation: float 3s ease-in-out infinite; }
        .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    </style>
</div>
