<div class="min-h-screen bg-slate-100 p-3 sm:p-5">
    <div class="mx-auto grid max-w-[1600px] gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
        <aside class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-4">
                <a href="{{ route('restaurant.floor') }}" class="text-sm font-bold text-orange-600"><i class="fas fa-arrow-left mr-1"></i>Sala</a>
                <div class="mt-3 flex items-center justify-between"><h1 class="text-2xl font-black text-slate-900">Comandas</h1><span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{{ $orders->total() }}</span></div>
                <div class="relative mt-3"><i class="fas fa-search absolute left-3 top-3.5 text-slate-400"></i><input wire:model.live.debounce.300ms="search" class="w-full rounded-xl border-slate-300 py-3 pl-10 focus:border-orange-500 focus:ring-orange-500" placeholder="Número ou mesa"></div>
            </div>
            <div class="max-h-[68vh] divide-y divide-slate-100 overflow-y-auto">
                @forelse($orders as $row)
                    <button wire:click="selectOrder({{ $row->id }})" class="w-full p-4 text-left transition hover:bg-orange-50 {{ $order === $row->id ? 'bg-orange-50 ring-1 ring-inset ring-orange-200' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <span><span class="block font-black text-slate-900">{{ $row->order_number }}</span><span class="mt-1 block text-sm text-slate-500">{{ $row->table?->name ?? ucfirst($row->channel) }}</span></span>
                            <span class="text-right"><span class="block font-black text-slate-900">{{ number_format($row->grand_total, 2, ',', '.') }} Kz</span><span class="text-[11px] font-bold uppercase text-orange-600">{{ str_replace('_', ' ', $row->status) }}</span></span>
                        </div>
                    </button>
                @empty
                    <div class="p-10 text-center text-slate-500"><i class="fas fa-receipt mb-3 text-4xl text-slate-300"></i><p>Sem comandas.</p></div>
                @endforelse
            </div>
            <div class="border-t border-slate-100 p-3">{{ $orders->links() }}</div>
        </aside>

        <main class="min-w-0">
            @if($selectedOrder)
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="text-sm font-bold text-orange-600">{{ $selectedOrder->table?->name ?? ucfirst($selectedOrder->channel) }}</p><h2 class="text-2xl font-black text-slate-900">{{ $selectedOrder->order_number }}</h2><p class="mt-1 text-sm text-slate-500">{{ $selectedOrder->guest_count }} pessoa(s) · {{ $selectedOrder->waiter?->name ?? 'Sistema' }}</p></div>
                        <div class="flex flex-wrap gap-2">
                            @if($selectedOrder->status === 'draft')
                                <button wire:click="confirmOrder" wire:confirm="Enviar esta comanda para preparação?" class="rounded-xl bg-violet-600 px-4 py-3 font-black text-white shadow hover:bg-violet-700"><i class="fas fa-fire-burner mr-2"></i>Enviar à cozinha</button>
                            @else
                                <span class="rounded-xl bg-violet-100 px-4 py-3 font-bold text-violet-800"><i class="fas fa-circle-check mr-2"></i>{{ str_replace('_', ' ', $selectedOrder->status) }}</span>
                            @endif
                            @if(in_array($selectedOrder->status, ['ready', 'served', 'partially_billed']))
                                <button wire:click="openCheckout" class="rounded-xl bg-emerald-600 px-4 py-3 font-black text-white shadow hover:bg-emerald-700"><i class="fas fa-cash-register mr-2"></i>Pedir conta</button>
                            @endif
                            @if($selectedOrder->status === 'billed' && $selectedOrder->table_id && $selectedOrder->table?->status === 'cleaning')
                                <button wire:click="releaseTable" wire:confirm="Confirmar que a mesa está limpa?" class="rounded-xl bg-cyan-600 px-4 py-3 font-black text-white shadow hover:bg-cyan-700"><i class="fas fa-sparkles mr-2"></i>Mesa limpa</button>
                            @endif
                        </div>
                    </header>

                    @if(in_array($selectedOrder->status, \App\Models\Restaurant\Order::OPEN_STATUSES, true))
                        <div class="grid gap-3 border-b border-slate-100 bg-white p-4 lg:grid-cols-2">
                            <div class="flex gap-2"><select wire:model="targetTableId" class="min-w-0 flex-1 rounded-xl border-slate-300"><option value="">Transferir para mesa...</option>@foreach($availableTables as $table)<option value="{{$table->id}}">{{$table->name}} ({{$table->capacity}} lugares)</option>@endforeach</select><button wire:click="transferTable" wire:confirm="Transferir esta comanda?" class="rounded-xl bg-cyan-600 px-4 font-black text-white disabled:opacity-40" @disabled($availableTables->isEmpty())><i class="fas fa-right-left"></i></button></div>
                            <div class="flex gap-2"><select wire:model="targetOrderId" class="min-w-0 flex-1 rounded-xl border-slate-300"><option value="">Juntar noutra comanda...</option>@foreach($mergeOrders as $merge)<option value="{{$merge->id}}">{{$merge->order_number}} · {{$merge->table?->name ?? ucfirst($merge->channel)}}</option>@endforeach</select><button wire:click="mergeOrder" wire:confirm="Juntar todos os artigos nesta comanda?" class="rounded-xl bg-indigo-600 px-4 font-black text-white disabled:opacity-40" @disabled($mergeOrders->isEmpty())><i class="fas fa-code-merge"></i></button></div>
                        </div>
                    @endif

                    @if(in_array($selectedOrder->status, ['draft', 'confirmed']))
                        <div class="border-b border-slate-100 bg-slate-50 p-4">
                            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_110px]">
                                <div class="relative"><i class="fas fa-bowl-food absolute left-3 top-3.5 text-slate-400"></i><input wire:model.live.debounce.300ms="productSearch" class="w-full rounded-xl border-slate-300 py-3 pl-10 focus:border-orange-500 focus:ring-orange-500" placeholder="Pesquisar artigo (mínimo 2 letras)"></div>
                                <input type="number" step="0.001" min="0.001" wire:model="quantity" class="rounded-xl border-slate-300 focus:border-orange-500 focus:ring-orange-500" aria-label="Quantidade">
                            </div>
                            @if($products->isNotEmpty())
                                <div class="mt-2 grid max-h-48 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach($products as $product)
                                        <button wire:click="$set('selectedProductId', {{ $product->id }})" class="flex items-center justify-between rounded-xl border p-3 text-left transition {{ $selectedProductId === $product->id ? 'border-orange-500 bg-orange-50' : 'border-slate-200 bg-white hover:border-orange-300' }}">
                                            <span class="truncate font-bold text-slate-800">{{ $product->name }}</span><span class="ml-2 whitespace-nowrap text-sm font-black text-orange-700">{{ number_format($product->price, 2, ',', '.') }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            @if($selectedProductId)
                                <div class="mt-3 flex flex-col gap-2 sm:flex-row"><input wire:model="itemNotes" class="min-w-0 flex-1 rounded-xl border-slate-300 focus:border-orange-500 focus:ring-orange-500" placeholder="Observações do artigo"><button wire:click="addItem" class="rounded-xl bg-orange-600 px-5 py-3 font-black text-white shadow hover:bg-orange-700"><i class="fas fa-plus mr-2"></i>Adicionar</button></div>
                            @endif
                        </div>
                    @endif

                    <div class="divide-y divide-slate-100">
                        @forelse($selectedOrder->items as $item)
                            <article class="flex items-center gap-4 p-4 sm:p-5">
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-orange-100 font-black text-orange-700">{{ number_format($item->quantity, 0) }}x</span>
                                <div class="min-w-0 flex-1"><h3 class="truncate font-black text-slate-900">{{ $item->product_name }}</h3><p class="text-sm text-slate-500">{{ number_format($item->unit_price, 2, ',', '.') }} Kz · IVA {{ number_format($item->tax_rate, 0) }}%</p>@if($item->notes)<p class="mt-1 text-xs font-semibold text-orange-700">{{ $item->notes }}</p>@endif</div>
                                <div class="text-right"><p class="font-black text-slate-900">{{ number_format($item->line_total, 2, ',', '.') }} Kz</p><p class="text-xs font-bold uppercase text-violet-600">{{ $item->kitchen_status }}</p></div>
                                @if($selectedOrder->status === 'draft' && $item->kitchen_status === 'draft')
                                    <button wire:click="removeItem({{ $item->id }})" wire:confirm="Remover este artigo?" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-red-50 text-red-600 hover:bg-red-100" aria-label="Remover artigo"><i class="fas fa-trash"></i></button>
                                @elseif(in_array($item->kitchen_status, ['queued','accepted','preparing','ready'], true))
                                    <button wire:click="voidProducedItem({{ $item->id }})" wire:confirm="Anular este artigo produzido? O motivo escrito abaixo será auditado." class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-red-50 text-red-600 hover:bg-red-100" aria-label="Anular artigo produzido"><i class="fas fa-ban"></i></button>
                                @endif
                            </article>
                        @empty
                            <div class="px-6 py-16 text-center text-slate-500"><i class="fas fa-bowl-food mb-3 text-5xl text-slate-300"></i><h3 class="font-black text-slate-800">Comanda vazia</h3><p class="mt-1">Pesquise e adicione o primeiro artigo.</p></div>
                        @endforelse
                    </div>
                    @if($selectedOrder->items->contains(fn($item) => in_array($item->kitchen_status, ['queued','accepted','preparing','ready'], true)))
                        <div class="border-t border-red-100 bg-red-50 p-4"><label class="text-sm font-black text-red-800">Motivo para anulação após envio à cozinha<input wire:model="voidReason" maxlength="500" class="mt-2 w-full rounded-xl border-red-200 bg-white focus:border-red-500 focus:ring-red-500" placeholder="Ex.: pedido cancelado pelo cliente"></label>@error('voidReason')<p class="mt-1 text-xs font-bold text-red-600">{{$message}}</p>@enderror</div>
                    @endif
                    <footer class="grid gap-2 border-t border-slate-200 bg-slate-900 p-5 text-white sm:grid-cols-4">
                        <div><p class="text-xs uppercase text-slate-400">Subtotal</p><p class="font-black">{{ number_format($selectedOrder->subtotal, 2, ',', '.') }} Kz</p></div>
                        <div><p class="text-xs uppercase text-slate-400">Desconto</p><p class="font-black">{{ number_format($selectedOrder->discount_total, 2, ',', '.') }} Kz</p></div>
                        <div><p class="text-xs uppercase text-slate-400">Imposto</p><p class="font-black">{{ number_format($selectedOrder->tax_total, 2, ',', '.') }} Kz</p></div>
                        <div class="sm:text-right"><p class="text-xs uppercase text-orange-300">Total</p><p class="text-2xl font-black text-orange-300">{{ number_format($selectedOrder->grand_total, 2, ',', '.') }} Kz</p></div>
                    </footer>
                </section>
                @if($showCheckout)
                    <div class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/70 p-4 backdrop-blur-sm">
                        <section class="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl">
                            <div class="flex items-start justify-between"><div><p class="text-sm font-black uppercase text-orange-600">Fecho fiscal</p><h2 class="text-2xl font-black text-slate-900">Faturar {{ $selectedOrder->order_number }}</h2></div><button wire:click="$set('showCheckout', false)" class="h-10 w-10 rounded-xl bg-slate-100 font-black">×</button></div>
                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                <label class="font-bold text-slate-700">Documento<select wire:model.live="documentType" class="mt-2 w-full rounded-xl border-slate-300"><option value="FR">FR — pago no ato</option><option value="FT">FT — venda a crédito</option></select></label>
                                <label class="font-bold text-slate-700">Cliente<select wire:model="clientId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Consumidor Final</option>@foreach($clients as $client)<option value="{{ $client->id }}">{{ $client->name }} · {{ $client->nif }}</option>@endforeach</select></label>
                                @if($documentType === 'FR')
                                    <label class="font-bold text-slate-700 sm:col-span-2">Método de pagamento<select wire:model="paymentMethodId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Selecione</option>@foreach($paymentMethods as $method)<option value="{{ $method->id }}">{{ $method->name }}</option>@endforeach</select></label>
                                @endif
                            </div>
                            <div class="mt-5 rounded-2xl border border-slate-200 p-4"><p class="font-black">Itens desta conta</p>@foreach($selectedOrder->items->where('billed_quantity','<','quantity') as $item)<label class="mt-2 flex items-center justify-between"><span><input type="checkbox" wire:model.live="billItemIds" value="{{$item->id}}" class="mr-2 rounded text-orange-600">{{$item->product_name}}</span><b>{{number_format($item->line_total,2,',','.')}} Kz</b></label>@endforeach</div>
                            @if($documentType==='FR')<div class="mt-4 grid gap-3 sm:grid-cols-2"><label class="font-bold">Segundo método (opcional)<select wire:model="secondPaymentMethodId" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Nenhum</option>@foreach($paymentMethods as $method)<option value="{{$method->id}}">{{$method->name}}</option>@endforeach</select></label><label class="font-bold">Valor no segundo método<input type="number" step=".01" min="0" max="{{$this->checkoutTotal}}" wire:model.live="secondPaymentAmount" class="mt-1 w-full rounded-xl border-slate-300"></label></div>@endif
                            <div class="mt-6 flex items-center justify-between rounded-2xl bg-slate-900 p-5 text-white"><span class="font-bold">Total a faturar</span><strong class="text-2xl text-orange-300">{{ number_format($this->checkoutTotal, 2, ',', '.') }} Kz</strong></div>
                            <button wire:click="checkout" wire:loading.attr="disabled" wire:confirm="Emitir este documento fiscal? Esta operação não pode ser desfeita." class="mt-5 w-full rounded-2xl bg-emerald-600 px-5 py-4 text-lg font-black text-white shadow hover:bg-emerald-700"><span wire:loading.remove wire:target="checkout">Emitir {{ $documentType }}</span><span wire:loading wire:target="checkout">A emitir...</span></button>
                        </section>
                    </div>
                @endif
            @else
                <section class="grid min-h-[70vh] place-items-center rounded-3xl border-2 border-dashed border-slate-300 bg-white text-center">
                    <div class="max-w-sm p-8"><span class="mx-auto grid h-20 w-20 place-items-center rounded-3xl bg-orange-100 text-3xl text-orange-600"><i class="fas fa-receipt"></i></span><h2 class="mt-5 text-2xl font-black text-slate-900">Seleccione uma comanda</h2><p class="mt-2 text-slate-500">Ou abra uma nova mesa no mapa da sala.</p><a href="{{ route('restaurant.floor') }}" class="mt-5 inline-flex rounded-xl bg-orange-600 px-5 py-3 font-black text-white">Abrir sala</a></div>
                </section>
            @endif
        </main>
    </div>
</div>
