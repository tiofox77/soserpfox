{{-- Depreciation History Modal --}}
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 flex items-center justify-between rounded-t-xl">
            <h2 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-chart-line mr-2"></i>
                Histórico de Depreciações
            </h2>
            <button wire:click="$set('showDepreciationModal', false)" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <div class="p-6">
            {{-- Asset Info --}}
            @if($selectedAsset)
            <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Ativo</p>
                        <p class="font-bold text-gray-900">{{ $selectedAsset->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Valor Aquisição</p>
                        <p class="font-bold text-gray-900">{{ number_format($selectedAsset->acquisition_value, 2) }} Kz</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Deprec. Acumulada</p>
                        <p class="font-bold text-red-600">{{ number_format($selectedAsset->accumulated_depreciation, 2) }} Kz</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Valor Líquido</p>
                        <p class="font-bold text-purple-600">{{ number_format($selectedAsset->book_value, 2) }} Kz</p>
                    </div>
                </div>
            </div>
            @endif

            {{-- Depreciation Table --}}
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Período</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Depreciação</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Deprec. Acum.</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Valor Líquido</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($depreciations as $dep)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ \Carbon\Carbon::parse($dep->depreciation_date)->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $dep->period->name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-mono text-red-600">
                                {{ number_format($dep->depreciation_amount, 2, ',', '.') }} Kz
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-mono text-gray-900">
                                {{ number_format($dep->accumulated_depreciation, 2, ',', '.') }} Kz
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-mono font-bold text-purple-600">
                                {{ number_format($dep->book_value, 2, ',', '.') }} Kz
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($dep->status === 'posted')
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Lançado</span>
                                @else
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Rascunho</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i>
                                <p>Nenhuma depreciação registada</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Chart Placeholder --}}
            <div class="mt-6 p-6 bg-gradient-to-r from-purple-50 to-indigo-50 rounded-lg">
                <p class="text-center text-gray-600">
                    <i class="fas fa-chart-area text-3xl text-purple-400 mb-2"></i>
                    <br>Gráfico de evolução disponível em breve
                </p>
            </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end rounded-b-xl">
            <button wire:click="$set('showDepreciationModal', false)"
                    class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition font-semibold">
                Fechar
            </button>
        </div>
    </div>
</div>
