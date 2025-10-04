<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-receipt mr-3 text-blue-600"></i>
                    Recibos
                </h2>
                <p class="text-gray-600 mt-1">Comprovantes de pagamento</p>
            </div>
            <a href="{{ route('invoicing.receipts.create') }}" 
               class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl font-bold transition shadow-lg transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Novo Recibo
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-200 text-xs font-medium">Total</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-receipt text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">Vendas</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['sales'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-shopping-cart text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-200 text-xs font-medium">Compras</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['purchases'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-box text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-200 text-xs font-medium">Valor Total</p>
                    <p class="text-2xl font-bold mt-1">{{ number_format($stats['total_amount'], 2) }}</p>
                    <p class="text-purple-200 text-xs">AOA</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-money-bill-wave text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-xl shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <input type="text" wire:model.live="search" placeholder="Pesquisar..." 
                   class="rounded-lg border-gray-300">
            
            <select wire:model.live="filterType" class="rounded-lg border-gray-300">
                <option value="">Todos os Tipos</option>
                <option value="sale">Vendas</option>
                <option value="purchase">Compras</option>
            </select>
            
            <select wire:model.live="filterStatus" class="rounded-lg border-gray-300">
                <option value="">Todos os Status</option>
                <option value="issued">Emitido</option>
                <option value="cancelled">Cancelado</option>
            </select>
            
            <input type="date" wire:model.live="filterDateFrom" class="rounded-lg border-gray-300">
            <input type="date" wire:model.live="filterDateTo" class="rounded-lg border-gray-300">
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-hashtag mr-1 text-blue-600"></i>Número
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-tag mr-1 text-blue-600"></i>Tipo
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-user mr-1 text-blue-600"></i>Cliente/Fornecedor
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-money-bill mr-1 text-blue-600"></i>Valor
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-calendar mr-1 text-blue-600"></i>Data
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-info-circle mr-1 text-blue-600"></i>Status
                    </th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-cog mr-1 text-blue-600"></i>Ações
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($receipts as $receipt)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="font-bold text-blue-600">{{ $receipt->receipt_number }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($receipt->type === 'sale')
                            <span class="inline-flex items-center px-3 py-1 bg-gradient-to-r from-green-100 to-green-200 text-green-800 text-xs font-bold rounded-full">
                                <i class="fas fa-shopping-cart mr-1"></i>Venda
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 bg-gradient-to-r from-orange-100 to-orange-200 text-orange-800 text-xs font-bold rounded-full">
                                <i class="fas fa-box mr-1"></i>Compra
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <i class="fas fa-user-circle text-gray-400 mr-2"></i>
                            <span class="font-medium text-gray-900">{{ $receipt->entity_name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-lg font-bold text-blue-600">{{ number_format($receipt->amount_paid, 2) }}</span>
                        <span class="text-xs text-gray-500 ml-1">AOA</span>
                    </td>
                    <td class="px-6 py-4 text-gray-600">
                        <i class="fas fa-calendar-alt mr-1 text-blue-400"></i>
                        {{ $receipt->payment_date->format('d/m/Y') }}
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-3 py-1 bg-gradient-to-r from-{{ $receipt->status_color }}-100 to-{{ $receipt->status_color }}-200 text-{{ $receipt->status_color }}-800 text-xs font-bold rounded-full">
                            <i class="fas fa-circle mr-1 text-xs"></i>
                            {{ $receipt->status_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('invoicing.receipts.pdf', $receipt->id) }}" 
                               target="_blank"
                               class="p-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition transform hover:scale-110" 
                               title="PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <a href="{{ route('invoicing.receipts.edit', $receipt->id) }}" 
                               class="p-2 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 transition transform hover:scale-110" 
                               title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button wire:click="viewReceipt({{ $receipt->id }})" 
                                    class="p-2 bg-purple-100 text-purple-600 rounded-lg hover:bg-purple-200 transition transform hover:scale-110" 
                                    title="Visualizar">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button wire:click="confirmDelete({{ $receipt->id }})" 
                                    class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition transform hover:scale-110" 
                                    title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                        Nenhum recibo encontrado
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="px-6 py-4">
            {{ $receipts->links() }}
        </div>
    </div>

    {{-- Delete Modal --}}
    @if($showDeleteModal)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center animate-fade-in">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full shadow-2xl transform animate-scale-in">
            <div class="text-center mb-6">
                <div class="bg-red-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Eliminar Recibo?</h3>
                <p class="text-gray-600">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="flex gap-3">
                <button wire:click="$set('showDeleteModal', false)" 
                        class="flex-1 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-bold transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button wire:click="deleteReceipt" 
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white rounded-xl font-bold transition shadow-lg">
                    <i class="fas fa-trash mr-2"></i>Eliminar
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Animations CSS --}}
    <style>
        @keyframes fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes scale-in {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .animate-fade-in {
            animation: fade-in 0.2s ease-out;
        }
        .animate-scale-in {
            animation: scale-in 0.2s ease-out;
        }
    </style>
</div>
