<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center">
            <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                <i class="fas fa-chart-bar text-3xl"></i>
            </div>
            <div>
                <h2 class="text-2xl font-bold">{{ __('Relatórios de Faturação') }}</h2>
                <p class="text-indigo-100 text-sm">{{ __('Análises e mapas operacionais da gestão de faturação') }}</p>
            </div>
        </div>
    </div>

    @php
        $sections = [
            [
                'title' => __('Rentabilidade & Análise'),
                'icon' => 'fa-coins',
                'color' => 'emerald',
                'reports' => [
                    ['name' => __('Relatório em Gráficos'), 'desc' => __('Evolução, rankings e cobrança num relance'), 'icon' => 'fa-chart-area', 'route' => 'invoicing.reports.charts'],
                    ['name' => __('Lucros e Perdas (DRE)'), 'desc' => __('Demonstração de resultados completa'), 'icon' => 'fa-chart-line', 'route' => 'invoicing.reports.profit-loss'],
                    ['name' => __('Análise de Margem'), 'desc' => __('Lucro e margem por produto'), 'icon' => 'fa-percentage', 'route' => 'invoicing.reports.margin'],
                    ['name' => __('Desempenho de Produtos'), 'desc' => __('Vendas, stock, lucro e rotação'), 'icon' => 'fa-chart-pie', 'route' => 'invoicing.reports.product-performance'],
                    ['name' => __('Comparativo'), 'desc' => __('Variação entre dois períodos'), 'icon' => 'fa-balance-scale', 'route' => 'invoicing.reports.comparative'],
                ],
            ],
            [
                'title' => __('Vendas'),
                'icon' => 'fa-arrow-trend-up',
                'color' => 'green',
                'reports' => [
                    ['name' => __('Mapa de Vendas'), 'desc' => __('Vendas por período, cliente e status'), 'icon' => 'fa-file-invoice', 'route' => 'invoicing.reports.sales'],
                    ['name' => __('Top Clientes'), 'desc' => __('Ranking de clientes por faturação'), 'icon' => 'fa-crown', 'route' => 'invoicing.reports.top-clients'],
                    ['name' => __('Top Produtos Vendidos'), 'desc' => __('Produtos com maior volume'), 'icon' => 'fa-star', 'route' => 'invoicing.reports.top-products'],
                    ['name' => __('Vendas por Vendedor'), 'desc' => __('Ranking e desempenho por utilizador'), 'icon' => 'fa-user-tie', 'route' => 'invoicing.reports.sales-by-user'],
                ],
            ],
            [
                'title' => __('Compras'),
                'icon' => 'fa-arrow-trend-down',
                'color' => 'orange',
                'reports' => [
                    ['name' => __('Mapa de Compras'), 'desc' => __('Compras por período e fornecedor'), 'icon' => 'fa-shopping-cart', 'route' => 'invoicing.reports.purchases'],
                    ['name' => __('Top Fornecedores'), 'desc' => __('Ranking de fornecedores'), 'icon' => 'fa-truck', 'route' => 'invoicing.reports.top-suppliers'],
                    ['name' => __('Melhor Fornecedor'), 'desc' => __('Score multi-critério (volume, fiabilidade)'), 'icon' => 'fa-medal', 'route' => 'invoicing.reports.best-supplier'],
                ],
            ],
            [
                'title' => __('Contas Correntes'),
                'icon' => 'fa-balance-scale',
                'color' => 'blue',
                'reports' => [
                    ['name' => __('Contas a Receber'), 'desc' => __('Faturas pendentes de clientes'), 'icon' => 'fa-hand-holding-usd', 'route' => 'invoicing.reports.accounts-receivable'],
                    ['name' => __('Contas a Pagar'), 'desc' => __('Faturas pendentes a fornecedores'), 'icon' => 'fa-money-bill-wave', 'route' => 'invoicing.reports.accounts-payable'],
                    ['name' => __('Recebimentos por Meio'), 'desc' => __('Total recebido por forma de pagamento'), 'icon' => 'fa-money-check-alt', 'route' => 'invoicing.reports.payment-methods'],
                    ['name' => __('Aging de Clientes'), 'desc' => __('Antiguidade de saldos por faixa'), 'icon' => 'fa-clock', 'route' => 'invoicing.reports.aging-clients'],
                    ['name' => __('Extracto de Conta Corrente'), 'desc' => __('Movimentos e saldo de um cliente ou fornecedor'), 'icon' => 'fa-file-invoice-dollar', 'route' => 'invoicing.reports.account-statement'],
                ],
            ],
            [
                'title' => __('Fiscal & SAFT'),
                'icon' => 'fa-landmark',
                'color' => 'red',
                'reports' => [
                    ['name' => __('Mapa de IVA'), 'desc' => __('IVA liquidado, dedutível e a pagar'), 'icon' => 'fa-percent', 'route' => 'invoicing.reports.vat'],
                    ['name' => __('Mapa de Documentos'), 'desc' => __('Resumo de faturas, NC, ND e recibos'), 'icon' => 'fa-file-alt', 'route' => 'invoicing.reports.documents'],
                ],
            ],
            [
                'title' => __('Produtos & Serviços'),
                'icon' => 'fa-box-open',
                'color' => 'pink',
                'reports' => [
                    ['name' => __('Tabela de Preços e Lucro'), 'desc' => __('Preço de compra, venda, lucro e margem'), 'icon' => 'fa-tags', 'route' => 'invoicing.reports.price-list'],
                    ['name' => __('Mapa de Serviços'), 'desc' => __('Análise específica de serviços prestados'), 'icon' => 'fa-concierge-bell', 'route' => 'invoicing.reports.services'],
                    ['name' => __('Validade de Produtos'), 'desc' => __('Produtos próximos da validade'), 'icon' => 'fa-calendar-check', 'route' => 'invoicing.expiry-report'],
                ],
            ],
            [
                'title' => __('Stock & Controlo'),
                'icon' => 'fa-boxes-stacked',
                'color' => 'amber',
                'reports' => [
                    ['name' => __('Ajustes de Stock'), 'desc' => __('Seguimento do que foi mexido à mão, por operador'), 'icon' => 'fa-sliders', 'route' => 'invoicing.reports.stock-adjustments'],
                ],
            ],
        ];
    @endphp

    @foreach($sections as $section)
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 bg-gradient-to-br from-{{ $section['color'] }}-500 to-{{ $section['color'] }}-700 rounded-xl flex items-center justify-center mr-3 shadow-lg">
                    <i class="fas {{ $section['icon'] }} text-white"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">{{ $section['title'] }}</h3>
                <div class="flex-1 ml-4 h-px bg-gradient-to-r from-{{ $section['color'] }}-200 to-transparent"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($section['reports'] as $report)
                    <a href="{{ route($report['route']) }}" class="group bg-white rounded-2xl shadow-md hover:shadow-2xl transition-all duration-300 p-5 border border-gray-100 hover:border-{{ $section['color'] }}-300 hover:-translate-y-1">
                        <div class="flex items-start justify-between mb-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-{{ $section['color'] }}-100 to-{{ $section['color'] }}-200 rounded-xl flex items-center justify-center group-hover:from-{{ $section['color'] }}-500 group-hover:to-{{ $section['color'] }}-700 transition-all">
                                <i class="fas {{ $report['icon'] }} text-{{ $section['color'] }}-600 group-hover:text-white transition"></i>
                            </div>
                            <i class="fas fa-arrow-right text-gray-300 group-hover:text-{{ $section['color'] }}-500 group-hover:translate-x-1 transition-all"></i>
                        </div>
                        <h4 class="font-bold text-gray-900 group-hover:text-{{ $section['color'] }}-700 mb-1">{{ $report['name'] }}</h4>
                        <p class="text-xs text-gray-500 leading-relaxed">{{ $report['desc'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
