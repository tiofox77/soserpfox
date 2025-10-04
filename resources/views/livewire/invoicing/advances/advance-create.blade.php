<div>
    {{-- Header Amarelo --}}
    <div class="mb-6 bg-gradient-to-r from-yellow-600 to-amber-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center mr-4 icon-float">
                    <i class="fas fa-coins text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-bold">{{ $isEdit ? 'Editar Adiantamento' : 'Novo Adiantamento' }}</h2>
                    <p class="text-yellow-100 text-sm mt-1">Pagamento antecipado do cliente</p>
                </div>
            </div>
            <a href="{{ route('invoicing.advances.index') }}" 
               class="bg-white text-yellow-600 hover:bg-yellow-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-arrow-left mr-2"></i>Voltar
            </a>
        </div>
    </div>

    <form wire:submit.prevent="save">
        <div class="bg-white rounded-2xl shadow-lg border border-yellow-100 overflow-hidden card-hover">
            <div class="bg-gradient-to-r from-yellow-600 to-amber-600 px-6 py-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-info-circle text-white text-xl"></i>
                    </div>
                    <h3 class="text-white font-bold text-lg">Informações do Adiantamento</h3>
                </div>
            </div>
            <div class="p-6 space-y-6">
                {{-- Cliente --}}
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                        <i class="fas fa-user mr-1 text-yellow-600"></i>Cliente *
                    </label>
                    @if($client_id && !$searchClient)
                        @php $selectedClient = $clients->where('id', $client_id)->first(); @endphp
                        @if($selectedClient)
                        <div class="p-4 bg-yellow-50 border-2 border-yellow-300 rounded-xl">
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
                                   class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all">
                        </div>
                        @if($searchClient && $clients->count() > 0)
                        <div class="mt-2 border-2 border-gray-200 rounded-xl max-h-60 overflow-y-auto">
                            @foreach($clients as $client)
                            <div wire:click="selectClient({{ $client->id }})" 
                                 class="p-3 hover:bg-yellow-50 cursor-pointer border-b transition-colors">
                                <div class="font-bold">{{ $client->name }}</div>
                                <div class="text-sm text-gray-600">NIF: {{ $client->nif }}</div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    @endif
                    @error('client_id') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Data --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                            <i class="fas fa-calendar mr-1 text-yellow-600"></i>Data do Adiantamento *
                        </label>
                        <input type="date" wire:model="payment_date" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500">
                        @error('payment_date') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Valor --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                            <i class="fas fa-money-bill-wave mr-1 text-yellow-600"></i>Valor (AOA) *
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-dollar-sign text-yellow-500"></i>
                            </div>
                            <input type="number" wire:model="amount" step="0.01" min="0.01" 
                                   class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500 text-lg font-bold" 
                                   placeholder="0.00">
                        </div>
                        @error('amount') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Método de Pagamento --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                            <i class="fas fa-credit-card mr-1 text-yellow-600"></i>Método de Pagamento *
                        </label>
                        <select wire:model="payment_method" 
                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500 appearance-none bg-white">
                            <option value="cash">💵 Dinheiro</option>
                            <option value="transfer">🏦 Transferência</option>
                            <option value="multicaixa">💳 Multicaixa</option>
                            <option value="tpa">💳 TPA</option>
                            <option value="check">📝 Cheque</option>
                            <option value="mbway">📱 MB Way</option>
                            <option value="other">❓ Outro</option>
                        </select>
                    </div>

                    {{-- Finalidade --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                            <i class="fas fa-hashtag mr-1 text-yellow-600"></i>Finalidade
                        </label>
                        <input type="text" wire:model="purpose" 
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500" 
                               placeholder="Ex: Pagamento antecipado...">
                    </div>
                </div>

                {{-- Observações --}}
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-2 uppercase tracking-wider">
                        <i class="fas fa-comment mr-1 text-yellow-600"></i>Observações
                    </label>
                    <textarea wire:model="notes" rows="3" 
                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500"
                              placeholder="Observações adicionais..."></textarea>
                </div>

                {{-- Botões --}}
                <div class="flex gap-4 pt-6 border-t-2 border-gray-100">
                    <a href="{{ route('invoicing.advances.index') }}" 
                       class="flex-1 px-8 py-4 bg-gray-100 text-gray-700 rounded-xl font-bold hover:bg-gray-200 transition-all text-center">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                    <button type="submit" 
                            class="flex-1 px-8 py-4 bg-gradient-to-r from-yellow-600 to-amber-600 hover:from-yellow-700 hover:to-amber-700 text-white rounded-xl font-bold transition-all shadow-lg">
                        <i class="fas fa-save mr-2"></i>{{ $isEdit ? 'Atualizar' : 'Criar' }} Adiantamento
                    </button>
                </div>
            </div>
        </div>
    </form>

    <style>
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-5px); } }
        .icon-float { animation: float 3s ease-in-out infinite; }
        .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    </style>
</div>
