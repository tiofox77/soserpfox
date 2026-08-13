<div>
    <div class="mb-6 bg-gradient-to-r from-slate-700 to-gray-800 rounded-2xl shadow-lg p-5 text-white flex items-center justify-between">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-3"><i class="fas fa-file-alt text-2xl"></i></div>
            <div><h2 class="text-2xl font-bold">{{ __('Mapa de Documentos') }}</h2><p class="text-gray-200 text-sm">{{ __('Resumo consolidado de documentos emitidos') }}</p></div>
        </div>
        <a href="{{ route('invoicing.reports.hub') }}" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition"><i class="fas fa-arrow-left mr-1"></i>{{ __('Voltar') }}</a>
    </div>

    <x-report-filters color="slate" />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($rows as $r)
            <div class="bg-white rounded-2xl shadow-md p-5 border-l-4 border-{{ $r['color'] }}-500 hover:shadow-xl transition">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-{{ $r['color'] }}-100 to-{{ $r['color'] }}-200 rounded-xl flex items-center justify-center">
                        <i class="fas {{ $r['icon'] }} text-{{ $r['color'] }}-600 text-xl"></i>
                    </div>
                    <span class="text-3xl font-bold text-gray-900">{{ $r['count'] }}</span>
                </div>
                <h4 class="font-bold text-gray-800">{{ $r['name'] }}</h4>
                <p class="text-sm text-gray-500 mt-1">{{ __('Total movimentado') }}</p>
                <p class="text-lg font-bold text-{{ $r['color'] }}-700">{{ number_format($r['total'], 2, ',', '.') }} <span class="text-xs text-gray-500">Kz</span></p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b">
            <h3 class="font-bold text-gray-800"><i class="fas fa-table mr-2 text-slate-600"></i>{{ __('Resumo Tabular') }}</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-3 py-2 text-left">{{ __('Tipo de Documento') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('Quantidade') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('Valor Total (Kz)') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($rows as $r)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-semibold"><i class="fas {{ $r['icon'] }} mr-2 text-{{ $r['color'] }}-500"></i>{{ $r['name'] }}</td>
                        <td class="px-3 py-2 text-right font-bold">{{ $r['count'] }}</td>
                        <td class="px-3 py-2 text-right font-bold text-{{ $r['color'] }}-700">{{ number_format($r['total'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
