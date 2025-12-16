<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-clock mr-1 text-blue-500"></i> Antecedência Mínima (horas)
            </label>
            <input wire:model="min_advance_booking_hours" type="number" min="0"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
            <p class="text-xs text-gray-500 mt-1">Horas mínimas antes do check-in</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-calendar-minus mr-1 text-orange-500"></i> Mínimo de Dias
            </label>
            <input wire:model="min_advance_booking_days" type="number" min="1"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
            <p class="text-xs text-gray-500 mt-1">Dias mínimos de estadia</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-calendar mr-1 text-blue-500"></i> Antecedência Máxima (dias)
            </label>
            <input wire:model="max_advance_booking_days" type="number" min="1"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
            <p class="text-xs text-gray-500 mt-1">Dias máximos para reservar</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-ban mr-1 text-red-500"></i> Prazo Cancelamento (horas)
            </label>
            <input wire:model="cancellation_hours" type="number" min="0"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
            <p class="text-xs text-gray-500 mt-1">Horas antes do check-in</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-gray-50 rounded-xl p-6">
            <label class="flex items-center gap-3 cursor-pointer">
                <input wire:model="online_booking_enabled" type="checkbox" class="w-5 h-5 text-blue-600 rounded">
                <div>
                    <span class="font-bold text-gray-900">Reservas Online Ativas</span>
                    <p class="text-sm text-gray-500">Permitir reservas através da página pública</p>
                </div>
            </label>
        </div>

        <div class="bg-gray-50 rounded-xl p-6">
            <label class="flex items-center gap-3 cursor-pointer">
                <input wire:model="require_deposit" type="checkbox" class="w-5 h-5 text-blue-600 rounded">
                <div>
                    <span class="font-bold text-gray-900">Exigir Depósito</span>
                    <p class="text-sm text-gray-500">Solicitar pagamento antecipado</p>
                </div>
            </label>
        </div>
    </div>

    @if($require_deposit)
    <div class="bg-yellow-50 rounded-xl p-6">
        <label class="block text-sm font-bold text-yellow-800 mb-2">
            <i class="fas fa-percent mr-1"></i> Percentagem do Depósito
        </label>
        <div class="flex items-center gap-4">
            <input wire:model="deposit_percent" type="range" min="10" max="100" step="5"
                   class="flex-1 h-2 bg-yellow-200 rounded-lg appearance-none cursor-pointer">
            <span class="text-2xl font-bold text-yellow-700 w-20 text-center">{{ $deposit_percent }}%</span>
        </div>
        <p class="text-xs text-yellow-600 mt-2">O cliente pagará {{ $deposit_percent }}% do valor total no ato da reserva</p>
    </div>
    @endif
</div>
