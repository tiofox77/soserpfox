<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl shadow-lg p-4 sm:p-6 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3 sm:mr-4">
                    <i class="fas fa-chart-line text-xl sm:text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-lg sm:text-2xl font-bold">Relatório de Vendas POS</h1>
                    <p class="text-indigo-100 text-xs sm:text-sm">Análise completa das vendas no ponto de venda</p>
                    @if($this->ownOnly)
                        <span class="inline-flex items-center mt-2 px-3 py-1 bg-white/20 text-white text-xs font-semibold rounded-full">
                            <i class="fas fa-user-lock mr-1"></i>
                            A visualizar apenas as suas próprias vendas
                        </span>
                    @endif
                </div>
            </div>
            <div class="flex gap-2">
                <button wire:click="exportExcel" 
                        class="bg-white text-green-600 hover:bg-green-50 px-4 py-2 rounded-xl font-semibold transition-all shadow-lg hover:scale-105 text-sm">
                    <i class="fas fa-file-excel mr-2"></i>Excel
                </button>
                <button wire:click="exportPdf" 
                        class="bg-white text-red-600 hover:bg-red-50 px-4 py-2 rounded-xl font-semibold transition-all shadow-lg hover:scale-105 text-sm">
                    <i class="fas fa-file-pdf mr-2"></i>PDF
                </button>
            </div>
        </div>
    </div>

    {{-- Estatísticas --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-blue-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/40 mb-3 sm:mb-4">
                <i class="fas fa-shopping-cart text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-blue-600 font-semibold mb-1">Total Vendas</p>
            <p class="text-2xl sm:text-4xl font-bold text-gray-900">{{ $totalSales }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-green-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/40 mb-3 sm:mb-4">
                <i class="fas fa-money-bill-wave text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-green-600 font-semibold mb-1">Receita Total</p>
            <p class="text-xl sm:text-3xl font-bold text-gray-900">{{ number_format($totalRevenue, 2) }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-indigo-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/40 mb-3 sm:mb-4">
                <i class="fas fa-percentage text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-indigo-600 font-semibold mb-1">IVA Total</p>
            <p class="text-xl sm:text-3xl font-bold text-gray-900">{{ number_format($totalTax, 2) }}</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-orange-100">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-orange-500 to-amber-600 rounded-2xl flex items-center justify-center shadow-lg shadow-orange-500/40 mb-3 sm:mb-4">
                <i class="fas fa-tag text-white text-xl sm:text-2xl"></i>
            </div>
            <p class="text-xs sm:text-sm text-orange-600 font-semibold mb-1">Descontos</p>
            <p class="text-xl sm:text-3xl font-bold text-gray-900">{{ number_format($totalDiscount, 2) }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="mb-6 bg-white rounded-2xl shadow-lg p-4 sm:p-6">
        <h3 class="text-lg font-bold text-gray-900 flex items-center mb-4">
            <i class="fas fa-filter mr-2 text-indigo-600"></i>Filtros
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 sm:gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-calendar-alt mr-1"></i>Data Início</label>
                <input type="date" wire:model.live="startDate" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-calendar-alt mr-1"></i>Data Fim</label>
                <input type="date" wire:model.live="endDate" 
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-info-circle mr-1"></i>Status</label>
                <select wire:model.live="status" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm bg-white">
                    <option value="">Todos</option>
                    <option value="paid">Pago</option>
                    <option value="pending">Pendente</option>
                    <option value="partially_paid">Parcialmente Pago</option>
                    <option value="credited">Creditado</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-credit-card mr-1"></i>Pagamento</label>
                <select wire:model.live="paymentMethod" 
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm bg-white">
                    <option value="">Todos</option>
                    <option value="cash">Dinheiro</option>
                    <option value="transfer">Transferência</option>
                    <option value="multicaixa">Multicaixa</option>
                    <option value="tpa">TPA</option>
                    <option value="mbway">MB Way</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-2 uppercase"><i class="fas fa-search mr-1"></i>Buscar</label>
                <input type="text" wire:model.live.debounce.300ms="search" 
                       placeholder="Nº Fatura, Cliente..."
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm">
            </div>
        </div>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200">
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
                            {{ ($invoice->system_entry_date ?? $invoice->invoice_date)->format('d/m/Y H:i') }}
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
                            @elseif($invoice->status === 'credited')
                            <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-bold">
                                <i class="fas fa-file-circle-minus mr-1"></i>Creditado
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
                                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition"
                                        title="Ver detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button wire:click="printInvoice({{ $invoice->id }})" 
                                        class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-semibold transition"
                                        title="Imprimir">
                                    <i class="fas fa-print"></i>
                                </button>
                                @if(!in_array($invoice->status, ['cancelled', 'credited']))
                                <button wire:click="openCreditNote({{ $invoice->id }})" 
                                        class="px-3 py-1.5 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-xs font-semibold transition"
                                        title="Nota de Crédito">
                                    <i class="fas fa-file-circle-minus"></i>
                                </button>
                                @endif
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

    {{-- Modal Nota de Crédito --}}
    @if($showCreditNoteModal && $creditNoteInvoice)
    @include('livewire.p-o-s.partials.credit-note-modal')
    @endif

    {{-- Modal Impressão — incluído sempre (o @if interno controla visibilidade) --}}
    @include('livewire.pos.partials.print-modal', ['lastInvoice' => $selectedInvoice])
</div>
