<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fab fa-instagram mr-1 text-pink-500"></i> Instagram
            </label>
            <input wire:model="instagram" type="text" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                   placeholder="@meuhotel">
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fab fa-facebook mr-1 text-blue-600"></i> Facebook
            </label>
            <input wire:model="facebook" type="text" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                   placeholder="https://facebook.com/meuhotel">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-map-marker-alt mr-1 text-red-500"></i> Google Maps
            </label>
            <input wire:model="google_maps_url" type="url" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                   placeholder="https://maps.google.com/...">
            <p class="text-xs text-gray-500 mt-1">Cole o link do Google Maps do seu hotel</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fab fa-tripadvisor mr-1 text-green-600"></i> TripAdvisor
            </label>
            <input wire:model="tripadvisor_url" type="url" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                   placeholder="https://tripadvisor.com/...">
        </div>
    </div>

    <div>
        <label class="block text-sm font-bold text-gray-700 mb-2">
            <i class="fas fa-bed mr-1 text-blue-500"></i> Booking.com
        </label>
        <input wire:model="booking_com_url" type="url" 
               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
               placeholder="https://booking.com/hotel/...">
    </div>

    {{-- Preview Links --}}
    <div class="bg-gray-50 rounded-xl p-6 mt-4">
        <h4 class="font-bold text-gray-700 mb-4">Preview dos Links</h4>
        <div class="flex flex-wrap gap-3">
            @if($instagram)
                <a href="https://instagram.com/{{ ltrim($instagram, '@') }}" target="_blank"
                   class="px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-xl hover:shadow-lg transition flex items-center gap-2">
                    <i class="fab fa-instagram"></i> Instagram
                </a>
            @endif
            @if($facebook)
                <a href="{{ $facebook }}" target="_blank"
                   class="px-4 py-2 bg-blue-600 text-white rounded-xl hover:shadow-lg transition flex items-center gap-2">
                    <i class="fab fa-facebook-f"></i> Facebook
                </a>
            @endif
            @if($google_maps_url)
                <a href="{{ $google_maps_url }}" target="_blank"
                   class="px-4 py-2 bg-red-500 text-white rounded-xl hover:shadow-lg transition flex items-center gap-2">
                    <i class="fas fa-map-marker-alt"></i> Google Maps
                </a>
            @endif
            @if($tripadvisor_url)
                <a href="{{ $tripadvisor_url }}" target="_blank"
                   class="px-4 py-2 bg-green-500 text-white rounded-xl hover:shadow-lg transition flex items-center gap-2">
                    <i class="fab fa-tripadvisor"></i> TripAdvisor
                </a>
            @endif
            @if($booking_com_url)
                <a href="{{ $booking_com_url }}" target="_blank"
                   class="px-4 py-2 bg-blue-700 text-white rounded-xl hover:shadow-lg transition flex items-center gap-2">
                    <i class="fas fa-bed"></i> Booking.com
                </a>
            @endif
        </div>
    </div>
</div>
