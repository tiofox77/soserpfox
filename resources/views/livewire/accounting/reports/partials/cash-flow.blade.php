{{-- Cash Flow Statement (Demonstração de Fluxos de Caixa) --}}
@if($reportType === 'cash_flow' && $cashFlow)
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 bg-gradient-to-r from-cyan-600 to-blue-600 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-water mr-2"></i>
                Demonstração de Fluxos de Caixa (DFC)
            </h2>
            <p class="text-cyan-100 text-sm">Método Indireto - Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</p>
        </div>
        <button class="px-4 py-2 bg-white text-cyan-600 rounded-lg hover:bg-cyan-50 transition font-semibold">
            <i class="fas fa-download mr-2"></i>Exportar PDF
        </button>
    </div>

    <div class="p-6">
        {{-- Status Reconciliação --}}
        @if($cashFlow['reconciled'])
        <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 text-xl mr-3"></i>
                <div>
                    <p class="font-semibold text-green-900">Reconciliação OK</p>
                    <p class="text-sm text-green-700">Variação calculada = Variação real de caixa</p>
                </div>
            </div>
        </div>
        @else
        <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl mr-3"></i>
                <div>
                    <p class="font-semibold text-red-900">Erro de Reconciliação</p>
                    <p class="text-sm text-red-700">Diferença: {{ number_format($cashFlow['difference'], 2, ',', '.') }} Kz</p>
                </div>
            </div>
        </div>
        @endif

        {{-- ATIVIDADES OPERACIONAIS --}}
        <div class="mb-6">
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-4 py-3 rounded-lg border-l-4 border-green-600 mb-3">
                <h3 class="text-lg font-bold text-green-900">ATIVIDADES OPERACIONAIS</h3>
            </div>
            
            <div class="space-y-2 pl-4">
                <div class="flex justify-between items-center py-2 px-3 bg-gray-50 rounded">
                    <span class="text-gray-900 font-medium">Resultado Líquido do Período</span>
                    <span class="font-mono font-bold {{ $cashFlow['operating']['net_income'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format($cashFlow['operating']['net_income'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="mt-3 mb-2">
                    <p class="text-xs font-semibold text-gray-600 uppercase px-3">Ajustamentos:</p>
                </div>

                @foreach($cashFlow['operating']['adjustments'] as $key => $value)
                <div class="flex justify-between items-center py-1 px-3 hover:bg-gray-50 rounded text-sm">
                    <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                    <span class="font-mono text-gray-900">
                        + {{ number_format($value, 2, ',', '.') }} Kz
                    </span>
                </div>
                @endforeach

                <div class="mt-3 mb-2">
                    <p class="text-xs font-semibold text-gray-600 uppercase px-3">Variação Capital Circulante:</p>
                </div>

                @foreach($cashFlow['operating']['working_capital'] as $key => $value)
                <div class="flex justify-between items-center py-1 px-3 hover:bg-gray-50 rounded text-sm">
                    <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                    <span class="font-mono {{ $value >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $value >= 0 ? '+' : '' }} {{ number_format($value, 2, ',', '.') }} Kz
                    </span>
                </div>
                @endforeach
            </div>

            <div class="bg-green-100 px-4 py-3 rounded-lg border-t-2 border-green-300 mt-3">
                <div class="flex justify-between items-center">
                    <span class="font-bold text-green-900">FLUXO ATIVIDADES OPERACIONAIS</span>
                    <span class="text-xl font-mono font-bold {{ $cashFlow['operating']['total'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ number_format($cashFlow['operating']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>
            </div>
        </div>

        {{-- ATIVIDADES DE INVESTIMENTO --}}
        <div class="mb-6">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-3 rounded-lg border-l-4 border-blue-600 mb-3">
                <h3 class="text-lg font-bold text-blue-900">ATIVIDADES DE INVESTIMENTO</h3>
            </div>
            
            <div class="space-y-2 pl-4">
                @foreach($cashFlow['investment']['items'] as $key => $value)
                @if(abs($value) > 0.01)
                <div class="flex justify-between items-center py-2 px-3 hover:bg-gray-50 rounded">
                    <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                    <span class="font-mono {{ $value >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $value >= 0 ? '+' : '' }} {{ number_format($value, 2, ',', '.') }} Kz
                    </span>
                </div>
                @endif
                @endforeach

                @if(count(array_filter($cashFlow['investment']['items'], fn($v) => abs($v) > 0.01)) === 0)
                <div class="py-3 px-3 text-center text-gray-500 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>Sem movimentos no período
                </div>
                @endif
            </div>

            <div class="bg-blue-100 px-4 py-3 rounded-lg border-t-2 border-blue-300 mt-3">
                <div class="flex justify-between items-center">
                    <span class="font-bold text-blue-900">FLUXO ATIVIDADES DE INVESTIMENTO</span>
                    <span class="text-xl font-mono font-bold {{ $cashFlow['investment']['total'] >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                        {{ number_format($cashFlow['investment']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>
            </div>
        </div>

        {{-- ATIVIDADES DE FINANCIAMENTO --}}
        <div class="mb-6">
            <div class="bg-gradient-to-r from-purple-50 to-pink-50 px-4 py-3 rounded-lg border-l-4 border-purple-600 mb-3">
                <h3 class="text-lg font-bold text-purple-900">ATIVIDADES DE FINANCIAMENTO</h3>
            </div>
            
            <div class="space-y-2 pl-4">
                @foreach($cashFlow['financing']['items'] as $key => $value)
                @if(abs($value) > 0.01)
                <div class="flex justify-between items-center py-2 px-3 hover:bg-gray-50 rounded">
                    <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                    <span class="font-mono {{ $value >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $value >= 0 ? '+' : '' }} {{ number_format($value, 2, ',', '.') }} Kz
                    </span>
                </div>
                @endif
                @endforeach

                @if(count(array_filter($cashFlow['financing']['items'], fn($v) => abs($v) > 0.01)) === 0)
                <div class="py-3 px-3 text-center text-gray-500 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>Sem movimentos no período
                </div>
                @endif
            </div>

            <div class="bg-purple-100 px-4 py-3 rounded-lg border-t-2 border-purple-300 mt-3">
                <div class="flex justify-between items-center">
                    <span class="font-bold text-purple-900">FLUXO ATIVIDADES DE FINANCIAMENTO</span>
                    <span class="text-xl font-mono font-bold {{ $cashFlow['financing']['total'] >= 0 ? 'text-purple-700' : 'text-red-700' }}">
                        {{ number_format($cashFlow['financing']['total'], 2, ',', '.') }} Kz
                    </span>
                </div>
            </div>
        </div>

        {{-- VARIAÇÃO LÍQUIDA DE CAIXA --}}
        <div class="bg-gradient-to-r from-amber-100 to-yellow-100 px-6 py-4 rounded-xl border-2 border-amber-300 mb-6">
            <div class="flex justify-between items-center">
                <span class="text-lg font-bold text-amber-900">VARIAÇÃO LÍQUIDA DE CAIXA (Calculada)</span>
                <span class="text-2xl font-mono font-bold {{ $cashFlow['net_cash_change'] >= 0 ? 'text-amber-700' : 'text-red-700' }}">
                    {{ $cashFlow['net_cash_change'] >= 0 ? '+' : '' }} {{ number_format($cashFlow['net_cash_change'], 2, ',', '.') }} Kz
                </span>
            </div>
        </div>

        {{-- RECONCILIAÇÃO --}}
        <div class="bg-gray-50 rounded-xl p-6 border-2 border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-balance-scale mr-2 text-cyan-600"></i>
                RECONCILIAÇÃO DE CAIXA
            </h3>
            
            <div class="space-y-3">
                <div class="flex justify-between items-center py-2 px-4 bg-white rounded-lg">
                    <span class="text-gray-700 font-medium">Caixa e Equivalentes - Início do Período</span>
                    <span class="font-mono font-bold text-gray-900">
                        {{ number_format($cashFlow['cash_start'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 px-4 bg-white rounded-lg">
                    <span class="text-gray-700 font-medium">Variação Líquida de Caixa</span>
                    <span class="font-mono font-bold {{ $cashFlow['net_cash_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $cashFlow['net_cash_change'] >= 0 ? '+' : '' }} {{ number_format($cashFlow['net_cash_change'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-3 px-4 bg-gradient-to-r from-cyan-500 to-blue-600 rounded-lg text-white">
                    <span class="font-bold text-lg">Caixa e Equivalentes - Fim do Período</span>
                    <span class="font-mono font-bold text-2xl">
                        {{ number_format($cashFlow['cash_end'], 2, ',', '.') }} Kz
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 px-4 bg-gray-100 rounded-lg border-t-2 border-gray-300">
                    <span class="text-gray-700 text-sm">Variação Real (contabilizada):</span>
                    <span class="font-mono text-sm {{ $cashFlow['cash_change'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ $cashFlow['cash_change'] >= 0 ? '+' : '' }} {{ number_format($cashFlow['cash_change'], 2, ',', '.') }} Kz
                    </span>
                </div>

                @if(!$cashFlow['reconciled'])
                <div class="flex justify-between items-center py-2 px-4 bg-red-100 rounded-lg border-l-4 border-red-500">
                    <span class="text-red-900 font-medium">Diferença:</span>
                    <span class="font-mono font-bold text-red-700">
                        {{ number_format($cashFlow['difference'], 2, ',', '.') }} Kz
                    </span>
                </div>
                @endif
            </div>
        </div>

        {{-- Análise Visual --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-lg p-4 text-white">
                <div class="text-sm font-semibold opacity-90">Atividades Operacionais</div>
                <div class="text-2xl font-bold mt-1">
                    {{ number_format($cashFlow['operating']['total'], 0, ',', '.') }} Kz
                </div>
                <div class="text-xs opacity-75 mt-1">Geração de caixa operacional</div>
            </div>

            <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg p-4 text-white">
                <div class="text-sm font-semibold opacity-90">Atividades de Investimento</div>
                <div class="text-2xl font-bold mt-1">
                    {{ number_format($cashFlow['investment']['total'], 0, ',', '.') }} Kz
                </div>
                <div class="text-xs opacity-75 mt-1">Investimentos realizados</div>
            </div>

            <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-lg p-4 text-white">
                <div class="text-sm font-semibold opacity-90">Atividades de Financiamento</div>
                <div class="text-2xl font-bold mt-1">
                    {{ number_format($cashFlow['financing']['total'], 0, ',', '.') }} Kz
                </div>
                <div class="text-xs opacity-75 mt-1">Fontes de financiamento</div>
            </div>
        </div>
    </div>

    {{-- Footer Info --}}
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
        <div class="flex items-center text-sm text-gray-600">
            <i class="fas fa-info-circle mr-2 text-cyan-600"></i>
            <span><strong>Método Indireto:</strong> Ajusta o resultado líquido pelos itens sem efeito caixa e variações no capital circulante</span>
        </div>
    </div>
</div>
@endif
