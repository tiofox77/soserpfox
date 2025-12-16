{{-- Modal de Reserva Rápida --}}
@if($showQuickModal && $quickRoom)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="closeModals">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 m-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-plus-circle text-blue-600"></i>
                Reserva Rápida
            </h3>
            <button wire:click="closeModals" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        {{-- Info do quarto --}}
        <div class="mb-4 p-4 bg-blue-50 rounded-xl flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold"
                 style="background-color: {{ $quickRoom->roomType->color ?? '#6366f1' }}">
                {{ $quickRoom->floor }}
            </div>
            <div>
                <p class="font-bold text-gray-900">Quarto {{ $quickRoom->number }}</p>
                <p class="text-sm text-gray-600">{{ $quickRoom->roomType->name ?? '-' }} | {{ number_format($quickRoom->roomType->base_price ?? 0, 0, ',', '.') }} Kz/noite</p>
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Nome do Hóspede *</label>
                <input wire:model="quickGuest" type="text" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500"
                       placeholder="Nome completo">
                @error('quickGuest') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Check-in</label>
                    <input wire:model="quickDate" type="date" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Check-out *</label>
                    <input wire:model="quickCheckOut" type="date" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                    @error('quickCheckOut') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Adultos</label>
                    <input wire:model="quickAdults" type="number" min="1" max="10"
                           class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Crianças</label>
                    <input wire:model="quickChildren" type="number" min="0" max="10"
                           class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Observações</label>
                <textarea wire:model="quickNotes" rows="2"
                          class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500"
                          placeholder="Pedidos especiais..."></textarea>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button wire:click="closeModals" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition">
                Cancelar
            </button>
            <button wire:click="createQuickReservation" wire:loading.attr="disabled" wire:target="createQuickReservation"
                    class="px-6 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-semibold disabled:opacity-50">
                <span wire:loading.remove wire:target="createQuickReservation">
                    <i class="fas fa-check mr-2"></i>Criar Reserva
                </span>
                <span wire:loading wire:target="createQuickReservation">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Criando...
                </span>
            </button>
        </div>
    </div>
</div>
@endif
