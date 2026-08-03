<div class="min-h-screen bg-slate-100 p-4 lg:p-6" wire:poll.15s>
    <div class="mx-auto max-w-7xl">
        <header class="mb-5 flex flex-col gap-4 rounded-3xl bg-slate-950 p-6 text-white shadow-xl md:flex-row md:items-center md:justify-between">
            <div>
                <a href="{{ route('restaurant.dashboard') }}" class="text-sm font-bold text-orange-400">← Restaurante</a>
                <h1 class="mt-2 text-3xl font-black">Cozinha / KDS</h1>
                <p class="mt-1 text-slate-400">Fila de preparação atualizada automaticamente.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button wire:click="$set('stationId', null)" class="rounded-xl px-4 py-2 text-sm font-bold {{ !$stationId ? 'bg-orange-500 text-white' : 'bg-slate-800 text-slate-300' }}">Todas</button>
                @foreach($stations as $station)
                    <button wire:click="$set('stationId', {{ $station->id }})" class="rounded-xl px-4 py-2 text-sm font-bold {{ $stationId === $station->id ? 'bg-orange-500 text-white' : 'bg-slate-800 text-slate-300' }}">{{ $station->name }}</button>
                @endforeach
            </div>
        </header>

        @if(session('success')) <div class="mb-4 rounded-2xl bg-emerald-100 p-4 font-bold text-emerald-800">{{ session('success') }}</div> @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($tickets as $ticket)
                @php
                    $minutes = (int) floor($ticket->queued_at->diffInMinutes(now()));
                    $next = ['queued' => ['Aceitar', 'bg-blue-600'], 'accepted' => ['Começar', 'bg-amber-500'], 'preparing' => ['Marcar pronto', 'bg-emerald-600'], 'ready' => ['Entregue', 'bg-violet-600']][$ticket->status];
                @endphp
                <article class="overflow-hidden rounded-3xl border-2 {{ $minutes >= 20 ? 'border-red-400' : 'border-transparent' }} bg-white shadow-lg">
                    <div class="flex items-start justify-between bg-slate-900 p-5 text-white">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-orange-400">{{ $ticket->station->name }}</p>
                            <h2 class="mt-1 text-xl font-black">{{ $ticket->order->table?->name ?? ucfirst($ticket->order->channel) }}</h2>
                            <p class="text-sm text-slate-400">{{ $ticket->order->order_number }}</p>
                        </div>
                        <div class="text-right"><p class="text-2xl font-black {{ $minutes >= 20 ? 'text-red-400' : 'text-white' }}">{{ $minutes }}m</p><p class="text-xs uppercase text-slate-400">{{ $ticket->status }}</p></div>
                    </div>
                    <div class="space-y-3 p-5">
                        @foreach($ticket->items as $ticketItem)
                            <div class="flex gap-3 border-b border-slate-100 pb-3 last:border-0">
                                <span class="rounded-lg bg-orange-100 px-2 py-1 font-black text-orange-700">{{ rtrim(rtrim(number_format((float)$ticketItem->orderItem->quantity, 2, ',', '.'), '0'), ',') }}x</span>
                                <div><p class="font-black text-slate-900">{{ $ticketItem->orderItem->product_name }}</p>@if($ticketItem->orderItem->notes)<p class="text-sm font-semibold text-amber-700">{{ $ticketItem->orderItem->notes }}</p>@endif</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mx-5 mb-2 flex gap-2"><a target="_blank" href="{{route('restaurant.kitchen.print',$ticket)}}" class="flex-1 rounded-xl bg-slate-100 p-2 text-center font-bold text-slate-700">Imprimir</a><a target="_blank" href="{{route('restaurant.kitchen.print',['ticket'=>$ticket,'copy'=>1])}}" class="flex-1 rounded-xl bg-slate-100 p-2 text-center font-bold text-slate-700">Reimprimir cópia</a></div>
                    <button wire:click="advance({{ $ticket->id }})" wire:confirm="Confirmar esta etapa?" class="m-5 mt-0 w-[calc(100%-2.5rem)] rounded-2xl {{ $next[1] }} px-4 py-4 text-lg font-black text-white shadow">{{ $next[0] }}</button>
                </article>
            @empty
                <div class="col-span-full rounded-3xl bg-white p-12 text-center shadow"><i class="fas fa-circle-check text-5xl text-emerald-500"></i><h2 class="mt-4 text-2xl font-black text-slate-900">Fila concluída</h2><p class="text-slate-500">Não existem pedidos pendentes nesta estação.</p></div>
            @endforelse
        </div>
    </div>
</div>
