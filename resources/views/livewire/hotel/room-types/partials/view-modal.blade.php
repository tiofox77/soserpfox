{{-- Modal de Visualização --}}
@if($showViewModal && $viewingRoomType)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="$set('showViewModal', false)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl m-4 max-h-[90vh] overflow-hidden">
        {{-- Header com imagem --}}
        <div class="h-48 relative">
            @if($viewingRoomType->featured_image)
                <img src="{{ asset('storage/' . $viewingRoomType->featured_image) }}" alt="{{ $viewingRoomType->name }}" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent"></div>
            @else
                <div class="w-full h-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                    <i class="fas fa-bed text-white/20 text-8xl"></i>
                </div>
            @endif
            
            {{-- Botão fechar --}}
            <button wire:click="$set('showViewModal', false)" class="absolute top-4 right-4 w-10 h-10 bg-black/30 hover:bg-black/50 text-white rounded-full flex items-center justify-center transition">
                <i class="fas fa-times text-xl"></i>
            </button>
            
            {{-- Info no header --}}
            <div class="absolute bottom-4 left-6 right-6">
                <div class="flex items-end justify-between">
                    <div>
                        <span class="px-3 py-1 bg-white/20 backdrop-blur-sm text-white text-sm font-bold rounded-lg">
                            {{ $viewingRoomType->code }}
                        </span>
                        <h2 class="text-3xl font-bold text-white mt-2">{{ $viewingRoomType->name }}</h2>
                    </div>
                    <div class="text-right">
                        <p class="text-white/70 text-sm">Por noite</p>
                        <p class="text-3xl font-bold text-white">{{ number_format($viewingRoomType->base_price, 0, ',', '.') }} <span class="text-lg">Kz</span></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Conteúdo --}}
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-12rem)]">
            {{-- Status e Info Rápida --}}
            <div class="flex flex-wrap gap-3 mb-6">
                <span class="px-4 py-2 rounded-xl text-sm font-bold {{ $viewingRoomType->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    <i class="fas {{ $viewingRoomType->is_active ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                    {{ $viewingRoomType->is_active ? 'Ativo' : 'Inativo' }}
                </span>
                <span class="px-4 py-2 bg-blue-100 text-blue-700 rounded-xl text-sm font-bold">
                    <i class="fas fa-users mr-1"></i>{{ $viewingRoomType->capacity }} pessoas
                </span>
                <span class="px-4 py-2 bg-indigo-100 text-indigo-700 rounded-xl text-sm font-bold">
                    <i class="fas fa-door-open mr-1"></i>{{ $viewingRoomType->rooms_count ?? 0 }} quartos
                </span>
                @if($viewingRoomType->extra_bed_capacity > 0)
                    <span class="px-4 py-2 bg-purple-100 text-purple-700 rounded-xl text-sm font-bold">
                        <i class="fas fa-bed mr-1"></i>+{{ $viewingRoomType->extra_bed_capacity }} camas extra
                    </span>
                @endif
            </div>

            {{-- Descrição --}}
            @if($viewingRoomType->description)
                <div class="mb-6">
                    <h3 class="font-bold text-gray-900 mb-2 flex items-center">
                        <i class="fas fa-align-left mr-2 text-indigo-500"></i>Descrição
                    </h3>
                    <p class="text-gray-600">{{ $viewingRoomType->description }}</p>
                </div>
            @endif

            {{-- Preços --}}
            <div class="mb-6 bg-green-50 rounded-xl p-4">
                <h3 class="font-bold text-green-700 mb-3 flex items-center">
                    <i class="fas fa-coins mr-2"></i>Preços
                </h3>
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-500 mb-1">Base/Noite</p>
                        <p class="text-xl font-bold text-gray-900">{{ number_format($viewingRoomType->base_price, 0, ',', '.') }} Kz</p>
                    </div>
                    <div class="bg-white rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-500 mb-1">Fim de Semana</p>
                        <p class="text-xl font-bold text-gray-900">{{ $viewingRoomType->weekend_price ? number_format($viewingRoomType->weekend_price, 0, ',', '.') . ' Kz' : '-' }}</p>
                    </div>
                    <div class="bg-white rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-500 mb-1">Cama Extra</p>
                        <p class="text-xl font-bold text-gray-900">{{ $viewingRoomType->extra_bed_price ? number_format($viewingRoomType->extra_bed_price, 0, ',', '.') . ' Kz' : '-' }}</p>
                    </div>
                </div>
            </div>

            {{-- Comodidades --}}
            @if($viewingRoomType->amenities && count($viewingRoomType->amenities) > 0)
                <div class="mb-6">
                    <h3 class="font-bold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-concierge-bell mr-2 text-purple-500"></i>Comodidades
                    </h3>
                    @php
                        $amenityIcons = [
                            'wifi' => 'fas fa-wifi text-blue-500',
                            'ac' => 'fas fa-snowflake text-cyan-500',
                            'tv' => 'fas fa-tv text-gray-600',
                            'minibar' => 'fas fa-wine-bottle text-purple-500',
                            'safe' => 'fas fa-lock text-yellow-600',
                            'balcony' => 'fas fa-door-open text-green-500',
                            'sea_view' => 'fas fa-water text-blue-400',
                            'bathtub' => 'fas fa-bath text-indigo-500',
                            'shower' => 'fas fa-shower text-blue-500',
                            'hairdryer' => 'fas fa-wind text-pink-500',
                            'iron' => 'fas fa-tshirt text-gray-500',
                            'desk' => 'fas fa-desktop text-slate-600',
                            'phone' => 'fas fa-phone text-green-600',
                            'room_service' => 'fas fa-concierge-bell text-amber-500',
                            'breakfast' => 'fas fa-coffee text-orange-500',
                        ];
                    @endphp
                    <div class="flex flex-wrap gap-2">
                        @foreach($viewingRoomType->amenities as $amenity)
                            <span class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium flex items-center gap-2">
                                <i class="{{ $amenityIcons[$amenity] ?? 'fas fa-check text-green-500' }}"></i>
                                {{ $availableAmenities[$amenity] ?? $amenity }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Galeria --}}
            @if($viewingRoomType->gallery && count($viewingRoomType->gallery) > 0)
                <div x-data="{ 
                    lightbox: false, 
                    currentImage: '', 
                    currentIndex: 0,
                    images: {{ json_encode(array_map(fn($img) => asset('storage/' . $img), $viewingRoomType->gallery)) }},
                    open(index) { 
                        this.currentIndex = index; 
                        this.currentImage = this.images[index]; 
                        this.lightbox = true; 
                    },
                    next() { 
                        this.currentIndex = (this.currentIndex + 1) % this.images.length;
                        this.currentImage = this.images[this.currentIndex];
                    },
                    prev() { 
                        this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
                        this.currentImage = this.images[this.currentIndex];
                    }
                }">
                    <h3 class="font-bold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-images mr-2 text-amber-500"></i>Galeria ({{ count($viewingRoomType->gallery) }} fotos)
                    </h3>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach($viewingRoomType->gallery as $index => $img)
                            <div class="aspect-square rounded-lg overflow-hidden cursor-pointer group relative" @click="open({{ $index }})">
                                <img src="{{ asset('storage/' . $img) }}" alt="Galeria" class="w-full h-full object-cover group-hover:scale-110 transition duration-300">
                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition flex items-center justify-center">
                                    <i class="fas fa-search-plus text-white text-2xl opacity-0 group-hover:opacity-100 transition"></i>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Lightbox --}}
                    <div x-show="lightbox" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @keydown.escape.window="lightbox = false"
                         @keydown.arrow-left.window="prev()"
                         @keydown.arrow-right.window="next()"
                         class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90" 
                         @click.self="lightbox = false"
                         x-cloak>
                        
                        {{-- Botão Fechar --}}
                        <button @click="lightbox = false" class="absolute top-4 right-4 w-12 h-12 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center transition">
                            <i class="fas fa-times text-2xl"></i>
                        </button>

                        {{-- Navegação Anterior --}}
                        <button @click="prev()" class="absolute left-4 w-12 h-12 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center transition">
                            <i class="fas fa-chevron-left text-xl"></i>
                        </button>

                        {{-- Imagem --}}
                        <div class="max-w-5xl max-h-[85vh] p-4">
                            <img :src="currentImage" alt="Zoom" class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl">
                        </div>

                        {{-- Navegação Próxima --}}
                        <button @click="next()" class="absolute right-4 w-12 h-12 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center transition">
                            <i class="fas fa-chevron-right text-xl"></i>
                        </button>

                        {{-- Contador --}}
                        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 px-4 py-2 bg-white/10 text-white rounded-full text-sm font-medium">
                            <span x-text="currentIndex + 1"></span> / <span x-text="images.length"></span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="border-t bg-gray-50 px-6 py-4 flex justify-between items-center">
            <div class="text-sm text-gray-500">
                <i class="fas fa-clock mr-1"></i>Criado em {{ $viewingRoomType->created_at->format('d/m/Y H:i') }}
            </div>
            <div class="flex gap-2">
                <button wire:click="$set('showViewModal', false)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition font-medium">
                    Fechar
                </button>
                <button wire:click="edit({{ $viewingRoomType->id }})" class="px-4 py-2 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition font-semibold">
                    <i class="fas fa-edit mr-2"></i>Editar
                </button>
            </div>
        </div>
    </div>
</div>
@endif
