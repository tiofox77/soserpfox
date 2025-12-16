{{-- Modal de Check-in --}}
@if($checkInModal && $checkingInReservation)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="$set('checkInModal', false)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 m-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-sign-in-alt text-green-600"></i>
                Check-in
            </h3>
            <button wire:click="$set('checkInModal', false)" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="mb-6">
            <div class="flex items-center gap-4 mb-4 p-4 bg-blue-50 rounded-xl">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xl font-bold">
                    {{ strtoupper(substr($checkingInReservation->client->name ?? 'C', 0, 1)) }}
                </div>
                <div>
                    <p class="font-bold text-gray-900">{{ $checkingInReservation->client->name ?? '-' }}</p>
                    <p class="text-sm text-gray-500">{{ $checkingInReservation->reservation_number }}</p>
                </div>
            </div>
            
            <div class="bg-gray-50 rounded-xl p-4 mb-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Tipo de Quarto</p>
                        <p class="font-bold text-gray-900">{{ $checkingInReservation->roomType->name }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Período</p>
                        <p class="font-bold text-gray-900">{{ $checkingInReservation->nights }} noite(s)</p>
                    </div>
                </div>
            </div>

            <h4 class="font-bold text-gray-700 mb-3">Selecione o Quarto:</h4>
            <div class="grid grid-cols-4 gap-2">
                @forelse($this->availableRooms as $room)
                    <button wire:click="processCheckIn({{ $room->id }})" 
                            wire:loading.attr="disabled"
                            class="p-3 bg-green-100 hover:bg-green-200 text-green-700 rounded-xl font-bold text-center transition shadow-md hover:shadow-lg disabled:opacity-50">
                        <span wire:loading.remove wire:target="processCheckIn({{ $room->id }})">{{ $room->number }}</span>
                        <span wire:loading wire:target="processCheckIn({{ $room->id }})"><i class="fas fa-spinner fa-spin"></i></span>
                    </button>
                @empty
                    <div class="col-span-4 text-center py-8 bg-gray-50 rounded-xl">
                        <i class="fas fa-door-closed text-4xl text-gray-300 mb-2"></i>
                        <p class="text-gray-500">Nenhum quarto disponível deste tipo</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t">
            <button wire:click="$set('checkInModal', false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">
                Cancelar
            </button>
        </div>
    </div>
</div>
@endif
