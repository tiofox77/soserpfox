<div class="p-3 sm:p-6">
    {{-- Header --}}
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-xl sm:text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-chart-line mr-2 sm:mr-3 text-blue-600"></i>
                    {{ __('Dashboard de Faturação') }}
                </h2>
                {{-- Frase inteira com marcador, e nao " - " colado ao mes: a
                     ordem das palavras noutras linguas nao e a portuguesa. --}}
                <p class="text-gray-600 mt-1 text-xs sm:text-base">{{ __('Visão geral do módulo de faturação - :periodo', ['periodo' => \Carbon\Carbon::now()->format('F Y')]) }}</p>
            </div>
            <div>
                <select wire:model.live="selectedPeriod" class="rounded-lg border-gray-300 shadow-sm text-sm sm:text-base">
                    <option value="week">{{ __('Esta Semana') }}</option>
                    <option value="month">{{ __('Este Mês') }}</option>
                    <option value="year">{{ __('Este Ano') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Cards de Estatísticas Principais --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 mb-4 sm:mb-6">
        {{-- Faturação do Mês --}}
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-3 sm:p-6 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-2 sm:mb-4">
                <div>
                    <p class="text-blue-200 text-xs sm:text-sm font-medium uppercase">{{ __('Faturação do Mês') }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-file-invoice-dollar text-base sm:text-2xl"></i>
                </div>
            </div>
            <div>
                <p class="text-lg sm:text-3xl font-bold">{{ number_format($stats['total_invoiced'], 2) }}</p>
                <p class="text-blue-200 text-xs sm:text-sm mt-1">AOA</p>
                @if($stats['growth'] > 0)
                    <p class="text-green-200 text-xs mt-2">
                        <i class="fas fa-arrow-up mr-1"></i>{{ __(':pct% vs mês anterior', ['pct' => number_format($stats['growth'], 1)]) }}
                    </p>
                @elseif($stats['growth'] < 0)
                    <p class="text-red-200 text-xs mt-2">
                        <i class="fas fa-arrow-down mr-1"></i>{{ __(':pct% vs mês anterior', ['pct' => number_format(abs($stats['growth']), 1)]) }}
                    </p>
                @else
                    <p class="text-blue-200 text-xs mt-2">
                        <i class="fas fa-minus mr-1"></i>{{ __('Sem alteração') }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Recebimentos --}}
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-3 sm:p-6 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-2 sm:mb-4">
                <div>
                    <p class="text-green-200 text-xs sm:text-sm font-medium uppercase">{{ __('Recebimentos') }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-money-bill-wave text-base sm:text-2xl"></i>
                </div>
            </div>
            <div>
                <p class="text-lg sm:text-3xl font-bold">{{ number_format($stats['total_received'], 2) }}</p>
                <p class="text-green-200 text-xs sm:text-sm mt-1">AOA</p>
                <p class="text-green-200 text-xs mt-2">
                    <i class="fas fa-check-circle mr-1"></i>{{ __('Pagamentos recebidos') }}
                </p>
            </div>
        </div>

        {{-- Valores Pendentes --}}
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-3 sm:p-6 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-2 sm:mb-4">
                <div>
                    <p class="text-yellow-200 text-xs sm:text-sm font-medium uppercase">{{ __('Valores Pendentes') }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-hourglass-half text-base sm:text-2xl"></i>
                </div>
            </div>
            <div>
                <p class="text-lg sm:text-3xl font-bold">{{ number_format($stats['total_pending'], 2) }}</p>
                <p class="text-yellow-200 text-xs sm:text-sm mt-1">AOA</p>
                <p class="text-yellow-200 text-xs mt-2">
                    <i class="fas fa-clock mr-1"></i>{{ __('Aguardando pagamento') }}
                </p>
            </div>
        </div>

        {{-- Valores Vencidos --}}
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-3 sm:p-6 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-2 sm:mb-4">
                <div>
                    <p class="text-red-200 text-xs sm:text-sm font-medium uppercase">{{ __('Valores Vencidos') }}</p>
                </div>
                <div class="bg-white/20 p-2 sm:p-3 rounded-full">
                    <i class="fas fa-exclamation-triangle text-base sm:text-2xl"></i>
                </div>
            </div>
            <div>
                <p class="text-lg sm:text-3xl font-bold">{{ number_format($stats['total_overdue'], 2) }}</p>
                <p class="text-red-200 text-xs sm:text-sm mt-1">AOA</p>
                <p class="text-red-200 text-xs mt-2">
                    <i class="fas fa-bell mr-1"></i>{{ __('Requer atenção') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Gráfico de Vendas --}}
    <div class="bg-white rounded-xl shadow-lg p-3 sm:p-6 mb-4 sm:mb-6">
        {{-- Bloco de PHP, e nao a forma de uma linha: a expressao tem escolha
             de tres ramos e a forma curta parte a compilacao.

             O ucfirst() saiu de cena. Os tres rotulos ja comecam por
             maiuscula nas tres linguas, portanto nao fazia nada — mas
             ucfirst() nao e multibyte, e no dia em que um rotulo comecasse por
             letra acentuada devolvia um caractere partido. --}}
        @php
            $__rotuloPeriodo = match ($selectedPeriod) {
                'week' => __('Esta Semana'),
                'year' => __('Este Ano'),
                default => __('Este Mês'),
            };
        @endphp
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-4">
            <h3 class="text-sm sm:text-lg font-bold text-gray-800 flex items-center">
                <i class="fas fa-chart-area mr-2 text-blue-600"></i>
                {{ __('Evolução de Vendas - :periodo', ['periodo' => $__rotuloPeriodo]) }}
            </h3>
            <div class="flex gap-2">
                <button onclick="exportToPDF()" class="px-3 sm:px-4 py-1.5 sm:py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all duration-300 hover:scale-105 text-xs sm:text-sm">
                    <i class="fas fa-file-pdf sm:mr-2"></i><span class="hidden sm:inline">{{ __('Exportar PDF') }}</span>
                </button>
                {{-- "Excel" fica fora do __(): e nome de produto, como Starter
                     ou FOX Friendly. Escreve-se igual nas tres linguas. --}}
                <button onclick="exportToExcel()" class="px-3 sm:px-4 py-1.5 sm:py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all duration-300 hover:scale-105 text-xs sm:text-sm">
                    <i class="fas fa-file-excel sm:mr-2"></i><span class="hidden sm:inline">Excel</span>
                </button>
            </div>
        </div>
        <canvas id="salesChart" height="80"></canvas>
    </div>

    {{-- Os dados dos gráficos novos, num nó que o Livewire actualiza quando se
         muda o período. Dentro do <script> ficariam presos ao primeiro render. --}}
    <script type="application/json" id="dadosPainel">@json($graficos ?? [])</script>

    {{-- E os do gráfico de vendas pela mesma razão: dentro do <script> do
         @push ficavam presos ao primeiro desenho, e trocar de período
         mudava os números dos cartões sem mudar a linha do gráfico. --}}
    <script type="application/json" id="dadosVendas">@json($chartData ?? [])</script>

    {{-- Os textos e os valores para exportar. O desenho vive em
         /js/painel-facturacao.js, que corre sempre — um script em linha
         não volta a correr quando se chega aqui pela barra lateral. --}}
    @php
        // O @json de uma linha não aguenta um array com várias linhas: monta-se
        // aqui e emite-se lá em baixo, como manda a regra da casa.
        // O formato de números segue a língua do utilizador. A definição
        // vivia no partial dos gráficos; aqui resolve-se no sítio.
        $__intl = ['pt' => 'pt-PT', 'en' => 'en-GB', 'fr' => 'fr-FR'][app()->getLocale()] ?? 'pt-PT';

        $__textosPainel = [
            'intl' => $__intl,
            't' => [
                'vendasAoa'    => __('Vendas (AOA)'),
                'vendas'       => __('Vendas'),
                'compras'      => __('Compras'),
                'titulo'       => __('Dashboard de Faturação'),
                'geradoEm'     => __('Gerado em: :data'),
                'facturado'    => __('Faturação do Mês'),
                'recebido'     => __('Recebimentos'),
                'pendente'     => __('Valores Pendentes'),
                'vencido'      => __('Valores Vencidos'),
                'data'         => __('Data'),
                'valorAoa'     => __('Valor (AOA)'),
                'estatisticas' => __('Estatísticas'),
            ],
            'valores' => [
                'facturado'    => number_format($stats['total_invoiced'], 2),
                'recebido'     => number_format($stats['total_received'], 2),
                'pendente'     => number_format($stats['total_pending'], 2),
                'vencido'      => number_format($stats['total_overdue'], 2),
                'facturadoCru' => number_format($stats['total_invoiced'], 2, '.', ''),
                'recebidoCru'  => number_format($stats['total_received'], 2, '.', ''),
                'pendenteCru'  => number_format($stats['total_pending'], 2, '.', ''),
                'vencidoCru'   => number_format($stats['total_overdue'], 2, '.', ''),
            ],
        ];
    @endphp

    <script type="application/json" id="textosPainel">@json($__textosPainel)</script>

    {{-- Gráficos do período escolhido --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-5">
            <h3 class="text-sm font-bold text-gray-800 mb-1">
                <i class="fas fa-clipboard-check text-rose-600 mr-2"></i>{{ __('Estado das Faturas') }}
            </h3>
            <p class="text-xs text-gray-500 mb-3">{{ __('Vencidas contadas à parte') }}</p>
            <div style="height:250px"><canvas id="pEstados"></canvas></div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5">
            <h3 class="text-sm font-bold text-gray-800 mb-1">
                <i class="fas fa-money-check-dollar text-cyan-600 mr-2"></i>{{ __('Como Recebemos') }}
            </h3>
            <p class="text-xs text-gray-500 mb-3">{{ __('Recebimentos por forma de pagamento') }}</p>
            <div style="height:250px"><canvas id="pMeios"></canvas></div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-5">
            <h3 class="text-sm font-bold text-gray-800 mb-1">
                <i class="fas fa-star text-violet-600 mr-2"></i>{{ __('Top Produtos') }}
            </h3>
            <p class="text-xs text-gray-500 mb-3">{{ __('Por valor vendido') }}</p>
            <div style="height:250px"><canvas id="pProdutos"></canvas></div>
        </div>

        <div class="lg:col-span-3 bg-white rounded-2xl shadow-lg p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                <h3 class="text-sm font-bold text-gray-800">
                    <i class="fas fa-scale-balanced text-orange-600 mr-2"></i>{{ __('Vendas vs. Compras') }}
                </h3>
                <a href="{{ route('invoicing.reports.charts') }}"
                   class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                    {{ __('Ver todos os gráficos') }} <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <p class="text-xs text-gray-500 mb-3">{{ __('A folga entre o que entra e o que sai') }}</p>
            <div style="height:260px"><canvas id="pVendasCompras"></canvas></div>
        </div>
    </div>

    {{-- Documentos e Gráfico --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Documentos por Tipo --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-file-alt mr-2 text-blue-600"></i>
                {{ __('Documentos Este Mês') }}
            </h3>
            
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white mr-3">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ __('Faturas') }}</p>
                            <p class="text-xs text-gray-500">{{ __('Vendas emitidas') }}</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-blue-600">{{ $documents['invoices'] }}</span>
                </div>

                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center text-white mr-3">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ __('Recibos') }}</p>
                            <p class="text-xs text-gray-500">{{ __('Pagamentos') }}</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-green-600">{{ $documents['receipts'] }}</span>
                </div>

                <div class="flex items-center justify-between p-3 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-emerald-600 rounded-full flex items-center justify-center text-white mr-3">
                            <i class="fas fa-minus-circle"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ __('Notas Crédito') }}</p>
                            <p class="text-xs text-gray-500">{{ __('Devoluções') }}</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-emerald-600">{{ $documents['credit_notes'] }}</span>
                </div>

                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg hover:bg-red-100 transition">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-red-600 rounded-full flex items-center justify-center text-white mr-3">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ __('Notas Débito') }}</p>
                            <p class="text-xs text-gray-500">{{ __('Cobranças extras') }}</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-red-600">{{ $documents['debit_notes'] }}</span>
                </div>

                <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-yellow-600 rounded-full flex items-center justify-center text-white mr-3">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ __('Adiantamentos') }}</p>
                            <p class="text-xs text-gray-500">{{ __('Valores antecipados') }}</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-yellow-600">{{ $documents['advances'] }}</span>
                </div>
            </div>
        </div>

        {{-- Status de Faturas --}}
        <div class="bg-white rounded-xl shadow-lg p-6 lg:col-span-2">
            {{-- Titulo diferente do grafico la de cima de proposito: aqui sao
                 CONTAGENS deste mes, la em cima sao VALORES do periodo
                 escolhido. Com o mesmo nome parecia que um dos dois mentia. --}}
            <h3 class="text-lg font-bold text-gray-800 mb-1 flex items-center">
                <i class="fas fa-chart-pie mr-2 text-blue-600"></i>
                {{ __('Faturas Deste Mês') }}
            </h3>
            <p class="text-xs text-gray-500 mb-4">{{ __('Quantas estão em cada estado') }}</p>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-6 bg-gradient-to-br from-green-50 to-green-100 rounded-xl border-2 border-green-200">
                    <div class="text-5xl font-bold text-green-600 mb-2">{{ $invoiceStatus['paid'] }}</div>
                    <p class="text-sm font-medium text-green-700">{{ __('Pagas') }}</p>
                    <i class="fas fa-check-circle text-green-600 text-2xl mt-2"></i>
                </div>

                <div class="text-center p-6 bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl border-2 border-yellow-200">
                    <div class="text-5xl font-bold text-yellow-600 mb-2">{{ $invoiceStatus['pending'] }}</div>
                    <p class="text-sm font-medium text-yellow-700">{{ __('Pendentes') }}</p>
                    <i class="fas fa-clock text-yellow-600 text-2xl mt-2"></i>
                </div>

                <div class="text-center p-6 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border-2 border-blue-200">
                    <div class="text-5xl font-bold text-blue-600 mb-2">{{ $invoiceStatus['partially_paid'] }}</div>
                    <p class="text-sm font-medium text-blue-700">{{ __('Parc. Pagas') }}</p>
                    <i class="fas fa-coins text-blue-600 text-2xl mt-2"></i>
                </div>

                <div class="text-center p-6 bg-gradient-to-br from-red-50 to-red-100 rounded-xl border-2 border-red-200">
                    <div class="text-5xl font-bold text-red-600 mb-2">{{ $invoiceStatus['overdue'] }}</div>
                    <p class="text-sm font-medium text-red-700">{{ __('Vencidas') }}</p>
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl mt-2"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Comparação Ano a Ano --}}
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-chart-bar mr-2 text-purple-600"></i>
            {{ __('Comparação Ano a Ano') }}
        </h3>
        
        {{-- A cor do crescimento tem tres estados: zero nao e uma queda, e
             estava a sair vermelho com seta para baixo. --}}
        @php
            $__crescimento = $stats['year_growth'];
            $__cor = $__crescimento > 0 ? 'green' : ($__crescimento < 0 ? 'red' : 'gray');
            $__seta = $__crescimento > 0 ? 'arrow-up' : ($__crescimento < 0 ? 'arrow-down' : 'minus');
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg border-2 border-blue-200">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-medium text-blue-700">{{ __('Faturação :ano', ['ano' => now()->year]) }}</p>
                    <i class="fas fa-calendar-check text-blue-600"></i>
                </div>
                <p class="text-2xl font-bold text-blue-900">{{ number_format($stats['year_invoiced'], 2) }}</p>
                <p class="text-xs text-blue-600 mt-1">AOA ({{ __('até hoje') }})</p>
            </div>

            <div class="p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg border-2 border-gray-200">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-medium text-gray-700">{{ __('Faturação :ano', ['ano' => now()->year - 1]) }}</p>
                    <i class="fas fa-calendar text-gray-600"></i>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['year_invoiced_previous'], 2) }}</p>
                <p class="text-xs text-gray-600 mt-1">AOA ({{ __('mesmo período') }})</p>
            </div>

            <div class="p-4 bg-gradient-to-br from-{{ $__cor }}-50 to-{{ $__cor }}-100 rounded-lg border-2 border-{{ $__cor }}-200">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-medium text-{{ $__cor }}-700">{{ __('Crescimento') }}</p>
                    <i class="fas fa-{{ $__seta }} text-{{ $__cor }}-600"></i>
                </div>
                <p class="text-2xl font-bold text-{{ $__cor }}-900">{{ number_format(abs($__crescimento), 1) }}%</p>
                <p class="text-xs text-{{ $__cor }}-600 mt-1">{{ __('vs ano anterior') }}</p>
            </div>
        </div>
    </div>

    {{-- Faturas Pendentes e Top Clientes --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Faturas Pendentes --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                    <i class="fas fa-clock mr-2 text-yellow-600"></i>
                    {{ __('Faturas Pendentes') }}
                </h3>
                <a href="{{ route('invoicing.sales.invoices') }}" class="text-sm text-blue-600 hover:text-blue-800">
                    {{ __('Ver todas') }} <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>

            <div class="space-y-2 max-h-96 overflow-y-auto">
                @forelse($pendingInvoices as $invoice)
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800">{{ $invoice->invoice_number }}</p>
                        <p class="text-xs text-gray-600">{{ $invoice->client?->name ?: __('Consumidor Final') }}</p>
                        {{-- Nem toda a factura por cobrar tem data de vencimento; sem
                             esta guarda a pagina inteira ia abaixo com erro 500. --}}
                        @if($invoice->due_date)
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-calendar mr-1"></i>
                            {{ __('Venc.: :data', ['data' => $invoice->due_date->format('d/m/Y')]) }}
                        </p>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-gray-800">{{ number_format($invoice->total, 2) }} AOA</p>
                        @if($invoice->due_date && $invoice->due_date->isPast())
                            <span class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded-full">{{ __('Vencida') }}</span>
                        @else
                            <span class="text-xs px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full">{{ __('Pendente') }}</span>
                        @endif
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-check-circle text-5xl mb-3"></i>
                    <p>{{ __('Nenhuma fatura pendente') }}</p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Top 5 Clientes --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                    <i class="fas fa-trophy mr-2 text-yellow-600"></i>
                    {{ __('Top 5 Clientes') }}
                </h3>
                <span class="text-xs text-gray-500">{{ __('Por faturação') }}</span>
            </div>

            <div class="space-y-3">
                @forelse($topClients as $index => $topClient)
                <div class="flex items-center p-3 bg-gradient-to-r from-blue-50 to-transparent rounded-lg">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-700 rounded-full flex items-center justify-center text-white font-bold mr-3">
                        {{ $index + 1 }}
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800">{{ $topClient->client->name }}</p>
                        <p class="text-xs text-gray-600">{{ trans_choice(':n fatura|:n faturas', $topClient->invoice_count, ['n' => $topClient->invoice_count]) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-blue-600">{{ number_format($topClient->total_amount, 2) }}</p>
                        <p class="text-xs text-gray-500">AOA</p>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-users text-5xl mb-3"></i>
                    <p>{{ __('Sem dados de clientes') }}</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Atividades Recentes --}}
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-history mr-2 text-blue-600"></i>
            {{ __('Atividades Recentes') }}
        </h3>

        <div class="space-y-2 max-h-96 overflow-y-auto">
            @forelse($recentActivities as $activity)
            <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mr-3">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="flex-1">
                    {{-- O <strong> vai dentro da cadeia traduzida, e nao a
                         partir dela em tres pedacos: a ordem "Fatura X criada"
                         nao se mantem noutras linguas, e partida em pedacos o
                         tradutor nao consegue reordena-la. O numero e escapado
                         a mao porque o {!! !!} nao o faz por nos. --}}
                    <p class="text-sm font-medium text-gray-800">
                        {!! __('Fatura <strong>:numero</strong> criada', ['numero' => e($activity->invoice_number)]) !!}
                    </p>
                    <p class="text-xs text-gray-600">
                        {{ __('Cliente: :nome • :quando', [
                            'nome' => $activity->client?->name ?? __('Sem cliente'),
                            'quando' => $activity->created_at->diffForHumans(),
                        ]) }}
                    </p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 bg-{{ $activity->status_color }}-100 text-{{ $activity->status_color }}-700 text-xs rounded-full">
                        {{ $activity->status_label }}
                    </span>
                </div>
            </div>
            @empty
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-inbox text-5xl mb-3"></i>
                <p>{{ __('Sem atividades recentes') }}</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Scripts --}}



