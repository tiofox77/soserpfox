<div class="space-y-8">
    {{-- Overbooking --}}
    <section>
        <h3 class="text-lg font-bold text-gray-800 mb-2 flex items-center gap-2">
            <i class="fas fa-layer-group text-amber-500"></i>Overbooking
        </h3>
        <p class="text-sm text-gray-500 mb-4">Permitir reservar acima da capacidade, assumindo percentual de cancelamentos.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-amber-400">
                <input type="checkbox" wire:model="overbooking_enabled" class="w-5 h-5 text-amber-500">
                <div>
                    <div class="font-bold text-gray-800">Permitir Overbooking</div>
                    <div class="text-xs text-gray-500">Aceita reservas acima da disponibilidade real.</div>
                </div>
            </label>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Percentual Máximo (%)</label>
                <input type="number" min="0" max="50" wire:model="overbooking_percent"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500">
            </div>
        </div>
    </section>

    {{-- Fidelidade --}}
    <section>
        <h3 class="text-lg font-bold text-gray-800 mb-2 flex items-center gap-2">
            <i class="fas fa-star text-yellow-500"></i>Programa de Fidelidade
        </h3>
        <p class="text-sm text-gray-500 mb-4">Recompense hóspedes com pontos por estadia, organizados em níveis.</p>

        <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-yellow-400 mb-4">
            <input type="checkbox" wire:model="loyalty_enabled" class="w-5 h-5 text-yellow-500">
            <div>
                <div class="font-bold text-gray-800">Ativar Programa de Fidelidade</div>
                <div class="text-xs text-gray-500">Os hóspedes acumulam pontos automaticamente no check-out.</div>
            </div>
        </label>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Pontos por Kz gasto</label>
                <input type="number" step="0.001" min="0" wire:model="loyalty_points_per_kz"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500">
                <p class="text-xs text-gray-400 mt-1">Ex: 0.01 = 1 ponto por cada 100 Kz</p>
            </div>
        </div>

        <h4 class="text-sm font-bold text-gray-700 mb-3">Limiares de Níveis (pontos acumulados)</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 border-2 border-gray-300 rounded-xl bg-gray-50">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-medal text-gray-500"></i>
                    <span class="font-bold text-gray-700">Silver</span>
                </div>
                <input type="number" min="0" wire:model="loyalty_tier_silver"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div class="p-4 border-2 border-yellow-300 rounded-xl bg-yellow-50">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-trophy text-yellow-600"></i>
                    <span class="font-bold text-yellow-800">Gold</span>
                </div>
                <input type="number" min="0" wire:model="loyalty_tier_gold"
                       class="w-full px-3 py-2 border border-yellow-300 rounded-lg">
            </div>
            <div class="p-4 border-2 border-purple-300 rounded-xl bg-purple-50">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-crown text-purple-600"></i>
                    <span class="font-bold text-purple-800">Platinum</span>
                </div>
                <input type="number" min="0" wire:model="loyalty_tier_platinum"
                       class="w-full px-3 py-2 border border-purple-300 rounded-lg">
            </div>
        </div>
    </section>

    {{-- Notificações --}}
    <section>
        <h3 class="text-lg font-bold text-gray-800 mb-2 flex items-center gap-2">
            <i class="fas fa-envelope text-blue-500"></i>Notificações por Email
        </h3>
        <p class="text-sm text-gray-500 mb-4">Envio automático de emails ao hóspede (requer email preenchido).</p>
        <div class="space-y-3">
            <label class="flex items-start gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-blue-400">
                <input type="checkbox" wire:model="notify_reservation_confirmed" class="w-5 h-5 text-blue-500 mt-0.5">
                <div>
                    <div class="font-bold text-gray-800">Confirmação de Reserva</div>
                    <div class="text-xs text-gray-500">Enviado quando a reserva é confirmada.</div>
                </div>
            </label>
            <label class="flex items-start gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-blue-400">
                <input type="checkbox" wire:model="notify_pre_arrival" class="w-5 h-5 text-blue-500 mt-0.5">
                <div>
                    <div class="font-bold text-gray-800">Lembrete de Pré-chegada</div>
                    <div class="text-xs text-gray-500">Enviado 2 dias antes do check-in (comando <code class="text-xs bg-gray-100 px-1">hotel:send-prearrival</code>).</div>
                </div>
            </label>
            <label class="flex items-start gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-blue-400">
                <input type="checkbox" wire:model="notify_post_stay" class="w-5 h-5 text-blue-500 mt-0.5">
                <div>
                    <div class="font-bold text-gray-800">Agradecimento Pós-estadia</div>
                    <div class="text-xs text-gray-500">Enviado após o check-out, inclui pontos de fidelidade.</div>
                </div>
            </label>
        </div>
    </section>
</div>
