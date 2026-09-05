@extends('layouts.pwa', ['title' => __('POS Offline')])

@section('content')
@php
    // O ícone do PWA, e NÃO o logótipo da empresa.
    //
    // O logótipo do tenant não está na lista de pré-guardados do service
    // worker: offline daria um quadrado partido em cada um dos cartões, que é
    // precisamente onde isto tem de funcionar. Este ícone está pré-guardado na
    // instalação e aparece sempre.
    //
    // Resolve-se aqui e não dentro do ciclo: são dezenas de cartões.
    $logoDoPos = asset('pwa/icon-192x192.png');
@endphp
<div x-data="posOffline()" x-init="init()" x-cloak class="-mx-4 -my-4">
    {{-- Altura em `dvh` e nao `vh`, e a descontar o cromado REAL.

         `100vh` no telemovel conta a barra do browser como se nao
         existisse, e o fundo do ecra fica escondido por baixo dela; `dvh`
         acompanha-a. E os 116px eram um numero magico que ja nao batia:
         o cabecalho tem 60 e a barra de navegacao de baixo 77, portanto
         os ultimos 21px da coluna ficavam por baixo dela e nao se
         chegava la. --}}
    <div class="lg:grid lg:grid-cols-12 lg:h-[calc(100dvh-137px)]">

        {{-- ============ COLUNA ESQUERDA — PRODUTOS ============ --}}
        <section class="lg:col-span-7 xl:col-span-8 bg-slate-100 lg:h-full lg:flex lg:flex-col lg:overflow-hidden">

            {{-- Barra de pesquisa + categorias (fixa) --}}
            {{-- `lg:static`: no desktop esta barra NAO pode ser sticky.
                 A partir de lg a coluna dos artigos e uma coluna flex com o
                 seu proprio scroll, e a barra ja esta fora dela. Sticky com
                 `top-14` empurrava-a 56px para baixo do lugar dela e TAPAVA
                 44px do topo da primeira fila de cartoes — era o corte que se
                 via. No telemovel continua sticky, porque ai quem rola e a
                 pagina inteira e a barra tem de acompanhar. --}}
            <div class="sticky top-14 lg:static z-30 bg-slate-100/95 backdrop-blur px-3 pt-3 pb-2 space-y-2 border-b border-slate-200">
                {{-- `min-w-0` NÃO É DECORAÇÃO, é o que impede esta barra de sair
                     do ecrã. Um item flex recusa-se a encolher abaixo do seu
                     conteúdo, e um `<input>` traz uma largura mínima própria
                     do browser (~170px) que o `flex-1` não desfaz. Num
                     telemóvel de 375px a caixa de pesquisa não cedia, a linha
                     transbordava, e o armazém e o turno ficavam cortados na
                     margem direita — sem barra de deslocamento para lá chegar.
                     Precisa de estar no contentor E no input: a regra tem de
                     descer toda a cadeia de flex. --}}
                <div class="flex gap-2 items-center">
                    <div class="flex-1 min-w-0 flex items-center gap-2 bg-white rounded-2xl shadow-sm px-3 h-12">
                        <i class="fas fa-magnifying-glass text-gray-400 shrink-0"></i>
                        <input x-model="search" type="search" inputmode="search"
                               placeholder="{{ __('Pesquisar ou scan código de barras…') }}"
                               @keydown.enter="quickAddByBarcode()"
                               class="flex-1 min-w-0 bg-transparent text-sm focus:outline-none">
                        <button @click="search = ''" x-show="search" class="shrink-0 text-gray-400 text-xl leading-none px-1">&times;</button>
                        <button @click="startBarcodeScanner()" class="shrink-0 text-gray-400 hover:text-blue-600 text-lg px-1" title="{{ __('Ler código de barras com câmara') }}">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    {{-- Armazém ativo (default do tenant).
                         O NOME SÓ APARECE QUANDO HÁ ESPAÇO. É informação de
                         leitura — o armazém não se troca aqui, é sempre o da
                         empresa — e num telemóvel um "Armazém da Ba…" cortado
                         não diz mais do que o ícone e rouba a largura à
                         pesquisa, que é o que se usa a sério. A partir de `sm`
                         o nome volta, inteiro. --}}
                    <div x-show="warehouse" class="shrink-0 h-12 px-3 bg-indigo-100 text-indigo-700 rounded-2xl text-[10px] font-bold flex flex-col items-center justify-center" :title="__('Armazém: :nome', { nome: warehouse?.name || '' })" x-cloak>
                        <i class="fas fa-warehouse text-sm"></i>
                        <span class="hidden sm:block max-w-[80px] truncate" x-text="warehouse?.name || ''"></span>
                    </div>
                    {{-- Estado do turno --}}
                    <button @click="manageShift()"
                            :class="shift.open ? 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700' : 'bg-red-100 hover:bg-red-200 text-red-700'"
                            class="shrink-0 h-12 px-3 rounded-2xl text-[10px] font-bold flex flex-col items-center justify-center transition" title="{{ __('Gerir turno') }}">
                        <i :class="shift.open ? 'fas fa-lock-open' : 'fas fa-triangle-exclamation'" class="text-sm"></i>
                        <span x-text="shift.open ? __('Turno') : __('S/ turno')"></span>
                    </button>
                    {{-- Pendentes --}}
                    <button x-show="pendingCount > 0" @click="showPending = true; refreshPending()"
                            class="shrink-0 h-12 px-3 bg-amber-100 hover:bg-amber-200 text-amber-700 rounded-2xl text-[10px] font-bold flex flex-col items-center justify-center transition" x-cloak>
                        <i class="fas fa-clock text-sm"></i>
                        <span x-text="pendingCount"></span>
                    </button>
                </div>

                {{-- Categorias --}}
                <div class="flex gap-2 overflow-x-auto pb-1 text-xs no-scrollbar">
                    <button @click="categoryFilter = null"
                            :class="!categoryFilter ? 'bg-blue-600 text-white shadow' : 'bg-white text-gray-700'"
                            class="px-3.5 py-2 rounded-full font-semibold whitespace-nowrap transition">
                        <i class="fas fa-border-all mr-1"></i>{{ __('Todos') }}
                    </button>
                    <template x-for="cat in categories" :key="cat">
                        <button @click="categoryFilter = cat"
                                :class="categoryFilter === cat ? 'bg-blue-600 text-white shadow' : 'bg-white text-gray-700'"
                                class="px-3.5 py-2 rounded-full font-semibold whitespace-nowrap transition" x-text="cat"></button>
                    </template>
                </div>
            </div>

            {{-- Grid de produtos --}}
            <div class="px-3 pt-3 pb-44 lg:pb-4 lg:flex-1 lg:overflow-y-auto" @scroll.passive="onScroll($event.target)">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5">
                    <template x-for="p in visibleProducts" :key="p.id">
                        <button @click="addToCart(p)"
                                :disabled="p.type !== 'servico' && p.manage_stock !== false && p.stock_quantity <= 0"
                                :class="p.type !== 'servico' && p.manage_stock !== false && p.stock_quantity <= 0 ? 'opacity-50 cursor-not-allowed' : ''"
                                class="relative bg-white rounded-2xl shadow-sm p-2.5 text-left flex flex-col border border-gray-100 hover:border-blue-300 hover:shadow-md active:scale-95 transition">
                            {{-- A miniatura tem ALTURA FIXA e baixa.

                                 Era `aspect-square`: num ecrã largo, com cinco
                                 colunas, dava 250px de altura para mostrar um
                                 ícone — cinco artigos enchiam o ecrã todo e
                                 parecia que o catálogo estava vazio. Com 80px
                                 cabem três vezes mais artigos e o preço, que é
                                 o que se procura, fica sempre à vista.

                                 O logótipo em marca de água em vez de um ícone
                                 genérico: os artigos não trazem fotografia
                                 (o sync não a envia, e offline não haveria como
                                 a ir buscar), portanto o espaço é sempre
                                 placeholder — mais vale que seja o da casa. --}}
                            <div class="relative w-full h-20 sm:h-24 rounded-xl bg-gradient-to-br from-slate-50 to-blue-50 border border-slate-100 flex items-center justify-center mb-2 overflow-hidden">
                                <img src="{{ $logoDoPos }}" alt=""
                                     class="h-8 sm:h-10 w-auto object-contain opacity-25 select-none pointer-events-none" draggable="false">

                                <span class="absolute bottom-1 left-1 w-5 h-5 rounded-full bg-white/85 flex items-center justify-center shadow-sm">
                                    <i :class="p.type === 'servico' ? 'fas fa-concierge-bell text-purple-500' : 'fas fa-box text-blue-500'"
                                       class="text-[10px]"></i>
                                </span>

                                <span x-show="qtyInCart(p) > 0"
                                      class="absolute top-1 right-1 bg-emerald-600 text-white text-[11px] font-bold min-w-[24px] h-6 px-1 rounded-full flex items-center justify-center shadow"
                                      x-text="qtyInCart(p)" x-cloak></span>
                                <span x-show="p.type !== 'servico' && p.manage_stock !== false && p.stock_quantity <= 0"
                                      class="absolute inset-0 rounded-xl bg-red-500/15 flex items-center justify-center" x-cloak>
                                    <span class="bg-red-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">{{ __('ESGOTADO') }}</span>
                                </span>
                            </div>
                            <p class="font-semibold text-[13px] leading-tight line-clamp-2 mb-0.5" x-text="p.name"
                               :title="[p.dosage, p.pharmaceutical_form, p.active_ingredient].filter(Boolean).join(' · ') || p.name"></p>
                            <p class="text-[10px] text-gray-400 truncate" x-show="p.sku" x-text="p.sku"></p>

                            {{-- Crachás de balcão: só o que muda a decisão no momento de
                                 escolher. A receita e o psicotrópico mudam o que há a
                                 pedir ao cliente antes de entregar; a dosagem, o tamanho
                                 e a cor decidem qual dos cartões iguais é o certo. Os
                                 valores são dados da empresa e saem como estão gravados. --}}
                            <div class="flex flex-wrap items-center gap-1 mt-1"
                                 x-show="p.is_controlled || p.requires_prescription || p.dosage || p.size || p.color" x-cloak>
                                <span x-show="p.is_controlled" class="text-[9px] font-bold bg-red-600 text-white px-1.5 py-0.5 rounded-full whitespace-nowrap"
                                      title="{{ __('Psicotrópico ou estupefaciente — venda sujeita a registo obrigatório') }}">
                                    <i class="fas fa-triangle-exclamation"></i> {{ __('CONTROLADO') }}
                                </span>
                                <span x-show="p.requires_prescription" class="text-[9px] font-bold bg-amber-500 text-white px-1.5 py-0.5 rounded-full whitespace-nowrap"
                                      title="{{ __('Exige receita médica') }}">
                                    <i class="fas fa-prescription"></i> {{ __('RECEITA') }}
                                </span>
                                <span x-show="p.dosage" class="text-[9px] font-semibold bg-sky-100 text-sky-800 px-1.5 py-0.5 rounded-full whitespace-nowrap"
                                      title="{{ __('Dosagem') }}" x-text="p.dosage"></span>
                                <span x-show="p.size" class="text-[9px] font-semibold bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded-full whitespace-nowrap"
                                      title="{{ __('Tamanho') }}" x-text="p.size"></span>
                                <span x-show="p.color" class="text-[9px] font-semibold bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full whitespace-nowrap"
                                      title="{{ __('Cor') }}" x-text="p.color"></span>
                            </div>
                            <div class="mt-auto pt-1.5 flex items-center justify-between gap-1">
                                <span class="font-bold text-blue-700 text-sm whitespace-nowrap" x-text="formatMoney(p.price)"></span>
                                {{-- Três estados, os mesmos do POS online: com
                                     stock gerido mostra-se o número; serviço
                                     diz-se que é serviço; e um produto sem
                                     gestão de stock diz que se vende sempre —
                                     um zero ali parecia falta de stock. --}}
                                <span x-show="p.type !== 'servico' && p.manage_stock !== false"
                                      :class="(p.stock_quantity > 0) ? 'bg-gray-100 text-gray-500' : 'bg-red-100 text-red-600'"
                                      class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full whitespace-nowrap"
                                      x-text="(p.stock_quantity > 0) ? p.stock_quantity : __('Esgot.')"></span>
                                <span x-show="p.type === 'servico'" x-cloak
                                      class="text-[10px] font-bold bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full whitespace-nowrap">
                                    {{ __('Serviço') }}
                                </span>
                                <span x-show="p.type !== 'servico' && p.manage_stock === false" x-cloak
                                      class="text-[10px] font-bold bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded-full whitespace-nowrap">
                                    {{ __('Sem stock gerido') }}
                                </span>
                            </div>
                        </button>
                    </template>
                </div>
                {{-- Mostrar mais / contador --}}
                <div x-show="filteredProducts.length > visibleLimit" class="mt-4 text-center">
                    <button @click="loadMore()" class="bg-white border border-gray-200 hover:border-blue-300 text-gray-700 px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm transition">
                        <i class="fas fa-chevron-down mr-1"></i>{{ __('Mostrar mais') }}
                        <span class="text-gray-400">(+<span x-text="Math.min(80, filteredProducts.length - visibleLimit)"></span>)</span>
                    </button>
                </div>
                {{-- Uma frase inteira, e nao "X" + " de " + "Y" + " produtos" colados:
                     noutras linguas a ordem das palavras e outra, e o plural nao
                     se resolve com um "(s)". --}}
                <p x-show="filteredProducts.length" class="mt-2 text-center text-[11px] text-gray-400"
                   x-text="__n(':mostrados de :n produto|:mostrados de :n produtos', filteredProducts.length, { mostrados: Math.min(visibleLimit, filteredProducts.length), n: filteredProducts.length })"></p>

                <div x-show="!filteredProducts.length" class="text-center py-16 text-gray-400">
                    <i class="fas fa-box-open text-5xl mb-3 block opacity-40"></i>
                    <p class="text-sm font-medium" x-text="search || categoryFilter ? __('Nenhum produto encontrado') : __('Sem produtos sincronizados')"></p>
                    <button x-show="!search && !categoryFilter" @click="window.SosPwa.sync(true)" class="mt-3 text-xs bg-blue-600 text-white px-4 py-2 rounded-lg font-bold">
                        <i class="fas fa-rotate mr-1"></i>{{ __('Sincronizar catálogo') }}
                    </button>
                </div>
            </div>
        </section>

        {{-- ============ CARRINHO (coluna direita no desktop / bottom-sheet no telemóvel) ============ --}}
        <aside class="bg-white flex flex-col
                      fixed inset-x-0 bottom-0 z-50 rounded-t-3xl shadow-2xl max-h-[92vh] transition-transform duration-300
                      lg:static lg:z-auto lg:col-span-5 xl:col-span-4 lg:h-full lg:max-h-none lg:rounded-none lg:shadow-none lg:translate-y-0 lg:border-l lg:border-gray-200"
               :class="showCart ? 'translate-y-0' : 'translate-y-full lg:translate-y-0'">

            {{-- Cabeçalho do carrinho --}}
            <div class="shrink-0 px-4 pt-3 pb-2 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-cart-shopping text-emerald-600"></i>{{ __('Carrinho') }}
                        <span x-show="cart.length" class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full" x-text="__n(':n item|:n itens', cartCount, { n: cartCount })"></span>
                    </h2>
                    <div class="flex items-center gap-1">
                        <button @click="clearCart()" x-show="cart.length" class="text-red-500 hover:text-red-600 text-sm px-2 py-1" title="{{ __('Limpar') }}">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button @click="showCart = false" class="lg:hidden text-gray-400 text-2xl leading-none px-1">&times;</button>
                    </div>
                </div>
                <span class="inline-block mt-1 text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold tracking-wide">{{ __('FATURA-RECIBO') }}</span>
            </div>

            {{-- Cliente --}}
            <div class="shrink-0 px-4 py-2">
                <button @click="showClientPicker = true"
                        class="w-full bg-blue-50 hover:bg-blue-100 border-2 border-dashed border-blue-200 rounded-xl px-3 py-2.5 text-left text-sm flex items-center gap-2 transition">
                    <i class="fas fa-user text-blue-600"></i>
                    <span class="flex-1 truncate" :class="selectedClient ? 'font-semibold text-gray-800' : 'text-gray-500'"
                          x-text="selectedClient ? selectedClient.name : __('Consumidor Final')"></span>
                    <i class="fas fa-chevron-right text-blue-300 text-xs"></i>
                </button>
            </div>

            {{-- Aviso: sem turno aberto.

                 Só com o carrinho JÁ CHEIO. Vazio, quem manda a mensagem é o
                 cartão grande no meio do painel — dizer a mesma coisa duas
                 vezes no mesmo ecrã não a torna mais clara, torna o ecrã mais
                 confuso. Com artigos no carrinho a história é outra: a pessoa
                 já escolheu e precisa de saber, ali mesmo, porque não avança. --}}
            <div x-show="!shift.open && cart.length" x-cloak class="shrink-0 mx-4 mb-2 bg-red-50 border border-red-200 text-red-700 rounded-xl px-3 py-2 text-xs flex items-center gap-2">
                <i class="fas fa-triangle-exclamation"></i>
                <span class="flex-1">{{ __('Sem turno aberto — abra um turno para poder vender.') }}</span>
                <button @click="openOpenShiftModal()" class="underline font-bold whitespace-nowrap">{{ __('Abrir') }}</button>
            </div>

            {{-- Aviso: turno aberto offline (por sincronizar) --}}
            <div x-show="shift.open && shift._local" x-cloak class="shrink-0 mx-4 mb-2 bg-amber-50 border border-amber-200 text-amber-700 rounded-xl px-3 py-2 text-xs flex items-center gap-2">
                <i class="fas fa-cloud-arrow-up"></i>
                <span class="flex-1">{{ __('Turno aberto offline — será sincronizado quando a internet voltar.') }}</span>
            </div>

            {{-- Itens --}}
            <div class="flex-1 overflow-y-auto px-4 space-y-2 min-h-[120px]">
                <template x-for="(itm, idx) in cart" :key="idx">
                    <div class="bg-gray-50 rounded-2xl p-2.5 flex items-center gap-2.5">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate" x-text="itm.product_name"></p>
                            <p class="text-[11px] text-gray-500" x-text="formatMoney(itm.unit_price) + ' · ' + (parseFloat(itm.tax_rate) > 0 ? __('IVA :taxa%', { taxa: itm.tax_rate }) : __('Isento'))"></p>
                            <p class="text-xs font-bold text-blue-700 mt-0.5" x-text="formatMoney(itm.quantity * itm.unit_price * (1 + itm.tax_rate/100)) + ' Kz'"></p>
                        </div>
                        <div class="flex items-center gap-1.5 bg-white rounded-xl p-1 shadow-sm">
                            <button @click="decrement(idx)" class="w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-lg font-bold text-lg leading-none flex items-center justify-center">−</button>
                            <input type="number" inputmode="numeric" min="1"
                                   :value="itm.quantity"
                                   @change="setQuantity(idx, $event.target.value)"
                                   @focus="$event.target.select()"
                                   class="w-10 text-center font-bold text-sm bg-transparent border-0 focus:outline-none focus:ring-1 focus:ring-blue-400 rounded">
                            <button @click="increment(idx)" class="w-8 h-8 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg font-bold text-lg leading-none flex items-center justify-center">+</button>
                        </div>
                    </div>
                </template>
                {{-- O vazio do carrinho diz o que FALTA FAZER, não que está vazio.

                     Sem turno, o ecrã dizia três coisas ao mesmo tempo: um
                     crachá vermelho "S/ turno" no topo, um aviso a meio, e no
                     fundo um botão verde de finalizar venda. Quem chega ao
                     balcão não sabe por onde começar. Aqui o espaço maior e
                     mais vazio passa a ter a única acção que interessa. --}}
                <div x-show="!cart.length && !shift.open" x-cloak class="text-center py-10 px-4">
                    <div class="w-16 h-16 rounded-2xl bg-red-50 border border-red-100 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-lock text-red-400 text-2xl"></i>
                    </div>
                    <p class="text-sm font-bold text-gray-800 mb-1">{{ __('Turno fechado') }}</p>
                    <p class="text-xs text-gray-500 mb-4">{{ __('Abra o turno para começar a vender.') }}</p>
                    <button @click="openOpenShiftModal()"
                            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow transition">
                        <i class="fas fa-lock-open mr-1.5"></i>{{ __('Abrir turno') }}
                    </button>
                </div>

                <div x-show="!cart.length && shift.open" x-cloak class="text-center py-10 text-gray-300">
                    <i class="fas fa-shopping-basket text-5xl mb-2 block"></i>
                    <p class="text-sm font-medium">{{ __('Carrinho vazio') }}</p>
                    <p class="text-xs">{{ __('Toca num produto para adicionar') }}</p>
                </div>
            </div>

            {{-- Aviso oversell no carrinho --}}
            <template x-if="cartHasOversell">
                <div class="shrink-0 mx-4 mb-1 bg-orange-50 border border-orange-200 text-orange-700 rounded-xl px-3 py-2 text-xs flex items-center gap-2">
                    <i class="fas fa-triangle-exclamation"></i>
                    <span>{{ __('Atenção: um ou mais itens excedem o stock local disponível. A venda será registada, mas pode ser rejeitada pelo servidor.') }}</span>
                </div>
            </template>

        {{-- Rodapé: totais + pagamento + ação --}}
            <div class="shrink-0 border-t border-gray-100 px-4 pt-3 pb-4 space-y-3 bg-white">
                {{-- Totais --}}
                <div class="bg-gradient-to-br from-emerald-600 to-green-700 text-white rounded-2xl shadow-lg p-3">
                    <div class="flex justify-between text-xs opacity-90"><span>{{ __('Subtotal') }}</span><span x-text="formatMoney(totals.subtotal) + ' Kz'"></span></div>
                    <div x-show="totals.discountAmount > 0" class="flex justify-between text-xs opacity-90 mt-0.5"><span x-text="__('Desconto (:pct%)', { pct: discountPercent })"></span><span x-text="'-' + formatMoney(totals.discountAmount) + ' Kz'"></span></div>
                    <div x-show="totals.tax > 0" class="flex justify-between text-xs opacity-90 mt-0.5"><span>{{ __('IVA') }}</span><span x-text="formatMoney(totals.tax) + ' Kz'"></span></div>
                    <div x-show="totals.tax <= 0" class="flex justify-between text-xs opacity-90 mt-0.5"><span>{{ __('IVA') }}</span><span>{{ __('Isento') }}</span></div>
                    <div class="border-t border-white/30 mt-1.5 pt-1.5 flex justify-between items-baseline">
                        <span class="font-semibold">{{ __('TOTAL') }}</span><span class="text-2xl font-extrabold" x-text="formatMoney(totals.total) + ' Kz'"></span>
                    </div>
                </div>

                <template x-if="cart.length">
                    <div class="space-y-3">
                        {{-- Pagamento --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-[10px] font-bold text-gray-500 uppercase">{{ __('Forma de Pagamento') }}</label>

                                {{-- DIVIDIR O PAGAMENTO.
                                     O servidor já aceitava `payments[]` e o POS
                                     online já dividia; só este ecrã é que não.
                                     Um cliente que paga metade em dinheiro e
                                     metade a cartão — coisa de todos os dias —
                                     obrigava o operador a fazer duas vendas, e
                                     saíam dois documentos fiscais para uma
                                     compra só. --}}
                                <button @click="alternarDivisao()"
                                        :class="dividirPagamento ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-500 border-gray-200'"
                                        class="rounded-lg border px-2.5 py-1 text-[10px] font-bold transition">
                                    <i class="fas fa-divide mr-1"></i>{{ __('Dividir') }}
                                </button>
                            </div>

                            {{-- Um método só: o caminho normal, e continua a um toque. --}}
                            <div x-show="!dividirPagamento" class="grid grid-cols-4 gap-1.5">
                                <template x-for="m in paymentMethods" :key="m.code">
                                    <button @click="payment = m.code"
                                            :class="payment === m.code ? 'bg-blue-600 text-white shadow border-blue-600' : 'bg-white text-gray-600 border-gray-200'"
                                            class="rounded-xl py-2 text-[10px] font-bold border-2 transition">
                                        <i :class="m.icon" class="block mb-0.5 text-sm"></i>
                                        <span x-text="m.label"></span>
                                    </button>
                                </template>
                            </div>

                            {{-- Dividido: uma linha por método. --}}
                            <div x-show="dividirPagamento" x-cloak class="space-y-1.5">
                                <template x-for="(linha, i) in pagamentos" :key="i">
                                    <div class="flex items-center gap-1.5">
                                        <select x-model="linha.method"
                                                class="flex-1 min-w-0 bg-white border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-bold focus:outline-none focus:border-blue-400">
                                            <template x-for="m in paymentMethods" :key="m.code">
                                                <option :value="m.code" x-text="m.label"></option>
                                            </template>
                                        </select>

                                        <input x-model.number="linha.amount" type="number" inputmode="decimal" min="0" step="0.01"
                                               class="w-24 bg-white border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-right font-bold focus:outline-none focus:border-blue-400">

                                        <button @click="removerPagamento(i)" x-show="pagamentos.length > 1"
                                                class="shrink-0 w-8 h-8 rounded-lg bg-gray-100 text-gray-500 text-lg leading-none">&times;</button>
                                    </div>
                                </template>

                                <div class="flex items-center justify-between gap-2 pt-0.5">
                                    <button @click="juntarPagamento()" x-show="pagamentos.length < paymentMethods.length"
                                            class="text-[11px] font-bold text-blue-600">
                                        <i class="fas fa-plus mr-1"></i>{{ __('Outro método') }}
                                    </button>

                                    {{-- A DIFERENÇA À VISTA. Sem ela, o operador
                                         só descobria que faltavam 200 Kz quando
                                         o botão de finalizar não respondia. --}}
                                    <span class="text-[11px] font-bold ml-auto"
                                          :class="Math.abs(faltaPagar) < 0.01 ? 'text-emerald-600' : 'text-red-600'">
                                        <span x-show="Math.abs(faltaPagar) < 0.01"><i class="fas fa-check mr-1"></i>{{ __('Certo') }}</span>
                                        <span x-show="faltaPagar > 0.01" x-cloak
                                              x-text="'{{ __('Falta') }} ' + formatMoney(faltaPagar) + ' Kz'"></span>
                                        <span x-show="faltaPagar < -0.01" x-cloak
                                              x-text="'{{ __('A mais') }} ' + formatMoney(-faltaPagar) + ' Kz'"></span>
                                    </span>
                                </div>
                            </div>
                        </div>

                                {{-- Desconto comercial --}}
                        <div class="flex items-center gap-2">
                            <label class="text-[10px] font-bold text-gray-500 uppercase whitespace-nowrap">{{ __('Desconto %') }}</label>
                            <input x-model="discountPercent" type="number" inputmode="decimal" min="0" max="100" step="0.5" placeholder="0"
                                   class="w-20 bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-sm text-right font-bold focus:outline-none focus:border-blue-400">
                            <span x-show="discountPercent > 0" class="text-xs text-red-600 font-bold" x-text="'-' + formatMoney(totals.discountAmount) + ' Kz'" x-cloak></span>
                        </div>

                        {{-- Troco (apenas dinheiro) --}}
                        <div x-show="payment === 'cash'" x-cloak class="bg-amber-50 border border-amber-200 rounded-xl p-2.5">
                            <div class="flex items-center gap-2">
                                <label class="text-[11px] font-bold text-amber-800 whitespace-nowrap">{{ __('Valor recebido') }}</label>
                                <input x-model="amountReceived" type="number" inputmode="decimal" min="0" step="0.01"
                                       :placeholder="formatMoney(totals.total)"
                                       class="flex-1 w-full bg-white border border-amber-300 rounded-lg px-2 py-1.5 text-sm text-right font-bold focus:outline-none focus:border-amber-500">
                            </div>
                            <div class="flex gap-1 mt-1.5">
                                <template x-for="q in quickCashOptions" :key="q">
                                    <button @click="amountReceived = q" class="flex-1 bg-white border border-amber-300 rounded-lg py-1 text-[11px] font-bold text-amber-700 hover:bg-amber-100" x-text="formatMoney(q)"></button>
                                </template>
                            </div>
                            <div x-show="changeDue > 0" class="flex justify-between mt-2 pt-2 border-t border-amber-200 text-sm font-bold text-amber-900">
                                <span>{{ __('Troco') }}</span><span x-text="formatMoney(changeDue) + ' Kz'"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Finalizar --}}
                {{-- Desactivado sem turno: um botao que se carrega e nao vende
                     ensina a carregar duas vezes. --}}
                <button @click="checkout()" :disabled="!cart.length || saving || !shift.open || !divisaoFechada"
                        class="w-full bg-gradient-to-r from-emerald-500 to-green-600 text-white rounded-2xl font-bold text-base py-4 shadow-lg disabled:opacity-50 active:scale-[0.99] transition">
                    <span x-show="!saving"><i class="fas fa-circle-check mr-1.5"></i>{{ __('Finalizar Venda') }}</span>
                    <span x-show="saving"><i class="fas fa-spinner fa-spin mr-1.5"></i>{{ __('A guardar…') }}</span>
                </button>

                {{-- Fechar turno (funciona offline — fecho enfileirado após as vendas) --}}
                <button x-show="shift.open" @click="closeShift()" x-cloak
                        class="w-full text-xs text-gray-500 hover:text-red-600 font-bold py-1.5">
                    <i class="fas fa-lock mr-1"></i>{{ __('Fechar turno') }}
                </button>
            </div>
        </aside>
    </div>

    {{-- Backdrop do bottom-sheet (telemóvel) --}}
    <div x-show="showCart" @click="showCart = false" class="lg:hidden fixed inset-0 bg-black/50 z-40" x-transition.opacity x-cloak></div>

    {{-- Aviso de receita médica — apaga-se sozinho ao fim de alguns segundos.
         Fica por cima do carrinho (z-55) porque no telemóvel o carrinho sobe em
         folha quase inteira, e um aviso escondido por trás dele não é aviso. --}}
    <div x-show="avisoReceita" x-cloak x-transition
         @click="avisoReceita = null"
         class="fixed top-16 inset-x-3 z-[55] bg-amber-500 text-white rounded-2xl shadow-2xl px-4 py-3 flex items-start gap-3 cursor-pointer">
        <i class="fas fa-prescription text-lg mt-0.5"></i>
        <p class="flex-1 text-sm font-bold leading-snug" x-text="avisoReceita"></p>
        <span class="text-white/70 text-xl leading-none">&times;</span>
    </div>

    {{-- Barra flutuante "Ver carrinho" (telemóvel) --}}
    <div class="lg:hidden fixed bottom-[72px] inset-x-3 z-40" x-show="cart.length && !showCart" x-cloak x-transition>
        <button @click="showCart = true"
                class="w-full bg-gradient-to-r from-emerald-600 to-green-700 text-white rounded-2xl shadow-2xl px-4 py-3.5 flex items-center justify-between active:scale-[0.99] transition">
            <span class="flex items-center gap-2.5 font-bold text-sm">
                <span class="bg-white/25 rounded-full min-w-[28px] h-7 px-1 flex items-center justify-center" x-text="cartCount"></span>
                {{ __('Ver carrinho') }}
            </span>
            <span class="font-extrabold text-base" x-text="formatMoney(totals.total) + ' Kz'"></span>
        </button>
    </div>

    {{-- ============ MODAL: selecionar cliente ============ --}}
    <div x-show="showClientPicker" @click.self="showClientPicker = false" class="fixed inset-0 bg-black/50 z-[60] flex items-end sm:items-center justify-center" x-transition x-cloak>
        <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl max-h-[80vh] flex flex-col" @click.stop>
            <div class="p-3 border-b flex items-center gap-2">
                <i class="fas fa-magnifying-glass text-gray-400 ml-1"></i>
                <input x-model="clientSearch" type="search" placeholder="{{ __('Pesquisar cliente…') }}" class="flex-1 px-1 py-2 text-sm focus:outline-none" autofocus>
                <button @click="showClientPicker = false" class="text-gray-400 text-2xl px-1">&times;</button>
            </div>
            <div class="overflow-y-auto flex-1 p-2 space-y-1">
                <button @click="selectClient(null); showClientPicker = false" class="w-full text-left p-3 hover:bg-blue-50 rounded-xl flex items-center gap-2">
                    <i class="fas fa-user-tag text-gray-400"></i>
                    <span class="font-semibold text-sm">{{ __('Consumidor Final') }}</span>
                </button>
                <template x-for="c in filteredClients" :key="c.id">
                    <button @click="selectClient(c); showClientPicker = false" class="w-full text-left p-3 hover:bg-blue-50 rounded-xl border-t border-gray-50">
                        <p class="font-semibold text-sm" x-text="c.name"></p>
                        <p class="text-xs text-gray-500" x-show="c.nif" x-text="__('NIF: :nif', { nif: c.nif })"></p>
                    </button>
                </template>
                <div x-show="!filteredClients.length" class="text-center py-6 text-gray-400 text-sm italic">{{ __('Nenhum cliente') }}</div>
            </div>
            <button @click="openClientCreate()" class="block p-3 border-t bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-center font-bold text-sm transition">
                <i class="fas fa-user-plus mr-1"></i>{{ __('Criar novo cliente') }}
            </button>
        </div>
    </div>

    {{-- ============ MODAL: criar cliente rápido ============ --}}
    <div x-show="showClientCreate" @click.self="showClientCreate = false" class="fixed inset-0 bg-black/50 z-[70] flex items-end sm:items-center justify-center" x-transition x-cloak>
        <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl max-h-[88vh] flex flex-col" @click.stop>
            <div class="p-3 border-b flex items-center justify-between">
                <h3 class="font-bold text-base text-gray-900"><i class="fas fa-user-plus mr-1 text-emerald-600"></i>{{ __('Novo Cliente') }}</h3>
                <button @click="showClientCreate = false" class="text-gray-400 text-2xl leading-none px-1">&times;</button>
            </div>
            <div class="overflow-y-auto flex-1 p-4 space-y-3">
                {{-- Tipo --}}
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="newClient.type = 'pessoa_fisica'" :class="newClient.type === 'pessoa_fisica' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200'" class="border-2 rounded-xl py-2.5 text-sm font-bold transition">
                        <i class="fas fa-user mr-1"></i>{{ __('Singular') }}
                    </button>
                    <button type="button" @click="newClient.type = 'pessoa_juridica'" :class="newClient.type === 'pessoa_juridica' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200'" class="border-2 rounded-xl py-2.5 text-sm font-bold transition">
                        <i class="fas fa-building mr-1"></i>{{ __('Empresa') }}
                    </button>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">{{ __('Nome') }} <span class="text-red-500">*</span></label>
                    <input x-model="newClient.name" type="text" maxlength="255" placeholder="{{ __('Nome ou Designação Social') }}"
                           class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-blue-500 focus:outline-none" @keydown.enter="createQuickClient()">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">NIF / BI</label>
                        <input x-model="newClient.nif" type="text" maxlength="50" inputmode="numeric" placeholder="{{ __('Opcional') }}"
                               class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">{{ __('Telemóvel') }}</label>
                        <input x-model="newClient.mobile" type="tel" maxlength="50" placeholder="{{ __('Opcional') }}"
                               class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                </div>
                <p class="text-[11px] text-gray-400"><i class="fas fa-circle-info mr-1"></i>{{ __('Guardado localmente e sincronizado automaticamente. Fica logo selecionado nesta venda.') }}</p>
            </div>
            <div class="p-3 border-t flex gap-2">
                <button @click="showClientCreate = false" class="flex-1 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-bold text-sm">{{ __('Cancelar') }}</button>
                <button @click="createQuickClient()" :disabled="creatingClient || !newClient.name.trim()" class="flex-[2] bg-gradient-to-r from-emerald-500 to-green-600 text-white py-3 rounded-xl font-bold text-sm shadow-lg disabled:opacity-50">
                    <span x-show="!creatingClient"><i class="fas fa-check mr-1"></i>{{ __('Guardar e selecionar') }}</span>
                    <span x-show="creatingClient"><i class="fas fa-spinner fa-spin mr-1"></i>{{ __('A guardar…') }}</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ============ MODAL: recibo após venda ============ --}}
    <div x-show="lastReceipt" @click.self="lastReceipt = null" class="fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4" x-transition x-cloak>
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 text-center shadow-2xl" @click.stop>
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3" :class="lastReceipt?.synced ? 'bg-emerald-100' : 'bg-amber-100'">
                <i class="text-3xl" :class="lastReceipt?.synced ? 'fas fa-check text-emerald-600' : 'fas fa-clock text-amber-600'"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-1" x-text="lastReceipt?.synced ? __('Venda Concluída!') : __('Venda Registada (Provisória)')"></h3>
            <p class="text-sm text-gray-600 mb-3" x-text="lastReceipt?.message"></p>

            <div class="bg-gray-50 rounded-2xl p-3 mb-3 text-left text-sm space-y-0.5">
                <p class="flex justify-between"><span class="text-gray-500">{{ __('Documento') }}</span><strong x-text="lastReceipt?.number"></strong></p>
                <p class="flex justify-between"><span class="text-gray-500">{{ __('Cliente') }}</span><strong x-text="lastReceipt?.client"></strong></p>
                <p class="flex justify-between"><span class="text-gray-500">{{ __('Itens') }}</span><strong x-text="lastReceipt?.itemCount"></strong></p>
                <p class="flex justify-between"><span class="text-gray-500">{{ __('Pagamento') }}</span><strong x-text="lastReceipt?.payment"></strong></p>
                <p class="flex justify-between text-lg mt-1"><span class="text-gray-500">{{ __('Total') }}</span><strong class="text-emerald-700" x-text="formatMoney(lastReceipt?.total) + ' Kz'"></strong></p>
            </div>

            <div class="flex gap-2">
                <button @click="reprintLast()" class="flex-1 bg-gradient-to-r from-emerald-600 to-green-700 text-white py-3 rounded-2xl font-bold shadow-lg">
                    <i class="fas fa-print mr-1"></i>{{ __('Imprimir') }}
                </button>
                {{-- O talão em PDF, para o WhatsApp — feito no aparelho, com
                     ou sem rede. Abre a folha de partilha do sistema. --}}
                <button @click="partilharLast()" :disabled="aPartilhar" data-ensaio="partilhar-pdf"
                        class="flex-1 bg-gradient-to-r from-teal-600 to-emerald-700 text-white py-3 rounded-2xl font-bold shadow-lg disabled:opacity-60">
                    <span x-show="!aPartilhar"><i class="fas fa-file-pdf mr-1"></i>{{ __('PDF · WhatsApp') }}</span>
                    <span x-show="aPartilhar" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i>{{ __('A gerar o PDF…') }}</span>
                </button>
                <button @click="lastReceipt = null" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-2xl font-bold">{{ __('Nova Venda') }}</button>
            </div>
        </div>
    </div>

    {{-- Os modais do turno vivem num partial: o restaurante usa os mesmos.
         Ver resources/views/partials/pwa-turno.blade.php --}}
    @include("partials.pwa-turno")

    {{-- ============ DRAWER: vendas pendentes ============ --}}
    <div x-show="showPending" @click.self="showPending = false" class="fixed inset-0 bg-black/60 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-4" x-cloak x-transition>
        <div class="bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl w-full max-w-md max-h-[85vh] flex flex-col" @click.stop>
            <div class="bg-gradient-to-r from-amber-500 to-orange-600 text-white px-5 py-3 rounded-t-3xl flex items-center justify-between">
                <h3 class="font-bold text-base"><i class="fas fa-clock mr-1"></i>{{ __('Documentos Offline') }}</h3>
                <button @click="showPending = false" class="text-2xl leading-none">&times;</button>
            </div>
            <div class="p-3 border-b text-xs text-gray-600 flex items-center justify-between gap-2">
                <span x-text="lastSyncLabel" class="truncate"></span>
                <button @click="window.SosPwa.sync(true).then(refreshPending)" class="bg-blue-600 text-white px-2.5 py-1 rounded-lg text-[10px] font-bold whitespace-nowrap shrink-0">
                    <i class="fas fa-rotate mr-1"></i>{{ __('Sincronizar') }}
                </button>
            </div>

            {{-- Separador: Jobs falhados --}}
            <template x-if="failedJobs.length">
                <div class="border-b">
                    <div class="px-3 py-2 bg-red-50 flex items-center justify-between">
                        {{-- Em portugues o plural nao se ve aqui, mas em EN e FR ve-se
                             ("1 with a permanent error" / "3 with permanent errors"):
                             por isso vai por __n com as duas formas iguais em PT. --}}
                        <span class="text-xs font-bold text-red-700"><i class="fas fa-circle-exclamation mr-1"></i><span x-text="__n(':n com erro permanente|:n com erro permanente', failedJobs.length, { n: failedJobs.length })"></span></span>
                        <button @click="retryAllFailed()" class="text-[10px] bg-red-600 text-white px-2 py-1 rounded-lg font-bold">{{ __('Tentar todos') }}</button>
                    </div>
                    <div class="divide-y max-h-40 overflow-y-auto">
                        <template x-for="j in failedJobs" :key="j.id">
                            <div class="px-3 py-2 hover:bg-red-50">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-red-700 truncate" x-text="j.op"></p>
                                        <p class="text-[10px] text-red-500 truncate" x-text="j.last_error || __('Erro desconhecido')"></p>
                                    </div>
                                    <button @click="retryJob(j.id)" class="shrink-0 text-[10px] bg-red-600 text-white px-2 py-1 rounded-lg font-bold">{{ __('Retry') }}</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <div class="overflow-y-auto flex-1 divide-y">
                <template x-for="ps in pendingSales" :key="ps.local_uuid">
                    <div class="p-3 hover:bg-gray-50">
                        <div class="flex items-start justify-between mb-1">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-sm truncate" x-text="ps._synced ? ps._server_number : ps.provisional_number"></p>
                                <p class="text-xs text-gray-500 truncate" x-text="ps.client_name + ' · ' + new Date(ps.created_at).toLocaleString('pt-AO')"></p>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ml-2 whitespace-nowrap"
                                  :class="ps._synced ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                  x-text="ps._synced ? '✓ ' + __('Sincronizada') : '⏳ ' + __('Pendente')"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-emerald-700 font-bold text-sm" x-text="formatMoney(ps.total) + ' Kz'"></p>
                            <div class="flex gap-1.5">
                                <button @click="partilhar(ps)" :disabled="aPartilhar" class="text-xs bg-teal-600 hover:bg-teal-700 text-white px-2.5 py-1 rounded-lg font-bold disabled:opacity-60">
                                    <i class="fas fa-file-pdf mr-1"></i>{{ __('PDF') }}
                                </button>
                                <button @click="reprint(ps)" class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1 rounded-lg font-bold">
                                    <i class="fas fa-print mr-1"></i>{{ __('Reimprimir') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="!pendingSales.length && !failedJobs.length" class="p-8 text-center text-gray-400 text-sm italic">{{ __('Sem documentos registados') }}</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function posOffline() {
    return {
        allProducts: [],
        allClients: [],
        cart: [],
        search: '',
        categoryFilter: null,
        clientSearch: '',
        selectedClient: null,
        aPartilhar: false,
        showClientPicker: false,
        showClientCreate: false,
        creatingClient: false,
        newClient: { type: 'pessoa_fisica', name: '', nif: '', mobile: '' },
        payment: 'cash',

        // ── Pagamento dividido ────────────────────────────────────────────
        // O servidor aceita `payments[]` desde sempre e o POS online já o
        // usava; só este ecrã é que obrigava a um método único. Metade em
        // dinheiro e metade a cartão é venda de todos os dias.
        dividirPagamento: false,
        pagamentos: [],
        amountReceived: '',
        discountPercent: 0,
        visibleLimit: 80,
        barcodeScanning: false,
        saving: false,
        // Aviso de receita médica em curso (texto já traduzido) e o temporizador
        // que o apaga. Guardado no estado, e não num alert(), pela razão que
        // está em avisarReceita().
        avisoReceita: null,
        avisoReceitaTimer: null,
        lastReceipt: null,
        lastSaleRecord: null,
        company: null,
        warehouse: null,
        // O TURNO VIVE NUM SO SITIO. Estado, modais e aritmetica da caixa
        // vem de public/js/pwa-turno.js, partilhado com o restaurante --
        // duas copias da mesma regra de caixa acabam sempre a discordar.
        ...TurnoDoPwa(),
        online: navigator.onLine,
        // UI
        showCart: false,
        // Pendentes + failed jobs
        showPending: false,
        pendingSales: [],
        failedJobs: [],
        pendingCount: 0,
        lastSyncDate: null,

        // DOIS rótulos por forma de pagamento, de propósito:
        //   label   — o que o caixa vê, na língua dele;
        //   labelPt — o que vai na nota do documento fiscal, que sai sempre em
        //             português (é matéria da AGT, não da interface).
        // Juntar os dois numa só chave punha "Cash" dentro de uma fatura angolana.
        paymentMethods: [
            { code: 'cash', labelPt: 'Dinheiro', label: __('Dinheiro'), icon: 'fas fa-money-bill-wave' },
            { code: 'card', labelPt: 'Cartão', label: __('Cartão'), icon: 'fas fa-credit-card' },
            { code: 'transfer', labelPt: 'Transf.', label: __('Transf.'), icon: 'fas fa-university' },
            { code: 'mobile', labelPt: 'Multic.', label: __('Multic.'), icon: 'fas fa-mobile-screen' },
        ],

        async init() {
            await this.loadCatalog();
            this.company = await window.SosPwa.getCompany();
            this.warehouse = await window.SosPwa.getWarehouse();
            await this.iniciarTurno();
            await this.refreshPending();

            // Auto-purge vendas sincronizadas com mais de 30 dias
            this.purgeOldSales();

            // Repõe o limite ao pesquisar/filtrar e auto-carrega ao chegar ao fundo (mobile)
            this.$watch('search', () => { this.visibleLimit = 80; });
            this.$watch('categoryFilter', () => { this.visibleLimit = 80; });
            window.addEventListener('scroll', () => this.onScrollWindow(), { passive: true });

            // Aviso ao fechar/sair com documentos por sincronizar
            window.addEventListener('beforeunload', (e) => {
                if (this.pendingCount > 0) {
                    // tenta sincronizar em segundo plano e bloqueia a saída
                    try { if (navigator.onLine) window.SosPwa.sync(true); } catch (_) {}
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });

            // Estado online reativo (para avisos nos modais)
            window.addEventListener('online', () => { this.online = true; });
            window.addEventListener('offline', () => { this.online = false; });

            // Atualiza estado do turno quando a abertura/fecho offline sincroniza
            window.addEventListener('pwa:shift-synced', async () => {
                this.shift = await window.SosPwa.getShift();
                await this.refreshPending();
            });

            // Atualiza após sync de venda — substitui número provisório → AGT
            window.addEventListener('pwa:pos-sale-synced', async (e) => {
                await this.refreshPending();
                if (this.lastSaleRecord && this.lastSaleRecord.local_uuid === e.detail.local_uuid) {
                    this.lastSaleRecord = await window.SosPwa.db.pos_sales
                        .where('local_uuid').equals(e.detail.local_uuid).first();
                    if (this.lastReceipt) {
                        this.lastReceipt.synced = true;
                        this.lastReceipt.number = e.detail.invoice_number;
                        // NAO dizer "sincronizada com a AGT": o documento foi
                        // emitido e numerado, e a comunicacao a AGT vai a
                        // seguir, em fila. Prometer o que ainda nao aconteceu
                        // e pior do que nao prometer nada — alguem confia e
                        // deixa de conferir.
                        this.lastReceipt.message = __('Emitida — :numero', { numero: e.detail.invoice_number });
                    }
                }
            });
            // Após cada sincronização do catálogo, recarrega produtos/clientes em memória.
            window.addEventListener('pwa:synced', async () => {
                await this.loadCatalog();
                this.company = (await window.SosPwa.getCompany()) || this.company;
                this.warehouse = (await window.SosPwa.getWarehouse()) || this.warehouse;
                this.shift = await window.SosPwa.getShift();
                await this.refreshPending();
            });

            // Rede de segurança: catálogo vazio mas online → força sync e recarrega.
            if (navigator.onLine && !this.allProducts.length) {
                try { await window.SosPwa.sync(true); }
                catch (e) { console.error('[POS] sync inicial falhou', e); }
                await this.loadCatalog();
            }
        },

        async loadCatalog() {
            this.allProducts = await window.SosPwa.db.products.toArray();
            this.allClients = await window.SosPwa.db.clients.toArray();
        },

        async refreshPending() {
            this.pendingSales = await window.SosPwa.getPosSales();
            this.pendingCount = this.pendingSales.filter(s => !s._synced).length;
            this.failedJobs = await window.SosPwa.getFailedJobs();
            this.lastSyncDate = await window.SosPwa.getLastSyncDate();
        },

        get lastSyncLabel() {
            if (!this.lastSyncDate) return __('Nunca sincronizado');
            const d = new Date(this.lastSyncDate);
            // A data mantém o formato da casa; o que se traduz é a frase à volta.
            const quando = d.toLocaleDateString('pt-AO') + ' ' +
                d.toLocaleTimeString('pt-AO', { hour: '2-digit', minute: '2-digit' });
            return __('Sync: :quando', { quando: quando });
        },

        get cartHasOversell() {
            for (const item of this.cart) {
                if (!item.product_id) continue;
                const prod = this.allProducts.find(p => p.id === item.product_id);
                // Serviços e produtos sem controlo de stock nunca sobrevendem.
                if (!prod || prod.type === 'servico' || prod.manage_stock === false) continue;
                if (item.quantity > (parseFloat(prod.stock_quantity) || 0)) return true;
            }
            return false;
        },

        async retryJob(jobId) {
            await window.SosPwa.retryFailedJob(jobId);
            await this.refreshPending();
        },

        async retryAllFailed() {
            await window.SosPwa.retryAllFailed();
            await this.refreshPending();
        },

        get categories() {
            const set = new Set();
            this.allProducts.forEach(p => { if (p.category) set.add(p.category); });
            return [...set].sort((a, b) => a.localeCompare(b, 'pt'));
        },

        get filteredProducts() {
            const s = this.search.toLowerCase().trim();
            return this.allProducts.filter(p => {
                if (this.categoryFilter && p.category !== this.categoryFilter) return false;
                if (!s) return true;
                return (p.name || '').toLowerCase().includes(s) ||
                       (p.sku || '').toLowerCase().includes(s) ||
                       (p.barcode || '').toLowerCase().includes(s) ||
                       // Numa farmácia pergunta-se pela substância, não pela
                       // marca: quem pede "paracetamol" não sabe se a caixa diz
                       // Ben-u-ron. Numa loja de roupa pergunta-se pelo tamanho,
                       // que não está no nome do artigo nem no código.
                       (p.active_ingredient || '').toLowerCase().includes(s) ||
                       (p.size || '').toLowerCase().includes(s);
            });
        },

        // Lista efetivamente renderizada (scroll infinito / "mostrar mais")
        get visibleProducts() {
            return this.filteredProducts.slice(0, this.visibleLimit);
        },

        loadMore() {
            this.visibleLimit += 80;
        },

        onScroll(el) {
            if (!el) return;
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 320) this.loadMore2();
        },

        onScrollWindow() {
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 320) this.loadMore2();
        },

        // loadMore com guarda (só aumenta se há mais para mostrar)
        loadMore2() {
            if (this.visibleLimit < this.filteredProducts.length) this.visibleLimit += 80;
        },

        get filteredClients() {
            const s = this.clientSearch.toLowerCase().trim();
            return !s ? this.allClients.slice(0, 50)
                : this.allClients.filter(c => (c.name || '').toLowerCase().includes(s) || (c.nif || '').toLowerCase().includes(s)).slice(0, 50);
        },

        get totals() {
            let subtotal = 0, tax = 0;
            for (const i of this.cart) {
                const net = i.quantity * i.unit_price;
                subtotal += net;
                tax += net * i.tax_rate / 100;
            }
            const disc = parseFloat(this.discountPercent) || 0;
            const discountAmount = subtotal * disc / 100;
            const baseAfterDisc = subtotal - discountAmount;
            const taxAfterDisc = tax * (subtotal > 0 ? baseAfterDisc / subtotal : 1);
            const total = baseAfterDisc + taxAfterDisc;
            return { subtotal, discountAmount, tax: taxAfterDisc, total };
        },

        get cartCount() {
            return this.cart.reduce((s, i) => s + i.quantity, 0);
        },

        qtyInCart(p) {
            const pid = Number.isInteger(p.id) ? p.id : null;
            return this.cart
                .filter(i => i.product_id === pid && i.product_name === p.name)
                .reduce((s, i) => s + i.quantity, 0);
        },

        get changeDue() {
            const r = parseFloat(this.amountReceived);
            if (!r || isNaN(r)) return 0;
            return Math.max(0, r - this.totals.total);
        },

        get quickCashOptions() {
            const t = this.totals.total;
            if (t <= 0) return [];
            const opts = new Set();
            opts.add(Math.ceil(t / 500) * 500);
            opts.add(Math.ceil(t / 1000) * 1000);
            opts.add(Math.ceil(t / 5000) * 5000);
            return [...opts].filter(v => v >= t).slice(0, 3);
        },

        quickAddByBarcode() {
            const s = this.search.trim();
            if (!s) return;
            const match = this.allProducts.find(p =>
                (p.barcode || '').toLowerCase() === s.toLowerCase() ||
                (p.sku || '').toLowerCase() === s.toLowerCase());
            if (match) {
                this.addToCart(match);
                this.search = '';
            }
        },

        // Aviso de receita: uma faixa que se apaga sozinha, e não um confirm().
        // Ao balcão, uma caixa de diálogo por cada caixa de antibiótico obriga a
        // duas acções por artigo — e o que se ganha em atenção volta a perder-se
        // em cliques dados sem ler. A pergunta bloqueante fica reservada ao
        // psicotrópico, onde é mesmo precisa.
        avisarReceita(nome) {
            this.avisoReceita = __(':artigo exige receita médica — confirme a receita antes de entregar.', { artigo: nome });
            clearTimeout(this.avisoReceitaTimer);
            this.avisoReceitaTimer = setTimeout(() => { this.avisoReceita = null; }, 8000);
            try { navigator.vibrate?.([40, 60, 40]); } catch (_) {}
        },

        addToCart(p) {
            // Bloquear se stock 0 (produto físico)
            if (p.type !== 'servico' && p.manage_stock !== false && (parseFloat(p.stock_quantity) || 0) <= 0) return;

            // Psicotrópico / estupefaciente: confirmar ANTES de entrar no
            // carrinho. Estes artigos têm registo obrigatório e vendê-los por
            // engano tem consequência legal para a farmácia. Offline pesa ainda
            // mais: não há servidor nenhum a rever o que sai daqui, e o talão
            // já foi impresso quando a venda chega a sincronizar.
            if (p.is_controlled && !confirm('⚠️ ' + __(':artigo é um medicamento controlado (psicotrópico ou estupefaciente), de registo obrigatório. Confirma a venda?', { artigo: p.name }))) {
                return;
            }

            const pid = Number.isInteger(p.id) ? p.id : null;
            const existing = this.cart.find(i => i.product_id === pid && i.product_name === p.name);
            if (existing) {
                existing.quantity++;
            } else {
                this.cart.push({
                    product_id: pid,
                    product_name: p.name,
                    quantity: 1,
                    unit_price: parseFloat(p.price) || 0,
                    // NÃO usar "|| 14": 0% (isento) é falsy e viraria 14%.
                    tax_rate: Number.isFinite(parseFloat(p.tax_rate)) ? parseFloat(p.tax_rate) : 0,
                    discount_percent: 0,
                });
            }

            // Avisa, não trava: o operador pode ter a receita na mão, e travar
            // a venda deixava a farmácia sem forma nenhuma de a fazer.
            if (p.requires_prescription) this.avisarReceita(p.name);

            // Feedback tátil
            try { navigator.vibrate?.(30); } catch(_) {}
        },

        increment(idx) {
            this.cart[idx].quantity++;
            try { navigator.vibrate?.(15); } catch(_) {}
        },
        decrement(idx) {
            if (this.cart[idx].quantity > 1) this.cart[idx].quantity--;
            else this.cart.splice(idx, 1);
        },

        setQuantity(idx, val) {
            const q = parseInt(val);
            if (!q || q < 1) {
                this.cart.splice(idx, 1);
            } else {
                this.cart[idx].quantity = q;
            }
        },

        clearCart() {
            if (!confirm(__('Limpar carrinho?'))) return;
            this.cart = [];
            this.selectedClient = null;
            this.amountReceived = '';
        },

        selectClient(c) { this.selectedClient = c; },

        openClientCreate() {
            // pré-preenche com o termo pesquisado, se houver
            this.newClient = { type: 'pessoa_fisica', name: this.clientSearch.trim(), nif: '', mobile: '' };
            this.showClientPicker = false;
            this.showClientCreate = true;
        },

        async createQuickClient() {
            if (this.creatingClient || !this.newClient.name.trim()) return;
            this.creatingClient = true;
            try {
                const record = await window.SosPwa.createClientOffline({
                    type: this.newClient.type,
                    name: this.newClient.name.trim(),
                    nif: this.newClient.nif.trim() || null,
                    mobile: this.newClient.mobile.trim() || null,
                    country: 'Angola',
                    tax_regime: 'geral',
                    is_iva_subject: false,
                });
                // recarrega a lista local e seleciona o novo cliente nesta venda
                this.allClients = await window.SosPwa.db.clients.toArray();
                this.selectClient(record);
                this.showClientCreate = false;
                await window.SosPwa.refreshPendingCount();
            } catch (err) {
                console.error(err);
                alert(__('Erro ao criar cliente: :erro', { erro: err.message }));
            } finally {
                this.creatingClient = false;
            }
        },

        formatMoney(v) {
            return new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v || 0);
        },

        // ── Pagamento dividido ────────────────────────────────────────────

        /**
         * Liga a divisão com a PRIMEIRA LINHA JÁ CHEIA.
         *
         * Abrir com duas linhas a zero obrigava o operador a somar de cabeça
         * para chegar ao total. Assim ele só tira do primeiro método o que vai
         * pagar de outra forma, e a segunda linha nasce com o que sobra.
         */
        alternarDivisao() {
            this.dividirPagamento = !this.dividirPagamento;

            if (!this.dividirPagamento) {
                this.pagamentos = [];
                return;
            }

            this.pagamentos = [{ method: this.payment || 'cash', amount: this.totals.total }];
        },

        juntarPagamento() {
            const usados = this.pagamentos.map((p) => p.method);
            const livre = this.paymentMethods.find((m) => !usados.includes(m.code));

            if (!livre) { return; }

            // A linha nova nasce com o que falta: é isso que o operador quer
            // escrever a seguir, e poupa-lhe a subtracção.
            const falta = Math.round(this.faltaPagar * 100) / 100;

            this.pagamentos.push({ method: livre.code, amount: falta > 0 ? falta : 0 });
        },

        removerPagamento(i) {
            this.pagamentos.splice(i, 1);
        },

        /** Quanto falta para as linhas somarem o total. Negativo = a mais. */
        get faltaPagar() {
            const somado = this.pagamentos.reduce((s, p) => s + (parseFloat(p.amount) || 0), 0);

            return Math.round((this.totals.total - somado) * 100) / 100;
        },

        /**
         * A divisão fecha? Só se somar o total ao cêntimo.
         *
         * O cêntimo de tolerância não é preguiça: os totais vêm de percentagens
         * de imposto e há arredondamentos por linha. Exigir igualdade exacta
         * fazia o botão recusar vendas correctas por 0,01 Kz.
         */
        get divisaoFechada() {
            return !this.dividirPagamento || Math.abs(this.faltaPagar) < 0.01;
        },


        async checkout() {
            if (!this.cart.length || this.saving) return;
            // SEM TURNO NÃO SE VENDE.
            //
            // Isto perguntava se queria continuar, e continuar era o caminho
            // fácil: a venda saía, mas ficava fora do fecho de caixa. Ao fim
            // do dia o dinheiro na gaveta não batia certo com o sistema e
            // ninguém sabia de que venda vinha a diferença — e uma caixa que
            // não fecha não serve para conferir ninguém.
            if (!this.shift.open) {
                this.openOpenShiftModal();
                return;
            }
            this.saving = true;
            try {
                const payMethod = this.paymentMethods.find(m => m.code === this.payment) || this.paymentMethods[0];
                const received = (this.payment === 'cash' && parseFloat(this.amountReceived) > 0)
                    ? parseFloat(this.amountReceived) : this.totals.total;

                // 1) Cria a venda offline (idempotente, enfileirada para sincronização)
                const sale = await window.SosPwa.createPosSaleOffline({
                    client_id: this.selectedClient && Number.isInteger(this.selectedClient.id) ? this.selectedClient.id : null,
                    client_local_uuid: this.selectedClient && !Number.isInteger(this.selectedClient.id) ? this.selectedClient.local_uuid : null,
                    // Daqui para baixo é a carga do documento fiscal: fica em
                    // português em qualquer língua da interface, porque é isto que
                    // sai impresso e vai para a AGT.
                    client_name: this.selectedClient?.name || 'Consumidor Final',
                    client_nif: this.selectedClient?.nif || '999999999',
                    payment_method: this.payment,

                    // AS LINHAS DA DIVISÃO, quando há. O servidor aceita-as
                    // desde sempre; era este ecrã que nunca as mandava, e uma
                    // compra paga em duas formas obrigava a duas vendas — e
                    // portanto a dois documentos fiscais.
                    //
                    // O `payment_method` continua a ir: é o método principal, e
                    // é dele que sai o texto do talão.
                    payments: this.dividirPagamento
                        ? this.pagamentos
                            .filter((p) => (parseFloat(p.amount) || 0) > 0)
                            .map((p) => ({ method: p.method, amount: Math.round((parseFloat(p.amount) || 0) * 100) / 100 }))
                        : null,

                    amount_received: received,
                    discount_commercial: parseFloat(this.discountPercent) || 0,
                    notes: 'POS Offline · Pagamento: ' + payMethod.labelPt,
                    items: this.cart.map(i => ({
                        product_id: i.product_id,
                        product_name: i.product_name,
                        quantity: i.quantity,
                        unit_price: i.unit_price,
                        tax_rate: i.tax_rate,
                        is_service: false,
                        unit: 'UN',
                    })),
                });
                this.lastSaleRecord = sale;

                // 2) Imprime IMEDIATAMENTE — funciona online ou offline
                window.PosOfflineTicket.print(sale, this.company || {});

                // 3) Mostra confirmação
                this.lastReceipt = {
                    synced: !!sale._synced,
                    number: sale._synced ? sale._server_number : sale.provisional_number,
                    client: this.selectedClient?.name || __('Consumidor Final'),
                    itemCount: this.cartCount,
                    payment: payMethod.label,
                    total: this.totals.total,
                    message: navigator.onLine
                        ? __('A sincronizar com o servidor…')
                        : __('Guardada localmente — será sincronizada quando voltar online.'),
                };

                this.cart = [];
                this.selectedClient = null;
                this.payment = 'cash';
                this.amountReceived = '';
                this.discountPercent = 0;
                this.showCart = false;
                await this.refreshPending();
            } catch (err) {
                console.error(err);
                alert(__('Erro: :erro', { erro: err.message }));
            } finally {
                this.saving = false;
            }
        },

        reprintLast() {
            if (!this.lastSaleRecord) return;
            window.PosOfflineTicket.print(this.lastSaleRecord, this.company || {});
        },

        async reprint(ps) {
            const fresh = await window.SosPwa.db.pos_sales.where('local_uuid').equals(ps.local_uuid).first();
            window.PosOfflineTicket.print(fresh || ps, this.company || {});
        },

        // ====== O talão em PDF, para o WhatsApp ======
        // Com ou sem rede: sem rede faz-se no aparelho; emitido e com rede,
        // vai o PDF do servidor. Quem cancela a folha de partilha não leva
        // erro nenhum — cancelar não é falhar.
        async partilharLast() {
            if (!this.lastSaleRecord) return;
            await this.partilhar(this.lastSaleRecord);
        },

        async partilhar(ps) {
            if (this.aPartilhar || !ps?.local_uuid) return;
            this.aPartilhar = true;
            try {
                const r = await window.SosPwa.partilharPdf('venda', ps.local_uuid);
                if (r.modo === 'descarregado') {
                    alert(__('PDF descarregado — anexe-o na conversa.'));
                }
            } catch (e) {
                if (e && e.name === 'AbortError') return;
                alert(__('Não foi possível gerar o PDF: :erro', { erro: e.message }));
            } finally {
                this.aPartilhar = false;
            }
        },

        // ====== Barcode Scanner via câmara (BarcodeDetector API) ======
        async startBarcodeScanner() {
            if (this.barcodeScanning) return;
            // Verificar suporte
            if (!('BarcodeDetector' in window)) {
                alert(__('Leitor de código de barras por câmara não suportado neste dispositivo/navegador.')
                    + '\n\n' + __('Use Chrome no Android para esta funcionalidade.'));
                return;
            }
            this.barcodeScanning = true;
            let stream = null;
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
                });
                const video = document.createElement('video');
                video.srcObject = stream;
                video.setAttribute('playsinline', 'true');
                await video.play();

                // Overlay visual
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);display:flex;flex-direction:column;align-items:center;justify-content:center;';
                overlay.innerHTML = '<p style="color:#fff;font-size:14px;font-weight:700;margin-bottom:12px"><i class="fas fa-barcode" style="margin-right:6px"></i>'
                    + __('Aponte para o código de barras') + '</p>';
                const canvas = document.createElement('canvas');
                canvas.width = 320; canvas.height = 240;
                canvas.style.cssText = 'border-radius:16px;border:3px solid #22c55e;';
                overlay.appendChild(canvas);
                const closeBtn = document.createElement('button');
                closeBtn.textContent = __('Cancelar');
                closeBtn.style.cssText = 'margin-top:16px;padding:10px 28px;background:#ef4444;color:#fff;border:none;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer;';
                overlay.appendChild(closeBtn);
                document.body.appendChild(overlay);

                const ctx = canvas.getContext('2d');
                const detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'qr_code', 'upc_a', 'upc_e'] });
                let found = false;

                const cleanup = () => {
                    found = true;
                    stream.getTracks().forEach(t => t.stop());
                    overlay.remove();
                    this.barcodeScanning = false;
                };

                closeBtn.onclick = cleanup;

                const scan = async () => {
                    if (found) return;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    try {
                        const barcodes = await detector.detect(canvas);
                        if (barcodes.length > 0) {
                            const code = barcodes[0].rawValue;
                            cleanup();
                            try { navigator.vibrate?.(100); } catch(_) {}
                            this.search = code;
                            this.quickAddByBarcode();
                            return;
                        }
                    } catch (_) {}
                    requestAnimationFrame(scan);
                };
                requestAnimationFrame(scan);

            } catch (err) {
                this.barcodeScanning = false;
                if (stream) stream.getTracks().forEach(t => t.stop());
                console.error('[POS] Barcode scanner:', err);
                alert(__('Não foi possível aceder à câmara: :erro', { erro: err.message }));
            }
        },

        // ====== Auto-purge pos_sales sincronizadas > 30 dias ======
        async purgeOldSales() {
            try {
                const cutoff = new Date();
                cutoff.setDate(cutoff.getDate() - 30);
                const old = await window.SosPwa.db.pos_sales
                    .where('_synced').equals(1)
                    .filter(s => new Date(s.created_at) < cutoff)
                    .toArray();
                if (old.length > 0) {
                    await window.SosPwa.db.pos_sales.bulkDelete(old.map(s => s.local_uuid));
                }
            } catch (e) { console.warn('[POS] Purge falhou:', e); }
        },
    };
}
</script>
@endpush
@endsection
