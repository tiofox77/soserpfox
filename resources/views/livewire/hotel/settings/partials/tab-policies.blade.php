<div class="space-y-6">
    <div>
        <label class="block text-sm font-bold text-gray-700 mb-2">
            <i class="fas fa-handshake mr-1 text-blue-500"></i> Mensagem de Boas-Vindas
        </label>
        <textarea wire:model="welcome_message" rows="3"
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                  placeholder="Bem-vindo ao nosso hotel! Estamos felizes em recebê-lo..."></textarea>
        <p class="text-xs text-gray-500 mt-1">Esta mensagem aparece na página de reservas</p>
    </div>

    <div>
        <label class="block text-sm font-bold text-gray-700 mb-2">
            <i class="fas fa-file-contract mr-1 text-purple-500"></i> Termos e Condições de Reserva
        </label>
        <textarea wire:model="booking_policies" rows="5"
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                  placeholder="Ao efectuar uma reserva, o cliente concorda com os seguintes termos..."></textarea>
    </div>

    <div>
        <label class="block text-sm font-bold text-gray-700 mb-2">
            <i class="fas fa-ban mr-1 text-red-500"></i> Política de Cancelamento
        </label>
        <textarea wire:model="cancellation_policies" rows="5"
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                  placeholder="Cancelamentos devem ser feitos com pelo menos 48 horas de antecedência..."></textarea>
    </div>

    <div>
        <label class="block text-sm font-bold text-gray-700 mb-2">
            <i class="fas fa-home mr-1 text-green-500"></i> Regras da Casa
        </label>
        <textarea wire:model="house_rules" rows="5"
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                  placeholder="- Proibido fumar nos quartos&#10;- Check-in: 14h | Check-out: 12h&#10;- Animais permitidos sob consulta&#10;- Silêncio após as 22h"></textarea>
        <p class="text-xs text-gray-500 mt-1">Use uma regra por linha</p>
    </div>

    {{-- Preview --}}
    @if($house_rules)
    <div class="bg-gray-50 rounded-xl p-6">
        <h4 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-eye text-blue-500"></i> Preview das Regras
        </h4>
        <ul class="space-y-2">
            @foreach(explode("\n", $house_rules) as $rule)
                @if(trim($rule))
                    <li class="flex items-start gap-2 text-gray-600">
                        <i class="fas fa-check-circle text-green-500 mt-1"></i>
                        <span>{{ ltrim(trim($rule), '- ') }}</span>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
    @endif
</div>
