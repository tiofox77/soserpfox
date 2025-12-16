<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-hotel mr-1 text-blue-500"></i> Nome do Hotel *
            </label>
            <input wire:model="hotel_name" type="text" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                   placeholder="Ex: Hotel Paradise">
            @error('hotel_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-star mr-1 text-yellow-500"></i> Classificação (Estrelas) *
            </label>
            <div class="flex gap-2">
                @for($i = 1; $i <= 5; $i++)
                    <button type="button" wire:click="$set('star_rating', {{ $i }})"
                            class="w-12 h-12 rounded-xl flex items-center justify-center transition
                                   {{ $star_rating >= $i ? 'bg-yellow-400 text-white' : 'bg-gray-100 text-gray-400 hover:bg-yellow-100' }}">
                        <i class="fas fa-star text-xl"></i>
                    </button>
                @endfor
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-bold text-gray-700 mb-2">
            <i class="fas fa-align-left mr-1 text-blue-500"></i> Descrição do Hotel
        </label>
        <textarea wire:model="hotel_description" rows="4"
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                  placeholder="Descreva o seu hotel, localização, serviços destacados..."></textarea>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-envelope mr-1 text-blue-500"></i> Email
            </label>
            <input wire:model="hotel_email" type="email" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                   placeholder="reservas@hotel.com">
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-phone mr-1 text-blue-500"></i> Telefone
            </label>
            <input wire:model="hotel_phone" type="text" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                   placeholder="+244 222 123 456">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fab fa-whatsapp mr-1 text-green-500"></i> WhatsApp
            </label>
            <input wire:model="hotel_whatsapp" type="text" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                   placeholder="+244 923 123 456">
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-globe mr-1 text-blue-500"></i> Website
            </label>
            <input wire:model="hotel_website" type="url" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                   placeholder="https://www.meuhotel.com">
        </div>
    </div>

    <div>
        <label class="block text-sm font-bold text-gray-700 mb-2">
            <i class="fas fa-map-marker-alt mr-1 text-red-500"></i> Endereço
        </label>
        <input wire:model="hotel_address" type="text" 
               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
               placeholder="Rua Principal, nº 123">
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-city mr-1 text-blue-500"></i> Cidade
            </label>
            <input wire:model="hotel_city" type="text" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                   placeholder="Luanda">
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-flag mr-1 text-blue-500"></i> País
            </label>
            <input wire:model="hotel_country" type="text" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                   placeholder="Angola">
        </div>
    </div>
</div>
