{{-- Modal de Visualização --}}
@if($viewModal && $viewingReservation)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeViewModal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 m-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-calendar-check text-blue-600"></i>
                    {{ $viewingReservation->reservation_number }}
                </h3>
                @php
                    $statusColors = [
                        'pending' => 'bg-yellow-100 text-yellow-700',
                        'confirmed' => 'bg-blue-100 text-blue-700',
                        'checked_in' => 'bg-green-100 text-green-700',
                        'checked_out' => 'bg-gray-100 text-gray-700',
                        'cancelled' => 'bg-red-100 text-red-700',
                        'no_show' => 'bg-orange-100 text-orange-700',
                    ];
                @endphp
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold mt-1 {{ $statusColors[$viewingReservation->status] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ $viewingReservation->status_label }}
                </span>
            </div>
            <button wire:click="closeViewModal" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
            <div class="bg-blue-50 rounded-xl p-4">
                <h4 class="font-bold text-blue-700 mb-2 flex items-center gap-2">
                    <i class="fas fa-user"></i> Cliente
                </h4>
                <p class="font-bold text-gray-900">{{ $viewingReservation->client->name ?? '-' }}</p>
                <p class="text-sm text-gray-600">{{ $viewingReservation->client->email ?? '' }}</p>
                <p class="text-sm text-gray-600">{{ $viewingReservation->client->phone ?? '' }}</p>
            </div>
            <div class="bg-purple-50 rounded-xl p-4">
                <h4 class="font-bold text-purple-700 mb-2 flex items-center gap-2">
                    <i class="fas fa-door-open"></i> Quarto
                </h4>
                <p class="font-bold text-gray-900">{{ $viewingReservation->roomType->name }}</p>
                @if($viewingReservation->room)
                    <p class="text-sm text-gray-600">Quarto {{ $viewingReservation->room->number }}</p>
                @else
                    <p class="text-sm text-orange-600">Sem quarto atribuído</p>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 rounded-xl p-3 text-center">
                <p class="text-xs text-gray-500 uppercase font-semibold">Check-in</p>
                <p class="font-bold text-gray-900">{{ $viewingReservation->check_in_date->format('d/m/Y') }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3 text-center">
                <p class="text-xs text-gray-500 uppercase font-semibold">Check-out</p>
                <p class="font-bold text-gray-900">{{ $viewingReservation->check_out_date->format('d/m/Y') }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3 text-center">
                <p class="text-xs text-gray-500 uppercase font-semibold">Noites</p>
                <p class="font-bold text-gray-900">{{ $viewingReservation->nights }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3 text-center">
                <p class="text-xs text-gray-500 uppercase font-semibold">Hóspedes</p>
                <p class="font-bold text-gray-900">{{ $viewingReservation->adults }}A + {{ $viewingReservation->children }}C</p>
            </div>
        </div>

        <div class="bg-amber-50 rounded-xl p-4 mb-6">
            <div class="flex justify-between items-center mb-2">
                <span class="text-gray-600">Subtotal</span>
                <span class="font-medium">{{ number_format($viewingReservation->subtotal, 0, ',', '.') }} Kz</span>
            </div>
            @if($viewingReservation->discount > 0)
                <div class="flex justify-between items-center mb-2 text-red-600">
                    <span>Desconto</span>
                    <span>-{{ number_format($viewingReservation->discount, 0, ',', '.') }} Kz</span>
                </div>
            @endif
            <div class="flex justify-between items-center mb-2">
                <span class="text-gray-600">IVA (14%)</span>
                <span class="font-medium">{{ number_format($viewingReservation->tax, 0, ',', '.') }} Kz</span>
            </div>
            <div class="flex justify-between items-center pt-2 border-t border-amber-200 font-bold text-lg">
                <span>Total</span>
                <span class="text-amber-700">{{ number_format($viewingReservation->total, 0, ',', '.') }} Kz</span>
            </div>
            <div class="flex justify-between items-center mt-2 text-sm">
                <span class="{{ $viewingReservation->payment_status === 'paid' ? 'text-green-600' : 'text-orange-600' }} font-semibold">
                    {{ $viewingReservation->payment_status_label }}
                </span>
                <span>Pago: {{ number_format($viewingReservation->paid_amount, 0, ',', '.') }} Kz</span>
            </div>
        </div>

        @if($viewingReservation->special_requests)
            <div class="mb-4">
                <h4 class="font-bold text-gray-700 mb-2 flex items-center gap-2">
                    <i class="fas fa-sticky-note text-yellow-600"></i> Pedidos Especiais
                </h4>
                <p class="text-gray-600 bg-yellow-50 p-3 rounded-xl">{{ $viewingReservation->special_requests }}</p>
            </div>
        @endif

        {{-- Fatura Associada --}}
        @if($viewingReservation->invoice_id)
        <div class="bg-blue-50 rounded-xl p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-file-invoice text-blue-600"></i>
                </div>
                <div>
                    <p class="font-bold text-blue-900">Fatura Emitida</p>
                    <p class="text-sm text-blue-700">{{ $viewingReservation->invoice->invoice_number ?? 'N/A' }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('invoicing.sales.invoices.preview', $viewingReservation->invoice_id) }}" target="_blank"
                   class="px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-medium">
                    <i class="fas fa-eye mr-2"></i>Preview
                </a>
                <a href="{{ route('invoicing.sales.invoices.pdf', $viewingReservation->invoice_id) }}" target="_blank"
                   class="px-4 py-2 bg-red-600 text-white rounded-xl hover:bg-red-700 transition font-medium">
                    <i class="fas fa-file-pdf mr-2"></i>PDF
                </a>
            </div>
        </div>
        @endif

        <div class="flex justify-between items-center pt-4 border-t">
            <div class="flex gap-2">
                @if($viewingReservation->payment_status !== 'paid')
                    <button wire:click="openPaymentModal({{ $viewingReservation->id }})" class="px-4 py-2 bg-green-600 text-white rounded-xl hover:bg-green-700 transition font-medium">
                        <i class="fas fa-cash-register mr-2"></i>Registar Pagamento
                    </button>
                @endif
                @if($viewingReservation->status === 'checked_in')
                    <button wire:click="checkOut({{ $viewingReservation->id }})" onclick="return confirm('Confirma o check-out?')" class="px-4 py-2 bg-orange-600 text-white rounded-xl hover:bg-orange-700 transition font-medium">
                        <i class="fas fa-sign-out-alt mr-2"></i>Check-out
                    </button>
                @endif
            </div>
            <button wire:click="closeViewModal" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">
                Fechar
            </button>
        </div>
    </div>
</div>
@endif
