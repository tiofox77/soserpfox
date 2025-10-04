<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-file-invoice mr-3 text-orange-600"></i>
                    Proformas de Compra
                </h2>
                <p class="text-gray-600 mt-1">Orçamentos e propostas de fornecedores</p>
            </div>
            <a href="{{ route('invoicing.purchases.proformas.create') }}" 
               class="px-6 py-3 bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 text-white rounded-xl font-bold transition shadow-lg transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Nova Proforma
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded-lg animate-fade-in">
            <i class="fas fa-check-circle mr-2"></i>{{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg animate-fade-in">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-gradient-to-br from-orange-500 to-red-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-200 text-xs font-medium">Total</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-file-alt text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-gray-500 to-gray-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-200 text-xs font-medium">Rascunho</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['draft'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-edit text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-200 text-xs font-medium">Enviadas</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['sent'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-paper-plane text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">Aceites</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['accepted'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-check text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-yellow-500 to-orange-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-200 text-xs font-medium">Valor Total</p>
                    <p class="text-xl font-bold mt-1">{{ number_format($stats['total_amount'], 2) }} Kz</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-money-bill-wave text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-md p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <input type="text" wire:model.live.debounce.300ms="search" 
                       placeholder="🔍 Pesquisar número ou fornecedor..." 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                    <option value="">Todos os Estados</option>
                    <option value="draft">Rascunho</option>
                    <option value="sent">Enviada</option>
                    <option value="accepted">Aceite</option>
                    <option value="rejected">Rejeitada</option>
                    <option value="expired">Expirada</option>
                    <option value="converted">Convertida</option>
                </select>
            </div>
            <div>
                <input type="date" wire:model.live="dateFrom" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
            </div>
            <div>
                <input type="date" wire:model.live="dateTo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500">
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4">
            <h3 class="text-white font-bold text-lg flex items-center">
                <i class="fas fa-list mr-2"></i>
                Lista de Proformas
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-hashtag mr-1 text-orange-600"></i>Número
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-truck mr-1 text-blue-600"></i>Fornecedor
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-calendar mr-1 text-green-600"></i>Data
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-hourglass-end mr-1 text-orange-600"></i>Validade
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-info-circle mr-1 text-gray-600"></i>Estado
                        </th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-money-bill mr-1 text-green-600"></i>Total
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-cog mr-1 text-gray-600"></i>Ações
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($proformas as $proforma)
                    <tr class="hover:bg-orange-50 transition-all duration-200">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-bold text-orange-600">{{ $proforma->proforma_number }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-semibold text-gray-900">{{ $proforma->supplier->name }}</div>
                            <div class="text-xs text-gray-500">{{ $proforma->supplier->email }}</div>
                        </td>
                        <td class="px-6 py-4 text-center text-sm text-gray-700">
                            {{ $proforma->proforma_date->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 text-center text-sm">
                            @if($proforma->valid_until)
                                <span class="{{ $proforma->valid_until->isPast() ? 'text-red-600 font-bold' : 'text-gray-700' }}">
                                    {{ $proforma->valid_until->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($proforma->status === 'draft')
                                <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-bold rounded-full">
                                    <i class="fas fa-edit mr-1"></i>Rascunho
                                </span>
                            @elseif($proforma->status === 'sent')
                                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded-full">
                                    <i class="fas fa-paper-plane mr-1"></i>Enviada
                                </span>
                            @elseif($proforma->status === 'accepted')
                                <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">
                                    <i class="fas fa-check mr-1"></i>Aceite
                                </span>
                            @elseif($proforma->status === 'rejected')
                                <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">
                                    <i class="fas fa-times mr-1"></i>Rejeitada
                                </span>
                            @elseif($proforma->status === 'expired')
                                <span class="px-3 py-1 bg-orange-100 text-orange-800 text-xs font-bold rounded-full">
                                    <i class="fas fa-clock mr-1"></i>Expirada
                                </span>
                            @else
                                <span class="px-3 py-1 bg-purple-100 text-purple-800 text-xs font-bold rounded-full">
                                    <i class="fas fa-exchange-alt mr-1"></i>Convertida
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-lg font-bold text-gray-900">{{ number_format($proforma->total, 2) }}</span>
                            <div class="text-xs text-gray-500">Kz</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center space-x-2">
                                {{-- Ver Proforma --}}
                                <button wire:click="viewProforma({{ $proforma->id }})"
                                        class="group relative p-2 bg-orange-100 hover:bg-orange-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-eye text-orange-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        Visualizar
                                    </span>
                                </button>
                                
                                {{-- Preview (ícone PDF abre preview HTML) --}}
                                <a href="{{ route('invoicing.purchases.proformas.preview', $proforma->id) }}" target="_blank"
                                   class="group relative p-2 bg-red-100 hover:bg-red-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-file-pdf text-red-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        Preview
                                    </span>
                                </a>
                                
                                <a href="{{ route('invoicing.purchases.proformas.edit', $proforma->id) }}"
                                   class="group relative p-2 bg-blue-100 hover:bg-blue-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-edit text-blue-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        Editar
                                    </span>
                                </a>

                                <button wire:click="convertToInvoice({{ $proforma->id }})"
                                        class="group relative p-2 bg-green-100 hover:bg-green-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-file-invoice text-green-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        Converter em Fatura
                                    </span>
                                </button>
                                
                                <button wire:click="showHistory({{ $proforma->id }})"
                                        class="group relative p-2 bg-purple-100 hover:bg-purple-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-history text-purple-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        Histórico de Conversões
                                    </span>
                                </button>

                                <button wire:click="confirmDelete({{ $proforma->id }})"
                                        class="group relative p-2 bg-red-100 hover:bg-red-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-trash text-red-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        Eliminar
                                    </span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center justify-center animate-pulse">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-file-invoice text-gray-300 text-4xl"></i>
                                </div>
                                <p class="text-gray-500 text-lg font-semibold">Nenhuma proforma encontrada</p>
                                <p class="text-gray-400 text-sm mt-2">Crie a sua primeira proforma de compra</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
            {{ $proformas->links() }}
        </div>
    </div>

    {{-- Modals --}}
    @include('livewire.invoicing.proformas-compra.delete-modal')
    @include('livewire.invoicing.proformas-compra.view-modal')
    @include('livewire.invoicing.proformas-compra.history-modal')
</div>
