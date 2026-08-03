<div class="min-h-screen bg-slate-50 p-4 sm:p-6">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="overflow-hidden rounded-3xl bg-gradient-to-r from-orange-600 via-amber-500 to-yellow-400 p-6 text-white shadow-xl sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-orange-100">Operação em tempo real</p>
                    <h1 class="mt-2 text-3xl font-black sm:text-4xl">Restaurante</h1>
                    <p class="mt-2 max-w-2xl text-orange-50">Sala, comandas e preparação ligadas à Facturação e Tesouraria.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('restaurant.floor') }}" class="rounded-xl bg-white px-5 py-3 font-bold text-orange-700 shadow transition hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-white/40">
                        <i class="fas fa-chair mr-2"></i>Sala e mesas
                    </a>
                    <a href="{{ route('restaurant.orders') }}" class="rounded-xl border border-white/40 bg-orange-900/20 px-5 py-3 font-bold text-white transition hover:bg-orange-900/30 focus:outline-none focus:ring-4 focus:ring-white/30">
                        <i class="fas fa-receipt mr-2"></i>Comandas
                    </a>
                </div>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-slate-500">Mesas</span>
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-100 text-blue-700"><i class="fas fa-chair"></i></span>
                </div>
                <p class="mt-3 text-3xl font-black text-slate-900">{{ $tablesTotal }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-slate-500">Ocupadas</span>
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-orange-100 text-orange-700"><i class="fas fa-users"></i></span>
                </div>
                <p class="mt-3 text-3xl font-black text-slate-900">{{ $tablesOccupied }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-slate-500">Comandas abertas</span>
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-violet-100 text-violet-700"><i class="fas fa-clipboard-list"></i></span>
                </div>
                <p class="mt-3 text-3xl font-black text-slate-900">{{ $openOrders }}</p>
            </article>
            <article class="col-span-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm lg:col-span-1">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-emerald-700">Consumo hoje</span>
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-600 text-white"><i class="fas fa-coins"></i></span>
                </div>
                <p class="mt-3 text-2xl font-black text-emerald-900">{{ number_format($todaySales, 2, ',', '.') }} Kz</p>
                <p class="mt-1 text-xs text-emerald-700">Comandas não canceladas; faturação no checkout.</p>
            </article>
        </section>

        <section class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="font-black text-slate-900">Comandas recentes</h2>
                        <p class="text-sm text-slate-500">Última atividade da sala</p>
                    </div>
                    <a href="{{ route('restaurant.orders') }}" class="text-sm font-bold text-orange-600 hover:text-orange-700">Ver todas</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($recentOrders as $order)
                        <a href="{{ route('restaurant.orders', ['order' => $order->id]) }}" class="flex items-center gap-4 px-5 py-4 transition hover:bg-orange-50">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-700"><i class="fas fa-receipt"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-bold text-slate-900">{{ $order->order_number }}</span>
                                <span class="block text-sm text-slate-500">{{ $order->table?->name ?? ucfirst($order->channel) }} · {{ $order->waiter?->name ?? 'Sistema' }}</span>
                            </span>
                            <span class="text-right">
                                <span class="block font-black text-slate-900">{{ number_format($order->grand_total, 2, ',', '.') }} Kz</span>
                                <span class="text-xs font-semibold uppercase text-slate-500">{{ str_replace('_', ' ', $order->status) }}</span>
                            </span>
                        </a>
                    @empty
                        <div class="px-5 py-12 text-center text-slate-500">
                            <i class="fas fa-utensils mb-3 text-4xl text-slate-300"></i>
                            <p>Ainda não existem comandas.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <aside class="rounded-2xl bg-slate-900 p-5 text-white shadow-xl">
                <h2 class="font-black">Estado da sala</h2>
                <p class="mt-1 text-sm text-slate-400">Distribuição atual das mesas</p>
                <div class="mt-5 space-y-3">
                    @foreach(\App\Models\Restaurant\DiningTable::STATUSES as $key => $label)
                        @php($count = (int) ($statusCounts[$key] ?? 0))
                        <div class="flex items-center justify-between rounded-xl bg-white/5 px-4 py-3">
                            <span class="text-sm text-slate-200">{{ $label }}</span>
                            <span class="rounded-lg bg-white/10 px-2.5 py-1 text-sm font-black">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </aside>
        </section>
    </div>
</div>

