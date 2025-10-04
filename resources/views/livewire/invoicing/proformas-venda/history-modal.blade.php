{{-- History Modal --}}
@if($showHistoryModal && $proformaHistory)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4" wire:click.self="closeHistoryModal">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 rounded-t-2xl flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-history mr-2"></i>
                    Histórico de Conversões
                </h3>
                <p class="text-purple-100 text-sm mt-1">Proforma: {{ $proformaHistory->proforma_number }}</p>
            </div>
            <button wire:click="closeHistoryModal" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        {{-- Content --}}
        <div class="p-6">
            {{-- Proforma Info --}}
            <div class="bg-purple-50 rounded-xl p-4 mb-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Cliente</p>
                        <p class="font-semibold text-gray-900">{{ $proformaHistory->client->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Data</p>
                        <p class="font-semibold text-gray-900">{{ $proformaHistory->proforma_date->format('d/m/Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Total</p>
                        <p class="font-semibold text-purple-600">{{ number_format($proformaHistory->total, 2) }} Kz</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Status</p>
                        <span class="inline-flex px-3 py-1 text-xs font-bold rounded-full
                            {{ $proformaHistory->status === 'draft' ? 'bg-gray-100 text-gray-800' : '' }}
                            {{ $proformaHistory->status === 'sent' ? 'bg-blue-100 text-blue-800' : '' }}
                            {{ $proformaHistory->status === 'accepted' ? 'bg-green-100 text-green-800' : '' }}">
                            {{ $proformaHistory->status === 'draft' ? 'Rascunho' : '' }}
                            {{ $proformaHistory->status === 'sent' ? 'Enviada' : '' }}
                            {{ $proformaHistory->status === 'accepted' ? 'Aceite' : '' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Invoices List --}}
            <div>
                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-file-invoice text-green-600 mr-2"></i>
                    Faturas Geradas ({{ count($relatedInvoices) }})
                </h4>
                
                @if(count($relatedInvoices) > 0)
                    <div class="space-y-3">
                        @foreach($relatedInvoices as $invoice)
                        <div class="border-2 border-gray-200 rounded-xl p-4 hover:border-green-500 hover:shadow-lg transition-all">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-file-invoice text-green-600 text-xl"></i>
                                        </div>
                                        <div>
                                            <h5 class="font-bold text-gray-900">{{ $invoice->invoice_number }}</h5>
                                            <p class="text-sm text-gray-500">
                                                Criada em {{ $invoice->created_at->format('d/m/Y H:i') }}
                                                @if($invoice->creator)
                                                    por {{ $invoice->creator->name }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 grid grid-cols-3 gap-4">
                                        <div>
                                            <p class="text-xs text-gray-500">Data Fatura</p>
                                            <p class="font-semibold">{{ $invoice->invoice_date->format('d/m/Y') }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Vencimento</p>
                                            <p class="font-semibold">{{ $invoice->due_date->format('d/m/Y') }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Total</p>
                                            <p class="font-semibold text-green-600">{{ number_format($invoice->total, 2) }} Kz</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ml-4 flex flex-col gap-2">
                                    <span class="inline-flex px-3 py-1 text-xs font-bold rounded-full
                                        {{ $invoice->status === 'draft' ? 'bg-gray-100 text-gray-800' : '' }}
                                        {{ $invoice->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $invoice->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}">
                                        {{ $invoice->status === 'draft' ? 'Rascunho' : '' }}
                                        {{ $invoice->status === 'pending' ? 'Pendente' : '' }}
                                        {{ $invoice->status === 'paid' ? 'Pago' : '' }}
                                        {{ $invoice->status === 'cancelled' ? 'Cancelado' : '' }}
                                    </span>
                                    <a href="{{ route('invoicing.sales.invoices.preview', $invoice->id) }}" target="_blank"
                                       class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-xs rounded-lg transition text-center">
                                        <i class="fas fa-eye mr-1"></i>Ver
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-inbox text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-gray-500">Nenhuma fatura gerada ainda</p>
                        <p class="text-sm text-gray-400 mt-1">Clique em "Converter em Fatura" para criar a primeira</p>
                    </div>
                @endif
            </div>
        </div>
        
        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-between items-center">
            <button wire:click="convertToInvoice({{ $proformaHistory->id }})" 
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold transition">
                <i class="fas fa-plus mr-2"></i>Converter Novamente
            </button>
            <button wire:click="closeHistoryModal" 
                    class="px-4 py-2 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                Fechar
            </button>
        </div>
    </div>
</div>
@endif
