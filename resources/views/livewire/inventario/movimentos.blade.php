<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-amber-600 to-yellow-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-right-left text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Movimentos de Stock</h2>
                    <p class="text-amber-100 text-sm">A resposta a «porque é que o stock mudou?» — tudo passa por aqui</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-amber-100 text-xs font-semibold uppercase">No período</p>
                <p class="text-2xl font-bold">{{ number_format($resumo['movimentos'], 0, ',', '.') }}</p>
                <p class="text-amber-100 text-xs">{{ $resumo['entradas'] }} entradas · {{ $resumo['saidas'] }} saídas</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex flex-wrap gap-3">
            <div class="relative flex-1 min-w-56">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
                <input wire:model.live.debounce.300ms="procurar" type="text" placeholder="Procurar artigo..."
                       class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all">
            </div>
            <select wire:model.live="tipo" class="px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 transition-all">
                <option value="todos">Todos os tipos</option>
                <option value="in">Entradas</option>
                <option value="out">Saídas</option>
                <option value="transfer">Transferências</option>
                <option value="adjustment">Ajustes / contagens</option>
            </select>
            <select wire:model.live="armazemId" class="px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 transition-all">
                <option value="">Todos os armazéns</option>
                @foreach($armazens as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
            </select>
            <input wire:model.live="de" type="date" class="px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 transition-all">
            <input wire:model.live="ate" type="date" class="px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 transition-all">
        </div>
    </div>

    <!-- Lista -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <i class="fas fa-list mr-2 text-amber-600"></i>Histórico ({{ $movimentos->total() }})
            </h3>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($movimentos as $m)
                <div wire:key="mov-{{ $m->id }}" class="grid grid-cols-1 gap-2 px-6 py-3 sm:grid-cols-[110px_minmax(0,1fr)_110px_130px_170px] sm:items-center hover:bg-amber-50 transition">
                    <span>
                        @php($cor = ['in' => 'bg-green-100 text-green-700', 'out' => 'bg-red-100 text-red-600', 'transfer' => 'bg-blue-100 text-blue-700', 'adjustment' => 'bg-violet-100 text-violet-700'][$m->type] ?? 'bg-gray-100 text-gray-600')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold {{ $cor }}">
                            {{ ['in' => 'ENTRADA', 'out' => 'SAÍDA', 'transfer' => 'TRANSF.', 'adjustment' => 'AJUSTE'][$m->type] ?? strtoupper($m->type) }}
                        </span>
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-bold text-gray-900">{{ $m->product?->name ?? '(apagado)' }}</p>
                        <p class="truncate text-xs text-gray-500">
                            {{ \App\Livewire\Inventario\Movimentos::ORIGENS[$m->reference_type] ?? ($m->reference_type ?: '—') }}
                            @if($m->notes) · {{ $m->notes }} @endif
                        </p>
                    </div>
                    <span class="text-right font-bold tabular-nums text-gray-900">
                        {{ $m->type === 'adjustment' ? '=' : ($m->type === 'out' ? '−' : '+') }}{{ rtrim(rtrim(number_format((float) $m->quantity, 2, ',', '.'), '0'), ',') }}
                    </span>
                    <span class="text-right text-xs tabular-nums text-gray-500">
                        @if($m->balance_after !== null) saldo {{ rtrim(rtrim(number_format((float) $m->balance_after, 2, ',', '.'), '0'), ',') }} @else — @endif
                    </span>
                    <span class="text-right text-xs text-gray-400">{{ $m->user?->name ?? '—' }} · {{ $m->created_at->format('d/m H:i') }}</span>
                </div>
            @empty
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-right-left text-gray-400 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Sem movimentos no período</h3>
                    <p class="text-gray-500">Alargue as datas ou limpe os filtros.</p>
                </div>
            @endforelse
        </div>
        @if($movimentos->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">{{ $movimentos->links() }}</div>
        @endif
    </div>
</div>
