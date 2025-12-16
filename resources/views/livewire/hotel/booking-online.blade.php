<div class="min-h-screen bg-white" x-data="{ 
    showBookingModal: false, 
    galleryModal: false, 
    galleryIndex: 0
}">

    {{-- Navbar Fixa --}}
    <nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300" 
         :class="window.scrollY > 50 ? 'bg-white shadow-lg' : 'bg-transparent'"
         x-data="{ scrolled: false }"
         @scroll.window="scrolled = window.scrollY > 50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 md:h-20">
                <div class="flex items-center gap-3">
                    @if($settings->logo_url)
                        <img src="{{ $settings->logo_url }}" alt="{{ $settings->hotel_name }}" class="h-10 w-10 md:h-12 md:w-12 rounded-xl object-cover shadow-lg">
                    @else
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl flex items-center justify-center shadow-lg"
                             style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#6366f1' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                            <i class="fas fa-hotel text-white text-xl"></i>
                        </div>
                    @endif
                    <div>
                        <h1 class="text-lg md:text-xl font-bold transition-colors"
                            :class="scrolled ? 'text-gray-900' : 'text-white'">{{ $settings->hotel_name ?? 'Hotel' }}</h1>
                        @if($settings->star_rating)
                        <p class="text-xs transition-colors" :class="scrolled ? 'text-yellow-500' : 'text-yellow-400'">
                            @for($i = 1; $i <= $settings->star_rating; $i++)<i class="fas fa-star"></i>@endfor
                        </p>
                        @endif
                    </div>
                </div>

                <div class="hidden md:flex items-center gap-8">
                    <a href="#inicio" class="text-sm font-medium transition-colors hover:opacity-80" :class="scrolled ? 'text-gray-700' : 'text-white'">Inicio</a>
                    <a href="#quartos" class="text-sm font-medium transition-colors hover:opacity-80" :class="scrolled ? 'text-gray-700' : 'text-white'">Quartos</a>
                    <a href="#galeria" class="text-sm font-medium transition-colors hover:opacity-80" :class="scrolled ? 'text-gray-700' : 'text-white'">Galeria</a>
                    <a href="#contacto" class="text-sm font-medium transition-colors hover:opacity-80" :class="scrolled ? 'text-gray-700' : 'text-white'">Contacto</a>
                </div>

                <div class="flex items-center gap-3">
                    @if($settings->hotel_whatsapp)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $settings->hotel_whatsapp) }}" target="_blank"
                           class="w-10 h-10 rounded-full flex items-center justify-center transition-all hover:scale-110"
                           :class="scrolled ? 'bg-green-500 text-white' : 'bg-white/20 text-white'">
                            <i class="fab fa-whatsapp text-lg"></i>
                        </a>
                    @endif
                    <button @click="showBookingModal = true; $nextTick(() => document.body.style.overflow = 'hidden')"
                            class="px-5 py-2.5 rounded-full font-bold text-sm transition-all hover:scale-105 shadow-lg"
                            style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#6366f1' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%); color: white;">
                        <i class="fas fa-calendar-check mr-2"></i>
                        <span class="hidden sm:inline">Reservar</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    {{-- HERO SECTION --}}
    <section id="inicio" class="relative min-h-screen flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0" style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#6366f1' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)"></div>
        
        @if($settings->cover_url)
            <div class="absolute inset-0">
                <img src="{{ $settings->cover_url }}" alt="Capa" class="w-full h-full object-cover opacity-30">
            </div>
            <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-transparent to-black/60"></div>
        @endif

        <div class="absolute top-20 left-10 w-20 h-20 bg-white/10 rounded-full blur-xl animate-pulse"></div>
        <div class="absolute bottom-20 right-10 w-32 h-32 bg-white/10 rounded-full blur-xl animate-pulse" style="animation-delay: 1s;"></div>

        <div class="relative z-10 text-center px-4 max-w-4xl mx-auto">
            @if($settings->logo_url)
                <img src="{{ $settings->logo_url }}" alt="{{ $settings->hotel_name }}" class="w-24 h-24 md:w-32 md:h-32 mx-auto mb-6 rounded-2xl shadow-2xl object-cover">
            @endif

            <h1 class="text-4xl md:text-6xl lg:text-7xl font-black text-white mb-4 leading-tight">
                {{ $settings->hotel_name ?? 'Meu Hotel' }}
            </h1>
            
            @if($settings->star_rating)
            <div class="flex items-center justify-center gap-1 mb-4 text-2xl text-yellow-400">
                @for($i = 1; $i <= $settings->star_rating; $i++)<i class="fas fa-star"></i>@endfor
            </div>
            @endif
            
            <p class="text-lg md:text-xl text-white/90 mb-8 max-w-2xl mx-auto">
                {{ $settings->hotel_description ?? 'Reserve o seu quarto online de forma rapida e segura.' }}
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <button @click="showBookingModal = true; $nextTick(() => document.body.style.overflow = 'hidden')"
                        class="px-8 py-4 bg-white text-gray-900 rounded-full font-bold text-lg shadow-2xl hover:shadow-xl transition-all hover:scale-105 flex items-center gap-2">
                    <i class="fas fa-calendar-check"></i>
                    Reservar Agora
                </button>
                <a href="#quartos" class="px-8 py-4 bg-white/20 backdrop-blur-sm text-white rounded-full font-bold text-lg border-2 border-white/30 hover:bg-white/30 transition-all flex items-center gap-2">
                    <i class="fas fa-bed"></i>
                    Ver Quartos
                </a>
            </div>

            <div class="mt-16 grid grid-cols-3 gap-8 max-w-lg mx-auto">
                <div class="text-center">
                    <p class="text-3xl md:text-4xl font-black text-white">{{ $roomTypes->count() }}+</p>
                    <p class="text-white/70 text-sm">Tipos de Quartos</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl md:text-4xl font-black text-white">{{ $settings->star_rating ?? 5 }}★</p>
                    <p class="text-white/70 text-sm">Estrelas</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl md:text-4xl font-black text-white">24h</p>
                    <p class="text-white/70 text-sm">Recepcao</p>
                </div>
            </div>
        </div>

        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
            <a href="#quartos" class="w-10 h-10 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-white">
                <i class="fas fa-chevron-down"></i>
            </a>
        </div>
    </section>

    {{-- QUARTOS SECTION --}}
    <section id="quartos" class="py-20 md:py-32">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="inline-block px-4 py-1 rounded-full text-sm font-bold mb-4"
                      style="background: {{ $settings->primary_color ?? '#6366f1' }}20; color: {{ $settings->primary_color ?? '#6366f1' }}">
                    Acomodacoes
                </span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 mb-4">Nossos Quartos</h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">Escolha o quarto ideal para a sua estadia</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @forelse($roomTypes as $room)
                    <div class="group bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100">
                        <div class="h-2" style="background: linear-gradient(90deg, {{ $settings->primary_color ?? '#6366f1' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)"></div>
                        
                        <div class="relative h-56 overflow-hidden">
                            @if($room->featured_image)
                                <img src="{{ Storage::url($room->featured_image) }}" alt="{{ $room->name }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gray-100">
                                    <i class="fas fa-bed text-6xl text-gray-300"></i>
                                </div>
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            
                            <button wire:click="viewRoomGallery({{ $room->id }})"
                                    class="absolute bottom-3 right-3 px-3 py-1.5 bg-white/90 text-gray-800 rounded-full text-sm font-semibold opacity-0 group-hover:opacity-100 transition">
                                <i class="fas fa-images mr-1"></i>{{ count($room->gallery_urls ?? []) + 1 }} fotos
                            </button>
                        </div>
                        
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900">{{ $room->name }}</h3>
                                    <p class="text-sm text-gray-500"><i class="fas fa-user mr-1"></i>{{ $room->capacity }} pessoas</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-black" style="color: {{ $settings->primary_color ?? '#6366f1' }}">{{ number_format($room->base_price, 0, ',', '.') }}</p>
                                    <p class="text-xs text-gray-500">Kz/noite</p>
                                </div>
                            </div>

                            @if($room->description)
                                <p class="text-gray-600 text-sm mb-4 line-clamp-2">{{ $room->description }}</p>
                            @endif

                            @if($room->amenities && count($room->amenities) > 0)
                            <div class="flex flex-wrap gap-2 mb-4">
                                @php 
                                    $icons = ['wifi'=>'fa-wifi','ac'=>'fa-snowflake','tv'=>'fa-tv','balcony'=>'fa-door-open','safe'=>'fa-lock','minibar'=>'fa-glass-martini'];
                                    $names = ['wifi'=>'Wi-Fi','ac'=>'A/C','tv'=>'TV','balcony'=>'Varanda','safe'=>'Cofre','minibar'=>'Minibar'];
                                @endphp
                                @foreach(array_slice($room->amenities, 0, 4) as $a)
                                <span class="text-xs px-2 py-1 rounded-full" style="background: {{ $settings->primary_color ?? '#6366f1' }}15; color: {{ $settings->primary_color ?? '#6366f1' }}">
                                    <i class="fas {{ $icons[$a] ?? 'fa-check' }} mr-1"></i>{{ $names[$a] ?? ucfirst($a) }}
                                </span>
                                @endforeach
                            </div>
                            @endif

                            <button @click="showBookingModal = true; $wire.selectRoomType({{ $room->id }})"
                                    class="w-full py-3 rounded-xl text-white font-bold transition-all hover:scale-[1.02]"
                                    style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#6366f1' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                                Reservar Este Quarto
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-bed text-6xl text-gray-200 mb-4"></i>
                        <p class="text-gray-500">Nenhum quarto disponivel</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- GALERIA SECTION --}}
    @if($roomTypes->where('featured_image', '!=', null)->count() > 0)
    <section id="galeria" class="py-20 md:py-32 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="inline-block px-4 py-1 rounded-full text-sm font-bold mb-4"
                      style="background: {{ $settings->primary_color ?? '#6366f1' }}20; color: {{ $settings->primary_color ?? '#6366f1' }}">
                    Galeria
                </span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 mb-4">Nossos Espacos</h2>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($roomTypes as $room)
                    @if($room->featured_image)
                    <div class="group relative aspect-square rounded-xl overflow-hidden cursor-pointer"
                         @click="showBookingModal = true; $wire.selectRoomType({{ $room->id }})">
                        <img src="{{ Storage::url($room->featured_image) }}" alt="{{ $room->name }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                            <div class="absolute bottom-4 left-4 text-white">
                                <p class="font-bold">{{ $room->name }}</p>
                                <p class="text-sm">{{ number_format($room->base_price, 0, ',', '.') }} Kz/noite</p>
                            </div>
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- CTA SECTION --}}
    <section class="py-20 md:py-32 relative overflow-hidden">
        <div class="absolute inset-0" style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#6366f1' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)"></div>
        <div class="relative z-10 max-w-4xl mx-auto text-center px-4">
            <h2 class="text-3xl md:text-5xl font-black text-white mb-6">Reserve Agora e Garanta o Melhor Preco</h2>
            <p class="text-xl text-white/80 mb-8 max-w-2xl mx-auto">Faca a sua reserva online e receba confirmacao imediata</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <button @click="showBookingModal = true; $nextTick(() => document.body.style.overflow = 'hidden')"
                        class="px-10 py-5 bg-white text-gray-900 rounded-full font-bold text-lg shadow-2xl hover:shadow-xl transition-all hover:scale-105 flex items-center gap-3">
                    <i class="fas fa-calendar-check text-xl"></i>
                    Reservar Online
                </button>
                @if($settings->hotel_whatsapp)
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $settings->hotel_whatsapp) }}?text=Ola! Gostaria de fazer uma reserva." target="_blank"
                       class="px-10 py-5 bg-green-500 text-white rounded-full font-bold text-lg shadow-2xl hover:shadow-xl transition-all hover:scale-105 flex items-center gap-3">
                        <i class="fab fa-whatsapp text-xl"></i>
                        WhatsApp
                    </a>
                @endif
            </div>
        </div>
    </section>

    {{-- CONTACTO SECTION --}}
    <section id="contacto" class="py-20 md:py-32 bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <div class="text-white">
                    <span class="inline-block px-4 py-1 rounded-full text-sm font-bold mb-4" style="background: {{ $settings->primary_color ?? '#6366f1' }}; color: white;">Contacto</span>
                    <h2 class="text-3xl md:text-4xl font-black mb-6">Entre em Contacto</h2>
                    <p class="text-gray-400 mb-8">Estamos disponiveis para ajuda-lo com sua reserva ou qualquer duvida.</p>

                    <div class="space-y-6">
                        @if($settings->hotel_address)
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0" style="background: {{ $settings->primary_color ?? '#6366f1' }}">
                                <i class="fas fa-map-marker-alt text-white"></i>
                            </div>
                            <div>
                                <p class="font-bold text-white">Endereco</p>
                                <p class="text-gray-400">{{ $settings->hotel_address }}</p>
                            </div>
                        </div>
                        @endif

                        @if($settings->hotel_phone)
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0" style="background: {{ $settings->primary_color ?? '#6366f1' }}">
                                <i class="fas fa-phone text-white"></i>
                            </div>
                            <div>
                                <p class="font-bold text-white">Telefone</p>
                                <p class="text-gray-400">{{ $settings->hotel_phone }}</p>
                            </div>
                        </div>
                        @endif

                        @if($settings->hotel_email)
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0" style="background: {{ $settings->primary_color ?? '#6366f1' }}">
                                <i class="fas fa-envelope text-white"></i>
                            </div>
                            <div>
                                <p class="font-bold text-white">Email</p>
                                <p class="text-gray-400">{{ $settings->hotel_email }}</p>
                            </div>
                        </div>
                        @endif

                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0" style="background: {{ $settings->primary_color ?? '#6366f1' }}">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                            <div>
                                <p class="font-bold text-white">Horarios</p>
                                <p class="text-gray-400">Check-in: {{ $settings->default_check_in_time ? $settings->default_check_in_time->format('H:i') : '14:00' }}</p>
                                <p class="text-gray-400">Check-out: {{ $settings->default_check_out_time ? $settings->default_check_out_time->format('H:i') : '12:00' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white/5 rounded-2xl p-8">
                    <h3 class="text-2xl font-bold text-white mb-6">Reserva Rapida</h3>
                    <button @click="showBookingModal = true; $nextTick(() => document.body.style.overflow = 'hidden')"
                            class="w-full py-4 rounded-xl text-white font-bold text-lg transition-all hover:scale-[1.02]"
                            style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#6366f1' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                        <i class="fas fa-calendar-check mr-2"></i>Abrir Formulario de Reserva
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- FOOTER --}}
    <footer class="py-8 text-center" style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#6366f1' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
        <p class="text-white/90">&copy; {{ date('Y') }} {{ $settings->hotel_name }}. Powered by <a href="https://soserp.ao" class="font-bold hover:underline">SOS ERP</a> - Todos os direitos reservados.</p>
    </footer>

    {{-- ================================================== --}}
    {{-- MODAL DE GALERIA DO QUARTO --}}
    {{-- ================================================== --}}
    @if($viewingRoom)
    <div class="fixed inset-0 z-[100] overflow-y-auto" x-data="{ currentImage: 0 }">
        <div class="fixed inset-0 bg-black/90" wire:click="closeGallery"></div>
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
                {{-- Header --}}
                <div class="sticky top-0 z-10 px-6 py-4 border-b bg-white flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ $viewingRoom->name }}</h2>
                        <p class="text-sm text-gray-500"><i class="fas fa-user mr-1"></i>{{ $viewingRoom->capacity }} pessoas | {{ number_format($viewingRoom->base_price, 0, ',', '.') }} Kz/noite</p>
                    </div>
                    <button wire:click="closeGallery" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                {{-- Imagem Principal --}}
                <div class="relative">
                    @php 
                        $allImages = [];
                        if($viewingRoom->featured_image) $allImages[] = Storage::url($viewingRoom->featured_image);
                        if($viewingRoom->gallery_urls) $allImages = array_merge($allImages, $viewingRoom->gallery_urls);
                    @endphp
                    
                    <div class="relative h-96 bg-gray-100">
                        @foreach($allImages as $index => $image)
                        <img x-show="currentImage === {{ $index }}" 
                             src="{{ $image }}" 
                             alt="{{ $viewingRoom->name }}" 
                             class="w-full h-full object-contain">
                        @endforeach
                        
                        @if(count($allImages) > 1)
                        <button @click="currentImage = (currentImage - 1 + {{ count($allImages) }}) % {{ count($allImages) }}"
                                class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 bg-white/80 rounded-full flex items-center justify-center hover:bg-white">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button @click="currentImage = (currentImage + 1) % {{ count($allImages) }}"
                                class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 bg-white/80 rounded-full flex items-center justify-center hover:bg-white">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-black/50 text-white px-3 py-1 rounded-full text-sm">
                            <span x-text="currentImage + 1"></span> / {{ count($allImages) }}
                        </div>
                        @endif
                    </div>
                </div>
                
                {{-- Thumbnails --}}
                @if(count($allImages) > 1)
                <div class="p-4 border-t">
                    <div class="flex gap-2 overflow-x-auto pb-2">
                        @foreach($allImages as $index => $image)
                        <button @click="currentImage = {{ $index }}"
                                class="flex-shrink-0 w-20 h-20 rounded-lg overflow-hidden border-2 transition"
                                :class="currentImage === {{ $index }} ? 'border-indigo-500' : 'border-transparent'">
                            <img src="{{ $image }}" alt="" class="w-full h-full object-cover">
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif
                
                {{-- Info e Amenidades --}}
                <div class="p-6 border-t">
                    @if($viewingRoom->description)
                    <p class="text-gray-600 mb-4">{{ $viewingRoom->description }}</p>
                    @endif
                    
                    @if($viewingRoom->amenities && count($viewingRoom->amenities) > 0)
                    <div class="flex flex-wrap gap-2 mb-4">
                        @php 
                            $icons = ['wifi'=>'fa-wifi','ac'=>'fa-snowflake','tv'=>'fa-tv','balcony'=>'fa-door-open','safe'=>'fa-lock','minibar'=>'fa-glass-martini'];
                            $names = ['wifi'=>'Wi-Fi','ac'=>'A/C','tv'=>'TV','balcony'=>'Varanda','safe'=>'Cofre','minibar'=>'Minibar'];
                        @endphp
                        @foreach($viewingRoom->amenities as $a)
                        <span class="px-3 py-1 rounded-full text-sm" style="background: {{ $settings->primary_color ?? '#6366f1' }}15; color: {{ $settings->primary_color ?? '#6366f1' }}">
                            <i class="fas {{ $icons[$a] ?? 'fa-check' }} mr-1"></i>{{ $names[$a] ?? ucfirst($a) }}
                        </span>
                        @endforeach
                    </div>
                    @endif
                    
                    <button wire:click="closeGallery" @click="showBookingModal = true; $wire.selectRoomType({{ $viewingRoom->id }})"
                            class="w-full py-3 text-white rounded-xl font-bold"
                            style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#6366f1' }}, {{ $settings->secondary_color ?? '#8b5cf6' }})">
                        <i class="fas fa-calendar-check mr-2"></i>Reservar Este Quarto
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ================================================== --}}
    {{-- MODAL DE RESERVA --}}
    {{-- ================================================== --}}
    @include('livewire.hotel.partials.booking-modal')

</div>
