<div class="space-y-6">
    {{-- Header com link --}}
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-bold text-gray-900">Tipos de Quartos</h3>
            <p class="text-gray-500 text-sm">Selecione os quartos em destaque para a página de reservas</p>
        </div>
        <a href="{{ route('hotel.room-types') }}" class="px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition flex items-center gap-2">
            <i class="fas fa-cog"></i> Gerir Tipos de Quartos
        </a>
    </div>

    {{-- Grid de Tipos de Quartos --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($roomTypes as $roomType)
            <div class="bg-white border-2 rounded-2xl overflow-hidden transition hover:shadow-lg cursor-pointer
                        {{ in_array($roomType->id, $featured_rooms) ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200' }}"
                 wire:click="toggleFeaturedRoom({{ $roomType->id }})">
                {{-- Imagem --}}
                <div class="relative h-48 bg-gradient-to-br from-blue-100 to-indigo-100">
                    @if($roomType->featured_image)
                        <img src="{{ Storage::url($roomType->featured_image) }}" 
                             alt="{{ $roomType->name }}"
                             class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            <i class="fas fa-bed text-6xl text-blue-300"></i>
                        </div>
                    @endif
                    
                    {{-- Badge destaque --}}
                    @if(in_array($roomType->id, $featured_rooms))
                        <div class="absolute top-3 right-3 bg-blue-600 text-white px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1">
                            <i class="fas fa-star"></i> Destaque
                        </div>
                    @endif

                    {{-- Badge quantidade --}}
                    <div class="absolute bottom-3 left-3 bg-black/60 text-white px-3 py-1 rounded-full text-xs">
                        <i class="fas fa-door-open mr-1"></i> {{ $roomType->rooms->count() }} quartos
                    </div>
                </div>

                {{-- Info --}}
                <div class="p-4">
                    <div class="flex items-start justify-between mb-2">
                        <h4 class="font-bold text-gray-900">{{ $roomType->name }}</h4>
                        <div class="text-right">
                            <p class="text-lg font-bold text-blue-600">{{ number_format($roomType->base_price, 0, ',', '.') }} Kz</p>
                            <p class="text-xs text-gray-500">/noite</p>
                        </div>
                    </div>

                    @if($roomType->description)
                        <p class="text-gray-600 text-sm mb-3 line-clamp-2">{{ $roomType->description }}</p>
                    @endif

                    {{-- Capacidade --}}
                    <div class="flex items-center gap-4 text-sm text-gray-600">
                        <span class="flex items-center gap-1">
                            <i class="fas fa-user text-blue-500"></i> {{ $roomType->max_adults }} adultos
                        </span>
                        @if($roomType->max_children > 0)
                            <span class="flex items-center gap-1">
                                <i class="fas fa-child text-green-500"></i> {{ $roomType->max_children }} crianças
                            </span>
                        @endif
                        <span class="flex items-center gap-1">
                            <i class="fas fa-ruler-combined text-purple-500"></i> {{ $roomType->size_sqm ?? '-' }}m²
                        </span>
                    </div>

                    {{-- Amenidades do quarto --}}
                    @if($roomType->amenities && count($roomType->amenities) > 0)
                        <div class="flex flex-wrap gap-2 mt-3">
                            @foreach(array_slice($roomType->amenities, 0, 4) as $amenity)
                                <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs">
                                    {{ $amenity }}
                                </span>
                            @endforeach
                            @if(count($roomType->amenities) > 4)
                                <span class="px-2 py-1 bg-blue-100 text-blue-600 rounded-lg text-xs">
                                    +{{ count($roomType->amenities) - 4 }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Checkbox visual --}}
                <div class="px-4 pb-4">
                    <div class="flex items-center gap-2 p-3 rounded-xl {{ in_array($roomType->id, $featured_rooms) ? 'bg-blue-50' : 'bg-gray-50' }}">
                        <div class="w-5 h-5 rounded border-2 flex items-center justify-center
                                    {{ in_array($roomType->id, $featured_rooms) ? 'bg-blue-600 border-blue-600' : 'border-gray-300' }}">
                            @if(in_array($roomType->id, $featured_rooms))
                                <i class="fas fa-check text-white text-xs"></i>
                            @endif
                        </div>
                        <span class="text-sm {{ in_array($roomType->id, $featured_rooms) ? 'text-blue-700 font-medium' : 'text-gray-600' }}">
                            {{ in_array($roomType->id, $featured_rooms) ? 'Em destaque na página' : 'Clique para destacar' }}
                        </span>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-3 text-center py-12">
                <div class="w-20 h-20 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-bed text-3xl text-gray-400"></i>
                </div>
                <h4 class="font-bold text-gray-700 mb-2">Nenhum tipo de quarto</h4>
                <p class="text-gray-500 mb-4">Comece por criar os tipos de quartos do seu hotel</p>
                <a href="{{ route('hotel.room-types') }}" class="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition inline-flex items-center gap-2">
                    <i class="fas fa-plus"></i> Criar Tipo de Quarto
                </a>
            </div>
        @endforelse
    </div>

    {{-- Lista de Quartos Individuais --}}
    @if($rooms->count() > 0)
    <div class="mt-8">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Quartos Disponíveis ({{ $rooms->count() }})</h3>
        <div class="bg-gray-50 rounded-xl p-4">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach($rooms as $room)
                    <div class="bg-white rounded-xl p-3 border border-gray-200 hover:border-blue-300 transition">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-door-open text-blue-600 text-sm"></i>
                            </span>
                            <span class="font-bold text-gray-900">{{ $room->number }}</span>
                        </div>
                        <p class="text-xs text-gray-500">{{ $room->roomType->name ?? '-' }}</p>
                        <div class="mt-2">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                {{ $room->status === 'available' ? 'bg-green-100 text-green-700' : 
                                   ($room->status === 'occupied' ? 'bg-red-100 text-red-700' : 
                                   ($room->status === 'maintenance' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700')) }}">
                                {{ $room->status === 'available' ? 'Disponível' : 
                                   ($room->status === 'occupied' ? 'Ocupado' : 
                                   ($room->status === 'maintenance' ? 'Manutenção' : $room->status)) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
