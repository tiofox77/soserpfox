<div>
    {{-- Header com Gradiente --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4 icon-float">
                    <i class="fas fa-receipt text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-bold">{{ $isEdit ? 'Editar Recibo' : 'Novo Recibo' }}</h2>
                    <p class="text-blue-100 text-sm mt-1">Comprovante de pagamento</p>
                </div>
            </div>
            <a href="{{ route('invoicing.receipts.index') }}" 
               class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-arrow-left mr-2"></i>Voltar
            </a>
        </div>
    </div>

    <form wire:submit.prevent="save" class="space-y-6">
        {{-- Card Principal com Animação --}}
        <div class="bg-white rounded-2xl shadow-lg border border-blue-100 overflow-hidden card-hover">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-info-circle text-white text-xl"></i>
                    </div>
                    <h3 class="text-white font-bold text-lg">Informações do Recibo</h3>
                </div>
            </div>
            <div class="p-6 space-y-6">
            
            {{-- Tipo de Recibo --}}
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-3 uppercase tracking-wider">
                    <i class="fas fa-tag mr-1 text-blue-600"></i>Tipo de Recibo *
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <label class="relative flex items-center p-4 bg-gradient-to-br from-green-50 to-emerald-50 border-2 {{ $type === 'sale' ? 'border-green-500 shadow-lg shadow-green-500/30' : 'border-gray-200' }} rounded-xl cursor-pointer transition-all hover:shadow-lg group">
                        <input type="radio" wire:model.live="type" value="sale" class="sr-only">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center {{ $type === 'sale' ? 'shadow-lg' : '' }}">
                                <i class="fas fa-shopping-cart text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-900">Venda</p>
                                <p class="text-xs text-gray-600">Recibo de Cliente</p>
                            </div>
                        </div>
                    </label>
                    <label class="relative flex items-center p-4 bg-gradient-to-br from-orange-50 to-red-50 border-2 {{ $type === 'purchase' ? 'border-orange-500 shadow-lg shadow-orange-500/30' : 'border-gray-200' }} rounded-xl cursor-pointer transition-all hover:shadow-lg group">
                        <input type="radio" wire:model.live="type" value="purchase" class="sr-only">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center {{ $type === 'purchase' ? 'shadow-lg' : '' }}">
                                <i class="fas fa-box text-white text-xl"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-900">Compra</p>
                                <p class="text-xs text-gray-600">Recibo de Fornecedor</p>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Cliente (se venda) --}}
                @if($type === 'sale')
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Cliente *</label>
                    @if($client_id && !$searchClient)
                        @php
                            $selectedClient = $clients->where('id', $client_id)->first();
                        @endphp
                        @if($selectedClient)
                        <div class="p-3 bg-green-50 border-2 border-green-200 rounded-xl">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-sm">{{ $selectedClient->name }}</div>
                                    <div class="text-xs text-gray-600">NIF: {{ $selectedClient->nif }}</div>
                                </div>
                                <button type="button" wire:click="$set('client_id', '')" class="text-red-600">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </div>
                        </div>
                        @endif
                    @else
                        <input type="text" wire:model.live="searchClient" placeholder="Pesquisar cliente..."
                               class="w-full rounded-lg border-gray-300">
                        @if($searchClient && $clients->count() > 0)
                        <div class="mt-2 border rounded-lg max-h-60 overflow-y-auto">
                            @foreach($clients as $client)
                            <div wire:click="selectClient({{ $client->id }})" 
                                 class="p-3 hover:bg-gray-100 cursor-pointer border-b">
                                <div class="font-bold text-sm">{{ $client->name }}</div>
                                <div class="text-xs text-gray-600">NIF: {{ $client->nif }}</div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    @endif
                    @error('client_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                @endif

                {{-- Fornecedor (se compra) --}}
                @if($type === 'purchase')
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Fornecedor *</label>
                    @if($supplier_id && !$searchSupplier)
                        @php
                            $selectedSupplier = $suppliers->where('id', $supplier_id)->first();
                        @endphp
                        @if($selectedSupplier)
                        <div class="p-3 bg-orange-50 border-2 border-orange-200 rounded-xl">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-sm">{{ $selectedSupplier->name }}</div>
                                    <div class="text-xs text-gray-600">NIF: {{ $selectedSupplier->nif }}</div>
                                </div>
                                <button type="button" wire:click="$set('supplier_id', '')" class="text-red-600">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </div>
                        </div>
                        @endif
                    @else
                        <input type="text" wire:model.live="searchSupplier" placeholder="Pesquisar fornecedor..."
                               class="w-full rounded-lg border-gray-300">
                        @if($searchSupplier && $suppliers->count() > 0)
                        <div class="mt-2 border rounded-lg max-h-60 overflow-y-auto">
                            @foreach($suppliers as $supplier)
                            <div wire:click="selectSupplier({{ $supplier->id }})" 
                                 class="p-3 hover:bg-gray-100 cursor-pointer border-b">
                                <div class="font-bold text-sm">{{ $supplier->name }}</div>
                                <div class="text-xs text-gray-600">NIF: {{ $supplier->nif }}</div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    @endif
                    @error('supplier_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                @endif

                {{-- Fatura Relacionada (opcional) --}}
                <div wire:key="invoice-select-{{ $client_id }}-{{ $supplier_id }}">
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                        <i class="fas fa-file-invoice mr-1 text-blue-600"></i>Fatura Relacionada (opcional)
                    </label>
                    @if(($type === 'sale' && $client_id) || ($type === 'purchase' && $supplier_id))
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-file-invoice text-blue-500"></i>
                            </div>
                            <select wire:model="invoice_id" class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all appearance-none bg-white">
                                <option value="">Sem fatura associada</option>
                                @foreach($invoices as $invoice)
                                <option value="{{ $invoice->id }}">
                                    {{ $invoice->invoice_number }} - {{ number_format($invoice->total, 2) }} AOA
                                    ({{ $invoice->invoice_date->format('d/m/Y') }})
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <p class="text-xs text-gray-600 mt-1"><i class="fas fa-info-circle mr-1"></i>Selecionar fatura atualiza automaticamente o status de pagamento</p>
                    @else
                        <div class="p-4 bg-gray-50 rounded-xl border-2 border-gray-200 text-center text-gray-500">
                            <i class="fas fa-arrow-up mr-1"></i>Selecione um {{ $type === 'sale' ? 'cliente' : 'fornecedor' }} primeiro para ver as faturas
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Valor Pago --}}
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                        <i class="fas fa-money-bill-wave mr-1 text-green-600"></i>Valor Pago (AOA) *
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-dollar-sign text-green-500"></i>
                        </div>
                        <input type="number" wire:model="amount_paid" step="0.01" min="0" 
                               class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all text-lg font-bold" 
                               placeholder="0.00">
                    </div>
                    @error('amount_paid') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Data do Pagamento --}}
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                        <i class="fas fa-calendar mr-1 text-blue-600"></i>Data do Pagamento *
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-calendar-alt text-blue-500"></i>
                        </div>
                        <input type="date" wire:model="payment_date" 
                               class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    </div>
                    @error('payment_date') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Método de Pagamento --}}
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                        <i class="fas fa-credit-card mr-1 text-purple-600"></i>Método de Pagamento *
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-wallet text-purple-500"></i>
                        </div>
                        <select wire:model="payment_method" 
                                class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all appearance-none bg-white">
                            <option value="cash">💵 Dinheiro</option>
                            <option value="transfer">🏦 Transferência</option>
                            <option value="multicaixa">💳 Multicaixa</option>
                            <option value="tpa">💳 TPA</option>
                            <option value="check">📝 Cheque</option>
                            <option value="mbway">📱 MB Way</option>
                            <option value="other">❓ Outro</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Referência --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Referência (opcional)</label>
                    <input type="text" wire:model="reference" 
                           class="w-full rounded-lg border-gray-300" 
                           placeholder="Ex: Nº transferência, nº cheque...">
                    <p class="text-xs text-gray-600 mt-1">Número de transferência, cheque, comprovante, etc</p>
                </div>

                {{-- Observações --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Observações (opcional)</label>
                    <textarea wire:model="notes" rows="3" 
                              class="w-full rounded-lg border-gray-300"
                              placeholder="Observações adicionais..."></textarea>
                </div>
            </div>

            {{-- Botões de Ação --}}
            <div class="flex gap-4 pt-6 border-t-2 border-gray-100 mt-8">
                <a href="{{ route('invoicing.receipts.index') }}" 
                   class="flex-1 px-8 py-4 bg-gray-100 text-gray-700 rounded-xl font-bold hover:bg-gray-200 transition-all text-center hover:shadow-lg transform hover:-translate-y-0.5">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </a>
                <button type="submit" 
                        class="flex-1 px-8 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl font-bold transition-all shadow-lg hover:shadow-2xl hover:shadow-blue-500/50 transform hover:-translate-y-0.5">
                    <i class="fas fa-save mr-2"></i>{{ $isEdit ? 'Atualizar' : 'Criar' }} Recibo
                </button>
            </div>
            </div>
        </div>
    </form>

    {{-- CSS Animations --}}
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .icon-float {
            animation: float 3s ease-in-out infinite;
        }

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        input:focus, select:focus, textarea:focus {
            transform: scale(1.01);
        }

        button:active {
            transform: scale(0.98);
        }
    </style>

    {{-- Toastr Notifications --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('notify', (event) => {
                const data = event[0] || event;
                const type = data.type || 'info';
                const message = data.message || 'Notificação';
                
                if (typeof toastr !== 'undefined') {
                    toastr.options = {
                        closeButton: true,
                        progressBar: true,
                        positionClass: 'toast-top-right',
                        timeOut: 3000
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
        });
    </script>
</div>
