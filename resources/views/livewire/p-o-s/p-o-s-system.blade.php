<div class="min-h-screen bg-gray-100">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-0 lg:gap-4 h-screen">
        
        {{-- Produtos (2 colunas) --}}
        <div class="lg:col-span-2 bg-white p-4 overflow-y-auto">
            {{-- Header POS --}}
            <div class="mb-4 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-4 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3">
                            <i class="fas fa-cash-register text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold">Ponto de Venda</h2>
                            <p class="text-xs text-indigo-200">Sistema POS Moderno</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-indigo-200">Caixa</p>
                        <p class="text-lg font-bold">{{ auth()->user()->name }}</p>
                    </div>
                </div>
            </div>

            {{-- Busca e Filtros --}}
            <div class="mb-4 space-y-3">
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search" 
                           placeholder="🔍 Buscar produtos... (Nome, SKU, Código de Barras)"
                           class="w-full px-4 py-3 pl-12 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-lg">
                    <i class="fas fa-search absolute left-4 top-4 text-gray-400"></i>
                </div>

                {{-- Categorias --}}
                <div class="flex gap-2 overflow-x-auto pb-2">
                    <button wire:click="$set('selectedCategory', null)" 
                            class="px-4 py-2 rounded-lg font-semibold whitespace-nowrap transition {{ !$selectedCategory ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                        <i class="fas fa-th mr-1"></i> Todos
                    </button>
                    @foreach($categories as $category)
                    <button wire:click="$set('selectedCategory', {{ $category->id }})" 
                            class="px-4 py-2 rounded-lg font-semibold whitespace-nowrap transition {{ $selectedCategory == $category->id ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                        @if($category->icon)
                            <i class="fas fa-{{ $category->icon }} mr-1"></i>
                        @endif
                        {{ $category->name }}
                        <span class="text-xs opacity-75">({{ $category->products_count }})</span>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Grid de Produtos --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @forelse($products as $product)
                <button wire:click="addToCart({{ $product->id }})" 
                        class="group relative bg-white border-2 border-gray-200 rounded-xl p-3 hover:border-indigo-500 hover:shadow-lg transition transform hover:scale-105 {{ $product->stock <= 0 ? 'opacity-50 cursor-not-allowed' : '' }}">
                    
                    {{-- Imagem --}}
                    <div class="aspect-square bg-gray-100 rounded-lg mb-2 overflow-hidden">
                        @if($product->image)
                            <img src="{{ Storage::url($product->image) }}" 
                                 alt="{{ $product->name }}"
                                 class="w-full h-full object-cover group-hover:scale-110 transition">
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fas fa-box text-4xl text-gray-300"></i>
                            </div>
                        @endif
                    </div>

                    {{-- Info --}}
                    <div class="text-left">
                        <p class="font-bold text-sm text-gray-800 line-clamp-2 mb-1">{{ $product->name }}</p>
                        <p class="text-xs text-gray-500 mb-2">{{ $product->sku }}</p>
                        <div class="flex items-center justify-between">
                            <p class="text-lg font-bold text-indigo-600">{{ number_format($product->price, 0) }} Kz</p>
                            <span class="text-xs {{ $product->stock > 10 ? 'text-green-600' : ($product->stock > 0 ? 'text-orange-600' : 'text-red-600') }} font-semibold">
                                {{ $product->stock > 0 ? $product->stock . ' un' : 'Sem stock' }}
                            </span>
                        </div>
                    </div>

                    {{-- Badge de Stock Baixo --}}
                    @if($product->stock > 0 && $product->stock <= 5)
                    <div class="absolute top-2 right-2 bg-orange-500 text-white text-xs px-2 py-1 rounded-full font-bold">
                        Baixo!
                    </div>
                    @endif

                    {{-- Badge Sem Stock --}}
                    @if($product->stock <= 0)
                    <div class="absolute inset-0 bg-black/50 rounded-xl flex items-center justify-center">
                        <span class="bg-red-600 text-white px-3 py-1 rounded-full font-bold text-sm">
                            Esgotado
                        </span>
                    </div>
                    @endif
                </button>
                @empty
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">Nenhum produto encontrado</p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Carrinho (1 coluna) --}}
        <div class="lg:col-span-1 bg-white shadow-2xl flex flex-col h-screen">
            {{-- Cliente --}}
            <div class="p-4 border-b-2 border-gray-200">
                @if($selectedClient)
                <div class="bg-indigo-50 border-2 border-indigo-200 rounded-xl p-3">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-xs text-indigo-600 font-semibold">CLIENTE</p>
                            <p class="font-bold text-gray-800">{{ $selectedClient->name }}</p>
                            <p class="text-xs text-gray-600">{{ $selectedClient->nif }}</p>
                        </div>
                        <button wire:click="$set('selectedClient', null)" 
                                class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                @else
                <button wire:click="$set('showClientModal', true)" 
                        class="w-full px-4 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-xl font-bold shadow-lg transition">
                    <i class="fas fa-user-plus mr-2"></i>Selecionar Cliente
                </button>
                @endif
            </div>

            {{-- Itens do Carrinho --}}
            <div class="flex-1 overflow-y-auto p-4">
                @if($cartItems->isEmpty())
                <div class="text-center py-12">
                    <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 font-semibold">Carrinho Vazio</p>
                    <p class="text-sm text-gray-400">Adicione produtos para continuar</p>
                </div>
                @else
                <div class="space-y-3">
                    @foreach($cartItems as $item)
                    <div class="bg-gray-50 border-2 border-gray-200 rounded-xl p-3 hover:border-indigo-300 transition">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex-1">
                                <p class="font-bold text-sm text-gray-800">{{ $item->name }}</p>
                                <p class="text-xs text-gray-500">{{ $item->attributes->sku }}</p>
                            </div>
                            <button wire:click="removeFromCart({{ $item->id }})" 
                                    class="text-red-500 hover:text-red-700">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <button wire:click="decreaseQuantity({{ $item->id }})" 
                                        class="w-8 h-8 bg-gray-300 hover:bg-gray-400 rounded-lg font-bold transition">
                                    -
                                </button>
                                <span class="w-12 text-center font-bold">{{ $item->quantity }}</span>
                                <button wire:click="increaseQuantity({{ $item->id }})" 
                                        class="w-8 h-8 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold transition">
                                    +
                                </button>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500">{{ number_format($item->price, 0) }} Kz</p>
                                <p class="font-bold text-indigo-600">{{ number_format($item->price * $item->quantity, 0) }} Kz</p>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Totais e Pagamento --}}
            <div class="border-t-2 border-gray-200 p-4 space-y-3">
                {{-- Resumo --}}
                <div class="bg-gray-50 rounded-xl p-3 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-bold">{{ number_format($cartTotal, 0) }} Kz</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Itens:</span>
                        <span class="font-bold">{{ $cartQuantity }}</span>
                    </div>
                    <div class="border-t-2 border-gray-200 pt-2 flex justify-between">
                        <span class="font-bold text-lg">TOTAL:</span>
                        <span class="font-bold text-2xl text-indigo-600">{{ number_format($cartTotal, 0) }} Kz</span>
                    </div>
                </div>

                {{-- Método de Pagamento --}}
                <select wire:model="paymentMethod" 
                        class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                    <option value="cash">💵 Dinheiro</option>
                    <option value="transfer">🏦 Transferência</option>
                    <option value="multicaixa">💳 Multicaixa</option>
                    <option value="tpa">💳 TPA</option>
                </select>

                {{-- Valor Recebido --}}
                <input type="number" wire:model.live="amountReceived" 
                       placeholder="Valor Recebido"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-lg font-bold">

                {{-- Troco --}}
                @if($amountReceived > 0 && $change > 0)
                <div class="bg-green-50 border-2 border-green-200 rounded-xl p-3">
                    <p class="text-sm text-green-700 font-semibold">TROCO:</p>
                    <p class="text-2xl font-bold text-green-600">{{ number_format($change, 0) }} Kz</p>
                </div>
                @endif

                {{-- Observações --}}
                <textarea wire:model="notes" 
                          placeholder="Observações (opcional)"
                          rows="2"
                          class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 text-sm"></textarea>

                {{-- Botões de Ação --}}
                <div class="flex gap-2">
                    <button wire:click="clearCart" 
                            class="flex-1 px-4 py-3 bg-red-500 hover:bg-red-600 text-white rounded-xl font-bold transition">
                        <i class="fas fa-trash mr-2"></i>Limpar
                    </button>
                    <button wire:click="completeSale" 
                            wire:loading.attr="disabled"
                            class="flex-1 px-4 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-xl font-bold shadow-lg transition">
                        <span wire:loading.remove wire:target="completeSale">
                            <i class="fas fa-check mr-2"></i>Finalizar
                        </span>
                        <span wire:loading wire:target="completeSale">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Processando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Seleção de Cliente --}}
    @if($showClientModal)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
            <div class="bg-indigo-600 px-6 py-4 flex items-center justify-between">
                <h3 class="text-xl font-bold text-white">Selecionar Cliente</h3>
                <button wire:click="$set('showClientModal', false)" 
                        class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-6">
                <input type="text" wire:model.live.debounce.300ms="searchClient" 
                       placeholder="Buscar cliente (Nome ou NIF)..."
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 mb-4">

                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @forelse($clients as $client)
                    <button wire:click="selectClient({{ $client->id }})" 
                            class="w-full text-left px-4 py-3 bg-gray-50 hover:bg-indigo-50 border-2 border-gray-200 hover:border-indigo-300 rounded-xl transition">
                        <p class="font-bold text-gray-800">{{ $client->name }}</p>
                        <p class="text-sm text-gray-600">NIF: {{ $client->nif }}</p>
                        @if($client->email)
                        <p class="text-xs text-gray-500">{{ $client->email }}</p>
                        @endif
                    </button>
                    @empty
                    <p class="text-center text-gray-500 py-8">Nenhum cliente encontrado</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
