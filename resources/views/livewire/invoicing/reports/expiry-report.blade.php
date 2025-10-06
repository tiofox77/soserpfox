<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">📊 Relatório de Validade de Produtos</h1>
        <p class="text-gray-600 mt-1">Controle e análise de produtos próximos da validade</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-600">Total c/ Validade</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $stats['total_with_expiry'] }}</p>
                </div>
                <i class="fas fa-boxes text-3xl text-blue-400"></i>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-600">Expira em 7 dias</p>
                    <p class="text-2xl font-bold text-red-600">{{ $stats['expiring_7_days'] }}</p>
                </div>
                <i class="fas fa-exclamation-circle text-3xl text-red-400"></i>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-600">Expira em 30 dias</p>
                    <p class="text-2xl font-bold text-orange-600">{{ $stats['expiring_30_days'] }}</p>
                </div>
                <i class="fas fa-exclamation-triangle text-3xl text-orange-400"></i>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-600">Já Expirados</p>
                    <p class="text-2xl font-bold text-gray-600">{{ $stats['expired'] }}</p>
                </div>
                <i class="fas fa-times-circle text-3xl text-gray-400"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-red-600 rounded-lg shadow p-4 text-white">
            <div>
                <p class="text-xs opacity-90">Valor em Risco</p>
                <p class="text-xl font-bold">{{ number_format($stats['value_at_risk'], 2, ',', '.') }} Kz</p>
                <p class="text-xs opacity-75 mt-1">Próximos 30 dias</p>
            </div>
        </div>

        <div class="bg-gradient-to-br from-gray-600 to-gray-800 rounded-lg shadow p-4 text-white">
            <div>
                <p class="text-xs opacity-90">Valor Perdido</p>
                <p class="text-xl font-bold">{{ number_format($stats['value_lost'], 2, ',', '.') }} Kz</p>
                <p class="text-xs opacity-75 mt-1">Produtos expirados</p>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-filter mr-2 text-blue-600"></i>Filtros
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Relatório</label>
                <select wire:model.live="reportType" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="expiring_soon">Expirando em Breve</option>
                    <option value="expired">Já Expirados</option>
                    <option value="all">Todos com Validade</option>
                </select>
            </div>

            @if($reportType === 'expiring_soon')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Dias</label>
                <select wire:model.live="daysFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="7">7 dias</option>
                    <option value="15">15 dias</option>
                    <option value="30">30 dias</option>
                    <option value="60">60 dias</option>
                    <option value="90">90 dias</option>
                </select>
            </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Armazém</label>
                <select wire:model.live="warehouseFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Categoria</label>
                <select wire:model.live="categoryFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Todas</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                <input type="text" wire:model.live="searchFilter" placeholder="Produto ou lote..." 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="flex items-end">
                <button wire:click="exportReport" 
                        class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-file-export mr-2"></i>Exportar
                </button>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-bold text-gray-800">
                Produtos Listados ({{ $batches->total() }})
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Produto</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Categoria</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Lote</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Armazém</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Validade</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Dias</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase">Qtd Disp.</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase">Valor</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-700 uppercase">Status</th>
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
                            <p>Nenhum produto encontrado com os filtros selecionados</p>
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
