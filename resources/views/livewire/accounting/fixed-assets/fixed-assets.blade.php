<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl shadow-lg p-6">
        <h1 class="text-3xl font-bold text-white flex items-center">
            <i class="fas fa-building mr-3"></i>
            Gestão de Imobilizado
        </h1>
        <p class="text-purple-100 mt-2">Ativos fixos e depreciações automáticas</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-90">Total Ativos</p>
                    <p class="text-3xl font-bold mt-1">{{ $totalAssets }}</p>
                </div>
                <i class="fas fa-box text-4xl opacity-50"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-90">Valor Aquisição</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($totalValue, 0) }} Kz</p>
                </div>
                <i class="fas fa-money-bill-wave text-4xl opacity-50"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-red-500 to-pink-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-90">Depreciação Acum.</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($totalDepreciation, 0) }} Kz</p>
                </div>
                <i class="fas fa-chart-line text-4xl opacity-50"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm opacity-90">Valor Líquido</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($totalBookValue, 0) }} Kz</p>
                </div>
                <i class="fas fa-balance-scale text-4xl opacity-50"></i>
            </div>
        </div>
    </div>

    {{-- Actions Bar --}}
    <div class="bg-white rounded-xl shadow-lg p-4 mb-6 flex items-center justify-between">
        <div class="flex gap-2">
            <button wire:click="$set('showModal', true)" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-semibold">
                <i class="fas fa-plus mr-2"></i>Novo Ativo
            </button>
            <button wire:click="calculateDepreciations" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold">
                <i class="fas fa-calculator mr-2"></i>Calcular Depreciações
            </button>
        </div>

        <div class="flex gap-2">
            <select wire:model.live="filterStatus" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                <option value="">Todos Status</option>
                <option value="active">Ativo</option>
                <option value="fully_depreciated">Depreciado</option>
                <option value="sold">Vendido</option>
            </select>
        </div>
    </div>

    {{-- Assets Table --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-purple-50 to-indigo-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Categoria</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Valor Aquisição</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Deprec. Acum.</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Valor Líquido</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($assets as $asset)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ $asset->code }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $asset->name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $asset->category->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-right font-mono text-gray-900">
                            {{ number_format($asset->acquisition_value, 2, ',', '.') }} Kz
                        </td>
                        <td class="px-6 py-4 text-sm text-right font-mono text-red-600">
                            ({{ number_format($asset->accumulated_depreciation, 2, ',', '.') }}) Kz
                        </td>
                        <td class="px-6 py-4 text-sm text-right font-mono font-bold text-purple-600">
                            {{ number_format($asset->book_value, 2, ',', '.') }} Kz
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($asset->status === 'active')
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Ativo</span>
                            @elseif($asset->status === 'fully_depreciated')
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Depreciado</span>
                            @else
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">{{ ucfirst($asset->status) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="edit({{ $asset->id }})" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button wire:click="viewDepreciations({{ $asset->id }})" class="text-purple-600 hover:text-purple-900">
                                    <i class="fas fa-chart-line"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-box-open text-4xl text-gray-300 mb-4"></i>
                            <p>Nenhum ativo fixo cadastrado</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            {{ $assets->links() }}
        </div>
    </div>

    {{-- Form Modal --}}
    @if($showModal)
    @include('livewire.accounting.fixed-assets.partials.form-modal')
    @endif

    {{-- Depreciation Modal --}}
    @if($showDepreciationModal)
    @include('livewire.accounting.fixed-assets.partials.depreciation-modal')
    @endif
</div>
