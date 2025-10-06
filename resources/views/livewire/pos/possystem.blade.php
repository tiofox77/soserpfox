<div>
    {{-- Prevenir FOUC no POS --}}
    <style>
        .bg-gradient-to-r img {
            max-height: 2rem !important;
            object-fit: contain !important;
        }
    </style>
    
    {{-- Container Principal POS --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-2">
        
        {{-- Produtos (2 colunas) --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-xl p-2 flex flex-col" style="height: calc(100vh - 140px);">
            {{-- Header POS --}}
            <div class="mb-1 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl shadow p-1.5 text-white flex-shrink-0">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        @if(app_logo())
                            <img src="{{ app_logo() }}" alt="{{ app_name() }}" style="max-height: 2rem;" class="h-8 w-auto mr-2 object-contain">
                        @else
                            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center mr-2">
                                <i class="fas fa-cash-register text-lg"></i>
                            </div>
                        @endif
                        <div>
                            <h2 class="text-base font-bold">Ponto de Venda</h2>
                            <p class="text-xs text-indigo-200">{{ auth()->user()->name }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Busca e Filtros --}}
            <div class="mb-1 space-y-1 flex-shrink-0 px-1">
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search" 
                           placeholder="🔍 Buscar produtos..."
                           class="w-full px-2 py-1 pl-8 border border-gray-300 rounded-lg focus:ring-1 focus:ring-indigo-500 text-sm">
                    <i class="fas fa-search absolute left-2 top-1.5 text-gray-400 text-xs"></i>
                </div>

                {{-- Categorias --}}
                <div class="flex gap-1 overflow-x-auto pb-0.5">
                    <button wire:click="$set('selectedCategory', null)" 
                            class="px-3 py-1 rounded-lg text-sm font-semibold whitespace-nowrap transition {{ !$selectedCategory ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                        <i class="fas fa-th mr-1"></i> Todos
                    </button>
                    @foreach($categories as $category)
                    <button wire:click="$set('selectedCategory', {{ $category->id }})" 
                            class="px-3 py-1 rounded-lg text-sm font-semibold whitespace-nowrap transition {{ $selectedCategory == $category->id ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                        {{ $category->name }} <span class="text-xs opacity-75">({{ $category->products_count }})</span>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Grid de Produtos --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-1.5 overflow-y-auto flex-1 px-1 pb-1 auto-rows-min content-start">
                @forelse($products as $product)
                <button wire:click="addToCart({{ $product->id }})" 
                        class="group relative bg-white border border-gray-200 rounded-lg p-1.5 hover:border-indigo-500 hover:shadow transition {{ $product->stock_quantity <= 0 ? 'opacity-50 cursor-not-allowed' : '' }} h-fit">
                    
                    {{-- Imagem --}}
                    <div class="aspect-square bg-gray-100 rounded mb-0.5 overflow-hidden">
                        @if($product->featured_image)
                            <img src="{{ Storage::url($product->featured_image) }}" 
                                 alt="{{ $product->name }}"
                                 class="w-full h-full object-cover">
                        @elseif(app_logo())
                            <div class="w-full h-full flex items-center justify-center p-2">
                                <img src="{{ app_logo() }}" 
                                     alt="{{ app_name() }}"
                                     class="max-w-full max-h-full object-contain opacity-20">
                            </div>
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fas fa-box text-2xl text-gray-300"></i>
                            </div>
                        @endif
                    </div>

                    {{-- Info --}}
                    <div class="text-left">
                        <p class="font-bold text-xs text-gray-800 line-clamp-1 leading-tight">{{ $product->name }}</p>
                        <p class="text-xs font-bold text-indigo-600">{{ number_format($product->price, 0) }}</p>
                        <div class="flex items-center gap-1 mt-0.5">
                            <span class="text-xs {{ $product->stock_quantity > 10 ? 'text-green-600' : ($product->stock_quantity > 5 ? 'text-orange-600' : 'text-red-600') }} font-bold">
                                <i class="fas fa-box-open text-[10px]"></i> {{ $product->stock_quantity }}
                            </span>
                            @php
                                $cartItem = \Darryldecode\Cart\Facades\CartFacade::session(auth()->id())->get($product->id);
                                $quantityInCart = $cartItem ? $cartItem->quantity : 0;
                            @endphp
                            @if($quantityInCart > 0)
                            <span class="text-xs bg-indigo-600 text-white px-1.5 rounded font-bold">
                                {{ $quantityInCart }} no 🛒
                            </span>
                            @endif
                        </div>
                    </div>

                    {{-- Badge Sem Stock --}}
                    @if($product->stock_quantity <= 0)
                    <div class="absolute inset-0 bg-black/50 rounded-lg flex items-center justify-center">
                        <span class="bg-red-600 text-white px-2 py-1 rounded font-bold text-xs">
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
        <div class="lg:col-span-1 bg-white rounded-2xl shadow-xl flex flex-col overflow-hidden" style="height: calc(100vh - 140px);">
            {{-- Cliente --}}
            <div class="p-1.5 border-b border-gray-200 flex-shrink-0">
                @if($selectedClient)
                <div class="bg-green-50 border border-green-300 rounded-lg p-1.5">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="text-xs text-green-800 font-semibold">{{ $selectedClient->name }}</p>
                            <p class="text-xs text-gray-500">NIF: {{ $selectedClient->nif }}</p>
                        </div>
                        <button wire:click="$set('showClientModal', true)" 
                                class="text-xs text-indigo-600 hover:text-indigo-800 ml-2">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
                @else
                <button wire:click="$set('showClientModal', true)" 
                        class="w-full px-2 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-bold transition">
                    <i class="fas fa-user-plus mr-1"></i>Cliente
                </button>
                @endif
            </div>

            {{-- Itens do Carrinho --}}
            <div class="flex-1 overflow-y-auto p-1.5 min-h-0">
                @if($cartItems->isEmpty())
                <div class="text-center py-4">
                    <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-2"></i>
                    <p class="text-sm text-gray-500 font-semibold">Carrinho Vazio</p>
                </div>
                @else
                <div class="space-y-1">
                    @foreach($cartItems as $item)
                    <div wire:key="cart-item-{{ $item->id }}" class="bg-gray-50 border border-gray-200 rounded-lg p-1.5">
                        <div class="flex items-start justify-between mb-1">
                            <div class="flex-1">
                                <p class="font-bold text-xs text-gray-800 line-clamp-1">{{ $item->name }}</p>
                            </div>
                            <button wire:click="removeFromCart({{ $item->id }})" 
                                    class="text-red-500 hover:text-red-700 text-xs ml-1">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <button wire:click="decreaseQuantity({{ $item->id }})" 
                                        class="w-6 h-6 bg-gray-300 hover:bg-gray-400 rounded text-xs font-bold transition">
                                    -
                                </button>
                                <span class="w-8 text-center text-sm font-bold">{{ $item->quantity }}</span>
                                <button wire:click="increaseQuantity({{ $item->id }})" 
                                        class="w-6 h-6 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-bold transition">
                                    +
                                </button>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-bold text-indigo-600">{{ number_format($item->price * $item->quantity, 0) }}</p>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Totais e Resumo --}}
            <div class="border-t border-gray-200 p-1.5 space-y-1.5 flex-shrink-0">
                {{-- Resumo Detalhado --}}
                <div class="bg-gray-50 rounded-lg p-1.5 space-y-1">
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-bold">{{ number_format($cartSubtotal, 2) }}</span>
                    </div>
                    
                    @if($cartDiscount > 0)
                    <div class="flex justify-between text-xs text-orange-600">
                        <span>Desconto:</span>
                        <span class="font-bold">-{{ number_format($cartDiscount, 2) }}</span>
                    </div>
                    @endif
                    
                    <div class="flex justify-between text-xs">
                        <span class="text-gray-600">IVA (14%):</span>
                        <span class="font-bold">{{ number_format($cartTax, 2) }}</span>
                    </div>
                    
                    <div class="border-t border-gray-300 pt-1 flex justify-between items-center">
                        <span class="font-bold text-sm text-gray-800">TOTAL:</span>
                        <span class="font-bold text-xl text-indigo-600">{{ number_format($cartTotal, 0) }} Kz</span>
                    </div>
                </div>

                {{-- Desconto --}}
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-1.5">
                    <p class="text-xs font-bold text-orange-800 mb-1">💰 Desconto</p>
                    <div class="flex gap-1">
                        <select wire:model.live="discountType" 
                                class="w-16 px-1 py-1 border border-orange-300 rounded text-xs">
                            <option value="percentage">%</option>
                            <option value="fixed">Kz</option>
                        </select>
                        <input type="number" wire:model.live="discount" 
                               placeholder="0"
                               step="0.01"
                               class="flex-1 px-2 py-1 border border-orange-300 rounded text-sm font-bold">
                    </div>
                </div>

                {{-- Botões de Ação --}}
                <div class="flex gap-1">
                    <button wire:click="clearCart" 
                            class="px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-bold transition text-sm">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button wire:click="openPaymentModal" 
                            class="flex-1 px-3 py-2 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-lg font-bold shadow transition text-sm">
                        <i class="fas fa-cash-register mr-1"></i>Pagar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modais --}}
    @include('livewire.pos.partials.client-modal')
    @include('livewire.pos.partials.payment-modal')
    @include('livewire.pos.partials.print-modal')
</div>

{{-- Scripts --}}
@include('livewire.pos.partials.scripts')
