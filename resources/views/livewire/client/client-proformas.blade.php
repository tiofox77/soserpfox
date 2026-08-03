<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-fuchsia-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                <i class="fas fa-file-alt text-2xl"></i>
            </div>
            <div>
                <h2 class="text-2xl font-bold">Minhas Proformas</h2>
                <p class="text-purple-100 text-sm">Visualize suas proformas e cotações</p>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow p-5 border border-purple-100">
            <p class="text-xs text-gray-500 uppercase font-bold">Total</p>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow p-5 border border-amber-100">
            <p class="text-xs text-gray-500 uppercase font-bold">Em Aberto</p>
            <p class="text-3xl font-bold text-amber-600">{{ $stats['pending'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow p-5 border border-green-100">
            <p class="text-xs text-gray-500 uppercase font-bold">Convertidas</p>
            <p class="text-3xl font-bold text-green-600">{{ $stats['converted'] }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-search mr-1"></i>Pesquisar</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Número da proforma..."
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-tag mr-1"></i>Status</label>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="">Todos os status</option>
                    <option value="draft">📝 Rascunho</option>
                    <option value="sent">📤 Enviada</option>
                    <option value="converted">✅ Convertida</option>
                    <option value="expired">⌛ Expirada</option>
                    <option value="cancelled">❌ Cancelada</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Lista --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Número</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Válida até</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($proformas as $proforma)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">{{ $proforma->proforma_number }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ optional($proforma->proforma_date)->format('d/m/Y') ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ optional($proforma->valid_until)->format('d/m/Y') ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap font-semibold text-gray-900">{{ number_format($proforma->total ?? 0, 2, ',', '.') }} Kz</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $pfLabels = ['draft'=>['Rascunho','gray'],'sent'=>['Enviada','blue'],'converted'=>['Convertida','green'],'approved'=>['Aprovada','green'],'rejected'=>['Rejeitada','red'],'expired'=>['Expirada','amber'],'cancelled'=>['Cancelada','red']];
                                    [$pfLabel,$pfColor] = $pfLabels[$proforma->status] ?? [ucfirst($proforma->status),'gray'];
                                @endphp
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $pfColor }}-100 text-{{ $pfColor }}-800">{{ $pfLabel }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                <i class="fas fa-inbox text-4xl mb-2"></i>
                                <p class="text-sm">Nenhuma proforma encontrada</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($proformas->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">{{ $proformas->links() }}</div>
        @endif
    </div>
</div>
