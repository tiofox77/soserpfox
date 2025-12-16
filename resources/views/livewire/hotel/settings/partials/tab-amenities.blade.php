<div class="space-y-6">
    <div>
        <h3 class="text-lg font-bold text-gray-900 mb-2">Comodidades do Hotel</h3>
        <p class="text-gray-500 text-sm mb-6">Selecione as comodidades disponíveis no seu hotel</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach($availableAmenities as $key => $amenity)
            <div wire:click="toggleAmenity('{{ $key }}')"
                 class="p-4 rounded-xl border-2 cursor-pointer transition hover:shadow-md
                        {{ in_array($key, $amenities) ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-300' }}">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center
                                {{ in_array($key, $amenities) ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-500' }}">
                        <i class="fas fa-{{ $amenity['icon'] }} text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium {{ in_array($key, $amenities) ? 'text-blue-900' : 'text-gray-700' }}">
                            {{ $amenity['label'] }}
                        </p>
                    </div>
                    <div class="w-5 h-5 rounded border-2 flex items-center justify-center
                                {{ in_array($key, $amenities) ? 'bg-blue-500 border-blue-500' : 'border-gray-300' }}">
                        @if(in_array($key, $amenities))
                            <i class="fas fa-check text-white text-xs"></i>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Resumo --}}
    @if(count($amenities) > 0)
    <div class="bg-blue-50 rounded-xl p-4 mt-6">
        <p class="text-blue-800 font-medium mb-2">
            <i class="fas fa-check-circle mr-1"></i> {{ count($amenities) }} comodidades selecionadas
        </p>
        <div class="flex flex-wrap gap-2">
            @foreach($amenities as $key)
                @if(isset($availableAmenities[$key]))
                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm flex items-center gap-1">
                        <i class="fas fa-{{ $availableAmenities[$key]['icon'] }} text-xs"></i>
                        {{ $availableAmenities[$key]['label'] }}
                    </span>
                @endif
            @endforeach
        </div>
    </div>
    @endif
</div>
