<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-file-invoice mr-3 text-purple-600"></i>
                    Faturas de Venda
                </h2>
                <p class="text-gray-600 mt-1">Faturas de vendas para clientes</p>
            </div>
            <a href="{{ route('invoicing.sales.invoices.create') }}" 
               class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-orange-700 hover:to-red-700 text-white rounded-xl font-bold transition shadow-lg transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Nova Fatura
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

        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-200 text-xs font-medium">Pendentes</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['pending'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-clock text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-xs font-medium">Pagas</p>
                    <p class="text-2xl font-bold mt-1">{{ $stats['paid'] }}</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-check-circle text-xl"></i>
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
                       placeholder="🔍 Pesquisar número ou Cliente..." 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>
            <div>
                <select wire:model.live="statusFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value="">Todos os Estados</option>
                    <option value="draft">Rascunho</option>
                    <option value="pending">Pendente</option>
                    <option value="paid">Pago</option>
                    <option value="cancelled">Cancelado</option>
                    <option value="overdue">Atrasado</option>
                </select>
            </div>
            <div>
                <input type="date" wire:model.live="dateFrom" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>
            <div>
                <input type="date" wire:model.live="dateTo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
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
                            <i class="fas fa-hashtag mr-1 text-purple-600"></i>Número
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-user mr-1 text-blue-600"></i>Cliente
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-calendar mr-1 text-green-600"></i>Data
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">
                            <i class="fas fa-calendar-check mr-1 text-purple-600"></i>Vencimento
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
                    @forelse($invoices as $invoice)
                    <tr class="hover:bg-purple-50 transition-all duration-200">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-bold text-purple-600">{{ $invoice->invoice_number }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-semibold text-gray-900">{{ $invoice->client->name }}</div>
                            <div class="text-xs text-gray-500">{{ $invoice->client->email }}</div>
                        </td>
                        <td class="px-6 py-4 text-center text-sm text-gray-700">
                            {{ $invoice->invoice_date->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 text-center text-sm">
                            @if($invoice->due_date)
                                <span class="{{ $invoice->due_date->isPast() ? 'text-red-600 font-bold' : 'text-gray-700' }}">
                                    {{ $invoice->due_date->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 bg-{{ $invoice->status_color }}-100 text-{{ $invoice->status_color }}-800 text-xs font-bold rounded-full inline-flex items-center gap-1">
                                @if($invoice->status === 'draft')
                                    <i class="fas fa-edit"></i>
                                @elseif($invoice->status === 'pending')
                                    <i class="fas fa-clock"></i>
                                @elseif($invoice->status === 'partially_paid')
                                    <i class="fas fa-circle-half-stroke"></i>
                                @elseif($invoice->status === 'paid')
                                    <i class="fas fa-check"></i>
                                @elseif($invoice->status === 'cancelled')
                                    <i class="fas fa-times"></i>
                                @elseif($invoice->status === 'overdue')
                                    <i class="fas fa-exclamation-triangle"></i>
                                @else
                                    <i class="fas fa-question"></i>
                                @endif
                                {{ $invoice->status_label }}
                            </span>
                            @if($invoice->status === 'partially_paid')
                                <div class="text-xs text-gray-600 mt-1">
                                    Pago: {{ number_format($invoice->paid_amount ?? 0, 2) }} AOA
                                    <br>Falta: {{ number_format($invoice->balance, 2) }} AOA
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-lg font-bold text-gray-900">{{ number_format($invoice->total, 2) }}</span>
                            <div class="text-xs text-gray-500">Kz</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center space-x-2">
                                {{-- Ver Proforma --}}
                                <button wire:click="viewInvoice({{ $invoice->id }})"
                                        class="group relative p-2 bg-purple-100 hover:bg-purple-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-eye text-purple-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        Visualizar
                                    </span>
                                </button>
                                
                                {{-- Preview (ícone PDF abre preview HTML) --}}
                                <a href="{{ route('invoicing.sales.invoices.preview', $invoice->id) }}" target="_blank"
                                   class="group relative p-2 bg-red-100 hover:bg-red-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-file-pdf text-red-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        Preview
                                    </span>
                                </a>
                                
                                <a href="{{ route('invoicing.sales.invoices.edit', $invoice->id) }}"
                                   class="group relative p-2 bg-blue-100 hover:bg-blue-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-edit text-blue-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        Editar
                                    </span>
                                </a>

                                @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                                <button wire:click="$dispatch('openPaymentModal', { invoiceType: 'sale', invoiceId: {{ $invoice->id }} })"
                                        class="group relative p-2 bg-gradient-to-r from-green-100 to-emerald-100 hover:from-green-600 hover:to-emerald-600 rounded-lg transition-all duration-200 transform hover:scale-110 shadow-sm">
                                    <i class="fas fa-money-bill-wave text-green-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        💰 Registrar Pagamento
                                    </span>
                                </button>
                                @endif

                                <button wire:click="markAsPaid({{ $invoice->id }})"
                                        class="group relative p-2 bg-green-100 hover:bg-green-600 rounded-lg transition-all duration-200 transform hover:scale-110">
                                    <i class="fas fa-check-circle text-green-600 group-hover:text-white transition-colors"></i>
                                    <span class="absolute hidden group-hover:block bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        Marcar como Pago
                                    </span>
                                </button>

                                <button wire:click="confirmDelete({{ $invoice->id }})"
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
            {{ $invoices->links() }}
        </div>
    </div>

    {{-- Modals --}}
    @include('livewire.invoicing.faturas-venda.delete-modal')
    @include('livewire.invoicing.faturas-venda.view-modal')
    
    {{-- Payment Modal --}}
    @livewire('invoicing.payment-modal')
</div>
