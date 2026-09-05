<div class="min-h-screen bg-slate-50 p-4 sm:p-6">
    <div class="mx-auto max-w-7xl space-y-5">
        <header class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a href="{{ route('restaurant.dashboard') }}" class="text-sm font-bold text-orange-600 hover:text-orange-700"><i class="fas fa-arrow-left mr-1"></i>Restaurante</a>
                <h1 class="mt-2 text-3xl font-black text-slate-900">Sala e mesas</h1>
                <p class="mt-1 text-slate-500">Toque numa mesa livre para abrir atendimento.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <label class="block">
                    <span class="sr-only">Estabelecimento</span>
                    <select wire:model.live="venueId" class="w-full rounded-xl border-slate-300 bg-white px-4 py-3 font-semibold focus:border-orange-500 focus:ring-orange-500 sm:w-64">
                        @foreach($venues as $venue)<option value="{{ $venue->id }}">{{ $venue->name }}</option>@endforeach
                    </select>
                </label>
                <button wire:click="$set('showTableForm', true)" class="rounded-xl bg-slate-900 px-5 py-3 font-bold text-white shadow transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300">
                    <i class="fas fa-plus mr-2"></i>Nova mesa
                </button>
            </div>
        </header>

        {{-- ============ PEDIDOS DA CARTA ONLINE ============
             Clientes que pediram pelo telemóvel e ainda ninguém atendeu.

             Aparece AQUI, no ecrã da sala, e não numa página à parte: um
             pedido que o empregado tem de ir procurar noutro sítio é um
             pedido que fica por atender. Aceitar abre a comanda com o turno
             de quem aceitou — é aí que a venda passa a existir. --}}
        @if($pedidosDoMenu->count())
            <section class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-5">
                <div class="flex items-center gap-2">
                    <i class="fas fa-mobile-screen text-amber-600"></i>
                    <h2 class="font-black text-amber-900">
                        {{ trans_choice(':n pedido da carta online|:n pedidos da carta online', $pedidosDoMenu->count(), ['n' => $pedidosDoMenu->count()]) }}
                    </h2>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($pedidosDoMenu as $pedido)
                        <div class="rounded-2xl bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-black text-slate-800">
                                        {{ $pedido->mesa?->name ?: ($pedido->table_code ?: __('Sem mesa')) }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ $pedido->created_at->diffForHumans() }}
                                        @if($pedido->customer_name) · {{ $pedido->customer_name }} @endif
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">
                                    {{ number_format((float) $pedido->estimated_total, 2, ',', '.') }} Kz
                                </span>
                            </div>

                            <ul class="mt-3 space-y-0.5 text-sm text-slate-700">
                                @foreach($pedido->items as $linha)
                                    <li>{{ $linha['quantity'] }}× {{ $linha['name'] }}</li>
                                @endforeach
                            </ul>

                            @if($pedido->notes)
                                <p class="mt-2 rounded-lg bg-slate-50 px-2 py-1.5 text-xs italic text-slate-600">{{ $pedido->notes }}</p>
                            @endif

                            <div class="mt-4 flex gap-2">
                                <button wire:click="descartarPedidoDoMenu({{ $pedido->id }})"
                                        class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-bold text-slate-600 hover:bg-slate-200">
                                    {{ __('Descartar') }}
                                </button>
                                <button wire:click="aceitarPedidoDoMenu({{ $pedido->id }})"
                                        class="flex-1 rounded-xl bg-orange-600 px-3 py-2 text-sm font-black text-white hover:bg-orange-700">
                                    {{ __('Aceitar e abrir comanda') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <nav class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Zonas da sala">
            @foreach($areas as $area)
                <button wire:click="$set('areaId', {{ $area->id }})" class="whitespace-nowrap rounded-xl px-5 py-2.5 text-sm font-bold transition {{ $areaId === $area->id ? 'bg-orange-600 text-white shadow' : 'text-slate-600 hover:bg-orange-50 hover:text-orange-700' }}">
                    {{ $area->name }}
                </button>
            @endforeach
        </nav>

        <main class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6">
            @forelse($tables as $table)
                @php
                    $styles = match($table->status) {
                        'available' => 'border-emerald-200 bg-emerald-50 text-emerald-900 hover:border-emerald-400',
                        'occupied' => 'border-orange-200 bg-orange-50 text-orange-900 hover:border-orange-400',
                        'waiting_kitchen' => 'border-violet-200 bg-violet-50 text-violet-900 hover:border-violet-400',
                        'served' => 'border-blue-200 bg-blue-50 text-blue-900 hover:border-blue-400',
                        'billing' => 'border-amber-300 bg-amber-50 text-amber-900 hover:border-amber-500',
                        'reserved' => 'border-cyan-200 bg-cyan-50 text-cyan-900',
                        default => 'border-slate-200 bg-slate-100 text-slate-700',
                    };
                @endphp
                <button
                    @if($table->status === 'cleaning')
                        wire:click="markTableClean({{ $table->id }})"
                        wire:confirm="Confirmar que a mesa já está limpa?"
                    @else
                        wire:click="prepareOpenOrder({{ $table->id }})"
                    @endif
                    class="group min-h-40 rounded-2xl border-2 p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-orange-200 {{ $styles }}">
                    <div class="flex items-start justify-between gap-2">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-white/70 text-lg shadow-sm"><i class="fas fa-chair"></i></span>
                        <span class="rounded-full bg-white/70 px-2.5 py-1 text-[11px] font-black uppercase tracking-wide">{{ $table->status_label }}</span>
                    </div>
                    <p class="mt-5 text-xl font-black">{{ $table->name }}</p>
                    <p class="mt-1 text-sm opacity-75"><i class="fas fa-user-group mr-1"></i>{{ $table->capacity }} lugares</p>
                    @if($table->activeOrder)
                        <p class="mt-2 truncate text-xs font-bold">{{ $table->activeOrder->order_number }}</p>
                    @elseif($table->status === 'cleaning')
                        <p class="mt-2 text-xs font-black"><i class="fas fa-sparkles mr-1"></i>Toque para marcar limpa</p>
                    @endif
                </button>
            @empty
                <div class="col-span-full rounded-3xl border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                    <i class="fas fa-chair text-5xl text-slate-300"></i>
                    <h2 class="mt-4 text-xl font-black text-slate-800">Crie a primeira mesa</h2>
                    <p class="mt-2 text-slate-500">As mesas aparecerão aqui organizadas por zona.</p>
                </div>
            @endforelse
        </main>
    </div>

    @if($showTableForm)
        <div class="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="new-table-title">
            <form wire:submit="createTable" class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h2 id="new-table-title" class="text-2xl font-black text-slate-900">Nova mesa</h2>
                    <button type="button" wire:click="$set('showTableForm', false)" class="grid h-10 w-10 place-items-center rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200" aria-label="Fechar"><i class="fas fa-times"></i></button>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <label class="block"><span class="mb-1 block text-sm font-bold text-slate-700">Código</span><input wire:model="tableCode" class="w-full rounded-xl border-slate-300 focus:border-orange-500 focus:ring-orange-500" placeholder="M01">@error('tableCode')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                    <label class="block"><span class="mb-1 block text-sm font-bold text-slate-700">Nome</span><input wire:model="tableName" class="w-full rounded-xl border-slate-300 focus:border-orange-500 focus:ring-orange-500" placeholder="Mesa 01">@error('tableName')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                    <label class="block sm:col-span-2"><span class="mb-1 block text-sm font-bold text-slate-700">Zona</span><select wire:model="areaId" class="w-full rounded-xl border-slate-300 focus:border-orange-500 focus:ring-orange-500">@foreach($areas as $area)<option value="{{ $area->id }}">{{ $area->name }}</option>@endforeach</select></label>
                    <label class="block sm:col-span-2"><span class="mb-1 block text-sm font-bold text-slate-700">Capacidade</span><input type="number" wire:model="capacity" min="1" max="50" class="w-full rounded-xl border-slate-300 focus:border-orange-500 focus:ring-orange-500"></label>
                </div>
                <button class="mt-6 w-full rounded-xl bg-orange-600 px-5 py-3.5 font-black text-white shadow-lg shadow-orange-200 transition hover:bg-orange-700 disabled:opacity-60" wire:loading.attr="disabled">
                    <span wire:loading.remove>Criar mesa</span><span wire:loading><i class="fas fa-spinner fa-spin mr-2"></i>A guardar</span>
                </button>
            </form>
        </div>
    @endif

    @if($showOpenOrder)
        <div class="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="open-order-title">
            <form wire:submit="openOrder" class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h2 id="open-order-title" class="text-2xl font-black text-slate-900">Abrir comanda</h2>
                    <button type="button" wire:click="$set('showOpenOrder', false)" class="grid h-10 w-10 place-items-center rounded-full bg-slate-100" aria-label="Fechar"><i class="fas fa-times"></i></button>
                </div>
                <label class="mt-6 block"><span class="mb-1 block text-sm font-bold text-slate-700">Número de pessoas</span><input type="number" wire:model="guestCount" min="1" max="100" class="w-full rounded-xl border-slate-300 focus:border-orange-500 focus:ring-orange-500"></label>
                <label class="mt-4 block"><span class="mb-1 block text-sm font-bold text-slate-700">Observações</span><textarea wire:model="notes" rows="3" class="w-full rounded-xl border-slate-300 focus:border-orange-500 focus:ring-orange-500" placeholder="Preferências ou informação da mesa"></textarea></label>
                <button class="mt-6 w-full rounded-xl bg-emerald-600 px-5 py-3.5 font-black text-white shadow-lg shadow-emerald-200 transition hover:bg-emerald-700">Abrir atendimento</button>
            </form>
        </div>
    @endif
</div>
