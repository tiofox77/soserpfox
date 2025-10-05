<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-chart-line mr-3 text-indigo-600"></i>
                    Relatório de Vendas POS
                </h1>
                <p class="text-gray-600 mt-2">Análise completa das vendas realizadas no ponto de venda</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="exportExcel" 
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-file-excel mr-2"></i>Excel
                </button>
                <button wire:click="exportPdf" 
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-file-pdf mr-2"></i>PDF
                </button>
            </div>
        </div>
    </div>

    {{-- Estatísticas --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total Vendas</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $totalSales }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-shopping-cart text-2xl text-blue-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Receita Total</p>
                    <p class="text-2xl font-bold text-green-600">{{ number_format($totalRevenue, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-2xl text-green-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">IVA Total</p>
                    <p class="text-2xl font-bold text-indigo-600">{{ number_format($totalTax, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-percentage text-2xl text-indigo-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Descontos</p>
                    <p class="text-2xl font-bold text-orange-600">{{ number_format($totalDiscount, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-tag text-2xl text-orange-600"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-xl shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Data Início</label>
                <input type="date" wire:model.live="startDate" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Data Fim</label>
                <input type="date" wire:model.live="endDate" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                <select wire:model.live="status" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="paid">Pago</option>
                    <option value="pending">Pendente</option>
                    <option value="partially_paid">Parcialmente Pago</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Pagamento</label>
                <select wire:model.live="paymentMethod" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="cash">Dinheiro</option>
                    <option value="transfer">Transferência</option>
                    <option value="multicaixa">Multicaixa</option>
                    <option value="tpa">TPA</option>
                    <option value="mbway">MB Way</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Buscar</label>
                <input type="text" wire:model.live.debounce.300ms="search" 
                       placeholder="Nº Fatura, Cliente..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Fatura</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Data</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Subtotal</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">IVA</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase">Total</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Pagamento</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($invoices as $invoice)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <span class="font-bold text-gray-900">{{ $invoice->invoice_number }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $invoice->invoice_date->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $invoice->client->name }}</p>
                                <p class="text-xs text-gray-500">NIF: {{ $invoice->client->nif }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            {{ number_format($invoice->subtotal, 2) }} Kz
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            {{ number_format($invoice->tax_amount, 2) }} Kz
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="font-bold text-gray-900">{{ number_format($invoice->total, 2) }} Kz</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold uppercase">
                                {{ $invoice->payment_method ?? 'cash' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($invoice->status === 'paid')
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">
                                <i class="fas fa-check-circle mr-1"></i>Pago
                            </span>
                            @elseif($invoice->status === 'pending')
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-bold">
                                <i class="fas fa-clock mr-1"></i>Pendente
                            </span>
                            @elseif($invoice->status === 'partially_paid')
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold">
                                <i class="fas fa-percent mr-1"></i>Parcial
                            </span>
                            @else
                            <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">
                                <i class="fas fa-times-circle mr-1"></i>Cancelado
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="viewDetails({{ $invoice->id }})" 
                                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button wire:click="printInvoice({{ $invoice->id }})" 
                                        class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-semibold transition">
                                    <i class="fas fa-print"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center">
                            <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 font-semibold">Nenhuma venda encontrada no período selecionado</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginação --}}
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $invoices->links() }}
        </div>
    </div>

    {{-- Modal Detalhes --}}
    @if($showDetailsModal && $selectedInvoice)
    @include('livewire.p-o-s.partials.details-modal')
    @endif

    {{-- Modal Impressão --}}
    @if($showPrintModal && $selectedInvoice)
    @include('livewire.pos.partials.print-modal', ['lastInvoice' => $selectedInvoice])
    @endif
</div>
