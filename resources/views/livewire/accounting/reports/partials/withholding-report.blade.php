{{-- Mapa de Retenções na Fonte --}}
@if($reportType === 'withholding' && $withholding)
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-hand-holding-usd text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Mapa de Retenções na Fonte</h2>
                    <p class="text-purple-100 text-sm">Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-purple-100 text-xs uppercase">Total Retido</p>
                <p class="text-3xl font-bold">{{ number_format($withholding['total'], 2, ',', '.') }} Kz</p>
                <div class="flex gap-2 mt-3 justify-end">
                    <button wire:click="exportPDF" class="px-3 py-1.5 bg-white text-purple-600 rounded-lg hover:bg-purple-50 transition text-sm font-semibold">
                        <i class="fas fa-file-pdf mr-1"></i>PDF
                    </button>
                    <button wire:click="exportExcel" class="px-3 py-1.5 bg-white text-purple-600 rounded-lg hover:bg-purple-50 transition text-sm font-semibold">
                        <i class="fas fa-file-excel mr-1"></i>Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Cards por tipo --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($withholding['types'] as $key => $type)
        <div class="bg-white rounded-2xl shadow-lg p-6 border border-purple-100">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-purple-600">{{ $type['name'] }}</p>
                <span class="text-xs text-gray-400">{{ $type['count'] }} mov.</span>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($type['total'], 2, ',', '.') }} Kz</p>
        </div>
        @endforeach
    </div>

    {{-- Detalhe --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-bold text-gray-900"><i class="fas fa-list mr-2 text-purple-600"></i>Movimentos Detalhados</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Documento</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Conta</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Descrição</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Valor Retido</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @php
                        $allLines = collect($withholding['lines'])->sortBy('date');
                    @endphp
                    @forelse($allLines as $line)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-600">{{ $line['date'] ? \Carbon\Carbon::parse($line['date'])->format('d/m/Y') : '-' }}</td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $line['ref'] ?: '-' }}</td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-600">{{ $line['account_code'] }} — {{ $line['account_name'] }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ $line['narration'] ?: '-' }}</td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm font-semibold text-right text-gray-900">{{ number_format($line['amount'], 2, ',', '.') }} Kz</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                <i class="fas fa-inbox text-4xl mb-2"></i>
                                <p class="text-sm">Sem retenções no período. (Requer contas com integration_key <code>withholding_irt</code>/<code>withholding_services</code> — presentes no plano PGC-AO.)</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($allLines->count() > 0)
                <tfoot class="bg-purple-50">
                    <tr>
                        <td colspan="4" class="px-6 py-3 text-right text-sm font-bold text-gray-900">TOTAL RETIDO</td>
                        <td class="px-6 py-3 text-right text-lg font-bold text-purple-700">{{ number_format($withholding['total'], 2, ',', '.') }} Kz</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endif
