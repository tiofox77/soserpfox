<div>
    {{-- Container Principal POS Salão --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-2">
        
        {{-- Serviços/Produtos (2 colunas) --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-xl p-2 flex flex-col" style="height: calc(100vh - 140px);">
            {{-- Header POS Salão --}}
            <div class="mb-1 bg-gradient-to-r from-pink-600 to-purple-600 rounded-xl shadow p-1.5 text-white flex-shrink-0">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center mr-2">
                            <i class="fas fa-spa text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold">POS Salão de Beleza</h2>
                            <p class="text-xs text-pink-200">{{ auth()->user()->name }}</p>
                        </div>
                    </div>
                    <a href="{{ route('salon.appointments') }}" class="px-3 py-1 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-semibold transition">
                        <i class="fas fa-arrow-left mr-1"></i>Voltar
                    </a>
                </div>
            </div>

            {{-- Tabs Serviços/Produtos --}}
            <div class="mb-1 flex-shrink-0 px-1">
                <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-0.5">
                    <button wire:click="setTab('services')" 
                            class="flex-1 px-3 py-1.5 rounded-lg text-sm font-bold transition {{ $activeTab === 'services' ? 'bg-pink-500 text-white shadow' : 'text-gray-600 hover:bg-gray-200' }}">
                        <i class="fas fa-spa mr-1"></i>Serviços
                    </button>
                    <button wire:click="setTab('products')" 
                            class="flex-1 px-3 py-1.5 rounded-lg text-sm font-bold transition {{ $activeTab === 'products' ? 'bg-emerald-500 text-white shadow' : 'text-gray-600 hover:bg-gray-200' }}">
                        <i class="fas fa-box mr-1"></i>Produtos
                    </button>
                </div>
            </div>

            {{-- Busca e Categorias --}}
            <div class="mb-1 space-y-1 flex-shrink-0 px-1">
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search" 
                           placeholder="🔍 Buscar {{ $activeTab === 'services' ? 'serviços' : 'produtos' }}..."
                           class="w-full px-2 py-1 pl-8 border border-gray-300 rounded-lg focus:ring-1 focus:ring-pink-500 text-sm">
                    <i class="fas fa-search absolute left-2 top-1.5 text-gray-400 text-xs"></i>
                </div>

                {{-- Categorias --}}
                <div class="flex gap-1 overflow-x-auto pb-0.5">
                    <button wire:click="$set('selectedCategory', null)" 
                            class="px-3 py-1 rounded-lg text-sm font-semibold whitespace-nowrap transition {{ !$selectedCategory ? 'bg-pink-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                        <i class="fas fa-th mr-1"></i>Todos
                    </button>
                    @if($activeTab === 'services')
                        @foreach($serviceCategories as $category)
                        <button wire:click="$set('selectedCategory', {{ $category->id }})" 
                                class="px-3 py-1 rounded-lg text-sm font-semibold whitespace-nowrap transition {{ $selectedCategory == $category->id ? 'bg-pink-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                            {{ $category->name }} <span class="text-xs opacity-75">({{ $category->services_count }})</span>
                        </button>
                        @endforeach
                    @else
                        @foreach($categories as $category)
                        <button wire:click="$set('selectedCategory', {{ $category->id }})" 
                                class="px-3 py-1 rounded-lg text-sm font-semibold whitespace-nowrap transition {{ $selectedCategory == $category->id ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                            {{ $category->name }} <span class="text-xs opacity-75">({{ $category->products_count }})</span>
                        </button>
                        @endforeach
                    @endif
                </div>
            </div>

            {{-- Grid de Serviços ou Produtos --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-1.5 overflow-y-auto flex-1 px-1 pb-1 auto-rows-min content-start">
                @if($activeTab === 'services')
                    {{-- Serviços --}}
                    @forelse($services as $service)
                    <button wire:click="addServiceToCart({{ $service->id }})" 
                            class="group relative bg-white border border-gray-200 rounded-lg p-1.5 hover:border-pink-500 hover:shadow transition h-fit">
                        
                        <div class="aspect-square bg-gradient-to-br from-pink-100 to-purple-100 rounded mb-0.5 flex items-center justify-center">
                            <i class="fas fa-spa text-3xl text-pink-400"></i>
                        </div>

                        <div class="text-left">
                            <p class="font-bold text-xs text-gray-800 line-clamp-2 leading-tight">{{ $service->name }}</p>
                            <p class="text-xs font-bold text-pink-600">{{ number_format($service->price, 0) }} Kz</p>
                            @if($service->duration)
                            <p class="text-xs text-gray-500"><i class="fas fa-clock text-[10px]"></i> {{ $service->duration }} min</p>
                            @endif
                        </div>
                    </button>
                    @empty
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-spa text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Nenhum serviço encontrado</p>
                    </div>
                    @endforelse
                @else
                    {{-- Produtos --}}
                    @forelse($products as $product)
                    <button wire:click="addToCart({{ $product->id }})" 
                            class="group relative bg-white border border-gray-200 rounded-lg p-1.5 hover:border-emerald-500 hover:shadow transition {{ $product->stock_quantity <= 0 ? 'opacity-50 cursor-not-allowed' : '' }} h-fit">
                        
                        <div class="aspect-square bg-gradient-to-br from-emerald-100 to-teal-100 rounded mb-0.5 overflow-hidden flex items-center justify-center">
                            @if($product->image_url)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover" loading="lazy">
                            @else
                                <i class="fas fa-box text-3xl text-emerald-400"></i>
                            @endif
                        </div>

                        <div class="text-left">
                            <p class="font-bold text-xs text-gray-800 line-clamp-1 leading-tight">{{ $product->name }}</p>
                            <p class="text-xs font-bold text-emerald-600">{{ number_format($product->price, 0) }} Kz</p>
                            <span class="text-xs {{ $product->stock_quantity > 10 ? 'text-green-600' : ($product->stock_quantity > 5 ? 'text-orange-600' : 'text-red-600') }} font-bold">
                                <i class="fas fa-box-open text-[10px]"></i> {{ $product->stock_quantity }}
                            </span>
                        </div>

                        @if($product->stock_quantity <= 0)
                        <div class="absolute inset-0 bg-black/50 rounded-lg flex items-center justify-center">
                            <span class="bg-red-600 text-white px-2 py-1 rounded font-bold text-xs">Esgotado</span>
                        </div>
                        @endif
                    </button>
                    @empty
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Nenhum produto encontrado</p>
                    </div>
                    @endforelse
                @endif
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
                        <button wire:click="$set('showClientModal', true)" class="text-xs text-pink-600 hover:text-pink-800 ml-2">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
                @else
                <button wire:click="$set('showClientModal', true)" 
                        class="w-full px-2 py-1.5 bg-pink-600 hover:bg-pink-700 text-white rounded-lg text-sm font-bold transition">
                    <i class="fas fa-user-plus mr-1"></i>Cliente
                </button>
                <p class="text-xs text-orange-600 mt-1 text-center">
                    <i class="fas fa-info-circle"></i> Sem cliente = Consumidor Final
                </p>
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
                            <div class="flex-1 flex items-center gap-1">
                                @if(isset($item->attributes['type']) && $item->attributes['type'] === 'service')
                                    <span class="w-5 h-5 bg-pink-100 rounded flex items-center justify-center">
                                        <i class="fas fa-spa text-pink-500 text-xs"></i>
                                    </span>
                                @else
                                    <span class="w-5 h-5 bg-emerald-100 rounded flex items-center justify-center">
                                        <i class="fas fa-box text-emerald-500 text-xs"></i>
                                    </span>
                                @endif
                                <p class="font-bold text-xs text-gray-800 line-clamp-1">{{ $item->name }}</p>
                            </div>
                            <button wire:click="removeFromCart('{{ $item->id }}')" class="text-red-500 hover:text-red-700 text-xs ml-1">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <button wire:click="decreaseQuantity('{{ $item->id }}')" class="w-6 h-6 bg-gray-300 hover:bg-gray-400 rounded text-xs font-bold transition">-</button>
                                <span class="w-8 text-center text-sm font-bold">{{ $item->quantity }}</span>
                                <button wire:click="increaseQuantity('{{ $item->id }}')" class="w-6 h-6 bg-pink-600 hover:bg-pink-700 text-white rounded text-xs font-bold transition">+</button>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-bold text-pink-600">{{ number_format($item->price * $item->quantity, 0) }} Kz</p>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Totais e Resumo --}}
            <div class="border-t border-gray-200 p-2 space-y-2 flex-shrink-0">
                {{-- Desconto --}}
                <div class="flex items-center gap-2 bg-orange-50 border border-orange-200 rounded-lg p-2">
                    <span class="text-xs font-bold text-orange-700"><i class="fas fa-tag"></i></span>
                    <select wire:model.live="discountType" class="w-14 px-1 py-1 border border-orange-300 rounded text-xs bg-white">
                        <option value="percentage">%</option>
                        <option value="fixed">Kz</option>
                    </select>
                    <input type="number" wire:model.live="discount" placeholder="0" step="0.01"
                           class="flex-1 px-2 py-1 border border-orange-300 rounded text-sm font-bold text-center">
                </div>

                {{-- Resumo Financeiro --}}
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-3 space-y-2 border border-gray-200">
                    {{-- Subtotal --}}
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-semibold text-gray-800">{{ number_format($cartSubtotal, 2, ',', '.') }} Kz</span>
                    </div>
                    
                    {{-- Desconto --}}
                    @if($cartDiscount > 0)
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-orange-600 flex items-center gap-1">
                            <i class="fas fa-minus-circle text-xs"></i> Desconto
                        </span>
                        <span class="font-semibold text-orange-600">-{{ number_format($cartDiscount, 2, ',', '.') }} Kz</span>
                    </div>
                    @endif
                    
                    {{-- IVA --}}
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-blue-600 flex items-center gap-1">
                            <i class="fas fa-plus-circle text-xs"></i> IVA ({{ number_format($taxRate, 0) }}%)
                        </span>
                        <span class="font-semibold text-blue-600">+{{ number_format($cartTax, 2, ',', '.') }} Kz</span>
                    </div>
                    
                    {{-- Retenção IRT --}}
                    @if($cartIrt > 0)
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-purple-600 flex items-center gap-1">
                            <i class="fas fa-hand-holding-usd text-xs"></i> Ret. IRT ({{ number_format($irtRate, 1) }}%)
                        </span>
                        <span class="font-semibold text-purple-600">-{{ number_format($cartIrt, 2, ',', '.') }} Kz</span>
                    </div>
                    @endif
                    
                    {{-- Linha divisória --}}
                    <div class="border-t-2 border-dashed border-gray-300 my-1"></div>
                    
                    {{-- Total --}}
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-gray-800 text-base">TOTAL A PAGAR</span>
                        <span class="font-bold text-2xl text-pink-600">{{ number_format($cartTotal, 2, ',', '.') }} <small class="text-sm">Kz</small></span>
                    </div>
                    
                    {{-- Info itens --}}
                    <div class="text-center text-xs text-gray-500">
                        {{ $cartQuantity }} {{ $cartQuantity == 1 ? 'item' : 'itens' }} no carrinho
                    </div>
                </div>

                {{-- Botões de Ação --}}
                <div class="flex gap-2">
                    <button wire:click="clearCart" 
                            class="px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl font-bold transition text-sm shadow-lg shadow-red-500/30">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button wire:click="openPaymentModal" 
                            class="flex-1 px-4 py-2.5 bg-gradient-to-r from-pink-500 to-purple-600 hover:from-pink-600 hover:to-purple-700 text-white rounded-xl font-bold shadow-lg shadow-purple-500/30 transition text-sm"
                            {{ $cartItems->isEmpty() ? 'disabled' : '' }}>
                        <i class="fas fa-cash-register mr-2"></i>Finalizar Venda
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modais do POS original --}}
    @include('livewire.pos.partials.client-modal')
    @include('livewire.pos.partials.payment-modal')
    @include('livewire.pos.partials.print-modal')
</div>

{{-- Scripts --}}
@include('livewire.pos.partials.scripts')
