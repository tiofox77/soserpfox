<div>
    {{-- Prevenir FOUC no POS --}}
    <style>
        .bg-gradient-to-r img {
            max-height: 2rem !important;
            object-fit: contain !important;
        }
    </style>
    
    {{-- Container Principal POS --}}
    <div x-data="{ cartOpen: false }" class="lg:grid lg:grid-cols-3 lg:gap-2">
        
        {{-- Produtos (2 colunas) --}}
        <div class="pos-products-panel lg:col-span-2 bg-white rounded-2xl shadow-xl p-2 flex flex-col">
            <style>
                @media (min-width: 1024px) {
                    .pos-products-panel { height: calc(100vh - 140px) !important; max-height: none !important; }
                    .pos-cart-panel { height: calc(100vh - 140px) !important; max-height: none !important; }
                }
                @media (max-width: 1023px) {
                    /* Produtos ocupam quase todo o ecrã; barra inferior reservada (~64px) */
                    .pos-products-panel { height: calc(100vh - 150px) !important; max-height: none !important; }
                    /* Carrinho vira painel deslizante em ecrã cheio */
                    .pos-cart-panel { height: 100vh !important; max-height: 100vh !important; }
                }
            </style>
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
                            <h2 class="text-base font-bold">{{ __('Ponto de Venda') }}</h2>
                            <p class="text-xs text-indigo-200">{{ auth()->user()->name }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- Próxima fatura --}}
                        <div class="flex items-center gap-1.5 bg-white/15 px-2 py-1 rounded-lg" title="{{ __('Próximo número de fatura POS') }}">
                            <i class="fas fa-receipt text-xs text-indigo-200"></i>
                            <div class="leading-tight text-right">
                                <p class="text-[10px] text-indigo-200 uppercase tracking-wide">{{ __('Próx. Fatura') }}</p>
                                <p class="text-xs font-semibold">{{ $this->nextInvoiceNumber }}</p>
                            </div>
                        </div>
                        {{-- Armazém ativo (default do tenant) --}}
                        <div class="flex items-center gap-1.5 bg-white/15 px-2 py-1 rounded-lg" title="{{ __('Armazém de origem dos produtos vendidos neste POS') }}">
                            <i class="fas fa-warehouse text-xs text-indigo-200"></i>
                            <div class="leading-tight text-right">
                                <p class="text-[10px] text-indigo-200 uppercase tracking-wide">{{ __('Armazém') }}</p>
                                <p class="text-xs font-semibold">{{ $this->warehouseName ?: '—' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Busca e Filtros --}}
            <div class="mb-1 space-y-1 flex-shrink-0 px-1">
                {{-- Procura e leitura de código de barras no MESMO campo.
                     O leitor escreve o código e carrega em Enter; o Enter é
                     travado para não submeter nada, e o debounce curto faz o
                     código chegar ao servidor de imediato — um leitor escreve
                     treze dígitos em menos de um décimo de segundo, e 300 ms
                     de espera davam a sensação de que a leitura falhava. --}}
                <div class="relative">
                    <input type="text" wire:model.live.debounce.150ms="search"
                           wire:keydown.enter.prevent=""
                           autofocus
                           placeholder="🔍 {{ __('Procurar ou ler código de barras…') }}"
                           class="w-full px-2 py-1 pl-8 border border-gray-300 rounded-lg focus:ring-1 focus:ring-indigo-500 text-sm">
                    <i class="fas fa-barcode absolute left-2 top-1.5 text-gray-400 text-xs"></i>
                </div>

                {{-- Categorias --}}
                <div class="flex gap-1 overflow-x-auto pb-0.5">
                    <button wire:click="$set('selectedCategory', null)" 
                            class="px-3 py-1 rounded-lg text-sm font-semibold whitespace-nowrap transition {{ !$selectedCategory ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                        <i class="fas fa-th mr-1"></i> {{ __('Todos') }}
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
            @php
                // Quantidades já no carrinho, indexadas por produto — uma única
                // leitura para todo o grid, em vez de uma por cartão.
                $__noCarrinho = collect($cartItems)->mapWithKeys(
                    fn ($i) => [$i->id => $i->quantity]
                )->all();
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-1.5 overflow-y-auto flex-1 px-1 pb-1 auto-rows-min content-start">
                @forelse($products as $product)
                {{-- wire:target é essencial: sem ele o wire:loading dispara em
                     QUALQUER pedido do componente, e clicar num produto (ou até
                     fechar um modal) desactivava e esbatia os 50 cartões todos.
                     Era isto que dava a sensação de "tudo recarrega". --}}
                <button wire:click="addToCart({{ $product->id }})"
                        wire:target="addToCart({{ $product->id }})"
                        wire:loading.attr="disabled"
                        wire:loading.class="scale-95 opacity-70"
                        class="group relative bg-white border border-gray-200 rounded-lg p-1.5 hover:border-indigo-500 hover:shadow transition-all duration-200 {{ ($product->controlaStock() && ($product->stock_in_warehouse ?? 0) <= 0) ? 'opacity-50 cursor-not-allowed' : '' }} h-fit disabled:cursor-wait">
                    
                    {{-- Imagem --}}
                    <div class="aspect-square bg-gray-100 rounded mb-0.5 overflow-hidden">
                        @if($product->image_url)
                            <img src="{{ $product->image_url }}" 
                                 alt="{{ $product->name }}"
                                 class="w-full h-full object-cover"
                                 loading="lazy"
                                 onerror="this.src='{{ app_logo() ?? asset('images/placeholder-product.png') }}'">
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
                        @php
                            // O que distingue dois artigos com o mesmo nome e não cabe no
                            // cartão: dosagem, forma farmacêutica, substância, composição.
                            // Vai para o title — ao alcance do rato, sem roubar linhas a
                            // uma grelha que mostra cinquenta cartões ao mesmo tempo.
                            $detalheArtigo = array_filter([
                                $product->dosage,
                                $product->pharmaceutical_form,
                                $product->active_ingredient,
                                $product->material,
                            ], fn ($valor) => trim((string) $valor) !== '');
                        @endphp
                        <p class="font-bold text-xs text-gray-800 line-clamp-1 leading-tight"
                           @if($detalheArtigo) title="{{ implode(' · ', $detalheArtigo) }}" @endif>{{ $product->name }}</p>

                        {{-- Crachás de balcão: só o que muda a decisão no momento de
                             escolher. A receita e o psicotrópico mudam o que há a pedir
                             ao cliente antes de entregar; o tamanho e a cor decidem qual
                             dos seis cartões iguais é o certo — numa loja de roupa,
                             "T-shirt" sozinho não chega para escolher. Os valores são
                             dados da empresa e saem como estão gravados. --}}
                        @if($product->is_controlled || $product->requires_prescription || filled($product->dosage) || filled($product->size) || filled($product->color))
                        <div class="flex flex-wrap items-center gap-0.5 mt-0.5">
                            @if($product->is_controlled)
                            <span class="text-[9px] font-bold bg-red-600 text-white px-1 rounded leading-tight"
                                  title="{{ __('Psicotrópico ou estupefaciente — venda sujeita a registo obrigatório') }}">
                                <i class="fas fa-triangle-exclamation"></i> {{ __('CONTROLADO') }}
                            </span>
                            @endif
                            @if($product->requires_prescription)
                            <span class="text-[9px] font-bold bg-amber-500 text-white px-1 rounded leading-tight"
                                  title="{{ __('Exige receita médica') }}">
                                <i class="fas fa-prescription"></i> {{ __('RECEITA') }}
                            </span>
                            @endif
                            @if(filled($product->dosage))
                            <span class="text-[9px] font-semibold bg-sky-100 text-sky-800 px-1 rounded leading-tight"
                                  title="{{ __('Dosagem') }}">{{ $product->dosage }}</span>
                            @endif
                            @if(filled($product->size))
                            <span class="text-[9px] font-semibold bg-gray-200 text-gray-700 px-1 rounded leading-tight"
                                  title="{{ __('Tamanho') }}">{{ $product->size }}</span>
                            @endif
                            @if(filled($product->color))
                            <span class="text-[9px] font-semibold bg-gray-100 text-gray-600 px-1 rounded leading-tight"
                                  title="{{ __('Cor') }}">{{ $product->color }}</span>
                            @endif
                        </div>
                        @endif

                        <p class="text-xs font-bold text-indigo-600">{{ number_format($product->price, 0) }}</p>
                        <div class="flex items-center gap-1 mt-0.5">
                            @php
                                $stockHere = (float) ($product->stock_in_warehouse ?? 0);
                                // Serviços e artigos sem gestão de stock não têm
                                // stock nenhum para mostrar: um "Corte de Cabelo"
                                // a -5 não quer dizer nada a quem está ao balcão.
                                $mostraStock = $product->controlaStock();
                            @endphp
                            @if($mostraStock)
                            <span class="text-xs {{ $stockHere > 10 ? 'text-green-600' : ($stockHere > 5 ? 'text-orange-600' : 'text-red-600') }} font-bold" title="{{ __('Stock no armazém :armazem', ['armazem' => $this->warehouseName]) }}">
                                <i class="fas fa-box-open text-[10px]"></i> {{ rtrim(rtrim(number_format($stockHere, 2, '.', ''), '0'), '.') }}
                            </span>
                            @elseif($product->type === 'servico')
                            {{-- Um serviço não tem stock: o que interessa saber
                                 é que é um serviço, não um número que não existe. --}}
                            <span class="text-[10px] font-bold bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full"
                                  title="{{ __('Serviço — não tem stock') }}">
                                <i class="fas fa-concierge-bell text-[9px]"></i> {{ __('Serviço') }}
                            </span>
                            @else
                            {{-- Produto com "Gerenciar Stock" desligado: vende-se
                                 sempre, e é isso que quem está ao balcão precisa
                                 de ver — não um zero que parece falta. --}}
                            <span class="text-[10px] font-bold bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded-full"
                                  title="{{ __('Não controla stock — vende-se sempre') }}">
                                <i class="fas fa-infinity text-[9px]"></i> {{ __('Sem stock gerido') }}
                            </span>
                            @endif
                            @php
                                // Mapa construído UMA vez antes do loop (ver acima):
                                // CartFacade::get() reconstrói a colecção inteira do
                                // carrinho a partir da sessão a cada chamada, e isto
                                // corria 50 vezes — uma por cartão de produto.
                                $quantityInCart = $__noCarrinho[$product->id] ?? 0;
                            @endphp
                            @if($quantityInCart > 0)
                            {{-- O 🛒 vai DENTRO da chave, ao contrário do resto do
                                 sistema (onde o emoji fica de fora): aqui ele não
                                 decora a frase, substitui a palavra "carrinho". Sem
                                 ele a etiqueta passava a "2 no carrinho" e não cabe
                                 num cartão de produto desta grelha. --}}
                            <span class="text-xs bg-indigo-600 text-white px-1.5 rounded font-bold">
                                {{ __(':n no 🛒', ['n' => $quantityInCart]) }}
                            </span>
                            @endif
                        </div>
                    </div>

                    {{-- Véu "Esgotado" — SÓ para artigos que controlam stock.
                         Estava incondicional: os serviços do salão apareciam
                         tapados e marcados como esgotados, quando um serviço
                         nunca esgota. --}}
                    @if($product->controlaStock() && ($product->stock_in_warehouse ?? 0) <= 0)
                    <div class="absolute inset-0 bg-black/50 rounded-lg flex items-center justify-center">
                        <span class="bg-red-600 text-white px-2 py-1 rounded font-bold text-xs">
                            {{ __('Esgotado em :armazem', ['armazem' => $this->warehouseName]) }}
                        </span>
                    </div>
                    @endif
                </button>
                @empty
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">{{ __('Nenhum produto encontrado') }}</p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Backdrop (apenas mobile/tablet quando carrinho aberto) --}}
        <div x-show="cartOpen" x-transition.opacity
             @click="cartOpen = false"
             class="fixed inset-0 bg-black/50 z-40 lg:hidden"
             style="display: none;"></div>

        {{-- Carrinho (1 coluna no desktop · slide-over no mobile) --}}
        <div class="pos-cart-panel bg-white shadow-xl flex flex-col overflow-hidden
                    fixed inset-y-0 right-0 z-50 w-full max-w-md rounded-none transform transition-transform duration-300
                    lg:static lg:z-auto lg:w-auto lg:max-w-none lg:col-span-1 lg:rounded-2xl lg:translate-x-0"
             :class="cartOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'">
            {{-- Cabeçalho do painel (apenas mobile) --}}
            <div class="lg:hidden flex items-center justify-between bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-3 py-2.5 flex-shrink-0">
                <span class="font-bold text-sm"><i class="fas fa-shopping-cart mr-2"></i>{{ __('Carrinho') }}</span>
                <button @click="cartOpen = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/20 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
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
                    <i class="fas fa-user-plus mr-1"></i>{{ __('Cliente') }}
                </button>
                @endif
            </div>

            {{-- Itens do Carrinho --}}
            <div class="flex-1 overflow-y-auto p-1.5 min-h-0">
                @if($cartItems->isEmpty())
                <div class="text-center py-4">
                    <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-2"></i>
                    <p class="text-sm text-gray-500 font-semibold">{{ __('Carrinho Vazio') }}</p>
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
                                wire:target="removeFromCart({{ $item->id }})"
                                wire:loading.attr="disabled"
                                class="text-red-500 hover:text-red-700 text-xs ml-1 disabled:opacity-50 transition">
                                <i class="fas fa-trash" wire:loading.remove wire:target="removeFromCart({{ $item->id }})"></i>
                                <i class="fas fa-spinner fa-spin" wire:loading wire:target="removeFromCart({{ $item->id }})"></i>
                            </button>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <button wire:click="decreaseQuantity({{ $item->id }})"
                                        wire:target="decreaseQuantity({{ $item->id }})"
                                        wire:loading.attr="disabled"
                                        class="w-6 h-6 bg-gray-300 hover:bg-gray-400 rounded text-xs font-bold transition-all duration-200 active:scale-90 disabled:opacity-50">
                                    -
                                </button>
                                <input type="number" min="1" inputmode="numeric"
                                       value="{{ $item->quantity }}"
                                       wire:key="qty-{{ $item->id }}-{{ $item->quantity }}"
                                       @change="$wire.updateQuantity({{ $item->id }}, $event.target.value)"
                                       @keydown.enter.prevent="$event.target.blur()"
                                       @focus="$event.target.select()"
                                       class="w-12 h-6 text-center text-sm font-bold border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                <button wire:click="increaseQuantity({{ $item->id }})"
                                        wire:target="increaseQuantity({{ $item->id }})"
                                        wire:loading.attr="disabled"
                                        class="w-6 h-6 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-bold transition-all duration-200 active:scale-90 disabled:opacity-50">
                                    +
                                </button>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-bold text-indigo-600">{{ number_format(round((float)$item->price * (float)$item->quantity, 2), 2, ',', '.') }}</p>
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
                        <span class="text-gray-600">{{ __('Subtotal') }}</span>
                        <span class="font-semibold text-gray-800">{{ number_format($cartSubtotal, 2, ',', '.') }} Kz</span>
                    </div>
                    
                    {{-- Desconto --}}
                    @if($cartDiscount > 0)
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-orange-600 flex items-center gap-1">
                            <i class="fas fa-minus-circle text-xs"></i> {{ __('Desconto') }}
                        </span>
                        <span class="font-semibold text-orange-600">-{{ number_format($cartDiscount, 2, ',', '.') }} Kz</span>
                    </div>
                    @endif
                    
                    {{-- IVA --}}
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-blue-600 flex items-center gap-1">
                            <i class="fas fa-plus-circle text-xs"></i> IVA ({{ $taxLabel }})
                        </span>
                        <span class="font-semibold text-blue-600">+{{ number_format($cartTax, 2, ',', '.') }} Kz</span>
                    </div>
                    
                    {{-- Retenção IRT --}}
                    @if($cartIrt > 0)
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-purple-600 flex items-center gap-1">
                            <i class="fas fa-hand-holding-usd text-xs"></i> {{ __('Ret. IRT') }} ({{ number_format($irtRate, 1) }}%)
                        </span>
                        <span class="font-semibold text-purple-600">-{{ number_format($cartIrt, 2, ',', '.') }} Kz</span>
                    </div>
                    @endif
                    
                    {{-- Linha divisória --}}
                    <div class="border-t-2 border-dashed border-gray-300 my-1"></div>
                    
                    {{-- Total --}}
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-gray-800 text-base">{{ __('TOTAL A PAGAR') }}</span>
                        <span class="font-bold text-2xl text-indigo-600">{{ number_format($cartTotal, 2, ',', '.') }} <small class="text-sm">Kz</small></span>
                    </div>
                    
                    {{-- Info itens.
                         Uma frase inteira por forma, e não "item"/"itens" colado
                         ao número: noutras línguas o plural não se resolve a
                         trocar uma palavra no meio. --}}
                    <div class="text-center text-xs text-gray-500">
                        {{ trans_choice(':n item no carrinho|:n itens no carrinho', $cartQuantity, ['n' => $cartQuantity]) }}
                    </div>
                </div>

                {{-- Botões de Ação --}}
                <div class="flex gap-2">
                    <button wire:click="clearCart"
                            wire:target="clearCart"
                            wire:loading.attr="disabled"
                            wire:confirm="{{ __('Limpar todo o carrinho?') }}"
                            class="px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl font-bold transition-all duration-300 text-sm shadow-lg shadow-red-500/30 hover:scale-105 active:scale-95 disabled:opacity-50">
                        <i class="fas fa-trash" wire:loading.remove wire:target="clearCart"></i>
                        <i class="fas fa-spinner fa-spin" wire:loading wire:target="clearCart"></i>
                    </button>
                    <button wire:click="openPaymentModal"
                            @click="cartOpen = false"
                            wire:target="openPaymentModal"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 scale-95"
                            class="flex-1 px-4 py-2.5 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl font-bold shadow-lg shadow-emerald-500/30 transition-all duration-300 text-sm hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                            {{ $cartItems->isEmpty() ? 'disabled' : '' }}>
                        <span wire:loading.remove wire:target="openPaymentModal">
                            <i class="fas fa-cash-register mr-2"></i>{{ __('Finalizar Venda') }}
                        </span>
                        <span wire:loading wire:target="openPaymentModal">
                            <i class="fas fa-spinner fa-spin mr-2"></i>{{ __('Abrindo...') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Barra inferior fixa (apenas mobile/tablet) — total + abrir carrinho --}}
        <div class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white border-t border-gray-200 shadow-[0_-4px_12px_rgba(0,0,0,0.08)] px-3 py-2"
             x-show="!cartOpen">
            <button @click="cartOpen = true"
                    class="w-full flex items-center justify-between bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl px-4 py-3 font-bold shadow-lg active:scale-95 transition">
                <span class="flex items-center gap-2">
                    <span class="relative">
                        <i class="fas fa-shopping-cart text-lg"></i>
                        @if($cartQuantity > 0)
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] w-5 h-5 flex items-center justify-center rounded-full">{{ $cartQuantity }}</span>
                        @endif
                    </span>
                    <span class="text-sm">{{ __('Ver carrinho') }}</span>
                </span>
                <span class="text-base">{{ number_format($cartTotal, 2, ',', '.') }} Kz</span>
            </button>
        </div>
    </div>

    {{-- Modais --}}
    @include('livewire.pos.partials.client-modal')
    @include('livewire.pos.partials.quick-client-modal')
    @include('livewire.pos.partials.payment-modal')
    @include('livewire.pos.partials.print-modal')
</div>

{{-- Scripts --}}
@include('livewire.pos.partials.scripts')
