<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-orange-500 to-red-600 rounded-2xl shadow-lg p-4 sm:p-6 text-white">
        <div class="flex items-center">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3 sm:mr-4">
                <i class="fas fa-calendar-check text-xl sm:text-2xl"></i>
            </div>
            <div>
                <h1 class="text-lg sm:text-2xl font-bold">{{ __('Relatório de Validade de Produtos') }}</h1>
                <p class="text-orange-100 text-xs sm:text-sm">{{ __('Controle e análise de produtos próximos da validade') }}</p>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-4 border border-blue-100">
            <div class="w-11 h-11 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/40 mb-3">
                <i class="fas fa-boxes text-white text-lg"></i>
            </div>
            <p class="text-xs text-blue-600 font-semibold mb-1">{{ __('Total c/ Validade') }}</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total_with_expiry'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 border border-red-100">
            <div class="w-11 h-11 bg-gradient-to-br from-red-500 to-rose-600 rounded-xl flex items-center justify-center shadow-lg shadow-red-500/40 mb-3">
                <i class="fas fa-exclamation-circle text-white text-lg"></i>
            </div>
            <p class="text-xs text-red-600 font-semibold mb-1">{{ __('Expira em 7 dias') }}</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['expiring_7_days'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 border border-orange-100">
            <div class="w-11 h-11 bg-gradient-to-br from-orange-500 to-amber-600 rounded-xl flex items-center justify-center shadow-lg shadow-orange-500/40 mb-3">
                <i class="fas fa-exclamation-triangle text-white text-lg"></i>
            </div>
            <p class="text-xs text-orange-600 font-semibold mb-1">{{ __('Expira em 30 dias') }}</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['expiring_30_days'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 border border-gray-100">
            <div class="w-11 h-11 bg-gradient-to-br from-gray-500 to-gray-700 rounded-xl flex items-center justify-center shadow-lg shadow-gray-500/40 mb-3">
                <i class="fas fa-times-circle text-white text-lg"></i>
            </div>
            <p class="text-xs text-gray-600 font-semibold mb-1">{{ __('Já Expirados') }}</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['expired'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 border border-red-100">
            <div class="w-11 h-11 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center shadow-lg shadow-red-500/40 mb-3">
                <i class="fas fa-fire text-white text-lg"></i>
            </div>
            <p class="text-xs text-red-600 font-semibold mb-1">{{ __('Valor em Risco') }}</p>
            <p class="text-base font-bold text-gray-900">{{ number_format($stats['value_at_risk'], 2, ',', '.') }} <span class="text-xs text-gray-500">Kz</span></p>
            <p class="text-[10px] text-gray-400 mt-0.5">{{ __('Próximos 30 dias') }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 border border-gray-100">
            <div class="w-11 h-11 bg-gradient-to-br from-gray-600 to-gray-800 rounded-xl flex items-center justify-center shadow-lg shadow-gray-500/40 mb-3">
                <i class="fas fa-ban text-white text-lg"></i>
            </div>
            <p class="text-xs text-gray-600 font-semibold mb-1">{{ __('Valor Perdido') }}</p>
            <p class="text-base font-bold text-gray-900">{{ number_format($stats['value_lost'], 2, ',', '.') }} <span class="text-xs text-gray-500">Kz</span></p>
            <p class="text-[10px] text-gray-400 mt-0.5">{{ __('Produtos expirados') }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-900 flex items-center mb-4">
            <i class="fas fa-filter mr-2 text-orange-600"></i>{{ __('Filtros') }}
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-6 gap-3 sm:gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">{{ __('Tipo de Relatório') }}</label>
                <select wire:model.live="reportType" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition text-sm bg-white">
                    <option value="expiring_soon">{{ __('Expirando em Breve') }}</option>
                    <option value="expired">{{ __('Já Expirados') }}</option>
                    <option value="all">{{ __('Todos com Validade') }}</option>
                </select>
            </div>

            @if($reportType === 'expiring_soon')
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">{{ __('Dias') }}</label>
                <select wire:model.live="daysFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition text-sm bg-white">
                    <option value="7">{{ __('7 dias') }}</option>
                    <option value="15">{{ __('15 dias') }}</option>
                    <option value="30">{{ __('30 dias') }}</option>
                    <option value="60">{{ __('60 dias') }}</option>
                    <option value="90">{{ __('90 dias') }}</option>
                </select>
            </div>
            @endif

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">{{ __('Armazém') }}</label>
                <select wire:model.live="warehouseFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition text-sm bg-white">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">{{ __('Categoria') }}</label>
                <select wire:model.live="categoryFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition text-sm bg-white">
                    <option value="">{{ __('Todas') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase">{{ __('Buscar') }}</label>
                <input type="text" wire:model.live="searchFilter" placeholder="{{ __('Produto ou lote...') }}" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition text-sm">
            </div>

            <div class="flex items-end">
                <button wire:click="exportReport" 
                        class="w-full px-4 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:from-green-700 hover:to-emerald-700 transition font-semibold text-sm shadow-lg">
                    <i class="fas fa-file-export mr-2"></i>{{ __('Exportar') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-orange-50 to-red-50">
            <h3 class="text-lg font-bold text-gray-900">
                Produtos Listados ({{ $batches->total() }})
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gradient-to-r from-orange-50 to-red-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Produto') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Categoria') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Lote') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">{{ __('Armazém') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Validade') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Dias') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase">{{ __('Qtd Disp.') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase">{{ __('Valor') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($batches as $batch)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $batch->product->name }}</div>
                            <div class="text-sm text-gray-500">{{ $batch->product->code }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $batch->product->category->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded font-mono text-xs">
                                {{ $batch->batch_number ?: 'S/N' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ $batch->warehouse->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="text-sm font-semibold text-gray-900">
                                {{ $batch->expiry_date->format('d/m/Y') }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php
                                $days = $batch->days_until_expiry;
                            @endphp
                            @if($days < 0)
                                <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-bold">
                                    {{ abs($days) }} dias atrás
                                </span>
                            @elseif($days <= 7)
                                <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-bold">
                                    {{ $days }} dias
                                </span>
                            @elseif($days <= 30)
                                <span class="px-3 py-1 bg-orange-100 text-orange-800 rounded-full text-sm font-bold">
                                    {{ $days }} dias
                                </span>
                            @else
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">
                                    {{ $days }} dias
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="text-sm font-bold text-gray-900">
                                {{ number_format($batch->quantity_available, 2, ',', '.') }}
                            </div>
                            <div class="text-xs text-gray-500">{{ $batch->product->unit ?? 'UN' }}</div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="text-sm font-bold text-gray-900">
                                {{ number_format($batch->quantity_available * $batch->cost_price, 2, ',', '.') }} Kz
                            </div>
                            <div class="text-xs text-gray-500">
                                @ {{ number_format($batch->cost_price, 2, ',', '.') }} Kz
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full 
                                {{ $batch->status_color === 'green' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $batch->status_color === 'orange' ? 'bg-orange-100 text-orange-800' : '' }}
                                {{ $batch->status_color === 'red' ? 'bg-red-100 text-red-800' : '' }}">
                                {{ $batch->status_label }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
                            <p>{{ __('Nenhum produto encontrado com os filtros selecionados') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t">
            {{ $batches->links() }}
        </div>
    </div>
</div>
