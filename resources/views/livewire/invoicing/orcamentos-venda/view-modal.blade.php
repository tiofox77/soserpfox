{{-- View Quote Modal --}}
@if($showViewModal && $selectedQuote)
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full my-8 animate-scale-in">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-2xl">
            <h3 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-file-signature mr-2"></i>
                {{ __('Orçamento :numero', ['numero' => $selectedQuote->quote_number]) }}
            </h3>
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
                    <p class="font-bold text-gray-900">{{ $selectedQuote->client->name }}</p>
                    @if($selectedQuote->client->nif)
                    <p class="text-sm text-gray-600">NIF: {{ $selectedQuote->client->nif }}</p>
                    @endif
                    @if($selectedQuote->client->email)
                    <p class="text-sm text-gray-600">{{ __('Email:') }} {{ $selectedQuote->client->email }}</p>
                    @endif
                    @if($selectedQuote->client->phone)
                    <p class="text-sm text-gray-600">{{ __('Tel:') }} {{ $selectedQuote->client->phone }}</p>
                    @endif
                </div>
            </div>

            {{-- Datas e Status --}}
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div>
                    <p class="text-sm text-gray-600 mb-1">{{ __('Data do Orçamento:') }}</p>
                    <p class="font-bold text-gray-900">{{ $selectedQuote->quote_date->format('d/m/Y') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 mb-1">{{ __('Válido Até:') }}</p>
                    <p class="font-bold text-gray-900">
                        {{ $selectedQuote->valid_until ? $selectedQuote->valid_until->format('d/m/Y') : '-' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 mb-1">{{ __('Status:') }}</p>
                    @if($selectedQuote->status === 'draft')
                        <span class="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-bold rounded-full">
                            {{ __('Rascunho') }}
                        </span>
                    @elseif($selectedQuote->status === 'sent')
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded-full">
                            {{ __('Enviado') }}
                        </span>
                    @elseif($selectedQuote->status === 'accepted')
                        <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">
                            {{ __('Aceite') }}
                        </span>
                    @else
                        <span class="px-3 py-1 bg-purple-100 text-purple-800 text-xs font-bold rounded-full">
                            {{ __('Convertido') }}
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
                                <th class="px-4 py-2 text-center text-xs font-bold text-gray-700">IVA</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-700">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($selectedQuote->items as $item)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900">{{ $item->product_name }}</p>
                                    @if($item->description)
                                    <p class="text-xs text-gray-500 whitespace-pre-line mt-0.5">{{ $item->description }}</p>
                                    @endif
                                    <p class="text-xs text-gray-400">{{ $item->unit }}</p>
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
                        <span class="font-semibold">{{ number_format($selectedQuote->subtotal, 2) }} Kz</span>
                    </div>
                    @if($selectedQuote->discount_commercial > 0 || $selectedQuote->discount_amount > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('Desconto Comercial:') }}</span>
                        <span class="font-semibold">{{ number_format($selectedQuote->discount_commercial + $selectedQuote->discount_amount, 2) }} Kz</span>
                    </div>
                    @endif
                    @if($selectedQuote->discount_financial > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('Desconto Financeiro:') }}</span>
                        <span class="font-semibold">{{ number_format($selectedQuote->discount_financial, 2) }} Kz</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">IVA:</span>
                        <span class="font-semibold">{{ number_format($selectedQuote->tax_amount, 2) }} Kz</span>
                    </div>
                    @if($selectedQuote->irt_amount > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ __('Retenção (6,5%):') }}</span>
                        <span class="font-semibold">{{ number_format($selectedQuote->irt_amount, 2) }} Kz</span>
                    </div>
                    @endif
                    <div class="flex justify-between pt-2 border-t-2 border-gray-300">
                        <span class="text-lg font-bold text-gray-900">{{ __('TOTAL:') }}</span>
                        <span class="text-2xl font-bold text-green-600">{{ number_format($selectedQuote->total, 2) }} Kz</span>
                    </div>
                </div>
            </div>

            {{-- Notas --}}
            @if($selectedQuote->notes)
            <div class="mt-6">
                <h4 class="text-sm font-bold text-gray-700 mb-2">{{ __('Notas:') }}</h4>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $selectedQuote->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end gap-3">
            <button wire:click="closeViewModal"
                    class="px-4 py-2 border-2 border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-100 transition">
                {{ __('Fechar') }}
            </button>
            <a href="{{ route('invoicing.sales.quotes.preview', $selectedQuote->id) }}" target="_blank"
               class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-xl font-semibold transition">
                <i class="fas fa-file-pdf mr-2"></i>{{ __('Preview') }}
            </a>
        </div>
    </div>
</div>
@endif
