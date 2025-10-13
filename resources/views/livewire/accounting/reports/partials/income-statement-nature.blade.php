{{-- Income Statement by Nature (DR por Natureza) --}}
@if($reportType === 'income_statement_nature' && $incomeStatementNature)
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-list-alt mr-2"></i>
                Demonstração de Resultados por Natureza (DRN)
            </h2>
            <p class="text-indigo-100 text-sm">Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</p>
        </div>
        <button class="px-4 py-2 bg-white text-indigo-600 rounded-lg hover:bg-indigo-50 transition font-semibold">
            <i class="fas fa-download mr-2"></i>Exportar PDF
        </button>
    </div>

    <div class="p-6">
        {{-- RENDIMENTOS --}}
        <div class="mb-6">
            <h3 class="text-lg font-bold text-gray-900 bg-green-50 px-4 py-3 rounded-lg border-l-4 border-green-600 mb-4">
                RENDIMENTOS
            </h3>
            
            <div class="space-y-2 pl-4">
                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Vendas e Prestações de Serviços</span>
                        <span class="text-xs text-gray-500 ml-2">(71)</span>
                    </div>
                    <span class="font-mono font-bold text-green-600">
                        {{ number_format($incomeStatementNature['vendas_servicos']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Subsídios à Exploração</span>
                        <span class="text-xs text-gray-500 ml-2">(75)</span>
                    </div>
                    <span class="font-mono text-green-600">
                        {{ number_format($incomeStatementNature['subsidios_exploracao']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Variações nos Inventários da Produção</span>
                        <span class="text-xs text-gray-500 ml-2">(73)</span>
                    </div>
                    <span class="font-mono text-green-600">
                        {{ number_format($incomeStatementNature['variacoes_producao']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Trabalhos para a Própria Empresa</span>
                        <span class="text-xs text-gray-500 ml-2">(72)</span>
                    </div>
                    <span class="font-mono text-green-600">
                        {{ number_format($incomeStatementNature['trabalhos_propria_empresa']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Outros Rendimentos</span>
                        <span class="text-xs text-gray-500 ml-2">(74)</span>
                    </div>
                    <span class="font-mono text-green-600">
                        {{ number_format($incomeStatementNature['outros_rendimentos']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>
            </div>
        </div>

        {{-- GASTOS --}}
        <div class="mb-6">
            <h3 class="text-lg font-bold text-gray-900 bg-red-50 px-4 py-3 rounded-lg border-l-4 border-red-600 mb-4">
                GASTOS
            </h3>
            
            <div class="space-y-2 pl-4">
                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Custo das Mercadorias Vendidas e Matérias Consumidas</span>
                        <span class="text-xs text-gray-500 ml-2">(61)</span>
                    </div>
                    <span class="font-mono font-bold text-red-600">
                        {{ number_format($incomeStatementNature['cmvmc']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Fornecimentos e Serviços de Terceiros</span>
                        <span class="text-xs text-gray-500 ml-2">(62)</span>
                    </div>
                    <span class="font-mono text-red-600">
                        {{ number_format($incomeStatementNature['fst']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Gastos com Pessoal</span>
                        <span class="text-xs text-gray-500 ml-2">(63)</span>
                    </div>
                    <span class="font-mono text-red-600">
                        {{ number_format($incomeStatementNature['gastos_pessoal']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Ajustamentos de Inventários</span>
                        <span class="text-xs text-gray-500 ml-2">(652, 653)</span>
                    </div>
                    <span class="font-mono text-red-600">
                        {{ number_format($incomeStatementNature['ajustamentos_inventarios']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Imparidades</span>
                        <span class="text-xs text-gray-500 ml-2">(65)</span>
                    </div>
                    <span class="font-mono text-red-600">
                        {{ number_format($incomeStatementNature['imparidades']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Provisões</span>
                        <span class="text-xs text-gray-500 ml-2">(67)</span>
                    </div>
                    <span class="font-mono text-red-600">
                        {{ number_format($incomeStatementNature['provisoes']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Depreciações e Amortizações</span>
                        <span class="text-xs text-gray-500 ml-2">(64)</span>
                    </div>
                    <span class="font-mono text-red-600">
                        {{ number_format($incomeStatementNature['depreciações']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Outros Gastos</span>
                        <span class="text-xs text-gray-500 ml-2">(68)</span>
                    </div>
                    <span class="font-mono text-red-600">
                        {{ number_format($incomeStatementNature['outros_gastos']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>
            </div>
        </div>

        {{-- RESULTADOS INTERMÉDIOS --}}
        <div class="space-y-3 mb-6">
            <div class="bg-blue-50 px-4 py-3 rounded-lg border-l-4 border-blue-600">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-bold text-blue-900">RESULTADO BRUTO</span>
                        <div class="text-xs text-blue-700 mt-1">
                            Margem: {{ number_format($incomeStatementNature['margem_bruta_percent'], 1, ',', '.') }}%
                        </div>
                    </div>
                    <span class="text-xl font-mono font-bold {{ $incomeStatementNature['resultado_bruto'] >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                        {{ number_format($incomeStatementNature['resultado_bruto'], 2, ',', '.') }} Kz
                    </span>
                </div>
            </div>

            <div class="bg-purple-50 px-4 py-3 rounded-lg border-l-4 border-purple-600">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="font-bold text-purple-900">RESULTADO OPERACIONAL (EBIT)</span>
                        <div class="text-xs text-purple-700 mt-1">
                            Margem: {{ number_format($incomeStatementNature['margem_operacional_percent'], 1, ',', '.') }}%
                        </div>
                    </div>
                    <span class="text-xl font-mono font-bold {{ $incomeStatementNature['resultado_operacional'] >= 0 ? 'text-purple-700' : 'text-red-700' }}">
                        {{ number_format($incomeStatementNature['resultado_operacional'], 2, ',', '.') }} Kz
                    </span>
                </div>
            </div>
        </div>

        {{-- RESULTADOS FINANCEIROS --}}
        <div class="mb-6">
            <h3 class="text-lg font-bold text-gray-900 bg-amber-50 px-4 py-3 rounded-lg border-l-4 border-amber-600 mb-4">
                RESULTADOS FINANCEIROS
            </h3>
            
            <div class="space-y-2 pl-4">
                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Juros e Rendimentos Similares</span>
                        <span class="text-xs text-gray-500 ml-2">(79)</span>
                    </div>
                    <span class="font-mono text-green-600">
                        {{ number_format($incomeStatementNature['juros_rendimentos_similares']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 hover:bg-gray-50 px-3 rounded">
                    <div>
                        <span class="text-gray-900 font-medium">Juros e Gastos Similares</span>
                        <span class="text-xs text-gray-500 ml-2">(69)</span>
                    </div>
                    <span class="font-mono text-red-600">
                        {{ number_format($incomeStatementNature['juros_gastos_similares']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>
            </div>

            <div class="bg-indigo-50 px-4 py-3 rounded-lg border-l-4 border-indigo-600 mt-3">
                <div class="flex justify-between items-center">
                    <span class="font-bold text-indigo-900">RESULTADO ANTES DE IMPOSTOS (EBT)</span>
                    <span class="text-xl font-mono font-bold {{ $incomeStatementNature['resultado_antes_impostos'] >= 0 ? 'text-indigo-700' : 'text-red-700' }}">
                        {{ number_format($incomeStatementNature['resultado_antes_impostos'], 2, ',', '.') }} Kz
                    </span>
                </div>
            </div>
        </div>

        {{-- IMPOSTO --}}
        <div class="mb-6">
            <div class="flex justify-between items-center py-3 hover:bg-gray-50 px-4 rounded">
                <div>
                    <span class="text-gray-900 font-medium">Imposto sobre o Rendimento</span>
                    <span class="text-xs text-gray-500 ml-2">(IRC - 89)</span>
                </div>
                <span class="font-mono text-red-600">
                    {{ number_format($incomeStatementNature['imposto_rendimento']['total'], 2, ',', '.') }} Kz
                </span>
            </div>
        </div>

        {{-- RESULTADO LÍQUIDO --}}
        <div class="bg-gradient-to-r {{ $incomeStatementNature['resultado_liquido'] >= 0 ? 'from-emerald-500 to-green-600' : 'from-red-500 to-rose-600' }} px-6 py-4 rounded-xl">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-white text-lg font-bold">RESULTADO LÍQUIDO DO PERÍODO</span>
                    <div class="text-sm text-white opacity-90 mt-1">
                        Margem Líquida: {{ number_format($incomeStatementNature['margem_liquida_percent'], 1, ',', '.') }}%
                    </div>
                </div>
                <span class="text-3xl font-mono font-bold text-white">
                    {{ number_format($incomeStatementNature['resultado_liquido'], 2, ',', '.') }} Kz
                </span>
            </div>
        </div>

        {{-- Indicadores de Performance --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gradient-to-br from-blue-500 to-cyan-600 rounded-lg p-4 text-white">
                <div class="text-sm font-semibold opacity-90">Margem Bruta</div>
                <div class="text-2xl font-bold mt-1">
                    {{ number_format($incomeStatementNature['margem_bruta_percent'], 1, ',', '.') }}%
                </div>
                <div class="text-xs opacity-75 mt-1">Resultado Bruto / Vendas</div>
            </div>

            <div class="bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg p-4 text-white">
                <div class="text-sm font-semibold opacity-90">Margem Operacional</div>
                <div class="text-2xl font-bold mt-1">
                    {{ number_format($incomeStatementNature['margem_operacional_percent'], 1, ',', '.') }}%
                </div>
                <div class="text-xs opacity-75 mt-1">EBIT / Total Rendimentos</div>
            </div>

            <div class="bg-gradient-to-br from-emerald-500 to-green-600 rounded-lg p-4 text-white">
                <div class="text-sm font-semibold opacity-90">Margem Líquida</div>
                <div class="text-2xl font-bold mt-1">
                    {{ number_format($incomeStatementNature['margem_liquida_percent'], 1, ',', '.') }}%
                </div>
                <div class="text-xs opacity-75 mt-1">Resultado Líquido / Total Rendimentos</div>
            </div>
        </div>
    </div>

    {{-- Footer Info --}}
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
        <div class="flex items-center text-sm text-gray-600">
            <i class="fas fa-info-circle mr-2 text-indigo-600"></i>
            <span><strong>SNC Angola:</strong> Demonstração de Resultados por Natureza conforme o Sistema de Normalização Contabilística</span>
        </div>
    </div>
</div>
@endif
