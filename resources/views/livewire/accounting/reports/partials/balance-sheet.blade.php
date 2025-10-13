{{-- Balance Sheet (Balanço) --}}
@if($reportType === 'balance_sheet' && $balanceSheet)
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 bg-gradient-to-r from-teal-600 to-cyan-600 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-balance-scale mr-2"></i>
                Balanço - Demonstração da Posição Financeira
            </h2>
            <p class="text-teal-100 text-sm">Em {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</p>
        </div>
        <div class="flex gap-2">
            <button wire:click="exportPDF" class="px-4 py-2 bg-white text-teal-600 rounded-lg hover:bg-teal-50 transition font-semibold">
                <i class="fas fa-file-pdf mr-2"></i>PDF
            </button>
            <button wire:click="exportExcel" class="px-4 py-2 bg-white text-teal-600 rounded-lg hover:bg-teal-50 transition font-semibold">
                <i class="fas fa-file-excel mr-2"></i>Excel
            </button>
        </div>
    </div>

    <div class="p-6">
        {{-- Status de Validação --}}
        @if($balanceSheet['balanced'])
        <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 text-xl mr-3"></i>
                <div>
                    <p class="font-semibold text-green-900">Balanço Balanceado</p>
                    <p class="text-sm text-green-700">Activo = Passivo + Capital Próprio</p>
                </div>
            </div>
        </div>
        @else
        <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl mr-3"></i>
                <div>
                    <p class="font-semibold text-red-900">Balanço Desbalanceado</p>
                    <p class="text-sm text-red-700">Diferença: {{ number_format($balanceSheet['difference'], 2, ',', '.') }} Kz</p>
                </div>
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- ACTIVO --}}
            <div class="border-2 border-teal-200 rounded-lg overflow-hidden">
                <div class="bg-teal-50 px-4 py-3 border-b-2 border-teal-200">
                    <h3 class="text-lg font-bold text-teal-900 uppercase">ACTIVO</h3>
                </div>
                
                <div class="p-4 space-y-4">
                    {{-- Activo Não Corrente --}}
                    <div>
                        <h4 class="text-sm font-bold text-gray-800 bg-gray-100 px-3 py-2 rounded mb-2">
                            {{ $balanceSheet['activo']['activo_nao_corrente']['label'] }}
                        </h4>
                        <div class="space-y-1 pl-3">
                            @foreach($balanceSheet['activo']['activo_nao_corrente']['items'] as $key => $item)
                            <div class="flex justify-between py-1 text-sm hover:bg-gray-50">
                                <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                                <span class="font-mono font-semibold text-gray-900">
                                    {{ number_format($item['total'], 2, ',', '.') }}
                                </span>
                            </div>
                            @endforeach
                        </div>
                        <div class="flex justify-between py-2 font-semibold text-teal-900 border-t mt-2 pl-3">
                            <span>Subtotal</span>
                            <span class="font-mono">
                                {{ number_format(collect($balanceSheet['activo']['activo_nao_corrente']['items'])->sum('total'), 2, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    {{-- Activo Corrente --}}
                    <div>
                        <h4 class="text-sm font-bold text-gray-800 bg-gray-100 px-3 py-2 rounded mb-2">
                            {{ $balanceSheet['activo']['activo_corrente']['label'] }}
                        </h4>
                        <div class="space-y-1 pl-3">
                            @foreach($balanceSheet['activo']['activo_corrente']['items'] as $key => $item)
                            <div class="flex justify-between py-1 text-sm hover:bg-gray-50">
                                <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                                <span class="font-mono font-semibold text-gray-900">
                                    {{ number_format($item['total'], 2, ',', '.') }}
                                </span>
                            </div>
                            @endforeach
                        </div>
                        <div class="flex justify-between py-2 font-semibold text-teal-900 border-t mt-2 pl-3">
                            <span>Subtotal</span>
                            <span class="font-mono">
                                {{ number_format(collect($balanceSheet['activo']['activo_corrente']['items'])->sum('total'), 2, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    {{-- Total Activo --}}
                    <div class="bg-teal-100 px-4 py-3 rounded-lg border-2 border-teal-300">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-teal-900">TOTAL ACTIVO</span>
                            <span class="text-xl font-mono font-bold text-teal-900">
                                {{ number_format($balanceSheet['total_activo'], 2, ',', '.') }} Kz
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- PASSIVO + CAPITAL PRÓPRIO --}}
            <div class="space-y-4">
                {{-- PASSIVO --}}
                <div class="border-2 border-red-200 rounded-lg overflow-hidden">
                    <div class="bg-red-50 px-4 py-3 border-b-2 border-red-200">
                        <h3 class="text-lg font-bold text-red-900 uppercase">PASSIVO</h3>
                    </div>
                    
                    <div class="p-4 space-y-4">
                        {{-- Passivo Não Corrente --}}
                        <div>
                            <h4 class="text-sm font-bold text-gray-800 bg-gray-100 px-3 py-2 rounded mb-2">
                                {{ $balanceSheet['passivo']['passivo_nao_corrente']['label'] }}
                            </h4>
                            <div class="space-y-1 pl-3">
                                @foreach($balanceSheet['passivo']['passivo_nao_corrente']['items'] as $key => $item)
                                <div class="flex justify-between py-1 text-sm hover:bg-gray-50">
                                    <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                                    <span class="font-mono font-semibold text-gray-900">
                                        {{ number_format($item['total'], 2, ',', '.') }}
                                    </span>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Passivo Corrente --}}
                        <div>
                            <h4 class="text-sm font-bold text-gray-800 bg-gray-100 px-3 py-2 rounded mb-2">
                                {{ $balanceSheet['passivo']['passivo_corrente']['label'] }}
                            </h4>
                            <div class="space-y-1 pl-3">
                                @foreach($balanceSheet['passivo']['passivo_corrente']['items'] as $key => $item)
                                <div class="flex justify-between py-1 text-sm hover:bg-gray-50">
                                    <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                                    <span class="font-mono font-semibold text-gray-900">
                                        {{ number_format($item['total'], 2, ',', '.') }}
                                    </span>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Total Passivo --}}
                        <div class="bg-red-100 px-4 py-2 rounded border-t-2 border-red-300">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-red-900">TOTAL PASSIVO</span>
                                <span class="font-mono font-bold text-red-900">
                                    {{ number_format($balanceSheet['total_passivo'], 2, ',', '.') }} Kz
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CAPITAL PRÓPRIO --}}
                <div class="border-2 border-blue-200 rounded-lg overflow-hidden">
                    <div class="bg-blue-50 px-4 py-3 border-b-2 border-blue-200">
                        <h3 class="text-lg font-bold text-blue-900 uppercase">CAPITAL PRÓPRIO</h3>
                    </div>
                    
                    <div class="p-4 space-y-4">
                        <div class="space-y-1">
                            @foreach($balanceSheet['capital_proprio']['capital_proprio']['items'] as $key => $item)
                            <div class="flex justify-between py-1 text-sm hover:bg-gray-50 pl-3">
                                <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                                <span class="font-mono font-semibold text-gray-900">
                                    {{ number_format($item['total'], 2, ',', '.') }}
                                </span>
                            </div>
                            @endforeach
                        </div>

                        {{-- Total Capital Próprio --}}
                        <div class="bg-blue-100 px-4 py-2 rounded border-t-2 border-blue-300">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-blue-900">TOTAL CAPITAL PRÓPRIO</span>
                                <span class="font-mono font-bold text-blue-900">
                                    {{ number_format($balanceSheet['total_capital_proprio'], 2, ',', '.') }} Kz
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Total Passivo + Capital Próprio --}}
                <div class="bg-gradient-to-r from-purple-100 to-blue-100 px-4 py-3 rounded-lg border-2 border-purple-300">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold text-purple-900">PASSIVO + CAPITAL PRÓPRIO</span>
                        <span class="text-xl font-mono font-bold text-purple-900">
                            {{ number_format($balanceSheet['total_passivo_capital'], 2, ',', '.') }} Kz
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Indicadores Financeiros --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gradient-to-br from-teal-500 to-cyan-600 rounded-lg p-4 text-white">
                <div class="text-sm font-semibold opacity-90">Liquidez Corrente</div>
                <div class="text-2xl font-bold mt-1">
                    {{ $balanceSheet['total_passivo'] > 0 ? number_format(collect($balanceSheet['activo']['activo_corrente']['items'])->sum('total') / collect($balanceSheet['passivo']['passivo_corrente']['items'])->sum('total'), 2, ',', '.') : '∞' }}
                </div>
                <div class="text-xs opacity-75 mt-1">Activo Corrente / Passivo Corrente</div>
            </div>

            <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg p-4 text-white">
                <div class="text-sm font-semibold opacity-90">Solvabilidade</div>
                <div class="text-2xl font-bold mt-1">
                    {{ $balanceSheet['total_activo'] > 0 ? number_format(($balanceSheet['total_capital_proprio'] / $balanceSheet['total_activo']) * 100, 1, ',', '.') : '0' }}%
                </div>
                <div class="text-xs opacity-75 mt-1">Capital Próprio / Activo Total</div>
            </div>

            <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-lg p-4 text-white">
                <div class="text-sm font-semibold opacity-90">Endividamento</div>
                <div class="text-2xl font-bold mt-1">
                    {{ $balanceSheet['total_activo'] > 0 ? number_format(($balanceSheet['total_passivo'] / $balanceSheet['total_activo']) * 100, 1, ',', '.') : '0' }}%
                </div>
                <div class="text-xs opacity-75 mt-1">Passivo Total / Activo Total</div>
            </div>
        </div>
    </div>

    {{-- Footer Info --}}
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
        <div class="flex items-center text-sm text-gray-600">
            <i class="fas fa-info-circle mr-2 text-teal-600"></i>
            <span><strong>SNC Angola:</strong> Balanço conforme o Sistema de Normalização Contabilística de Angola</span>
        </div>
    </div>
</div>
@endif
