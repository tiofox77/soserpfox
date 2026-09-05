<div>
    <!-- Header — o desenho da página de Produtos, na cor do CRM -->
    <div class="mb-6 bg-gradient-to-r from-teal-600 to-cyan-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-filter text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Funil de Vendas</h2>
                    <p class="text-teal-100 text-sm">As oportunidades abertas, etapa a etapa — mover acerta a probabilidade sozinho</p>
                </div>
            </div>
            <a href="{{ route('crm.oportunidades') }}" class="bg-white text-teal-600 hover:bg-teal-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                <i class="fas fa-plus mr-2"></i>Nova Oportunidade
            </a>
        </div>
    </div>

    <!-- O quadro: uma coluna por etapa -->
    <div class="flex gap-4 overflow-x-auto pb-4">
        @foreach($colunas as $i => $coluna)
            <div class="w-80 shrink-0" wire:key="col-{{ $coluna['etapa']->id }}">
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="px-5 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-bold text-gray-900 truncate">{{ $coluna['etapa']->name }}</h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 bg-teal-100 text-teal-700 rounded-lg text-xs font-bold">{{ $coluna['cartoes']->count() }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            <span class="font-bold text-gray-700">{{ number_format($coluna['total'], 0, ',', '.') }} Kz</span>
                            · ponderado {{ number_format($coluna['ponderado'], 0, ',', '.') }}
                        </p>
                    </div>

                    <div class="p-3 space-y-3 min-h-24">
                        @forelse($coluna['cartoes'] as $o)
                            <div wire:key="op-{{ $o->id }}" class="group border border-gray-200 rounded-xl p-4 hover:bg-teal-50 hover:border-teal-200 transition-all">
                                <h4 class="font-bold text-gray-900 leading-tight">{{ $o->title }}</h4>
                                <p class="mt-0.5 text-xs text-gray-500 truncate">{{ $o->client?->name ?? 'Sem cliente' }}</p>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                    <span class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 rounded-lg font-semibold">
                                        {{ number_format((float) $o->amount, 0, ',', '.') }} Kz
                                    </span>
                                    @if($o->expected_close_date)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg font-semibold {{ $o->expected_close_date->isPast() ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600' }}">
                                            <i class="fas fa-calendar mr-1"></i>{{ $o->expected_close_date->format('d/m') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button wire:click="mover({{ $o->id }}, 'tras')" @disabled($i === 0)
                                            class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg text-xs font-semibold transition disabled:opacity-30"
                                            title="Etapa anterior">
                                        <i class="fas fa-arrow-left"></i>
                                    </button>
                                    <button wire:click="mover({{ $o->id }}, 'frente')" @disabled($i === count($colunas) - 1)
                                            class="px-3 py-1.5 bg-teal-500 hover:bg-teal-600 text-white rounded-lg text-xs font-semibold transition shadow-md disabled:opacity-30"
                                            title="Etapa seguinte">
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="border-2 border-dashed border-gray-200 rounded-xl py-8 text-center text-xs text-gray-400">Vazio</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <p class="mt-2 text-xs text-gray-400">
        <i class="fas fa-circle-info mr-1"></i>Ganhar e perder faz-se nas
        <a href="{{ route('crm.oportunidades') }}" class="font-semibold underline">Oportunidades</a> — com motivo, porque é dos motivos que sai a taxa de conversão.
    </p>
</div>
