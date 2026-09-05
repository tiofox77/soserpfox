<div>
    <!-- Header — o desenho da página de Produtos, na cor do Inventário -->
    <div class="mb-6 bg-gradient-to-r from-amber-600 to-yellow-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-warehouse text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Inventário</h2>
                    <p class="text-amber-100 text-sm">Quanto vale, o que está errado, o que se perdeu</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('inventario.contagem') }}" class="bg-white text-amber-700 hover:bg-amber-50 px-6 py-3 rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-clipboard-check mr-2"></i>Contagem física
                </a>
                <a href="{{ route('inventario.movimentos') }}" class="bg-white/15 hover:bg-white/25 px-5 py-3 rounded-xl font-semibold transition-all">
                    <i class="fas fa-right-left mr-2"></i>Movimentos
                </a>
            </div>
        </div>
    </div>

    <!-- Os quatro números -->
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-gray-500">Valor do stock (a custo)</p>
                <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center"><i class="fas fa-coins text-amber-600"></i></div>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ valorProtegido($resumo['valor'], 'inventario.contagem.manage', 'invoicing.reports.view') }} Kz</p>
            <p class="mt-1 text-xs text-gray-500">{{ $resumo['artigos_geridos'] }} artigo(s) com stock gerido</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-6 {{ $resumo['negativos'] > 0 ? 'ring-2 ring-red-200' : '' }}">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-gray-500">Stocks negativos</p>
                <div class="w-10 h-10 {{ $resumo['negativos'] > 0 ? 'bg-red-100' : 'bg-green-100' }} rounded-xl flex items-center justify-center">
                    <i class="fas {{ $resumo['negativos'] > 0 ? 'fa-triangle-exclamation text-red-600' : 'fa-check text-green-600' }}"></i>
                </div>
            </div>
            <p class="text-2xl font-bold {{ $resumo['negativos'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $resumo['negativos'] }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $resumo['negativos'] > 0 ? 'impossíveis físicos — contar e acertar' : 'nenhum — bom sinal' }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-gray-500">Geridos a zero</p>
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center"><i class="fas fa-box-open text-blue-600"></i></div>
            </div>
            <p class="text-2xl font-bold text-gray-900">{{ $resumo['a_zero'] }}</p>
            <p class="mt-1 text-xs text-gray-500">fora do POS até haver entrada</p>
        </div>
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-gray-500">Quebras do mês</p>
                <div class="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center"><i class="fas fa-dumpster-fire text-rose-600"></i></div>
            </div>
            <p class="text-2xl font-bold text-rose-600">{{ valorProtegido($resumo['quebras_mes'], 'inventario.contagem.manage', 'invoicing.reports.view') }} Kz</p>
            <a href="{{ route('invoicing.quebras') }}" class="mt-1 text-xs font-semibold text-rose-600 hover:underline">Ver o relatório de quebras →</a>
        </div>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-1">
        <x-grafico titulo="O pulso do armazém"
                   subtitulo="Entradas e saídas por dia — últimos 30 dias"
                   id="grInvMovimentos"
                   :altura="230"
                   :vazio="!array_sum($graficoMovimentos['entradas']) && !array_sum($graficoMovimentos['saidas'])" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Negativos -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center"><i class="fas fa-triangle-exclamation mr-2 text-red-500"></i>Negativos</h3>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($negativos as $n)
                    <div class="flex items-center justify-between px-6 py-3">
                        <span class="font-semibold text-gray-700 truncate">{{ $n->name }}</span>
                        <span class="shrink-0 font-bold tabular-nums text-red-600">{{ rtrim(rtrim(number_format((float) $n->quantity, 2, ',', '.'), '0'), ',') }}</span>
                    </div>
                @empty
                    <p class="px-6 py-8 text-center text-sm text-gray-400">Nenhum. O stock:reconcile agradece.</p>
                @endforelse
            </div>
        </div>

        <!-- Mais valiosos -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center"><i class="fas fa-coins mr-2 text-amber-600"></i>Onde está o dinheiro</h3>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($maisValiosos as $m)
                    <div class="flex items-center justify-between px-6 py-3 gap-3">
                        <span class="font-semibold text-gray-700 truncate">{{ $m->name }}</span>
                        <span class="shrink-0 text-right">
                            <span class="text-xs text-gray-400 mr-2">{{ rtrim(rtrim(number_format((float) $m->quantity, 2, ',', '.'), '0'), ',') }} un.</span>
                            <b class="tabular-nums text-gray-900">{{ valorProtegido((float) $m->valor, 'inventario.contagem.manage', 'invoicing.reports.view') }} Kz</b>
                        </span>
                    </div>
                @empty
                    <p class="px-6 py-8 text-center text-sm text-gray-400">Sem custos preenchidos nos artigos — o valor sai a zero.</p>
                @endforelse
            </div>
        </div>

        <!-- Última contagem -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900 flex items-center"><i class="fas fa-clipboard-check mr-2 text-amber-600"></i>A idade da verdade</h3>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($ultimasContagens as $c)
                    <div class="flex items-center justify-between px-6 py-3 gap-3">
                        <span class="font-semibold text-gray-700 truncate">{{ $c->warehouse?->name ?? '—' }}</span>
                        <span class="shrink-0 text-xs font-bold {{ $c->closed_at->lt(now()->subDays(90)) ? 'text-red-500' : 'text-gray-500' }}">
                            {{ $c->closed_at->format('d/m/Y') }}
                        </span>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-400">
                        <p class="mb-2">Nunca se contou. Um inventário nunca contado é um número em que ninguém deve confiar.</p>
                        <a href="{{ route('inventario.contagem') }}" class="font-semibold text-amber-700 hover:underline">Abrir a primeira contagem →</a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @include('partials.graficos')
    <script>
        // Um sítio só para o quando: desenha já e outra vez depois de o
        // Livewire trocar o canvas. Dois ouvintes registados aqui eram
        // empilhados a cada navegação, porque o script volta a correr.
        sosDesenhar(iniciarGraficosInventario);

        function iniciarGraficosInventario() {
            const dados = @json($graficoMovimentos);
            const el = document.getElementById('grInvMovimentos');
            if (!el || !window.Chart) return;
            sosGrafico('grInvMovimentos', {
                type: 'line',
                data: {
                    labels: dados.etiquetas,
                    datasets: [
                        { label: 'Entradas', data: dados.entradas, borderColor: SOS_CORES[2], backgroundColor: 'transparent', tension: .3, pointRadius: 0, borderWidth: 2 },
                        { label: 'Saídas', data: dados.saidas, borderColor: SOS_CORES[1], backgroundColor: 'transparent', tension: .3, pointRadius: 0, borderWidth: 2 },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                },
            });
        }
    </script>
</div>
