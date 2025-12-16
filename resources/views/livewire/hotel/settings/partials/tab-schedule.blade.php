<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-blue-50 rounded-xl p-6">
            <h4 class="font-bold text-blue-900 mb-4 flex items-center gap-2">
                <i class="fas fa-sign-in-alt"></i> Check-in
            </h4>
            <div>
                <label class="block text-sm font-medium text-blue-700 mb-2">Horário de Check-in *</label>
                <input wire:model="check_in_time" type="time" 
                       class="w-full px-4 py-3 border border-blue-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition bg-white">
            </div>
            <div class="mt-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model="early_check_in_available" type="checkbox" class="w-5 h-5 text-blue-600 rounded">
                    <span class="text-blue-800">Permitir check-in antecipado</span>
                </label>
            </div>
            @if($early_check_in_available)
            <div class="mt-3">
                <label class="block text-sm font-medium text-blue-700 mb-2">Taxa de Check-in Antecipado (Kz)</label>
                <input wire:model="early_check_in_fee" type="number" step="0.01" min="0"
                       class="w-full px-4 py-3 border border-blue-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition bg-white">
            </div>
            @endif
        </div>

        <div class="bg-orange-50 rounded-xl p-6">
            <h4 class="font-bold text-orange-900 mb-4 flex items-center gap-2">
                <i class="fas fa-sign-out-alt"></i> Check-out
            </h4>
            <div>
                <label class="block text-sm font-medium text-orange-700 mb-2">Horário de Check-out *</label>
                <input wire:model="check_out_time" type="time" 
                       class="w-full px-4 py-3 border border-orange-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition bg-white">
            </div>
            <div class="mt-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model="late_check_out_available" type="checkbox" class="w-5 h-5 text-orange-600 rounded">
                    <span class="text-orange-800">Permitir check-out tardio</span>
                </label>
            </div>
            @if($late_check_out_available)
            <div class="mt-3">
                <label class="block text-sm font-medium text-orange-700 mb-2">Taxa de Check-out Tardio (Kz)</label>
                <input wire:model="late_check_out_fee" type="number" step="0.01" min="0"
                       class="w-full px-4 py-3 border border-orange-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition bg-white">
            </div>
            @endif
        </div>
    </div>

    {{-- Resumo visual --}}
    <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-6">
        <h4 class="font-bold text-gray-700 mb-4">Resumo do Horário</h4>
        <div class="flex items-center justify-center gap-8">
            <div class="text-center">
                <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-2">
                    <i class="fas fa-sign-in-alt text-2xl text-blue-600"></i>
                </div>
                <p class="text-sm text-gray-500">Check-in</p>
                <p class="text-2xl font-bold text-blue-600">{{ $check_in_time }}</p>
            </div>
            <div class="text-4xl text-gray-300">→</div>
            <div class="text-center">
                <div class="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center mb-2">
                    <i class="fas fa-sign-out-alt text-2xl text-orange-600"></i>
                </div>
                <p class="text-sm text-gray-500">Check-out</p>
                <p class="text-2xl font-bold text-orange-600">{{ $check_out_time }}</p>
            </div>
        </div>
    </div>
</div>
