{{-- Modal de Visualização --}}
@if($showViewModal && $viewingReservation)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeModals">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 m-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-info-circle text-blue-600"></i>
                Reserva #{{ $viewingReservation->reservation_number }}
            </h3>
            <button wire:click="closeModals" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        @php
            $statusColors = [
                'pending' => 'bg-yellow-100 text-yellow-700',
                'confirmed' => 'bg-blue-100 text-blue-700',
                'checked_in' => 'bg-green-100 text-green-700',
                'checked_out' => 'bg-gray-100 text-gray-700',
                'cancelled' => 'bg-red-100 text-red-700',
                'no_show' => 'bg-red-100 text-red-700',
            ];
        @endphp

        {{-- Status Badge --}}
        <div class="mb-4 flex items-center gap-2">
            <span class="px-3 py-1 rounded-lg font-bold text-sm {{ $statusColors[$viewingReservation->status] ?? 'bg-gray-100 text-gray-700' }}">
                {{ \App\Models\Hotel\Reservation::STATUSES[$viewingReservation->status] ?? $viewingReservation->status }}
            </span>
            <span class="px-3 py-1 rounded-lg text-sm {{ $viewingReservation->payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' }}">
                {{ $viewingReservation->payment_status === 'paid' ? 'Pago' : 'Pendente' }}
            </span>
        </div>

        <div class="space-y-4">
            {{-- Cliente --}}
            <div class="p-4 bg-gray-50 rounded-xl">
                <p class="text-sm text-gray-500 mb-1">Cliente</p>
                <p class="font-bold text-gray-900">{{ $viewingReservation->client->name ?? 'Não definido' }}</p>
                @if($viewingReservation->client?->phone)
                    <p class="text-sm text-gray-600"><i class="fas fa-phone mr-2"></i>{{ $viewingReservation->client->phone }}</p>
                @endif
            </div>

            {{-- Quarto e Datas --}}
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 bg-blue-50 rounded-xl">
                    <p class="text-sm text-blue-600 mb-1">Quarto</p>
                    <p class="font-bold text-gray-900">{{ $viewingReservation->room?->number ?? 'A definir' }}</p>
                    <p class="text-sm text-gray-600">{{ $viewingReservation->roomType?->name }}</p>
                </div>
                <div class="p-4 bg-green-50 rounded-xl">
                    <p class="text-sm text-green-600 mb-1">Período</p>
                    <p class="font-bold text-gray-900">{{ $viewingReservation->check_in_date->format('d/m') }} - {{ $viewingReservation->check_out_date->format('d/m') }}</p>
                    <p class="text-sm text-gray-600">{{ $viewingReservation->nights }} noite(s)</p>
                </div>
            </div>

            {{-- Valores --}}
            <div class="p-4 bg-amber-50 rounded-xl flex items-center justify-between">
                <div>
                    <p class="text-sm text-amber-600 mb-1">Total</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($viewingReservation->total, 0, ',', '.') }} Kz</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">{{ $viewingReservation->adults }} adulto(s) {{ $viewingReservation->children > 0 ? '+ ' . $viewingReservation->children . ' criança(s)' : '' }}</p>
                    <p class="text-sm text-gray-500">{{ number_format($viewingReservation->room_rate, 0, ',', '.') }} Kz/noite</p>
                </div>
            </div>

            @if($viewingReservation->special_requests)
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm text-gray-500 mb-1">Pedidos Especiais</p>
                    <p class="text-gray-700">{{ $viewingReservation->special_requests }}</p>
                </div>
            @endif
        </div>

        {{-- Ações --}}
        <div class="mt-6 flex flex-wrap gap-2">
            @if($viewingReservation->status === 'pending')
                <button wire:click="updateStatus({{ $viewingReservation->id }}, 'confirmed')" 
                        wire:loading.attr="disabled"
                        class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-semibold disabled:opacity-50">
                    <i class="fas fa-check mr-2"></i>Confirmar
                </button>
            @endif

            @if($viewingReservation->status === 'confirmed')
                <button wire:click="updateStatus({{ $viewingReservation->id }}, 'checked_in')" 
                        wire:loading.attr="disabled"
                        class="flex-1 px-4 py-2 bg-green-600 text-white rounded-xl hover:bg-green-700 transition font-semibold disabled:opacity-50">
                    <i class="fas fa-sign-in-alt mr-2"></i>Check-in
                </button>
            @endif

            @if($viewingReservation->status === 'checked_in')
                <button wire:click="updateStatus({{ $viewingReservation->id }}, 'checked_out')" 
                        wire:loading.attr="disabled"
                        class="flex-1 px-4 py-2 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition font-semibold disabled:opacity-50">
                    <i class="fas fa-sign-out-alt mr-2"></i>Check-out
                </button>
            @endif

            @if(in_array($viewingReservation->status, ['pending', 'confirmed']))
                <button wire:click="updateStatus({{ $viewingReservation->id }}, 'cancelled')" 
                        wire:loading.attr="disabled"
                        class="px-4 py-2 bg-red-100 text-red-700 rounded-xl hover:bg-red-200 transition disabled:opacity-50">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
            @endif

            <a href="{{ route('hotel.reservations') }}?view={{ $viewingReservation->id }}" 
               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition">
                <i class="fas fa-external-link-alt mr-2"></i>Ver Completo
            </a>
        </div>
    </div>
</div>
@endif
