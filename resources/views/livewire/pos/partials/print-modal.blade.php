{{-- Modal Impressão de Ticket --}}
@if($showPrintModal && $lastInvoice)
<div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-receipt mr-2"></i>Impressão de Ticket
            </h3>
            <button wire:click="$set('showPrintModal', false)" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        {{-- Ticket Preview --}}
        <div id="ticket-print" class="p-6 bg-white" style="font-family: 'Courier New', monospace;">
            {{-- Cabeçalho Empresa --}}
            <div class="text-center border-b-2 border-dashed border-gray-400 pb-3 mb-3">
                @if(app_logo())
                    <img src="{{ app_logo() }}" alt="{{ app_name() }}" class="h-12 w-auto mx-auto mb-2">
                @endif
                <h2 class="text-lg font-bold">{{ app_name() }}</h2>
                <p class="text-xs">NIF: {{ auth()->user()->activeTenant()->nif ?? 'N/A' }}</p>
                <p class="text-xs">{{ auth()->user()->activeTenant()->address ?? 'Endereço' }}</p>
                <p class="text-xs">Tel: {{ auth()->user()->activeTenant()->phone ?? 'Telefone' }}</p>
            </div>

            {{-- Dados Fatura --}}
            <div class="text-xs mb-3 space-y-1">
                <div class="flex justify-between">
                    <span class="font-bold">FATURA:</span>
                    <span>{{ $lastInvoice->invoice_number }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold">DATA:</span>
                    <span>{{ $lastInvoice->invoice_date->format('d/m/Y H:i') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-bold">OPERADOR:</span>
                    <span>{{ auth()->user()->name }}</span>
                </div>
            </div>

            {{-- Dados Cliente --}}
            <div class="text-xs mb-3 pb-2 border-b border-dashed border-gray-400">
                <div class="font-bold mb-1">CLIENTE:</div>
                <div>{{ $lastInvoice->client->name }}</div>
                <div>NIF: {{ $lastInvoice->client->nif }}</div>
            </div>

            {{-- Itens --}}
            <div class="text-xs mb-3">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-400">
                            <th class="text-left py-1">ITEM</th>
                            <th class="text-center">QTD</th>
                            <th class="text-right">PREÇO</th>
                            <th class="text-right">SUBTOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lastInvoice->items as $item)
                        <tr class="border-b border-dotted border-gray-300">
                            <td class="py-1">{{ $item->product_name }}</td>
                            <td class="text-center">{{ number_format($item->quantity, 0) }}</td>
                            <td class="text-right">{{ number_format($item->unit_price, 0) }}</td>
                            <td class="text-right">{{ number_format($item->subtotal, 0) }}</td>
                        </tr>
                        <tr class="text-[10px] text-gray-600">
                            <td colspan="4" class="pl-2 pb-1">
                                @if($item->tax_rate > 0)
                                Incidência IVA {{ $item->tax_rate }}%: {{ number_format($item->subtotal, 2) }} Kz | IVA: {{ number_format($item->tax_amount, 2) }} Kz
                                @else
                                Isento de IVA
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Resumo Fiscal SAFT --}}
            <div class="text-xs mb-3 pb-3 border-t-2 border-gray-400 pt-2 space-y-1">
                <div class="font-bold mb-1">RESUMO FISCAL:</div>
                
                {{-- Base Incidência --}}
                <div class="flex justify-between">
                    <span>Total Base Incidência IVA:</span>
                    <span>{{ number_format($lastInvoice->subtotal, 2) }} Kz</span>
                </div>
                
                {{-- Desconto se houver --}}
                @if($lastInvoice->discount_amount > 0)
                <div class="flex justify-between text-orange-600">
                    <span>Desconto Comercial:</span>
                    <span>-{{ number_format($lastInvoice->discount_amount, 2) }} Kz</span>
                </div>
                <div class="flex justify-between">
                    <span>Base após Desconto:</span>
                    <span>{{ number_format($lastInvoice->subtotal - $lastInvoice->discount_amount, 2) }} Kz</span>
                </div>
                @endif
                
                {{-- Total IVA --}}
                <div class="flex justify-between">
                    <span>Total IVA (14%):</span>
                    <span>{{ number_format($lastInvoice->tax_amount, 2) }} Kz</span>
                </div>
                
                {{-- Total Geral --}}
                <div class="flex justify-between font-bold text-base border-t-2 border-gray-900 pt-1 mt-1">
                    <span>TOTAL GERAL:</span>
                    <span>{{ number_format($lastInvoice->total, 2) }} Kz</span>
                </div>
            </div>

            {{-- Pagamento --}}
            <div class="text-xs mb-3 pb-3 border-b border-dashed border-gray-400 space-y-1">
                <div class="flex justify-between">
                    <span>Forma Pagamento:</span>
                    <span class="font-bold uppercase">{{ $lastInvoice->payment_method ?? 'Dinheiro' }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Valor Recebido:</span>
                    <span>{{ number_format($lastInvoice->paid_amount, 2) }} Kz</span>
                </div>
                @if($lastInvoice->paid_amount - $lastInvoice->total > 0)
                <div class="flex justify-between font-bold">
                    <span>Troco:</span>
                    <span>{{ number_format($lastInvoice->paid_amount - $lastInvoice->total, 2) }} Kz</span>
                </div>
                @endif
            </div>

            {{-- Certificação e Observações --}}
            @if($lastInvoice->notes)
            <div class="text-xs mb-3 pb-2 border-b border-dashed border-gray-400">
                <div class="font-bold mb-1">OBSERVAÇÕES:</div>
                <div>{{ $lastInvoice->notes }}</div>
            </div>
            @endif

            {{-- Rodapé SAFT Angola --}}
            <div class="text-[10px] text-center space-y-1 text-gray-700">
                <p class="font-bold mb-2">═══════════════════════</p>
                <p class="font-bold">Processado por programa validado</p>
                <p class="font-bold">Certificado AGT Nº {{ auth()->user()->activeTenant()->agt_certificate ?? 'xxxxxxxx/AGT/xxxx' }}</p>
                <p class="mt-1">Software: {{ config('app.name', 'SOS ERP') }}</p>
                <p class="mt-2 font-mono text-[9px]">HASH: {{ strtoupper(substr(md5($lastInvoice->invoice_number . $lastInvoice->total . $lastInvoice->invoice_date), 0, 32)) }}</p>
                <p class="mt-2 font-bold">{{ $lastInvoice->notes ? 'Obrigado pela sua preferência!' : ($lastInvoice->notes ?? 'Obrigado pela sua preferência!') }}</p>
                <p class="mt-3 text-[9px] italic">Este documento não serve de fatura</p>
            </div>
        </div>

        {{-- Botões --}}
        <div class="px-6 pb-6 flex space-x-3">
            <button wire:click="$set('showPrintModal', false)" 
                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">
                <i class="fas fa-times mr-2"></i>Fechar
            </button>
            <button onclick="printTicket()" 
                    class="flex-1 px-4 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl font-semibold hover:from-green-700 hover:to-green-800 transition shadow-lg">
                <i class="fas fa-print mr-2"></i>Imprimir
            </button>
        </div>
    </div>
</div>
@endif
