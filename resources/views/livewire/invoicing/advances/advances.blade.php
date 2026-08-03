<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-yellow-500 to-amber-600 rounded-2xl shadow-lg p-4 sm:p-6 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3 sm:mr-4">
                    <i class="fas fa-coins text-xl sm:text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-lg sm:text-2xl font-bold">Adiantamentos</h2>
                    <p class="text-yellow-100 text-xs sm:text-sm">Pagamentos antecipados de clientes</p>
                </div>
            </div>
            <a href="{{ route('invoicing.advances.create') }}" 
               class="bg-white text-amber-600 hover:bg-amber-50 px-4 sm:px-6 py-2 sm:py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105 text-sm sm:text-base">
                <i class="fas fa-plus mr-2"></i>Novo Adiantamento
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-yellow-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-yellow-500 to-amber-600 rounded-2xl flex items-center justify-center shadow-lg shadow-yellow-500/40 mb-3 sm:mb-4">
                <i class="fas fa-coins text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-yellow-600 font-semibold mb-1">Total</p>
            <p class="text-2xl sm:text-4xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-green-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/40 mb-3 sm:mb-4">
                <i class="fas fa-check-circle text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-green-600 font-semibold mb-1">Ativos</p>
            <p class="text-2xl sm:text-4xl font-bold text-gray-900">{{ $stats['active'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-amber-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg shadow-amber-500/40 mb-3 sm:mb-4">
                <i class="fas fa-money-bill-wave text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-amber-600 font-semibold mb-1">Valor Total</p>
            <p class="text-xl sm:text-3xl font-bold text-gray-900">{{ number_format($stats['total_amount'], 2) }} <span class="text-xs text-gray-500">AOA</span></p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-orange-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl flex items-center justify-center shadow-lg shadow-orange-500/40 mb-3 sm:mb-4">
                <i class="fas fa-wallet text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-orange-600 font-semibold mb-1">Disponível</p>
            <p class="text-xl sm:text-3xl font-bold text-gray-900">{{ number_format($stats['available_amount'], 2) }} <span class="text-xs text-gray-500">AOA</span></p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-4 sm:p-6">
        <h3 class="text-lg font-bold text-gray-900 flex items-center mb-4">
            <i class="fas fa-filter mr-2 text-amber-600"></i>Filtros
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 sm:gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-search mr-1"></i>Pesquisar</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Pesquisar..." class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-info-circle mr-1"></i>Status</label>
                <select wire:model.live="filterStatus" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition text-sm bg-white">
                    <option value="">Todos</option>
                    <option value="active">Ativo</option>
                    <option value="used">Utilizado</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-calendar-alt mr-1"></i>De</label>
                <input type="date" wire:model.live="filterDateFrom" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-calendar-alt mr-1"></i>Até</label>
                <input type="date" wire:model.live="filterDateTo" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition text-sm">
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-yellow-50 to-amber-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">
                        <i class="fas fa-hashtag mr-1 text-yellow-600"></i>Número
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">
                        <i class="fas fa-user mr-1 text-yellow-600"></i>Cliente
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">
                        <i class="fas fa-calendar mr-1 text-yellow-600"></i>Data
                    </th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase">
                        <i class="fas fa-money-bill mr-1 text-yellow-600"></i>Valor
                    </th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase">
                        <i class="fas fa-coins mr-1 text-yellow-600"></i>Usado
                    </th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase">
                        <i class="fas fa-wallet mr-1 text-yellow-600"></i>Disponível
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">
                        <i class="fas fa-info-circle mr-1 text-yellow-600"></i>Status
                    </th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">
                        <i class="fas fa-cog mr-1 text-yellow-600"></i>Ações
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($advances as $advance)
                <tr>
                    <td class="px-6 py-4">
                        <span class="font-bold text-yellow-600">{{ $advance->advance_number }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <i class="fas fa-user-circle text-gray-400 mr-2"></i>
                            <span class="font-medium">{{ $advance->client->name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-600">
                        {{ $advance->payment_date->format('d/m/Y') }}
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-lg font-bold text-gray-900">{{ number_format($advance->amount, 2) }}</span>
                        <span class="text-xs text-gray-500 ml-1">AOA</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-sm font-semibold text-gray-600">{{ number_format($advance->used_amount, 2) }}</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-lg font-bold text-yellow-600">{{ number_format($advance->remaining_amount, 2) }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-3 py-1 bg-gradient-to-r from-{{ $advance->status_color }}-100 to-{{ $advance->status_color }}-200 text-{{ $advance->status_color }}-800 text-xs font-bold rounded-full">
                            <i class="fas fa-circle mr-1 text-xs"></i>
                            {{ $advance->status_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('invoicing.advances.preview', $advance->id) }}" 
                               target="_blank"
                               class="p-2 bg-purple-100 text-purple-600 rounded-lg hover:bg-purple-200 transition" 
                               title="Preview HTML">
                                <i class="fas fa-file-alt"></i>
                            </a>
                            <a href="{{ route('invoicing.advances.pdf', $advance->id) }}" 
                               target="_blank"
                               class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition" 
                               title="Gerar PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <a href="{{ route('invoicing.advances.edit', $advance->id) }}" 
                               class="p-2 bg-indigo-100 text-indigo-600 rounded-lg hover:bg-indigo-200 transition" 
                               title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button wire:click="confirmDelete({{ $advance->id }})" 
                                    class="p-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition" 
                                    title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center">
                        <div class="flex flex-col items-center justify-center text-gray-400">
                            <i class="fas fa-coins text-6xl mb-4"></i>
                            <p class="text-lg font-medium">Nenhum adiantamento encontrado</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $advances->links() }}
        </div>
    </div>

    {{-- Delete Modal --}}
    @if($showDeleteModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full shadow-2xl">
            <div class="text-center mb-6">
                <div class="bg-red-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Eliminar Adiantamento?</h3>
                <p class="text-gray-600">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="flex gap-3">
                <button wire:click="$set('showDeleteModal', false)" 
                        class="flex-1 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-bold transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button wire:click="deleteAdvance" 
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white rounded-xl font-bold transition shadow-lg">
                    <i class="fas fa-trash mr-2"></i>Eliminar
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
