<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-file-invoice mr-3 text-purple-600"></i>
                    {{ $isEdit ? 'Editar Fatura de Venda' : 'Nova Fatura de Venda' }}
                </h2>
                <p class="text-gray-600 mt-1">Crie orçamentos de compras de Clientees</p>
            </div>
            <a href="{{ route('invoicing.sales.invoices') }}" 
               class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                <i class="fas fa-arrow-left mr-2"></i>Voltar
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <form wire:submit.prevent="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left Column: Form --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Customer & Warehouse Info --}}
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                        <h3 class="text-white font-bold text-lg flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Informações Gerais
                        </h3>
                    </div>
                    <div class="p-6 space-y-4">
                        {{-- Client --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-user mr-1 text-purple-600"></i>Cliente *
                            </label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <input type="text" 
                                           wire:model.live.debounce.300ms="searchClient"
                                           placeholder="🔍 Pesquisar Cliente por nome, email ou telefone..."
                                           x-data
                                           @supplier-selected.window="$el.value = ''"
                                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition">
                                </div>
                                <button type="button" 
                                        wire:click="$set('showQuickClientModal', true)"
                                        class="px-4 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-semibold transition shadow-lg whitespace-nowrap">
                                    <i class="fas fa-plus mr-2"></i>Novo Cliente
                                </button>
                            </div>
                            @if($searchClient && $clients->count() > 0)
                            <div class="mt-2 max-h-60 overflow-y-auto border-2 border-gray-200 rounded-xl bg-white shadow-lg">
                                @foreach($clients as $client)
                                <div wire:click="selectClient({{ $client->id }})"
                                     class="p-3 hover:bg-purple-50 cursor-pointer transition border-b border-gray-100 last:border-b-0">
                                    <div class="font-bold text-sm text-gray-900">{{ $client->name }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ $client->email }} @if($client->phone) • {{ $client->phone }} @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endif
                            @if($client_id && !$searchClient)
                                @php
                                    $selectedClient = $clients->where('id', $client_id)->first();
                                @endphp
                                @if($selectedClient)
                                <div class="mt-2 p-3 {{ $selectedClient->nif === '999999999' ? 'bg-yellow-50 border-2 border-yellow-300' : 'bg-purple-50 border-2 border-purple-200' }} rounded-xl">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <div class="font-bold text-sm text-gray-900">{{ $selectedClient->name }}</div>
                                                @if($selectedClient->nif === '999999999')
                                                    <span class="px-2 py-0.5 bg-yellow-200 text-yellow-800 text-xs font-bold rounded-full">
                                                        <i class="fas fa-user-tag mr-1"></i>Cliente Padrão
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                @if($selectedClient->nif)
                                                    NIF: {{ $selectedClient->nif }}
                                                @endif
                                                @if($selectedClient->email)
                                                    • {{ $selectedClient->email }}
                                                @endif
                                            </div>
                                        </div>
                                        <button type="button" wire:click="clearClient"
                                                class="text-red-600 hover:text-red-800 ml-2">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    </div>
                                </div>
                                @endif
                            @endif
                            @error('client_id') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Warehouse --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-warehouse mr-1 text-purple-600"></i>Armazém *
                            </label>
                            <select wire:model="warehouse_id" 
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition">
                                <option value="">Selecione o armazém...</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                            @error('warehouse_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        {{-- Dates --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    <i class="fas fa-calendar mr-1 text-purple-600"></i>Data *
                                </label>
                                <input type="date" wire:model="invoice_date" 
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition">
                                @error('invoice_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    <i class="fas fa-hourglass-end mr-1 text-purple-600"></i>Válido Até
                                </label>
                                <input type="date" wire:model="due_date" 
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition">
                                @error('due_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Products --}}
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                        <h3 class="text-white font-bold text-lg flex items-center">
                            <i class="fas fa-box mr-2"></i>
                            Produtos ({{ $cartItems->count() }})
                        </h3>
                        <button type="button" wire:click="$set('showProductModal', true)"
                                class="px-4 py-2 bg-white hover:bg-gray-100 text-purple-600 rounded-lg font-semibold transition">
                            <i class="fas fa-plus mr-2"></i>Adicionar Produto
                        </button>
                    </div>

                    @if($cartItems->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Produto/Serviço</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Qtd</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Preço</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Desc %</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">
                                        <i class="fas fa-percent mr-1 text-blue-500"></i>Taxa
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Total</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($cartItems as $item)
                                <tr class="hover:bg-purple-50 transition" wire:key="cart-item-{{ $item->id }}">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="text-sm font-bold text-gray-900">{{ $item->name }}</div>
                                            @if(isset($item->attributes['type']) && $item->attributes['type'] === 'servico')
                                                <span class="px-2 py-0.5 bg-pink-100 text-pink-700 text-xs font-semibold rounded-full">
                                                    <i class="fas fa-concierge-bell mr-1"></i>Serviço
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">
                                                    <i class="fas fa-box mr-1"></i>Produto
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500">{{ $item->attributes['unit'] }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="1" min="1"
                                               wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                               value="{{ $item->quantity }}"
                                               class="w-20 px-2 py-1 text-center border border-gray-300 rounded focus:ring-2 focus:ring-purple-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="0.01" min="0"
                                               wire:change="updatePrice({{ $item->id }}, $event.target.value)"
                                               value="{{ $item->price }}"
                                               class="w-28 px-2 py-1 text-center border border-gray-300 rounded focus:ring-2 focus:ring-purple-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="0.01" min="0" max="100"
                                               wire:change="updateDiscount({{ $item->id }}, $event.target.value)"
                                               value="{{ $item->attributes['discount_percent'] ?? 0 }}"
                                               class="w-20 px-2 py-1 text-center border border-gray-300 rounded focus:ring-2 focus:ring-purple-500"
                                               placeholder="0">
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @php
                                            $taxRate = $item->attributes['tax_rate'] ?? 0;
                                            $taxType = $item->attributes['tax_type'] ?? 'iva';
                                        @endphp
                                        
                                        @if($taxType === 'isento' || $taxRate == 0)
                                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-bold rounded inline-flex items-center">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Isento
                                            </span>
                                        @else
                                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded inline-flex items-center">
                                                <i class="fas fa-percent mr-1"></i>
                                                IVA {{ $taxRate }}%
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @php
                                            // Usar métodos do Cart para cálculos corretos
                                            $itemPriceSum = $item->getPriceSum(); // Preço × Qtd com desconto aplicado
                                            $itemTax = $itemPriceSum * (($item->attributes['tax_rate'] ?? 14) / 100);
                                            $itemGrandTotal = $itemPriceSum + $itemTax;
                                            
                                            // Calcular desconto aplicado
                                            $itemSubtotalOriginal = $item->price * $item->quantity;
                                            $itemDiscountAmount = $itemSubtotalOriginal - $itemPriceSum;
                                        @endphp
                                        <span class="text-lg font-bold text-gray-900">{{ number_format($itemGrandTotal, 2) }}</span>
                                        <div class="text-xs text-gray-500">Kz</div>
                                        @if($itemDiscountAmount > 0)
                                        <div class="text-xs text-purple-600">-{{ number_format($itemDiscountAmount, 2) }} Kz desc</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" wire:click="removeProduct({{ $item->id }})"
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
                        <p class="text-gray-400 text-sm mt-2">Clique em "Adicionar Produto" para começar</p>
                    </div>
                    @endif
                </div>

                {{-- Descontos --}}
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                        <h3 class="text-white font-bold text-lg flex items-center">
                            <i class="fas fa-percentage mr-2"></i>
                            Descontos
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-3 gap-4">
                            {{-- Desconto Comercial --}}
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    <i class="fas fa-tags mr-1 text-purple-600"></i>
                                    Desconto Comercial (antes IVA):
                                </label>
                                <input type="number" step="0.01" min="0" wire:model.live="discount_commercial"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition text-right"
                                       placeholder="0">
                            </div>

                            {{-- Desconto Legado --}}
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Desconto (legado):</label>
                                <input type="number" step="0.01" min="0" wire:model.live="discount_amount"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-gray-400 focus:ring-2 focus:ring-gray-200 transition text-right"
                                       placeholder="0">
                            </div>

                            {{-- Desconto Financeiro --}}
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    <i class="fas fa-hand-holding-usd mr-1 text-green-600"></i>
                                    Desconto Financeiro (após IVA):
                                </label>
                                <input type="number" step="0.01" min="0" wire:model.live="discount_financial"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 transition text-right"
                                       placeholder="0">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Notes & Terms --}}
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                        <h3 class="text-white font-bold text-lg flex items-center">
                            <i class="fas fa-sticky-note mr-2"></i>
                            Observações
                        </h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Notas</label>
                            <textarea wire:model="notes" rows="3"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition"
                                      placeholder="Informações adicionais..."></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Termos e Condições</label>
                            <textarea wire:model="terms" rows="3"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition"
                                      placeholder="Condições de pagamento, garantias, etc."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Totals & Actions --}}
            <div class="space-y-6">
                {{-- Totals --}}
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden sticky top-6">
                    <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
                        <h3 class="text-white font-bold text-lg flex items-center">
                            <i class="fas fa-calculator mr-2"></i>
                            Resumo
                        </h3>
                    </div>
                    <div class="p-6">
                        {{-- Tipo de Documento --}}
                        <div class="pb-4 mb-4 border-b border-gray-200">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.live="is_service" 
                                       class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                <span class="ml-3 text-sm font-bold text-gray-700">
                                    <i class="fas fa-concierge-bell mr-1 text-purple-600"></i>
                                    É Prestação de Serviço (IRT 6.5%)
                                </span>
                            </label>
                        </div>
                        
                        {{-- Resumo Simplificado - MODELO AGT ANGOLA --}}
                        <div class="space-y-3">
                            {{-- Total Líquido (Total Bruto) --}}
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-sm font-semibold text-gray-700">Total líquido</span>
                                <span class="text-base font-bold text-gray-900">{{ number_format($subtotal_original, 2) }}</span>
                            </div>
                            
                            {{-- Desconto Comercial (Linhas + Adicional) --}}
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-sm font-semibold text-gray-700">Desconto Comercial</span>
                                <span class="text-base font-bold text-gray-900">{{ number_format($desconto_comercial_total ?? 0, 2) }}</span>
                            </div>
                            
                            {{-- Desconto Financeiro --}}
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-sm font-semibold text-gray-700">Desconto Financeiro</span>
                                <span class="text-base font-bold text-gray-900">{{ number_format($discount_financial, 2) }}</span>
                            </div>
                            
                            {{-- Total De Imposto (IVA sobre Incidência) --}}
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-sm font-semibold text-gray-700">Total De Imposto</span>
                                <span class="text-base font-bold text-gray-900">{{ number_format($tax_amount, 2) }}</span>
                            </div>
                            
                            {{-- Retenção IRT (6,5% sobre Incidência IVA) --}}
                            <div class="flex justify-between items-center py-2 border-b-2 border-gray-300">
                                <span class="text-sm font-semibold text-gray-700">Retenção (6,5%)</span>
                                <span class="text-base font-bold text-gray-900">{{ number_format($irt_amount, 2) }}</span>
                            </div>
                            
                            {{-- Total (AOA) - Incidência + IVA - Retenção --}}
                            <div class="flex justify-between items-center pt-3">
                                <span class="text-lg font-bold text-gray-700">Total (AOA)</span>
                                <span class="text-3xl font-bold text-green-600">{{ number_format($total, 2) }}</span>
                            </div>
                            
                            {{-- Info Adicional --}}
                            <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-gray-600">
                                <div class="flex justify-between mb-1">
                                    <span>Incidência IVA (Base):</span>
                                    <span class="font-semibold">{{ number_format($incidencia_iva ?? 0, 2) }} Kz</span>
                                </div>
                                <div class="text-gray-500 text-[10px] mt-2">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Cálculo conforme Decreto Presidencial 312/18 - AGT Angola
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="bg-white rounded-2xl shadow-xl p-6 space-y-3">
                    <button type="button" wire:click="save('draft')"
                            class="w-full px-6 py-3 border-2 border-purple-600 text-purple-600 hover:bg-purple-50 rounded-xl font-bold transition">
                        <i class="fas fa-save mr-2"></i>Salvar Rascunho
                    </button>
                    <button type="button" wire:click="save('sent')"
                            class="w-full px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-xl font-bold transition shadow-lg">
                        <i class="fas fa-paper-plane mr-2"></i>Salvar e Enviar
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Quick Client Creation Modal --}}
    @if($showQuickClientModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4 animate-fade-in">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto animate-scale-in">
            <div class="sticky top-0 bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4 flex items-center justify-between z-10">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-user-plus mr-2"></i>
                    Criar Cliente Rápido
                </h3>
                <button wire:click="$set('showQuickClientModal', false)" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <form wire:submit.prevent="createquickClient">
                <div class="p-6 space-y-4">
                    {{-- Name --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-user mr-1 text-green-600"></i>Nome *
                        </label>
                        <input type="text" wire:model="quickClientName"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 transition"
                               placeholder="Nome do Cliente..." required>
                        @error('quickClientName') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    {{-- Tax ID (NIF) --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-id-card mr-1 text-green-600"></i>NIF (9 ou 14 dígitos)
                        </label>
                        <input type="text" wire:model="quickClientTaxId"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 transition"
                               placeholder="999999999"
                               maxlength="14">
                        @error('quickClientTaxId') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            NIF genérico Angola: 999999999
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        {{-- Email --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-envelope mr-1 text-green-600"></i>Email
                            </label>
                            <input type="email" wire:model="quickClientEmail"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 transition"
                                   placeholder="email@exemplo.ao">
                            @error('quickClientEmail') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        {{-- Phone --}}
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-phone mr-1 text-green-600"></i>Telefone
                            </label>
                            <input type="text" wire:model="quickClientPhone"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 transition"
                                   placeholder="923456789">
                            @error('quickClientPhone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Address --}}
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-1 text-green-600"></i>Morada
                        </label>
                        <textarea wire:model="quickClientAddress" rows="2"
                                  class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 transition"
                                  placeholder="Rua, Bairro, Cidade..."></textarea>
                    </div>
                </div>

                <div class="sticky bottom-0 bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end gap-3 border-t border-gray-200">
                    <button type="button" wire:click="$set('showQuickClientModal', false)"
                            class="px-6 py-3 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-semibold transition shadow-lg">
                        <i class="fas fa-save mr-2"></i>Criar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Toastr Notifications & Preview Handler --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('notify', (event) => {
                const data = event[0] || event;
                const type = data.type || 'info';
                const message = data.message || 'Ação realizada';
                
                // Configure toastr
                if (typeof toastr !== 'undefined') {
                    toastr.options = {
                        "closeButton": true,
                        "progressBar": true,
                        "positionClass": "toast-top-right",
                        "timeOut": "3000",
                        "showDuration": "300",
                        "hideDuration": "1000"
                    };
                    
                    switch(type) {
                        case 'success':
                            toastr.success(message, 'Sucesso');
                            break;
                        case 'error':
                            toastr.error(message, 'Erro');
                            break;
                        case 'warning':
                            toastr.warning(message, 'Atenção');
                            break;
                        case 'info':
                            toastr.info(message, 'Info');
                            break;
                    }
                }
            });
            
            // Abrir preview da fatura em nova aba para impressão
            Livewire.on('openInvoicePreview', (event) => {
                const data = event[0] || event;
                const invoiceId = data.invoiceId;
                
                if (invoiceId) {
                    // Abrir preview em nova aba
                    const previewUrl = '{{ url("/invoicing/sales/invoices") }}/' + invoiceId + '/preview';
                    window.open(previewUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes,toolbar=yes,location=yes');
                }
            });
        });
    </script>

    {{-- Product Selection Modal --}}
    @if($showProductModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4 animate-fade-in">
        <div class="bg-white rounded-2xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-y-auto animate-scale-in">
            <div class="sticky top-0 bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between z-10">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-box-open mr-2"></i>
                    Selecionar Produtos
                </h3>
                <button wire:click="$set('showProductModal', false)" class="text-white hover:text-gray-200 transition">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                {{-- Search --}}
                <div class="mb-6">
                    <input type="text" wire:model.live.debounce.300ms="searchProduct"
                           placeholder="🔍 Pesquisar produtos..."
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition">
                </div>

                {{-- Products Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @forelse($products as $product)
                    <div wire:click="addProduct({{ $product->id }})"
                         class="p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-400 hover:shadow-lg bg-white hover:scale-105 transition-all duration-200">
                        <div class="flex flex-col h-full">
                            <div class="mb-3">
                                <p class="font-bold text-sm text-gray-900 line-clamp-2">{{ $product->name }}</p>
                                <p class="text-xs text-gray-600 mt-1">{{ $product->code }}</p>
                            </div>
                            <div class="mt-auto pt-3 border-t border-gray-200">
                                <p class="text-lg font-bold text-purple-600">{{ number_format($product->sale_price, 2) }} Kz</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-cube mr-1"></i>
                                    Stock: {{ number_format($product->stock_quantity ?? 0, 0) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Nenhum produto encontrado</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
