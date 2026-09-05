{{-- View Invoice Modal --}}
@if($showViewModal && $selectedInvoice)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full my-8 animate-scale-in">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <div class="min-w-0">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-file-invoice mr-2"></i>
                    {{ __('Fatura :numero', ['numero' => $selectedInvoice->numeroInterno()]) }}
                </h3>
                @if($selectedInvoice->numeroAgt())
                    <p class="text-xs text-purple-100 mt-0.5 font-mono">
                        <i class="fas fa-landmark mr-1"></i>{{ __('AGT') }}: {{ $selectedInvoice->numeroAgt() }}
                    </p>
                @else
                    <p class="text-xs text-purple-200/80 mt-0.5">{{ __('Série ainda não registada na AGT') }}</p>
                @endif
            </div>
            <button wire:click="closeViewModal" class="text-white hover:text-gray-200 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        {{-- Body --}}
        <div class="p-6 max-h-[calc(100vh-200px)] overflow-y-auto">
            {{-- Informações do Cliente --}}
            <div class="mb-6">
                <h4 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-user mr-2 text-purple-600"></i>
                    {{ __('Informações do Cliente') }}
                </h4>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="font-bold text-gray-900">{{ $selectedInvoice->client->name }}</p>
                    @if($selectedInvoice->client->nif)
                    <p class="text-sm text-gray-600">NIF: {{ $selectedInvoice->client->nif }}</p>
                    @endif
                    @if($selectedInvoice->client->email)
                    <p class="text-sm text-gray-600">{{ __('Email:') }} {{ $selectedInvoice->client->email }}</p>
                    @endif
                    @if($selectedInvoice->client->phone)
                    <p class="text-sm text-gray-600">{{ __('Tel:') }} {{ $selectedInvoice->client->phone }}</p>
                    @endif
                </div>
            </div>
            
            {{-- Datas e Status --}}
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div>
                    <p class="text-sm text-gray-600 mb-1">{{ __('Data da Fatura:') }}</p>
                    <p class="font-bold text-gray-900">{{ $selectedInvoice->invoice_date->format('d/m/Y') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 mb-1">{{ __('Vencimento:') }}</p>
                    <p class="font-bold text-gray-900">
                        {{ $selectedInvoice->due_date ? $selectedInvoice->due_date->format('d/m/Y') : '-' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 mb-1">{{ __('Status:') }}</p>
                    @if($selectedInvoice->status === 'draft')
                        <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-bold rounded-full">
                            {{ __('Rascunho') }}
                        </span>
                    @elseif($selectedInvoice->status === 'sent')
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded-full">
                            {{ __('Enviada') }}
                        </span>
                    @elseif($selectedInvoice->status === 'accepted')
                        <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">
                            {{ __('Aceite') }}
                        </span>
                    @else
                        <span class="px-3 py-1 bg-purple-100 text-purple-800 text-xs font-bold rounded-full">
                            {{ __('Convertida') }}
                        </span>
                    @endif
                </div>
            </div>
            
            {{-- Produtos --}}
            <div class="mb-6">
                <h4 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-box mr-2 text-purple-600"></i>
                    {{ __('Produtos') }}
                </h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-700">{{ __('Produto') }}</th>
                                <th class="px-4 py-2 text-center text-xs font-bold text-gray-700">{{ __('Qtd') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-700">{{ __('Preço') }}</th>
                                <th class="px-4 py-2 text-center text-xs font-bold text-gray-700">{{ __('Desc%') }}</th>
                                <th class="px-4 py-2 text-center text-xs font-bold text-gray-700">{{ __('IVA') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-700">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($selectedInvoice->items as $item)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900">{{ $item->description }}</p>
                                    <p class="text-xs text-gray-500">{{ $item->unit }}</p>
                                </td>
                                <td class="px-4 py-3 text-center">{{ $item->quantity }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($item->unit_price, 2) }} Kz</td>
                                <td class="px-4 py-3 text-center">{{ $item->discount_percent }}%</td>
                                <td class="px-4 py-3 text-center">{{ $item->tax_rate }}%</td>
                                <td class="px-4 py-3 text-right font-bold">{{ number_format($item->total, 2) }} Kz</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            
            {{-- Totais --}}
            <div class="bg-gray-50 rounded-lg p-4">
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('Subtotal:') }}</span>
                        <span class="font-semibold">{{ number_format($selectedInvoice->subtotal, 2) }} Kz</span>
                    </div>
                    @if($selectedInvoice->discount_commercial > 0 || $selectedInvoice->discount_amount > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('Desconto Comercial:') }}</span>
                        <span class="font-semibold">{{ number_format($selectedInvoice->discount_commercial + $selectedInvoice->discount_amount, 2) }} Kz</span>
                    </div>
                    @endif
                    @if($selectedInvoice->discount_financial > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('Desconto Financeiro:') }}</span>
                        <span class="font-semibold">{{ number_format($selectedInvoice->discount_financial, 2) }} Kz</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('IVA:') }}</span>
                        <span class="font-semibold">{{ number_format($selectedInvoice->tax_amount, 2) }} Kz</span>
                    </div>

                    {{-- IEC e Imposto de Selo: se foram declarados à AGT têm de
                         estar visíveis aqui, senão o total não reconcilia. --}}
                    @php
                        $__extras = collect();
                        $__primeiro = $selectedInvoice->items->first();
                        if ($__primeiro) {
                            $__extras = \App\Models\Invoicing\LineTax::where('line_type', get_class($__primeiro))
                                ->whereIn('line_id', $selectedInvoice->items->pluck('id'))
                                ->get()
                                ->groupBy('tax_type');
                        }
                    @endphp
                    @foreach($__extras as $__tipo => $__grupo)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">
                            {{ $__tipo === 'IEC' ? 'IEC' : __('Imposto de Selo') }}
                            @if($__tipo === 'IS' && $__grupo->first()->verba_no)
                                <span class="text-xs text-gray-400">{{ __('(verba :numero)', ['numero' => $__grupo->first()->verba_no]) }}</span>
                            @endif
                        </span>
                        <span class="font-semibold {{ $__tipo === 'IEC' ? 'text-orange-700' : 'text-purple-700' }}">
                            {{ number_format($__grupo->sum(fn($t) => (float) $t->tax_amount), 2) }} Kz
                        </span>
                    </div>
                    @endforeach

                    @if($selectedInvoice->items->first()?->tax_country_region === 'AO-CAB')
                    <div class="flex justify-between text-xs">
                        <span class="text-amber-700 font-semibold">{{ __('Região fiscal:') }}</span>
                        <span class="text-amber-700 font-semibold">Cabinda (AO-CAB)</span>
                    </div>
                    @endif

                    @if($selectedInvoice->irt_amount > 0)
                    @php
                        $__ret = \Illuminate\Support\Facades\DB::table('invoicing_withholding_taxes')
                            ->where('document_type', get_class($selectedInvoice))
                            ->where('document_id', $selectedInvoice->id)->first();
                    @endphp
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">
                            {{ __('Retenção :imposto', ['imposto' => $__ret->withholding_tax_type ?? 'IRT']) }}
                            @if($__ret && (float) $__ret->withholding_tax_percentage > 0)
                                ({{ rtrim(rtrim(number_format((float) $__ret->withholding_tax_percentage, 2, ',', ''), '0'), ',') }}%)
                            @endif
                        </span>
                        <span class="font-semibold text-rose-700">-{{ number_format($selectedInvoice->irt_amount, 2) }} Kz</span>
                    </div>
                    @endif
                    <div class="flex justify-between pt-2 border-t-2 border-gray-300">
                        <span class="text-lg font-bold text-gray-900">{{ __('TOTAL:') }}</span>
                        <span class="text-2xl font-bold text-green-600">{{ number_format($selectedInvoice->total, 2) }} Kz</span>
                    </div>
                </div>
            </div>
            
            {{-- Notas --}}
            @if($selectedInvoice->notes)
            <div class="mt-6">
                <h4 class="text-sm font-bold text-gray-700 mb-2">{{ __('Notas:') }}</h4>
                <p class="text-sm text-gray-600">{{ $selectedInvoice->notes }}</p>
            </div>
            @endif
        </div>
        
        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end gap-3">
            <button wire:click="closeViewModal" 
                    class="px-4 py-2 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                {{ __('Fechar') }}
            </button>
            <a href="{{ route('invoicing.sales.invoices.preview', $selectedInvoice->id) }}" target="_blank"
               class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-semibold transition">
                <i class="fas fa-file-pdf mr-2"></i>{{ __('Preview') }}
            </a>
            <x-pdf-descarregar :url="route('invoicing.sales.invoices.preview', $selectedInvoice->id)" />
        </div>
    </div>
</div>
@endif
