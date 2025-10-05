{{-- Modal de Visualização --}}
@if($showViewModal && $viewingTransaction)
<div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-teal-600 to-cyan-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-eye mr-2"></i>Detalhes da Transação
            </h3>
            <button wire:click="closeViewModal" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <div class="p-6 space-y-4">
            {{-- Número e Tipo --}}
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-gray-50 rounded-xl p-4">
                    <p class="text-xs text-gray-500 mb-1">Número da Transação</p>
                    <p class="text-lg font-mono font-bold text-gray-900">{{ $viewingTransaction->transaction_number }}</p>
                </div>
                <div class="bg-gray-50 rounded-xl p-4">
                    <p class="text-xs text-gray-500 mb-1">Tipo</p>
                    @if($viewingTransaction->type === 'income')
                        <span class="px-3 py-1 text-sm font-semibold bg-green-100 text-green-700 rounded-full">
                            <i class="fas fa-arrow-down mr-1"></i>Entrada
                        </span>
                    @elseif($viewingTransaction->type === 'expense')
                        <span class="px-3 py-1 text-sm font-semibold bg-red-100 text-red-700 rounded-full">
                            <i class="fas fa-arrow-up mr-1"></i>Saída
                        </span>
                    @else
                        <span class="px-3 py-1 text-sm font-semibold bg-blue-100 text-blue-700 rounded-full">
                            <i class="fas fa-exchange-alt mr-1"></i>Transferência
                        </span>
                    @endif
                </div>
            </div>

            {{-- Valor --}}
            <div class="bg-gradient-to-r {{ $viewingTransaction->type === 'income' ? 'from-green-50 to-emerald-50 border-green-300' : 'from-red-50 to-pink-50 border-red-300' }} border-2 rounded-xl p-6 text-center">
                <p class="text-sm font-semibold {{ $viewingTransaction->type === 'income' ? 'text-green-700' : 'text-red-700' }} mb-2">
                    Valor da Transação
                </p>
                <p class="text-4xl font-bold {{ $viewingTransaction->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                    {{ $viewingTransaction->type === 'income' ? '+' : '-' }} {{ number_format($viewingTransaction->amount, 2) }} {{ $viewingTransaction->currency }}
                </p>
            </div>

            {{-- Informações --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Data</p>
                    <p class="font-semibold text-gray-900">{{ $viewingTransaction->transaction_date->format('d/m/Y') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Status</p>
                    @if($viewingTransaction->status === 'completed')
                        <span class="px-3 py-1 text-xs font-semibold bg-green-100 text-green-700 rounded-full">Concluído</span>
                    @elseif($viewingTransaction->status === 'pending')
                        <span class="px-3 py-1 text-xs font-semibold bg-yellow-100 text-yellow-700 rounded-full">Pendente</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">Cancelado</span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Método de Pagamento</p>
                    <p class="font-semibold text-gray-900">{{ $viewingTransaction->paymentMethod->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Categoria</p>
                    <p class="font-semibold text-gray-900">{{ ucfirst($viewingTransaction->category) ?? '-' }}</p>
                </div>
            </div>

            @if($viewingTransaction->reference)
            <div>
                <p class="text-xs text-gray-500 mb-1">Referência</p>
                <p class="font-semibold text-gray-900">{{ $viewingTransaction->reference }}</p>
            </div>
            @endif

            <div>
                <p class="text-xs text-gray-500 mb-1">Descrição</p>
                <p class="text-gray-900">{{ $viewingTransaction->description }}</p>
            </div>

            @if($viewingTransaction->notes)
            <div>
                <p class="text-xs text-gray-500 mb-1">Observações</p>
                <p class="text-gray-900">{{ $viewingTransaction->notes }}</p>
            </div>
            @endif

            @if($viewingTransaction->salesInvoice)
            <div class="bg-blue-50 border-2 border-blue-300 rounded-xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs text-blue-700 font-semibold">
                        <i class="fas fa-file-invoice mr-1"></i>Fatura de Venda Associada
                    </p>
                    @php
                        $invoice = $viewingTransaction->salesInvoice;
                        $statusColor = match($invoice->status) {
                            'draft' => 'gray',
                            'paid' => 'green',
                            'credited' => 'orange',
                            'cancelled' => 'red',
                            default => 'blue'
                        };
                        $statusLabel = match($invoice->status) {
                            'draft' => 'Rascunho',
                            'paid' => 'Paga',
                            'credited' => 'Creditada',
                            'cancelled' => 'Cancelada',
                            default => ucfirst($invoice->status)
                        };
                    @endphp
                    <span class="px-2 py-1 text-xs font-semibold bg-{{ $statusColor }}-100 text-{{ $statusColor }}-700 rounded-full">
                        {{ $statusLabel }}
                    </span>
                </div>
                
                <div class="space-y-2 mb-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-600">Número:</span>
                        <span class="font-mono font-bold text-blue-900">{{ $invoice->invoice_number }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-600">Data:</span>
                        <span class="font-semibold text-blue-900">{{ $invoice->invoice_date->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-600">Cliente:</span>
                        <span class="font-semibold text-blue-900">{{ $invoice->client->name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between text-sm border-t border-blue-200 pt-2">
                        <span class="text-blue-600 font-semibold">Total:</span>
                        <span class="font-bold text-blue-900">{{ number_format($invoice->total, 2) }} AOA</span>
                    </div>
                </div>
                
                <a href="{{ route('invoicing.sales.invoices.preview', $invoice->id) }}" 
                   target="_blank"
                   class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-external-link-alt mr-2"></i>
                    Ver Fatura Completa
                </a>
            </div>
            @endif

            @if($viewingTransaction->purchaseInvoice)
            <div class="bg-orange-50 border-2 border-orange-300 rounded-xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs text-orange-700 font-semibold">
                        <i class="fas fa-file-invoice mr-1"></i>Fatura de Compra Associada
                    </p>
                    @php
                        $purchase = $viewingTransaction->purchaseInvoice;
                        $statusColor = match($purchase->status) {
                            'draft' => 'gray',
                            'paid' => 'green',
                            'credited' => 'orange',
                            'cancelled' => 'red',
                            default => 'blue'
                        };
                        $statusLabel = match($purchase->status) {
                            'draft' => 'Rascunho',
                            'paid' => 'Paga',
                            'credited' => 'Creditada',
                            'cancelled' => 'Cancelada',
                            default => ucfirst($purchase->status)
                        };
                    @endphp
                    <span class="px-2 py-1 text-xs font-semibold bg-{{ $statusColor }}-100 text-{{ $statusColor }}-700 rounded-full">
                        {{ $statusLabel }}
                    </span>
                </div>
                
                <div class="space-y-2 mb-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-orange-600">Número:</span>
                        <span class="font-mono font-bold text-orange-900">{{ $purchase->invoice_number }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-orange-600">Data:</span>
                        <span class="font-semibold text-orange-900">{{ $purchase->invoice_date->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-orange-600">Fornecedor:</span>
                        <span class="font-semibold text-orange-900">{{ $purchase->supplier->name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between text-sm border-t border-orange-200 pt-2">
                        <span class="text-orange-600 font-semibold">Total:</span>
                        <span class="font-bold text-orange-900">{{ number_format($purchase->total, 2) }} AOA</span>
                    </div>
                </div>
                
                <a href="{{ route('invoicing.purchases.invoices.preview', $purchase->id) }}" 
                   target="_blank"
                   class="w-full inline-flex items-center justify-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-external-link-alt mr-2"></i>
                    Ver Fatura Completa
                </a>
            </div>
            @endif
            
            @if($viewingTransaction->category === 'credit_note' && $viewingTransaction->reference && str_starts_with($viewingTransaction->reference, 'CREDIT-'))
            {{-- Se é uma transação de crédito, procurar a NC associada --}}
            @php
                $originalTrxNumber = str_replace('CREDIT-', '', $viewingTransaction->reference);
                $originalTransaction = \App\Models\Treasury\Transaction::where('tenant_id', activeTenantId())
                    ->where('transaction_number', $originalTrxNumber)
                    ->first();
                
                $creditNote = null;
                if ($originalTransaction && $originalTransaction->invoice_id) {
                    $creditNote = \App\Models\Invoicing\CreditNote::where('tenant_id', activeTenantId())
                        ->where('invoice_id', $originalTransaction->invoice_id)
                        ->orderBy('created_at', 'desc')
                        ->first();
                }
            @endphp
            
            @if($creditNote)
            <div class="bg-green-50 border-2 border-green-300 rounded-xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs text-green-700 font-semibold">
                        <i class="fas fa-file-circle-minus mr-1"></i>Nota de Crédito Criada
                    </p>
                    @php
                        $ncStatusColor = match($creditNote->status) {
                            'draft' => 'gray',
                            'issued' => 'green',
                            'cancelled' => 'red',
                            default => 'blue'
                        };
                        $ncStatusLabel = match($creditNote->status) {
                            'draft' => 'Rascunho',
                            'issued' => 'Emitida',
                            'cancelled' => 'Cancelada',
                            default => ucfirst($creditNote->status)
                        };
                    @endphp
                    <span class="px-2 py-1 text-xs font-semibold bg-{{ $ncStatusColor }}-100 text-{{ $ncStatusColor }}-700 rounded-full">
                        {{ $ncStatusLabel }}
                    </span>
                </div>
                
                <div class="space-y-2 mb-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-green-600">Número NC:</span>
                        <span class="font-mono font-bold text-green-900">{{ $creditNote->credit_note_number }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-600">Data Emissão:</span>
                        <span class="font-semibold text-green-900">{{ $creditNote->issue_date->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-600">Motivo:</span>
                        <span class="font-semibold text-green-900">{{ $creditNote->reason_label }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-600">Cliente:</span>
                        <span class="font-semibold text-green-900">{{ $creditNote->client->name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between text-sm border-t border-green-200 pt-2">
                        <span class="text-green-600 font-semibold">Total:</span>
                        <span class="font-bold text-green-900">{{ number_format($creditNote->total, 2) }} AOA</span>
                    </div>
                </div>
                
                <a href="{{ route('invoicing.credit-notes.edit', $creditNote->id) }}" 
                   target="_blank"
                   class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition">
                    <i class="fas fa-external-link-alt mr-2"></i>
                    Ver Nota de Crédito
                </a>
            </div>
            @endif
            @endif

            {{-- Informações de Auditoria --}}
            <div class="bg-gray-50 rounded-xl p-4 mt-4">
                <p class="text-xs text-gray-500 mb-2 font-semibold">Informações de Auditoria</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <span class="text-gray-500">Criado por:</span>
                        <span class="font-semibold text-gray-900">{{ $viewingTransaction->user->name ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Criado em:</span>
                        <span class="font-semibold text-gray-900">{{ $viewingTransaction->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>

            {{-- Botão Fechar --}}
            <div class="pt-4">
                <button wire:click="closeViewModal" 
                        class="w-full px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-bold transition">
                    <i class="fas fa-times mr-2"></i>Fechar
                </button>
            </div>
        </div>
    </div>
</div>
@endif
