{{-- Income Statement by Function (DR por Funções) --}}
@if($reportType === 'income_statement_function' && $incomeStatementFunction)
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 bg-gradient-to-r from-pink-600 to-rose-600 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-sitemap mr-2"></i>
                Demonstração de Resultados por Funções (DRF)
            </h2>
            <p class="text-pink-100 text-sm">Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</p>
        </div>
        <button class="px-4 py-2 bg-white text-pink-600 rounded-lg hover:bg-pink-50 transition font-semibold">
            <i class="fas fa-download mr-2"></i>Exportar PDF
        </button>
    </div>

    <div class="p-6">
        {{-- VENDAS E SERVIÇOS --}}
        <div class="flex justify-between items-center py-3 px-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border-l-4 border-green-600 mb-4">
            <span class="text-lg font-bold text-green-900">VENDAS E PRESTAÇÕES DE SERVIÇOS</span>
            <span class="text-xl font-mono font-bold text-green-700">
                {{ number_format($incomeStatementFunction['sales'], 2, ',', '.') }} Kz
            </span>
        </div>

        {{-- CUSTO DAS VENDAS --}}
        <div class="mb-4">
            <div class="flex justify-between items-center py-2 px-4 hover:bg-gray-50 rounded">
                <div>
                    <span class="text-gray-900 font-medium">Custo das Vendas</span>
                    <span class="text-xs text-gray-500 ml-2">(Alocação por função)</span>
                </div>
                <span class="font-mono text-red-600">
                    ({{ number_format($incomeStatementFunction['cost_of_sales'], 2, ',', '.') }}) Kz
                </span>
            </div>
        </div>

        {{-- MARGEM BRUTA --}}
        <div class="bg-blue-50 px-4 py-3 rounded-lg border-l-4 border-blue-600 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <span class="font-bold text-blue-900 text-lg">MARGEM BRUTA</span>
                    <div class="text-xs text-blue-700 mt-1">
                        Margem: {{ number_format($incomeStatementFunction['gross_margin_percent'], 1, ',', '.') }}%
                    </div>
                </div>
                <span class="text-2xl font-mono font-bold {{ $incomeStatementFunction['gross_margin'] >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                    {{ number_format($incomeStatementFunction['gross_margin'], 2, ',', '.') }} Kz
                </span>
            </div>
        </div>

        {{-- GASTOS POR FUNÇÃO --}}
        <div class="mb-6">
            <h3 class="text-sm font-bold text-gray-800 bg-gray-100 px-3 py-2 rounded mb-3">
                GASTOS OPERACIONAIS POR FUNÇÃO
            </h3>
            
            <div class="space-y-2 pl-3">
                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Gastos de Distribuição</span>
                        <span class="text-xs text-gray-500 ml-2">(Comercial, Marketing)</span>
                    </div>
                    <span class="font-mono text-red-600">
                        ({{ number_format($incomeStatementFunction['distribution_costs'], 2, ',', '.') }}) Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Gastos Administrativos</span>
                        <span class="text-xs text-gray-500 ml-2">(Gestão, RH, Finanças)</span>
                    </div>
                    <span class="font-mono text-red-600">
                        ({{ number_format($incomeStatementFunction['administrative_costs'], 2, ',', '.') }}) Kz
                    </span>
                </div>

                @if($incomeStatementFunction['rd_costs'] > 0)
                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Gastos de I&D</span>
                        <span class="text-xs text-gray-500 ml-2">(Investigação e Desenvolvimento)</span>
                    </div>
                    <span class="font-mono text-red-600">
                        ({{ number_format($incomeStatementFunction['rd_costs'], 2, ',', '.') }}) Kz
                    </span>
                </div>
                @endif

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <span class="text-gray-900 font-medium">Outros Rendimentos</span>
                    <span class="font-mono text-green-600">
                        {{ number_format($incomeStatementFunction['other_income'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <span class="text-gray-900 font-medium">Outros Gastos</span>
                    <span class="font-mono text-red-600">
                        ({{ number_format($incomeStatementFunction['other_expenses'], 2, ',', '.') }}) Kz
                    </span>
                </div>
            </div>
        </div>

        {{-- RESULTADO OPERACIONAL --}}
        <div class="bg-purple-50 px-4 py-3 rounded-lg border-l-4 border-purple-600 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <span class="font-bold text-purple-900 text-lg">RESULTADO OPERACIONAL (EBIT)</span>
                    <div class="text-xs text-purple-700 mt-1">
                        Margem: {{ number_format($incomeStatementFunction['operating_margin_percent'], 1, ',', '.') }}%
                    </div>
                </div>
                <span class="text-2xl font-mono font-bold {{ $incomeStatementFunction['operating_result'] >= 0 ? 'text-purple-700' : 'text-red-700' }}">
                    {{ number_format($incomeStatementFunction['operating_result'], 2, ',', '.') }} Kz
                </span>
            </div>
        </div>

        {{-- RESULTADOS FINANCEIROS --}}
        <div class="mb-6">
            <h3 class="text-sm font-bold text-gray-800 bg-gray-100 px-3 py-2 rounded mb-3">
                RESULTADOS FINANCEIROS
            </h3>
            
            <div class="space-y-2 pl-3">
                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <span class="text-gray-900 font-medium">Rendimentos Financeiros</span>
                    <span class="font-mono text-green-600">
                        {{ number_format($incomeStatementFunction['financial_income'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <span class="text-gray-900 font-medium">Gastos Financeiros</span>
                    <span class="font-mono text-red-600">
                        ({{ number_format($incomeStatementFunction['financial_expenses'], 2, ',', '.') }}) Kz
                    </span>
                </div>
            </div>

            <div class="bg-indigo-50 px-4 py-3 rounded-lg border-l-4 border-indigo-600 mt-3">
                <div class="flex justify-between items-center">
                    <span class="font-bold text-indigo-900">RESULTADO ANTES DE IMPOSTOS (EBT)</span>
                    <span class="text-xl font-mono font-bold {{ $incomeStatementFunction['result_before_tax'] >= 0 ? 'text-indigo-700' : 'text-red-700' }}">
                        {{ number_format($incomeStatementFunction['result_before_tax'], 2, ',', '.') }} Kz
                    </span>
                </div>
            </div>
        </div>

        {{-- IMPOSTO --}}
        <div class="mb-6">
            <div class="flex justify-between items-center py-3 px-4 hover:bg-gray-50 rounded">
                <span class="text-gray-900 font-medium">Imposto sobre o Rendimento (IRC)</span>
                <span class="font-mono text-red-600">
                    ({{ number_format($incomeStatementFunction['income_tax'], 2, ',', '.') }}) Kz
                </span>
            </div>
        </div>

        {{-- RESULTADO LÍQUIDO --}}
        <div class="bg-gradient-to-r {{ $incomeStatementFunction['net_income'] >= 0 ? 'from-emerald-500 to-green-600' : 'from-red-500 to-rose-600' }} px-6 py-4 rounded-xl">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-white text-lg font-bold">RESULTADO LÍQUIDO DO PERÍODO</span>
                    <div class="text-sm text-white opacity-90 mt-1">
                        Margem Líquida: {{ number_format($incomeStatementFunction['net_margin_percent'], 1, ',', '.') }}%
                    </div>
                </div>
                <span class="text-3xl font-mono font-bold text-white">
                    {{ number_format($incomeStatementFunction['net_income'], 2, ',', '.') }} Kz
                </span>
            </div>
        </div>

        {{-- Indicadores de Margens --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gradient-to-br from-blue-500 to-cyan-600 rounded-lg p-4 text-white">
                <div class="text-sm font-semibold opacity-90">Margem Bruta</div>
                <div class="text-2xl font-bold mt-1">
                    {{ number_format($incomeStatementFunction['gross_margin_percent'], 1, ',', '.') }}%
                </div>
                <div class="text-xs opacity-75 mt-1">Margem Bruta / Vendas</div>
            </div>

            <div class="bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg p-4 text-white">
                <div class="text-sm font-semibold opacity-90">Margem Operacional</div>
                <div class="text-2xl font-bold mt-1">
                    {{ number_format($incomeStatementFunction['operating_margin_percent'], 1, ',', '.') }}%
                </div>
                <div class="text-xs opacity-75 mt-1">EBIT / Vendas</div>
            </div>

            <div class="bg-gradient-to-br from-emerald-500 to-green-600 rounded-lg p-4 text-white">
                <div class="text-sm font-semibold opacity-90">Margem Líquida</div>
                <div class="text-2xl font-bold mt-1">
                    {{ number_format($incomeStatementFunction['net_margin_percent'], 1, ',', '.') }}%
                </div>
                <div class="text-xs opacity-75 mt-1">Resultado Líquido / Vendas</div>
            </div>
        </div>

        {{-- Info sobre alocações --}}
        <div class="mt-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-amber-600 text-lg mr-3 mt-1"></i>
                <div class="text-sm text-amber-900">
                    <p class="font-semibold mb-1">Sistema de Alocação por Função</p>
                    <p>Os gastos operacionais são alocados por função (Custo Vendas, Distribuição, Administrativo) com base em matriz de alocação configurável. Alocações padrão são aplicadas quando não configuradas.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer Info --}}
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
        <div class="flex items-center text-sm text-gray-600">
            <i class="fas fa-info-circle mr-2 text-pink-600"></i>
            <span><strong>SNC Angola:</strong> Demonstração de Resultados por Funções com sistema de alocação funcional</span>
        </div>
    </div>
</div>
@endif
