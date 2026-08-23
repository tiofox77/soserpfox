<div class="min-h-[calc(100vh-4rem)] bg-slate-100 p-2 sm:p-3" x-data="{ mobileCart: false }">
    <div class="mx-auto flex max-w-[1900px] flex-col gap-3">
        {{-- Barra de operação: estabelecimento, mesas e comandas sem sair do POS --}}
        <header class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <div class="mr-2 flex items-center gap-2">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-orange-600 text-xl text-white"><i class="fas fa-cash-register"></i></span>
                    <div><h1 class="font-black leading-tight text-slate-900">POS Restaurante</h1><p class="text-xs text-slate-500">Mesa, comanda e venda numa janela</p></div>
                </div>
                <select wire:model.live="venueId" class="min-w-44 rounded-xl border-slate-300 py-2.5 text-sm font-bold focus:border-orange-500 focus:ring-orange-500">
                    @foreach($venues as $venue)<option value="{{ $venue->id }}">{{ $venue->name }}</option>@endforeach
                </select>
                <select wire:model.live="areaId" class="rounded-xl border-slate-300 py-2.5 text-sm font-bold focus:border-orange-500 focus:ring-orange-500">
                    <option value="">Todas as áreas</option>
                    @foreach($areas as $area)<option value="{{ $area->id }}">{{ $area->name }}</option>@endforeach
                </select>
                <button wire:click="$toggle('showTables')" class="rounded-xl px-4 py-2.5 text-sm font-black {{ $showTables ? 'bg-orange-600 text-white' : 'bg-slate-100 text-slate-700' }}"><i class="fas fa-chair mr-2"></i>Mesas</button>
                <button wire:click="$toggle('showOrders')" class="rounded-xl px-4 py-2.5 text-sm font-black {{ $showOrders ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-700' }}"><i class="fas fa-receipt mr-2"></i>Comandas <span class="ml-1 rounded-full bg-white/20 px-2">{{ $orders->count() }}</span></button>
                <button wire:click="openCounterOrder" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-black text-white"><i class="fas fa-bag-shopping mr-2 text-orange-300"></i>Balcão</button>
                @if(!$restaurantSettings->require_recipe_for_products)<button wire:click="$set('showQuickProduct',true)" class="rounded-xl bg-amber-100 px-4 py-2.5 text-sm font-black text-amber-800"><i class="fas fa-bowl-food mr-2"></i>Prato rápido</button>@endif
                <a href="{{ route('restaurant.floor') }}" class="ml-auto rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50"><i class="fas fa-expand mr-2"></i>Mapa da sala</a>
            </div>

            @if($showTables)
                <div class="mt-3 grid max-h-48 grid-cols-3 gap-2 overflow-y-auto border-t border-slate-100 pt-3 sm:grid-cols-5 lg:grid-cols-8 xl:grid-cols-10">
                    @forelse($tables as $table)
                        @php
                            $tone = match($table->status) {
                                'available' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                                'cleaning' => 'border-cyan-200 bg-cyan-50 text-cyan-800',
                                'blocked' => 'border-red-200 bg-red-50 text-red-700',
                                default => 'border-orange-200 bg-orange-50 text-orange-800'
                            };
                        @endphp
                        <article class="overflow-hidden rounded-xl border text-center transition hover:-translate-y-0.5 hover:shadow {{ $tone }}">
                            <button wire:click="chooseTable({{ $table->id }})" class="min-h-16 w-full p-2">
                                <i class="fas fa-chair text-lg"></i><span class="mt-1 block truncate text-sm font-black">{{ $table->name }}</span>
                                <span class="block text-[10px] font-bold uppercase">{{ $table->activeOrder?->order_number ?? $table->status_label }}</span>
                            </button>
                            @if(!$table->activeOrder)
                                <div class="grid grid-cols-2 border-t border-current/10 bg-white/55 text-[10px] font-black">
                                    @if($table->status === 'available')
                                        <button wire:click="prepareReservation({{ $table->id }})" class="border-r border-current/10 px-1 py-2 hover:bg-cyan-100"><i class="fas fa-calendar-plus mr-1"></i>Reservar</button>
                                        <button wire:click="tableStatus({{ $table->id }}, 'blocked')" wire:confirm="Bloquear esta mesa?" class="px-1 py-2 hover:bg-red-100"><i class="fas fa-ban mr-1"></i>Bloquear</button>
                                    @else
                                        <button wire:click="tableStatus({{ $table->id }}, 'available')" wire:confirm="Deixar esta mesa livre?" class="col-span-2 px-1 py-2 hover:bg-emerald-100"><i class="fas fa-check mr-1"></i>Deixar livre</button>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @empty
                        <p class="col-span-full py-5 text-center text-sm text-slate-500">Sem mesas nesta área. Configure-as em Sala e Mesas.</p>
                    @endforelse
                </div>
            @endif

            @if($showOrders)
                <div class="mt-3 flex gap-2 overflow-x-auto border-t border-slate-100 pt-3">
                    @forelse($orders as $row)
                        <button wire:click="selectOrder({{ $row->id }})" class="min-w-48 rounded-xl border p-3 text-left {{ $order === $row->id ? 'border-violet-400 bg-violet-50 ring-2 ring-violet-100' : 'border-slate-200 bg-white' }}">
                            <span class="block text-xs font-bold text-violet-600">{{ $row->table?->name ?? 'Balcão' }} · {{ str_replace('_',' ', $row->status) }}</span>
                            <span class="block font-black text-slate-900">{{ $row->order_number }}</span>
                            <span class="text-sm font-black text-orange-700">{{ number_format($row->grand_total, 2, ',', '.') }} Kz</span>
                        </button>
                    @empty<p class="py-3 text-sm text-slate-500">Sem comandas abertas.</p>@endforelse
                </div>
            @endif
        </header>

        <div class="grid min-h-[680px] gap-3 xl:grid-cols-[210px_minmax(0,1fr)_400px]">
            {{-- Categorias --}}
            <aside class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-slate-900 text-white xl:block">
                <div class="border-b border-white/10 p-4"><p class="text-xs font-bold uppercase tracking-widest text-orange-300">Menu</p><h2 class="text-lg font-black">Categorias</h2></div>
                <nav class="max-h-[72vh] space-y-1 overflow-y-auto p-2">
                    <button wire:click="$set('categoryId', null)" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-bold {{ !$categoryId ? 'bg-orange-600 text-white' : 'text-slate-300 hover:bg-white/10' }}"><i class="fas fa-border-all w-5"></i>Todos</button>
                    @foreach($categories as $category)
                        <button wire:click="$set('categoryId', {{ $category->id }})" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-bold {{ $categoryId === $category->id ? 'bg-orange-600 text-white' : 'text-slate-300 hover:bg-white/10' }}">
                            <i class="fas {{ $category->icon ?: 'fa-utensils' }} w-5"></i><span class="truncate">{{ $category->name }}</span>
                        </button>
                    @endforeach
                </nav>
            </aside>

            {{-- Catálogo visual --}}
            <main class="min-w-0 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative flex-1"><i class="fas fa-search absolute left-4 top-3.5 text-slate-400"></i><input wire:model.live.debounce.250ms="productSearch" class="w-full rounded-xl border-slate-300 py-3 pl-11 focus:border-orange-500 focus:ring-orange-500" placeholder="Pesquisar prato, bebida, código ou barras"></div>
                    <select wire:model.live="categoryId" class="rounded-xl border-slate-300 py-3 text-sm font-bold xl:hidden"><option value="">Todas as categorias</option>@foreach($categories as $category)<option value="{{$category->id}}">{{$category->name}}</option>@endforeach</select>
                    <label class="flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 font-bold text-slate-600">Qtd.<input type="number" min="0.001" step="0.001" wire:model.blur="quantity" class="w-20 border-0 bg-transparent text-center font-black focus:ring-0"></label>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                    @forelse($products as $product)
                        <button wire:click="quickAddProduct({{ $product->id }})" wire:loading.attr="disabled" @disabled(!$selectedOrder || !in_array($selectedOrder->status, ['draft','confirmed','in_preparation','ready','served'])) class="group overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-sm transition hover:-translate-y-0.5 hover:border-orange-400 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-45">
                            <div class="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-orange-50 to-amber-100">
                                @if($product->featured_image)<img src="{{ $product->featured_image_url }}" alt="" class="h-full w-full object-cover transition group-hover:scale-105">@else<div class="grid h-full place-items-center text-4xl text-orange-300"><i class="fas fa-bowl-food"></i></div>@endif
                                <span class="absolute right-2 top-2 rounded-lg bg-white/90 px-2 py-1 text-xs font-black text-orange-700 shadow">{{ number_format($product->price, 0, ',', '.') }} Kz</span>
                            </div>
                            <div class="p-3"><h3 class="line-clamp-2 min-h-10 text-sm font-black text-slate-900">{{ $product->name }}</h3><p class="mt-1 truncate text-[11px] font-semibold text-slate-400">{{ $product->category?->name ?? $product->code }}</p></div>
                        </button>
                    @empty<div class="col-span-full grid min-h-64 place-items-center text-center text-slate-500"><div><i class="fas fa-bowl-food text-5xl text-slate-200"></i><p class="mt-3 font-bold">Nenhum artigo encontrado</p></div></div>@endforelse
                </div>
            </main>

            {{-- Comanda sempre visível no desktop --}}
            <aside class="fixed inset-x-2 bottom-2 z-40 max-h-[82vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl xl:static xl:z-auto xl:max-h-none xl:shadow-sm" :class="mobileCart ? 'block' : 'hidden xl:block'">
                <div class="sticky top-0 z-10 border-b border-slate-100 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>@if($selectedOrder)<p class="text-xs font-black uppercase text-orange-600">{{ $selectedOrder->table?->name ?? 'Venda ao balcão' }}</p><h2 class="text-xl font-black text-slate-900">{{ $selectedOrder->order_number }}</h2><p class="text-xs text-slate-500">{{ $selectedOrder->guest_count }} pessoa(s) · {{ str_replace('_',' ', $selectedOrder->status) }}</p>@else<h2 class="text-xl font-black">Nova venda</h2><p class="text-sm text-slate-500">Escolha uma mesa ou Balcão</p>@endif</div>
                        <button @click="mobileCart=false" class="grid h-9 w-9 place-items-center rounded-lg bg-slate-100 xl:hidden">×</button>
                    </div>
                </div>

                @if($selectedOrder)
                    <div class="divide-y divide-slate-100">
                        @forelse($selectedOrder->items as $item)
                            <article class="flex items-center gap-3 p-3">
                                @if($item->kitchen_status === 'draft')
                                    <div class="flex shrink-0 items-center overflow-hidden rounded-xl border border-orange-200 bg-orange-50">
                                        <button wire:click="changeItemQuantity({{$item->id}}, -1)" class="grid h-10 w-8 place-items-center font-black text-orange-700 hover:bg-orange-100" aria-label="Diminuir quantidade">−</button>
                                        <span class="min-w-9 text-center text-sm font-black text-orange-800">{{ rtrim(rtrim(number_format((float)$item->quantity, 3, ',', '.'), '0'), ',') }}</span>
                                        <button wire:click="changeItemQuantity({{$item->id}}, 1)" class="grid h-10 w-8 place-items-center font-black text-orange-700 hover:bg-orange-100" aria-label="Aumentar quantidade">+</button>
                                    </div>
                                @else
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-sm font-black text-slate-600">{{ rtrim(rtrim(number_format((float)$item->quantity, 3, ',', '.'), '0'), ',') }}x</span>
                                @endif
                                <div class="min-w-0 flex-1"><h3 class="truncate text-sm font-black text-slate-900">{{ $item->product_name }}</h3><p class="text-xs text-slate-500">{{ number_format($item->unit_price,2,',','.') }} Kz · {{ $item->kitchen_status }}</p></div>
                                <div class="text-right"><b class="block text-sm">{{ number_format($item->line_total,2,',','.') }}</b><span class="text-[10px] font-bold text-slate-400">IVA {{ number_format((float)$item->tax_rate, 2, ',', '.') }}% · {{ number_format((float)$item->tax_amount, 2, ',', '.') }}</span>@if($item->kitchen_status==='draft')<button wire:click="removeItem({{$item->id}})" class="ml-2 text-red-500" aria-label="Remover {{ $item->product_name }}"><i class="fas fa-trash"></i></button>@endif</div>
                            </article>
                        @empty<div class="p-10 text-center text-slate-400"><i class="fas fa-cart-plus text-4xl"></i><p class="mt-2 font-bold">Toque nos produtos para adicionar</p></div>@endforelse
                    </div>
                    <details class="mx-3 rounded-xl border border-slate-200 bg-slate-50 p-3"><summary class="cursor-pointer text-sm font-bold text-slate-600">Observação do próximo artigo</summary><input wire:model="itemNotes" class="mt-2 w-full rounded-lg border-slate-300" placeholder="Sem cebola, bem passado..."></details>
                    <div class="mt-3 bg-slate-900 p-4 text-white">
                        <div class="flex justify-between text-sm text-slate-300"><span>Subtotal sem IVA</span><b>{{number_format($selectedOrder->subtotal,2,',','.')}} Kz</b></div>
                        <div class="mt-1 flex justify-between text-sm text-slate-300"><span>IVA</span><b>{{number_format($selectedOrder->tax_total,2,',','.')}} Kz</b></div>
                        <div class="mt-2 flex items-end justify-between"><span class="font-bold">Total</span><strong class="text-2xl text-orange-300">{{number_format($selectedOrder->grand_total,2,',','.')}} Kz</strong></div>
                    </div>
                    <div class="grid gap-2 p-3">
                        @if(in_array($selectedOrder->status, ['draft','confirmed','in_preparation','ready','served']) && $selectedOrder->items->contains(fn($i) => $i->kitchen_status === 'draft'))
                            <button wire:click="confirmOrder" wire:confirm="{{ $restaurantSettings->use_kitchen_workflow ? 'Enviar os novos artigos à cozinha?' : 'Confirmar este pedido rápido?' }}" class="rounded-xl bg-violet-600 px-4 py-3 font-black text-white"><i class="fas {{ $restaurantSettings->use_kitchen_workflow ? 'fa-fire-burner' : 'fa-bolt' }} mr-2"></i>{{ $restaurantSettings->use_kitchen_workflow ? 'Enviar à cozinha' : 'Confirmar pedido rápido' }}</button>
                        @endif
                        @if(in_array($selectedOrder->status, ['ready','served','partially_billed']))
                            <button wire:click="openCheckout" class="rounded-xl bg-emerald-600 px-4 py-4 text-lg font-black text-white"><i class="fas fa-cash-register mr-2"></i>Receber e faturar</button>
                            <a target="_blank" href="{{route('restaurant.orders.consultation-receipt',['id'=>$selectedOrder->id,'print'=>1])}}" class="rounded-xl border border-slate-300 px-4 py-3 text-center font-bold text-slate-700"><i class="fas fa-print mr-2 text-orange-600"></i>Conta de consulta</a>
                        @endif
                        <a href="{{route('restaurant.orders',['order'=>$selectedOrder->id])}}" class="text-center text-xs font-bold text-slate-500 hover:text-orange-600">Mais ações: transferir, juntar ou anular</a>
                    </div>
                @endif
            </aside>
        </div>
    </div>

    <button @click="mobileCart=true" class="fixed bottom-4 right-4 z-30 flex items-center gap-3 rounded-2xl bg-slate-900 px-5 py-4 font-black text-white shadow-2xl xl:hidden"><i class="fas fa-receipt text-orange-300"></i><span>{{ $selectedOrder?->order_number ?? 'Comanda' }}</span><b class="text-orange-300">{{number_format($selectedOrder?->grand_total ?? 0,0,',','.')}} Kz</b></button>

    @include('livewire.restaurant.partials.shift-required-modal')

    @if($showCheckout && $selectedOrder)
        <div class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/75 p-3 backdrop-blur-sm">
            <section class="max-h-[95vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white shadow-2xl">
                <div class="bg-gradient-to-r from-slate-950 via-blue-950 to-slate-900 p-5 text-white sm:p-6"><div class="flex justify-between"><div><p class="text-xs font-black uppercase tracking-widest text-orange-300">Pagamento seguro</p><h2 class="mt-1 text-2xl font-black">Finalizar {{ $selectedOrder->order_number }}</h2><p class="mt-1 text-sm text-slate-300">Confirme cliente, itens e destino do recebimento.</p></div><button wire:click="$set('showCheckout',false)" class="h-10 w-10 rounded-xl bg-white/10 font-black hover:bg-white/20">×</button></div><div class="mt-5 flex items-center justify-between rounded-2xl bg-white/10 p-4"><span class="text-sm text-slate-300">Total a receber</span><strong class="text-3xl text-orange-300">{{number_format($this->checkoutTotal,2,',','.')}} Kz</strong></div></div>
                <div class="p-5 sm:p-6"><div class="grid gap-3 sm:grid-cols-2">
                    <label class="font-bold">Documento<select wire:model.live="documentType" class="mt-1 w-full rounded-xl border-slate-300"><option value="FR">FR — pago no ato</option><option value="FT">FT — crédito</option></select></label>
                    <label class="font-bold">Cliente <a href="{{route('restaurant.contacts')}}" target="_blank" class="float-right text-xs text-blue-600">+ Criar cliente</a><select wire:model="clientId" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Consumidor Final</option>@foreach($clients as $client)<option value="{{$client->id}}">{{$client->name}} · {{$client->nif}}</option>@endforeach</select></label>
                    @if($documentType==='FR' && !$multiPayment)<label class="font-bold sm:col-span-2">Pagamento<select wire:model="paymentMethodId" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Selecione</option>@foreach($paymentMethods as $method)<option value="{{$method->id}}">{{$method->name}}</option>@endforeach</select></label>@endif
                </div>
                <div class="mt-4 rounded-xl border border-slate-200 p-3"><p class="font-black">Itens desta conta</p>@foreach($selectedOrder->items->where('billed_quantity','<','quantity') as $item)<label class="mt-2 flex justify-between gap-3 text-sm"><span><input type="checkbox" wire:model.live="billItemIds" value="{{$item->id}}" class="mr-2 rounded text-orange-600">{{$item->product_name}}</span><b>{{number_format($item->line_total,2,',','.')}} Kz</b></label>@endforeach</div>
                @if($documentType==='FR')
                <div class="mt-4 rounded-2xl border border-slate-200 p-4"><div class="flex items-center justify-between gap-3"><div><b class="text-slate-900">Formas de pagamento</b><p class="text-xs text-slate-500">Divida o total por dois ou mais métodos.</p></div><button type="button" wire:click="toggleMultiPayment" class="rounded-xl px-4 py-2 text-sm font-black {{$multiPayment?'bg-slate-900 text-white':'bg-blue-50 text-blue-700'}}">{{$multiPayment?'Usar um método':'Pagamento múltiplo'}}</button></div>
                @if($multiPayment)<div class="mt-4 space-y-3">@foreach($payments as $i => $payment)<div wire:key="restaurant-payment-{{$i}}" class="grid grid-cols-[1fr_130px_42px] gap-2"><select wire:model="payments.{{$i}}.payment_method_id" class="min-w-0 rounded-xl border-slate-300"><option value="">Selecione</option>@foreach($paymentMethods as $method)<option value="{{$method->id}}">{{$method->name}}</option>@endforeach</select><input type="number" min="0.01" step=".01" wire:model.live.debounce.250ms="payments.{{$i}}.amount" class="rounded-xl border-slate-300 text-right font-black"><button type="button" wire:click="removePayment({{$i}})" class="rounded-xl bg-red-50 text-red-600" title="Remover"><i class="fas fa-trash"></i></button></div>@endforeach<button type="button" wire:click="addPayment" class="w-full rounded-xl border-2 border-dashed border-slate-300 p-3 text-sm font-black text-slate-600 hover:bg-slate-50"><i class="fas fa-plus mr-2"></i>Adicionar pagamento</button>
                <div class="flex items-center justify-between rounded-xl border-2 p-3 {{abs($this->paymentRemaining)<=.02?'border-emerald-300 bg-emerald-50':($this->paymentRemaining>0?'border-amber-300 bg-amber-50':'border-red-300 bg-red-50')}}"><span class="text-sm font-black {{abs($this->paymentRemaining)<=.02?'text-emerald-800':($this->paymentRemaining>0?'text-amber-800':'text-red-800')}}">{{abs($this->paymentRemaining)<=.02?'✓ Pagamentos somam o total':($this->paymentRemaining>0?'Falta distribuir':'Excede o total')}}</span><b class="{{abs($this->paymentRemaining)<=.02?'text-emerald-700':($this->paymentRemaining>0?'text-amber-700':'text-red-700')}}">{{number_format(abs($this->paymentRemaining),2,',','.')}} Kz</b></div></div>@endif</div>
                @endif
                <div class="mt-4 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"><i class="fas fa-circle-check text-xl text-emerald-600"></i><span>Ao confirmar, o documento fiscal será emitido, o valor entrará na Tesouraria e o ticket ficará pronto para imprimir.</span></div>
                @php($paymentInvalid = $documentType==='FR' && $multiPayment && (count($payments)<2 || abs($this->paymentRemaining)>.02))
                <button wire:click="checkout" wire:loading.attr="disabled" @disabled($paymentInvalid) class="mt-4 w-full rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 p-4 text-lg font-black text-white shadow-lg shadow-emerald-600/20 transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-40"><span wire:loading.remove wire:target="checkout"><i class="fas fa-cash-register mr-2"></i>Confirmar e emitir {{$documentType}}</span><span wire:loading wire:target="checkout"><i class="fas fa-spinner fa-spin mr-2"></i>A processar...</span></button>
                <button wire:click="$set('showCheckout',false)" class="mt-2 w-full p-2 text-sm font-bold text-slate-500">Voltar à comanda</button></div>
            </section>
        </div>
    @endif

    @if($showReservation)
        <div class="fixed inset-0 z-[105] grid place-items-center bg-slate-950/75 p-3 backdrop-blur-sm" role="dialog" aria-modal="true">
            <form wire:submit="saveReservation" class="max-h-[95vh] w-full max-w-xl overflow-y-auto rounded-3xl bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex items-start justify-between"><div><p class="text-xs font-black uppercase tracking-wider text-cyan-600">Reserva rápida</p><h2 class="text-2xl font-black text-slate-900">Reservar mesa</h2></div><button type="button" wire:click="$set('showReservation',false)" class="grid h-10 w-10 place-items-center rounded-xl bg-slate-100 font-black">×</button></div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="font-bold sm:col-span-2">Nome do cliente<input wire:model="reservationGuestName" class="mt-1 w-full rounded-xl border-slate-300 focus:border-cyan-500 focus:ring-cyan-500">@error('reservationGuestName')<span class="text-xs text-red-600">{{$message}}</span>@enderror</label>
                    <label class="font-bold">Telefone<input wire:model="reservationPhone" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="font-bold">Pessoas<input type="number" min="1" wire:model="reservationGuests" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="font-bold">Data e hora<input type="datetime-local" wire:model="reservationAt" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="font-bold">Duração<select wire:model="reservationDuration" class="mt-1 w-full rounded-xl border-slate-300"><option value="60">1 hora</option><option value="90">1h30</option><option value="120">2 horas</option><option value="180">3 horas</option></select></label>
                    <label class="font-bold sm:col-span-2">Observações<textarea wire:model="reservationNotes" rows="2" class="mt-1 w-full rounded-xl border-slate-300"></textarea></label>
                </div>
                <button class="mt-5 w-full rounded-2xl bg-cyan-600 p-4 text-lg font-black text-white hover:bg-cyan-700"><i class="fas fa-calendar-check mr-2"></i>Confirmar reserva</button>
            </form>
        </div>
    @endif

    @if($showQuickProduct)
        <div class="fixed inset-0 z-[106] grid place-items-center bg-slate-950/75 p-3 backdrop-blur-sm" role="dialog" aria-modal="true">
            <form wire:submit="createQuickProduct" class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex justify-between"><div><p class="text-xs font-black uppercase text-amber-600">Menu sem ficha técnica</p><h2 class="text-2xl font-black">Criar prato rápido</h2></div><button type="button" wire:click="$set('showQuickProduct',false)" class="h-10 w-10 rounded-xl bg-slate-100 font-black">×</button></div>
                <label class="mt-5 block font-bold">Nome do prato<input wire:model="quickProductName" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Ex.: Bitoque da casa">@error('quickProductName')<span class="text-xs text-red-600">{{$message}}</span>@enderror</label>
                <div class="mt-3 grid gap-3 sm:grid-cols-2"><label class="font-bold">Preço (Kz)<input type="number" min="0" step=".01" wire:model="quickProductPrice" class="mt-1 w-full rounded-xl border-slate-300"></label><label class="font-bold">IVA<select wire:model="quickProductTaxId" class="mt-1 w-full rounded-xl border-slate-300">@foreach($taxes as $tax)<option value="{{$tax->id}}">{{$tax->name}} · {{number_format((float)$tax->rate,2,',','.')}}%</option>@endforeach</select></label></div>
                <label class="mt-3 block font-bold">Categoria<select wire:model="quickProductCategoryId" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Sem categoria</option>@foreach($categories as $category)<option value="{{$category->id}}">{{$category->name}}</option>@endforeach</select></label>
                <label class="mt-3 flex items-center justify-between rounded-xl bg-slate-50 p-4 font-bold">Controlar stock do próprio prato<input type="checkbox" wire:model="quickProductManageStock" class="rounded text-orange-600"></label>
                <button class="mt-4 w-full rounded-2xl bg-amber-500 p-4 text-lg font-black text-white hover:bg-amber-600"><i class="fas fa-plus mr-2"></i>Criar e mostrar no POS</button>
            </form>
        </div>
    @endif

    @if($showPrintModal && $invoiceResultId)
        <div class="fixed inset-0 z-[110] grid place-items-center bg-slate-950/75 p-4"><section class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl"><div class="bg-emerald-600 p-7 text-center text-white"><i class="fas fa-check-circle text-5xl"></i><p class="mt-3 text-xs font-black uppercase">Documento emitido</p><h2 class="text-2xl font-black">{{$invoiceResult}}</h2></div><div class="p-5"><a target="_blank" href="{{route('restaurant.documents.print',['id'=>$invoiceResultId,'print'=>1])}}" class="flex w-full justify-center rounded-xl bg-slate-900 p-4 font-black text-white"><i class="fas fa-print mr-2 text-orange-300"></i>Imprimir ticket</a><button wire:click="$set('showPrintModal',false)" class="mt-3 w-full rounded-xl border border-slate-300 p-3 font-bold">Continuar no POS</button></div></section></div>
    @endif
</div>
