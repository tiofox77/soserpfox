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
                                <a target="_blank" href="{{ route('restaurant.orders.consultation-receipt', ['id' => $selectedOrder->id, 'print' => 1]) }}" class="rounded-xl bg-white px-4 py-3 font-black text-slate-700 shadow ring-1 ring-slate-200 hover:bg-slate-50"><i class="fas fa-print mr-2 text-orange-600"></i>Conta consulta</a>
                                <button wire:click="openCheckout" class="rounded-xl bg-emerald-600 px-5 py-3 font-black text-white shadow hover:bg-emerald-700"><i class="fas fa-cash-register mr-2"></i>Receber e faturar</button>
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

                    @if(in_array($selectedOrder->status, ['draft', 'confirmed', 'in_preparation', 'ready', 'served']))
                        <div class="border-b border-slate-100 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <div class="relative min-w-0 flex-1"><i class="fas fa-bowl-food absolute left-3 top-3.5 text-slate-400"></i><input wire:model.live.debounce.250ms="productSearch" class="w-full rounded-xl border-slate-300 py-3 pl-10 focus:border-orange-500 focus:ring-orange-500" placeholder="Pesquisar prato, bebida ou código"></div>
                                <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 font-bold text-slate-600">Qtd.<input type="number" step="0.001" min="0.001" wire:model="quantity" class="w-20 border-0 p-2 text-center font-black focus:ring-0" aria-label="Quantidade"></label>
                            </div>
                            @if($products->isNotEmpty())
                                <p class="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">Toque num artigo para adicionar imediatamente</p>
                                <div class="mt-2 grid max-h-72 grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3 lg:grid-cols-4">
                                    @foreach($products as $product)
                                        <button wire:click="quickAddProduct({{ $product->id }})" wire:loading.attr="disabled" wire:target="quickAddProduct({{ $product->id }})" class="min-h-20 rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:-translate-y-0.5 hover:border-orange-400 hover:bg-orange-50 disabled:opacity-50">
                                            <span class="block line-clamp-2 font-black text-slate-800">{{ $product->name }}</span><span class="mt-2 block whitespace-nowrap text-sm font-black text-orange-700">{{ number_format($product->price, 2, ',', '.') }} Kz</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            <details class="mt-3 rounded-xl border border-slate-200 bg-white p-3"><summary class="cursor-pointer text-sm font-bold text-slate-600">Observação para o próximo artigo</summary><input wire:model="itemNotes" class="mt-2 w-full rounded-xl border-slate-300 focus:border-orange-500 focus:ring-orange-500" placeholder="Ex.: sem cebola, bem passado"></details>
                        </div>
                    @endif

                    <div class="divide-y divide-slate-100">
                        @forelse($selectedOrder->items as $item)
                            <article class="flex items-center gap-4 p-4 sm:p-5">
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-orange-100 font-black text-orange-700">{{ number_format($item->quantity, 0) }}x</span>
                                <div class="min-w-0 flex-1"><h3 class="truncate font-black text-slate-900">{{ $item->product_name }}</h3><p class="text-sm text-slate-500">{{ number_format($item->unit_price, 2, ',', '.') }} Kz · IVA {{ number_format($item->tax_rate, 0) }}%</p>@if($item->notes)<p class="mt-1 text-xs font-semibold text-orange-700">{{ $item->notes }}</p>@endif</div>
                                <div class="text-right"><p class="font-black text-slate-900">{{ number_format($item->line_total, 2, ',', '.') }} Kz</p><p class="text-xs font-bold uppercase text-violet-600">{{ $item->kitchen_status }}</p></div>
                                        @if($item->kitchen_status === 'draft')
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
                @include('livewire.restaurant.partials.shift-required-modal')

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

    @if($showPrintModal && $invoiceResultId)
        <div class="fixed inset-0 z-[110] grid place-items-center bg-slate-950/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="print-result-title">
            <section class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl">
                <div class="bg-emerald-600 px-6 py-7 text-center text-white">
                    <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-white/20 text-3xl"><i class="fas fa-check"></i></span>
                    <p class="mt-4 text-sm font-black uppercase tracking-wider text-emerald-100">Documento emitido</p>
                    <h2 id="print-result-title" class="mt-1 text-2xl font-black">{{ $invoiceResult }}</h2>
                </div>
                <div class="p-6">
                    <p class="text-center text-slate-600">A venda foi concluída. Pode imprimir agora ou voltar à sala.</p>
                    <a target="_blank" href="{{ route('restaurant.documents.print', ['id' => $invoiceResultId, 'print' => 1]) }}" class="mt-5 flex w-full items-center justify-center rounded-2xl bg-slate-900 px-5 py-4 text-lg font-black text-white shadow hover:bg-slate-800"><i class="fas fa-print mr-3 text-orange-300"></i>Imprimir ticket {{ str_starts_with($invoiceResult, 'FR') ? 'FR' : '' }}</a>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <button wire:click="$set('showPrintModal', false)" class="rounded-xl border border-slate-300 px-4 py-3 font-bold text-slate-700 hover:bg-slate-50">Continuar</button>
                        <a href="{{ route('restaurant.floor') }}" class="rounded-xl bg-orange-600 px-4 py-3 text-center font-bold text-white hover:bg-orange-700">Voltar à sala</a>
                    </div>
                </div>
            </section>
        </div>
    @endif
</div>
