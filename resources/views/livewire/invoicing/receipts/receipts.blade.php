<div class="p-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl shadow-lg p-4 sm:p-6 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3 sm:mr-4">
                    <i class="fas fa-receipt text-xl sm:text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-lg sm:text-2xl font-bold">{{ __('Recibos') }}</h2>
                    <p class="text-blue-100 text-xs sm:text-sm">{{ __('Comprovantes de pagamento') }}</p>
                </div>
            </div>
            <a href="{{ route('invoicing.receipts.create') }}"
               class="bg-white text-blue-600 hover:bg-blue-50 px-4 sm:px-6 py-2 sm:py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105 text-sm sm:text-base">
                <i class="fas fa-plus mr-2"></i>{{ __('Novo Recibo') }}
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-blue-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/40 mb-3 sm:mb-4">
                <i class="fas fa-receipt text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-blue-600 font-semibold mb-1">{{ __('Total') }}</p>
            <p class="text-2xl sm:text-4xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-green-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/40 mb-3 sm:mb-4">
                <i class="fas fa-shopping-cart text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-green-600 font-semibold mb-1">{{ __('Vendas') }}</p>
            <p class="text-2xl sm:text-4xl font-bold text-gray-900">{{ $stats['sales'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-orange-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-orange-500 to-amber-600 rounded-2xl flex items-center justify-center shadow-lg shadow-orange-500/40 mb-3 sm:mb-4">
                <i class="fas fa-box text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-orange-600 font-semibold mb-1">{{ __('Compras') }}</p>
            <p class="text-2xl sm:text-4xl font-bold text-gray-900">{{ $stats['purchases'] }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-purple-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-purple-500 to-fuchsia-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/40 mb-3 sm:mb-4">
                <i class="fas fa-money-bill-wave text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-purple-600 font-semibold mb-1">{{ __('Valor Total') }}</p>
            <p class="text-xl sm:text-3xl font-bold text-gray-900">{{ number_format($stats['total_amount'], 2) }} <span class="text-xs text-gray-500">AOA</span></p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-4 sm:p-6">
        <h3 class="text-lg font-bold text-gray-900 flex items-center mb-4">
            <i class="fas fa-filter mr-2 text-blue-600"></i>{{ __('Filtros') }}
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 sm:gap-4">
            <x-filtro-autor :autores="$this->autoresDosDocumentos" :todos="$this->veDocumentosDeTodos" />
            <div class="md:col-span-1">
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-search mr-1"></i>{{ __('Pesquisar') }}</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Pesquisar...') }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-tag mr-1"></i>{{ __('Tipo') }}</label>
                <select wire:model.live="filterType" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm bg-white">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="sale">{{ __('Vendas') }}</option>
                    <option value="purchase">{{ __('Compras') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-info-circle mr-1"></i>{{ __('Status') }}</label>
                <select wire:model.live="filterStatus" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm bg-white">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="issued">{{ __('Emitido') }}</option>
                    <option value="cancelled">{{ __('Cancelado') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-calendar-alt mr-1"></i>{{ __('De') }}</label>
                <input type="date" wire:model.live="filterDateFrom" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-calendar-alt mr-1"></i>{{ __('Até') }}</label>
                <input type="date" wire:model.live="filterDateTo" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm">
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-50 to-indigo-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-hashtag mr-1 text-blue-600"></i>{{ __('Número') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-tag mr-1 text-blue-600"></i>{{ __('Tipo') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-user mr-1 text-blue-600"></i>{{ __('Cliente/Fornecedor') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-money-bill mr-1 text-blue-600"></i>{{ __('Valor') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-calendar mr-1 text-blue-600"></i>{{ __('Data') }}
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-info-circle mr-1 text-blue-600"></i>{{ __('Status') }}
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">
                        <i class="fas fa-landmark mr-1 text-emerald-600"></i>{{ __('Portal AGT') }}
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <i class="fas fa-cog mr-1 text-blue-600"></i>{{ __('Ações') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($receipts as $receipt)
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="text-sm font-bold text-blue-700" title="{{ __('Numeração interna') }}">{{ $receipt->numeroInterno() }}</div>
                        @if($receipt->numeroAgt())
                            <div class="text-[10px] text-gray-400 font-mono mt-0.5" title="{{ __('Numeração AGT') }}">
                                <i class="fas fa-landmark mr-0.5"></i>{{ $receipt->numeroAgt() }}
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if($receipt->type === 'sale')
                            <span class="inline-flex items-center px-3 py-1 bg-gradient-to-r from-green-100 to-green-200 text-green-800 text-xs font-bold rounded-full">
                                <i class="fas fa-shopping-cart mr-1"></i>{{ __('Venda') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 bg-gradient-to-r from-orange-100 to-orange-200 text-orange-800 text-xs font-bold rounded-full">
                                <i class="fas fa-box mr-1"></i>{{ __('Compra') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <i class="fas fa-user-circle text-gray-400 mr-2"></i>
                            <span class="font-medium text-gray-900">{{ $receipt->entity_name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-lg font-bold text-blue-600">{{ number_format($receipt->amount_paid, 2) }}</span>
                        <span class="text-xs text-gray-500 ml-1">AOA</span>
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        <i class="fas fa-calendar-alt mr-1 text-blue-400"></i>
                        {{ $receipt->payment_date->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-3 py-1 bg-gradient-to-r from-{{ $receipt->status_color }}-100 to-{{ $receipt->status_color }}-200 text-{{ $receipt->status_color }}-800 text-xs font-bold rounded-full">
                            <i class="fas fa-circle mr-1 text-xs"></i>
                            {{ $receipt->status_label }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center whitespace-nowrap">
                        @include('livewire.invoicing.partials.agt-document-status', ['document' => $receipt])
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('invoicing.receipts.preview', $receipt->id) }}" 
                               target="_blank"
                               class="p-2 bg-purple-100 text-purple-600 rounded-lg hover:bg-purple-200 transition transform hover:scale-110" 
                               title="{{ __('Ver documento') }}">
                                <i class="fas fa-file-alt"></i>
                            </a>
                            <x-pdf-descarregar :url="route('invoicing.receipts.preview', $receipt->id)" />
                            <a href="{{ route('invoicing.receipts.pdf', $receipt->id) }}"
                               target="_blank"
                               class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition transform hover:scale-110"
                               title="{{ __('Descarregar PDF') }}">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <a href="{{ route('invoicing.receipts.edit', $receipt->id) }}"
                               class="p-2 bg-indigo-100 text-indigo-600 rounded-lg hover:bg-indigo-200 transition transform hover:scale-110"
                               title="{{ __('Editar') }}">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button wire:click="confirmDelete({{ $receipt->id }})"
                                    class="p-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition transform hover:scale-110"
                                    title="{{ __('Eliminar') }}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-3 text-center text-gray-500">
                        {{ __('Nenhum recibo encontrado') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-100">
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
                <h3 class="text-2xl font-bold text-gray-800 mb-2">{{ __('Eliminar Recibo?') }}</h3>
                <p class="text-gray-600">{{ __('Esta ação não pode ser desfeita.') }}</p>
            </div>
            <div class="flex gap-3">
                <button wire:click="$set('showDeleteModal', false)"
                        class="flex-1 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-bold transition">
                    <i class="fas fa-times mr-2"></i>{{ __('Cancelar') }}
                </button>
                <button wire:click="deleteReceipt"
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white rounded-xl font-bold transition shadow-lg">
                    <i class="fas fa-trash mr-2"></i>{{ __('Eliminar') }}
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
