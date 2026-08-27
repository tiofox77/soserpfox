<div>

    {{-- Os dados vivem AQUI, dentro do componente, e não dentro do bloco de
         script: esse corre uma vez só, por isso mudar o período deixava os
         gráficos com os números do período anterior. Este nó é actualizado
         pelo Livewire como o resto do HTML, e o script relê-o. --}}
    <script type="application/json" id="dadosDosGraficos">@json($g)</script>

    {{-- Cabeçalho --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-cyan-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-chart-line text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Relatório em Gráficos</h2>
                    <p class="text-blue-100 text-sm">A facturação vista de relance</p>
                </div>
            </div>
            <a href="{{ route('invoicing.reports.hub') }}"
               class="px-4 py-2.5 bg-white/20 hover:bg-white/30 rounded-xl text-sm font-semibold transition">
                <i class="fas fa-arrow-left mr-1"></i>Relatórios
            </a>
        </div>
    </div>

    {{-- Período --}}
    <div class="mb-6 bg-white rounded-2xl shadow p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-1.5">
                @foreach(['mes' => 'Este mês', 'trimestre' => 'Trimestre', 'ano' => 'Este ano', 'ano_passado' => 'Ano passado'] as $chave => $rotulo)
                    <button wire:click="aplicarAtalho('{{ $chave }}')"
                            class="px-3.5 py-2 rounded-xl text-sm font-semibold transition {{ $atalho === $chave ? 'bg-blue-600 text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        {{ $rotulo }}
                    </button>
                @endforeach
            </div>

            <div class="ml-auto flex items-end gap-2">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">De</label>
                    <input type="date" wire:model.live="de" class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Até</label>
                    <input type="date" wire:model.live="ate" class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>
    </div>

    {{-- Números de topo --}}
    @php
        $r = $g['resumo'];
        $cartoes = [
            ['Vendas', number_format($r['vendas'], 2, ',', '.') . ' Kz', 'fa-file-invoice-dollar', 'blue'],
            ['Recebido', number_format($r['recebido'], 2, ',', '.') . ' Kz', 'fa-hand-holding-dollar', 'emerald'],
            ['Compras', number_format($r['compras'], 2, ',', '.') . ' Kz', 'fa-cart-shopping', 'orange'],
            ['Ticket médio', number_format($r['ticket'], 2, ',', '.') . ' Kz', 'fa-receipt', 'violet'],
        ];
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach($cartoes as [$rotulo, $valor, $icone, $cor])
            <div class="bg-white rounded-2xl shadow p-4 border-l-4 border-{{ $cor }}-500">
                <div class="flex items-center justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-gray-500 uppercase">{{ $rotulo }}</p>
                        <p class="text-lg font-bold text-gray-900 mt-1 truncate">{{ $valor }}</p>
                    </div>
                    <i class="fas {{ $icone }} text-{{ $cor }}-400 text-xl ml-2"></i>
                </div>
            </div>
        @endforeach
    </div>

    @if($r['documentos'] === 0)
        <div class="bg-white rounded-2xl shadow p-12 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-chart-simple text-gray-400 text-3xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Sem facturação neste período</h3>
            <p class="text-gray-500">Escolha outro intervalo de datas acima.</p>
        </div>
    @else
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

            {{-- A evolução ocupa a largura toda: é a linha que se olha primeiro. --}}
            <div class="xl:col-span-2 bg-white rounded-2xl shadow p-5">
                <h3 class="font-bold text-gray-900 mb-1">
                    <i class="fas fa-chart-area text-blue-600 mr-2"></i>Evolução das vendas
                </h3>
                <p class="text-xs text-gray-500 mb-4">
                    {{ $g['evolucao']['porMes'] ? 'Agrupado por mês' : 'Agrupado por dia' }} ·
                    {{ array_sum($g['evolucao']['documentos']) }} documento(s)
                </p>
                <div style="height:300px"><canvas id="gEvolucao"></canvas></div>
            </div>

            <div class="bg-white rounded-2xl shadow p-5">
                <h3 class="font-bold text-gray-900 mb-1">
                    <i class="fas fa-scale-balanced text-orange-600 mr-2"></i>Vendas vs. Compras
                </h3>
                <p class="text-xs text-gray-500 mb-4">A folga entre o que entra e o que sai</p>
                <div style="height:280px"><canvas id="gVendasCompras"></canvas></div>
            </div>

            <div class="bg-white rounded-2xl shadow p-5">
                <h3 class="font-bold text-gray-900 mb-1">
                    <i class="fas fa-hand-holding-dollar text-emerald-600 mr-2"></i>Facturado vs. Recebido
                </h3>
                <p class="text-xs text-gray-500 mb-4">A distância entre os dois é a cobrança por fazer</p>
                <div style="height:280px"><canvas id="gCobranca"></canvas></div>
            </div>

            <div class="bg-white rounded-2xl shadow p-5">
                <h3 class="font-bold text-gray-900 mb-1">
                    <i class="fas fa-crown text-amber-600 mr-2"></i>Top clientes
                </h3>
                <p class="text-xs text-gray-500 mb-4">Quem pesa mais na facturação do período</p>
                <div style="height:300px"><canvas id="gClientes"></canvas></div>
            </div>

            <div class="bg-white rounded-2xl shadow p-5">
                <h3 class="font-bold text-gray-900 mb-1">
                    <i class="fas fa-star text-violet-600 mr-2"></i>Top produtos
                </h3>
                <p class="text-xs text-gray-500 mb-4">Por valor vendido, não por quantidade</p>
                <div style="height:300px"><canvas id="gProdutos"></canvas></div>
            </div>

            <div class="bg-white rounded-2xl shadow p-5">
                <h3 class="font-bold text-gray-900 mb-1">
                    <i class="fas fa-clipboard-check text-rose-600 mr-2"></i>Estado das facturas
                </h3>
                <p class="text-xs text-gray-500 mb-4">Vencidas contadas à parte das que ainda têm prazo</p>
                <div style="height:280px"><canvas id="gEstados"></canvas></div>
            </div>

            <div class="bg-white rounded-2xl shadow p-5">
                <h3 class="font-bold text-gray-900 mb-1">
                    <i class="fas fa-money-check-dollar text-cyan-600 mr-2"></i>Como recebemos
                </h3>
                <p class="text-xs text-gray-500 mb-4">Recebimentos por forma de pagamento</p>
                <div style="height:280px"><canvas id="gMeios"></canvas></div>
            </div>

            <div class="bg-white rounded-2xl shadow p-5">
                <h3 class="font-bold text-gray-900 mb-1">
                    <i class="fas fa-user-tie text-indigo-600 mr-2"></i>Vendas por vendedor
                </h3>
                <p class="text-xs text-gray-500 mb-4">Quem emitiu cada documento</p>
                <div style="height:280px"><canvas id="gVendedores"></canvas></div>
            </div>

            <div class="bg-white rounded-2xl shadow p-5">
                <h3 class="font-bold text-gray-900 mb-1">
                    <i class="fas fa-calendar-week text-teal-600 mr-2"></i>Vendas por dia da semana
                </h3>
                <p class="text-xs text-gray-500 mb-4">Onde estão os dias fortes e os fracos</p>
                <div style="height:280px"><canvas id="gSemana"></canvas></div>
            </div>

            <div class="xl:col-span-2 bg-white rounded-2xl shadow p-5">
                <h3 class="font-bold text-gray-900 mb-1">
                    <i class="fas fa-percent text-slate-600 mr-2"></i>IVA liquidado vs. suportado
                </h3>
                <p class="text-xs text-gray-500 mb-4">
                    A diferença é o que se entrega ao Estado. Para conferir número a número,
                    use o <a href="{{ route('invoicing.reports.vat') }}" class="text-blue-600 hover:underline">Mapa de IVA</a>.
                </p>
                <div style="height:280px"><canvas id="gIva"></canvas></div>
            </div>
        </div>
    @endif
</div>

@assets
{{-- Chart.js LOCAL, não de CDN: a versão on-premise corre sem internet e um
     script de CDN deixaria esta página só com quadrados brancos. --}}
<script src="{{ asset('vendor/js/chart.min.js') }}"></script>
@endassets

@script
<script>
    const kz = v => new Intl.NumberFormat('pt-PT', { maximumFractionDigits: 0 }).format(v) + ' Kz';

    // Eixo em milhares/milhões: "1.250.000 Kz" em cada marca tapa o gráfico.
    const curto = v => Math.abs(v) >= 1e6 ? (v / 1e6).toFixed(1) + 'M'
                     : Math.abs(v) >= 1e3 ? Math.round(v / 1e3) + 'k'
                     : v;

    let feitos = [];

    const base = (extra = {}) => Object.assign({
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: c => ' ' + kz(c.parsed.y ?? c.parsed) } },
        },
        scales: { y: { beginAtZero: true, ticks: { callback: curto } } },
    }, extra);

    const legendaEmBaixo = () => ({
        legend: { display: true, position: 'bottom' },
        tooltip: { callbacks: { label: c => ' ' + c.dataset.label + ': ' + kz(c.parsed.y) } },
    });

    const desenhar = (id, config) => {
        const el = document.getElementById(id);
        if (!el || !window.Chart) return;
        feitos.push(new Chart(el, config));
    };

    function desenharTudo() {
        // Destruir antes de redesenhar: sem isto o Chart.js queixa-se de
        // "Canvas is already in use" e o gráfico velho fica por baixo.
        feitos.forEach(c => { try { c.destroy(); } catch (e) {} });
        feitos = [];

        const no = document.getElementById('dadosDosGraficos');
        if (!no || !window.Chart) return;

        let d;
        try { d = JSON.parse(no.textContent); } catch (e) { return; }
        if (!d || !d.resumo || d.resumo.documentos === 0) return;

        desenhar('gEvolucao', {
            type: 'line',
            data: {
                labels: d.evolucao.rotulos,
                datasets: [{
                    data: d.evolucao.valores, borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79,70,229,.10)', fill: true,
                    tension: .3, borderWidth: 2,
                    pointRadius: d.evolucao.rotulos.length > 40 ? 0 : 3,
                }],
            },
            options: base(),
        });

        desenhar('gVendasCompras', {
            type: 'bar',
            data: {
                labels: d.vendasCompras.rotulos,
                datasets: [
                    { label: 'Vendas', data: d.vendasCompras.vendas, backgroundColor: '#4f46e5' },
                    { label: 'Compras', data: d.vendasCompras.compras, backgroundColor: '#ea580c' },
                ],
            },
            options: base({ plugins: legendaEmBaixo() }),
        });

        desenhar('gCobranca', {
            type: 'line',
            data: {
                labels: d.cobranca.rotulos,
                datasets: [
                    { label: 'Facturado', data: d.cobranca.facturado, borderColor: '#4f46e5', tension: .3, borderWidth: 2, pointRadius: 0 },
                    { label: 'Recebido', data: d.cobranca.recebido, borderColor: '#16a34a', tension: .3, borderWidth: 2, pointRadius: 0 },
                ],
            },
            options: base({ plugins: legendaEmBaixo() }),
        });

        // Rankings em barras HORIZONTAIS: nomes de cliente e de produto não
        // cabem por baixo de uma barra vertical sem ficarem de lado.
        const ranking = (id, fonte) => desenhar(id, {
            type: 'bar',
            data: { labels: fonte.rotulos, datasets: [{ data: fonte.valores, backgroundColor: fonte.cores }] },
            options: base({
                indexAxis: 'y',
                scales: { x: { beginAtZero: true, ticks: { callback: curto } } },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: c => ' ' + kz(c.parsed.x) } },
                },
            }),
        });

        ranking('gClientes', d.topClientes);
        ranking('gProdutos', d.topProdutos);
        ranking('gVendedores', d.vendedores);

        const rosca = (id, fonte) => desenhar(id, {
            type: 'doughnut',
            data: { labels: fonte.rotulos, datasets: [{ data: fonte.valores, backgroundColor: fonte.cores, borderWidth: 0 }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '58%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } },
                    tooltip: { callbacks: { label: c => ' ' + c.label + ': ' + kz(c.parsed) } },
                },
            },
        });

        rosca('gEstados', d.estados);
        rosca('gMeios', d.meiosPagamento);

        desenhar('gSemana', {
            type: 'bar',
            data: { labels: d.diasDaSemana.rotulos, datasets: [{ data: d.diasDaSemana.valores, backgroundColor: '#0d9488' }] },
            options: base(),
        });

        desenhar('gIva', {
            type: 'bar',
            data: {
                labels: d.iva.rotulos,
                datasets: [
                    { label: 'IVA liquidado', data: d.iva.liquidado, backgroundColor: '#dc2626' },
                    { label: 'IVA suportado', data: d.iva.suportado, backgroundColor: '#0891b2' },
                ],
            },
            options: base({ plugins: legendaEmBaixo() }),
        });
    }

    desenharTudo();

    // Mudar o período troca o HTML todo: redesenhar a seguir ao morph é o que
    // faz os gráficos seguirem as datas escolhidas.
    Livewire.hook('morph.updated', ({ component }) => {
        if (component.id === $wire.id) {
            requestAnimationFrame(desenharTudo);
        }
    });
</script>
@endscript
