<div class="p-6">
    {{-- Header Amarelo --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-coins mr-3 text-yellow-600"></i>
                    Adiantamentos
                </h2>
                <p class="text-gray-600 mt-1">Pagamentos antecipados de clientes</p>
            </div>
            <a href="{{ route('invoicing.advances.create') }}" 
               class="px-6 py-3 bg-gradient-to-r from-yellow-600 to-amber-600 hover:from-yellow-700 hover:to-amber-700 text-white rounded-xl font-bold transition shadow-lg transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Novo Adiantamento
            </a>
        </div>
    </div>

    {{-- Stats Cards Amarelos --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-200 text-xs font-medium">Total</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-coins text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">Ativos</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['active'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-amber-200 text-xs font-medium">Valor Total</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($stats['total_amount'], 2) }}</p>
                    <p class="text-amber-200 text-xs">AOA</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-money-bill-wave text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-200 text-xs font-medium">Disponível</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($stats['available_amount'], 2) }}</p>
                    <p class="text-orange-200 text-xs">AOA</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-wallet text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-xl shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="text" wire:model.live="search" placeholder="Pesquisar..." class="rounded-lg border-gray-300">
            <select wire:model.live="filterStatus" class="rounded-lg border-gray-300">
                <option value="">Todos os Status</option>
                <option value="active">Ativo</option>
                <option value="used">Utilizado</option>
                <option value="cancelled">Cancelado</option>
            </select>
            <input type="date" wire:model.live="filterDateFrom" placeholder="Data inicial" class="rounded-lg border-gray-300">
            <input type="date" wire:model.live="filterDateTo" placeholder="Data final" class="rounded-lg border-gray-300">
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
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
                            <a href="{{ route('invoicing.advances.edit', $advance->id) }}" 
                               class="p-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition" 
                               title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button wire:click="confirmDelete({{ $advance->id }})" 
                                    class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition" 
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
        
        <div class="px-6 py-4">
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
