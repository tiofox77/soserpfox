<div class="min-h-screen bg-white" x-data="{ 
    showBookingModal: false, 
    currentSlide: 0, 
    galleryModal: false, 
    galleryIndex: 0,
    autoSlide: null,
    startAutoSlide() {
        this.autoSlide = setInterval(() => {
            this.currentSlide = (this.currentSlide + 1) % {{ max(1, count($settings->gallery_images ?? [])) }};
        }, 5000);
    },
    stopAutoSlide() {
        if(this.autoSlide) clearInterval(this.autoSlide);
    }
}" x-init="startAutoSlide()">

    {{-- Navbar Fixa --}}
    <nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300" 
         :class="window.scrollY > 50 ? 'bg-white shadow-lg' : 'bg-transparent'"
         x-data="{ scrolled: false }"
         @scroll.window="scrolled = window.scrollY > 50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 md:h-20">
                {{-- Logo --}}
                <div class="flex items-center gap-3">
                    @if($settings->logo_url)
                        <img src="{{ $settings->logo_url }}" alt="{{ $settings->salon_name }}" class="h-10 w-10 md:h-12 md:w-12 rounded-xl object-cover shadow-lg">
                    @else
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl flex items-center justify-center shadow-lg"
                             style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                            <i class="fas fa-spa text-white text-xl"></i>
                        </div>
                    @endif
                    <div>
                        <h1 class="text-lg md:text-xl font-bold transition-colors"
                            :class="scrolled ? 'text-gray-900' : 'text-white'">{{ $settings->salon_name ?? 'Salão' }}</h1>
                        <p class="text-xs hidden md:block transition-colors"
                           :class="scrolled ? 'text-gray-500' : 'text-white/70'">Beleza & Bem-estar</p>
                    </div>
                </div>

                {{-- Menu Links (Desktop) --}}
                <div class="hidden md:flex items-center gap-8">
                    <a href="#inicio" class="text-sm font-medium transition-colors hover:opacity-80"
                       :class="scrolled ? 'text-gray-700' : 'text-white'">Início</a>
                    <a href="#sobre" class="text-sm font-medium transition-colors hover:opacity-80"
                       :class="scrolled ? 'text-gray-700' : 'text-white'">Sobre</a>
                    <a href="#servicos" class="text-sm font-medium transition-colors hover:opacity-80"
                       :class="scrolled ? 'text-gray-700' : 'text-white'">Serviços</a>
                    <a href="#equipa" class="text-sm font-medium transition-colors hover:opacity-80"
                       :class="scrolled ? 'text-gray-700' : 'text-white'">Equipa</a>
                    <a href="#galeria" class="text-sm font-medium transition-colors hover:opacity-80"
                       :class="scrolled ? 'text-gray-700' : 'text-white'">Galeria</a>
                    <a href="#contacto" class="text-sm font-medium transition-colors hover:opacity-80"
                       :class="scrolled ? 'text-gray-700' : 'text-white'">Contacto</a>
                </div>

                {{-- CTA Button --}}
                <div class="flex items-center gap-3">
                    @if($settings->salon_whatsapp)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $settings->salon_whatsapp) }}" target="_blank"
                           class="w-10 h-10 rounded-full flex items-center justify-center transition-all hover:scale-110"
                           :class="scrolled ? 'bg-green-500 text-white' : 'bg-white/20 text-white'">
                            <i class="fab fa-whatsapp text-lg"></i>
                        </a>
                    @endif
                    <button @click="showBookingModal = true; $nextTick(() => document.body.style.overflow = 'hidden')"
                            class="px-5 py-2.5 rounded-full font-bold text-sm transition-all hover:scale-105 shadow-lg"
                            style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%); color: white;">
                        <i class="fas fa-calendar-check mr-2"></i>
                        <span class="hidden sm:inline">Agendar</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    {{-- ============================================== --}}
    {{-- HERO SECTION --}}
    {{-- ============================================== --}}
    <section id="inicio" class="relative min-h-screen flex items-center justify-center overflow-hidden">
        {{-- Background Gradient --}}
        <div class="absolute inset-0" style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)"></div>
        
        {{-- Background Pattern --}}
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.4\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
        </div>

        {{-- Cover Image Overlay --}}
        @if($settings->cover_url)
            <div class="absolute inset-0">
                <img src="{{ $settings->cover_url }}" alt="Capa" class="w-full h-full object-cover opacity-30">
            </div>
            <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-transparent to-black/60"></div>
        @endif

        {{-- Floating Elements --}}
        <div class="absolute top-20 left-10 w-20 h-20 bg-white/10 rounded-full blur-xl animate-pulse"></div>
        <div class="absolute bottom-20 right-10 w-32 h-32 bg-white/10 rounded-full blur-xl animate-pulse" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/2 right-20 w-16 h-16 bg-white/10 rounded-full blur-xl animate-pulse" style="animation-delay: 2s;"></div>

        {{-- Content --}}
        <div class="relative z-10 text-center px-4 max-w-4xl mx-auto">
            @if($settings->logo_url)
                <img src="{{ $settings->logo_url }}" alt="{{ $settings->salon_name }}" 
                     class="w-24 h-24 md:w-32 md:h-32 mx-auto mb-6 rounded-2xl shadow-2xl object-cover">
            @endif

            <h1 class="text-4xl md:text-6xl lg:text-7xl font-black text-white mb-4 leading-tight">
                {{ $settings->salon_name ?? 'Meu Salão' }}
            </h1>
            
            @if($settings->welcome_message)
                <p class="text-lg md:text-xl text-white/90 mb-8 max-w-2xl mx-auto">
                    {{ $settings->welcome_message }}
                </p>
            @else
                <p class="text-lg md:text-xl text-white/90 mb-8 max-w-2xl mx-auto">
                    Transforme seu visual com os melhores profissionais da cidade
                </p>
            @endif

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <button @click="showBookingModal = true; $nextTick(() => document.body.style.overflow = 'hidden')"
                        class="px-8 py-4 bg-white text-gray-900 rounded-full font-bold text-lg shadow-2xl hover:shadow-xl transition-all hover:scale-105 flex items-center gap-2">
                    <i class="fas fa-calendar-check"></i>
                    Agendar Agora
                </button>
                <a href="#servicos" 
                   class="px-8 py-4 bg-white/20 backdrop-blur-sm text-white rounded-full font-bold text-lg border-2 border-white/30 hover:bg-white/30 transition-all flex items-center gap-2">
                    <i class="fas fa-spa"></i>
                    Ver Serviços
                </a>
            </div>

            {{-- Stats --}}
            <div class="mt-16 grid grid-cols-3 gap-8 max-w-lg mx-auto">
                <div class="text-center">
                    <p class="text-3xl md:text-4xl font-black text-white">{{ $professionals->count() }}+</p>
                    <p class="text-white/70 text-sm">Profissionais</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl md:text-4xl font-black text-white">{{ $services->count() }}+</p>
                    <p class="text-white/70 text-sm">Serviços</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl md:text-4xl font-black text-white">5★</p>
                    <p class="text-white/70 text-sm">Avaliação</p>
                </div>
            </div>
        </div>

        {{-- Scroll Indicator --}}
        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
            <a href="#sobre" class="w-10 h-10 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-white">
                <i class="fas fa-chevron-down"></i>
            </a>
        </div>
    </section>

    {{-- ============================================== --}}
    {{-- SOBRE SECTION --}}
    {{-- ============================================== --}}
    <section id="sobre" class="py-20 md:py-32 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                {{-- Image/Logo Side --}}
                <div class="relative">
                    @if($settings->cover_url)
                        <div class="relative rounded-2xl overflow-hidden shadow-2xl">
                            <img src="{{ $settings->cover_url }}" alt="{{ $settings->salon_name }}" class="w-full h-96 object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                        </div>
                    @else
                        <div class="relative rounded-2xl overflow-hidden shadow-2xl h-96 flex items-center justify-center"
                             style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }}30 0%, {{ $settings->secondary_color ?? '#8b5cf6' }}30 100%)">
                            <i class="fas fa-spa text-9xl" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
                        </div>
                    @endif
                    
                    {{-- Floating Card --}}
                    <div class="absolute -bottom-6 -right-6 bg-white rounded-2xl shadow-xl p-6 max-w-xs">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-full flex items-center justify-center text-white"
                                 style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                                <i class="fas fa-clock text-xl"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-900">Horário</p>
                                <p class="text-sm text-gray-500">{{ $settings->schedule_formatted ?? '09:00 - 19:00' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Text Side --}}
                <div>
                    <span class="inline-block px-4 py-1 rounded-full text-sm font-bold mb-4"
                          style="background: {{ $settings->primary_color ?? '#ec4899' }}20; color: {{ $settings->primary_color ?? '#ec4899' }}">
                        Sobre Nós
                    </span>
                    <h2 class="text-3xl md:text-4xl font-black text-gray-900 mb-6">
                        Bem-vindo ao <span style="color: {{ $settings->primary_color ?? '#ec4899' }}">{{ $settings->salon_name ?? 'Nosso Salão' }}</span>
                    </h2>
                    
                    @if($settings->salon_description)
                        <p class="text-lg text-gray-600 mb-6 leading-relaxed">
                            {{ $settings->salon_description }}
                        </p>
                    @else
                        <p class="text-lg text-gray-600 mb-6 leading-relaxed">
                            Somos um espaço dedicado à sua beleza e bem-estar. Com profissionais qualificados e um ambiente acolhedor, oferecemos serviços de alta qualidade para realçar a sua beleza natural.
                        </p>
                    @endif

                    {{-- Features --}}
                    <div class="grid grid-cols-2 gap-4 mb-8">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                 style="background: {{ $settings->primary_color ?? '#ec4899' }}20">
                                <i class="fas fa-check" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
                            </div>
                            <span class="text-gray-700 font-medium">Profissionais Qualificados</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                 style="background: {{ $settings->primary_color ?? '#ec4899' }}20">
                                <i class="fas fa-check" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
                            </div>
                            <span class="text-gray-700 font-medium">Produtos Premium</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                 style="background: {{ $settings->primary_color ?? '#ec4899' }}20">
                                <i class="fas fa-check" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
                            </div>
                            <span class="text-gray-700 font-medium">Ambiente Acolhedor</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                 style="background: {{ $settings->primary_color ?? '#ec4899' }}20">
                                <i class="fas fa-check" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
                            </div>
                            <span class="text-gray-700 font-medium">Agendamento Online</span>
                        </div>
                    </div>

                    {{-- Contact Info --}}
                    <div class="flex flex-wrap gap-4">
                        @if($settings->salon_phone)
                            <a href="tel:{{ $settings->salon_phone }}" 
                               class="flex items-center gap-2 px-4 py-2 bg-gray-100 rounded-full text-gray-700 hover:bg-gray-200 transition">
                                <i class="fas fa-phone"></i>
                                {{ $settings->salon_phone }}
                            </a>
                        @endif
                        @if($settings->salon_whatsapp)
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $settings->salon_whatsapp) }}" target="_blank"
                               class="flex items-center gap-2 px-4 py-2 bg-green-500 text-white rounded-full hover:bg-green-600 transition">
                                <i class="fab fa-whatsapp"></i>
                                WhatsApp
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================== --}}
    {{-- SERVIÇOS SECTION --}}
    {{-- ============================================== --}}
    <section id="servicos" class="py-20 md:py-32">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
            <div class="text-center mb-16">
                <span class="inline-block px-4 py-1 rounded-full text-sm font-bold mb-4"
                      style="background: {{ $settings->primary_color ?? '#ec4899' }}20; color: {{ $settings->primary_color ?? '#ec4899' }}">
                    Nossos Serviços
                </span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 mb-4">
                    O Que Oferecemos
                </h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Descubra nossa variedade de serviços pensados para realçar sua beleza
                </p>
            </div>

            {{-- Categories Filter --}}
            @if($categories->count() > 0)
            <div class="flex flex-wrap justify-center gap-3 mb-12">
                <button wire:click="selectCategory(null)"
                        class="px-6 py-2 rounded-full font-medium transition-all
                               {{ !$selectedCategory ? 'text-white shadow-lg' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                        @if(!$selectedCategory) style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)" @endif>
                    Todos
                </button>
                @foreach($categories as $category)
                    <button wire:click="selectCategory({{ $category->id }})"
                            class="px-6 py-2 rounded-full font-medium transition-all
                                   {{ $selectedCategory == $category->id ? 'text-white shadow-lg' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                            @if($selectedCategory == $category->id) style="background: {{ $category->color ?? $settings->primary_color }}" @endif>
                        @if($category->icon) <span class="mr-1">{{ $category->icon }}</span> @endif
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>
            @endif

            {{-- Services Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($services->take(6) as $service)
                    <div class="group bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100">
                        {{-- Service Header --}}
                        <div class="h-2" style="background: linear-gradient(90deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)"></div>
                        
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-gray-900 group-hover:text-pink-600 transition">
                                        {{ $service->name }}
                                    </h3>
                                    @if($service->category)
                                        <span class="text-xs px-2 py-1 rounded-full mt-2 inline-block"
                                              style="background: {{ $service->category->color ?? '#e5e7eb' }}20; color: {{ $service->category->color ?? '#6b7280' }}">
                                            {{ $service->category->name }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-black" style="color: {{ $settings->primary_color ?? '#ec4899' }}">
                                        {{ number_format($service->price, 0, ',', '.') }}
                                    </p>
                                    <p class="text-xs text-gray-500">Kz</p>
                                </div>
                            </div>

                            @if($service->text_description)
                                <p class="text-gray-600 text-sm mb-4 line-clamp-2">{{ $service->text_description }}</p>
                            @endif

                            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                                <span class="flex items-center gap-1 text-sm text-gray-500">
                                    <i class="fas fa-clock"></i>
                                    {{ $service->duration_formatted }}
                                </span>
                                <button @click="showBookingModal = true; $wire.toggleService({{ $service->id }})"
                                        class="px-4 py-2 rounded-full text-sm font-bold text-white transition-all hover:scale-105"
                                        style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                                    Agendar
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-spa text-6xl text-gray-200 mb-4"></i>
                        <p class="text-gray-500">Nenhum serviço disponível no momento</p>
                    </div>
                @endforelse
            </div>

            {{-- Ver Todos --}}
            @if($services->count() > 6)
            <div class="text-center mt-12">
                <button @click="showBookingModal = true"
                        class="px-8 py-4 rounded-full font-bold text-white shadow-lg hover:shadow-xl transition-all hover:scale-105"
                        style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                    Ver Todos os Serviços <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
            @endif
        </div>
    </section>

    {{-- ============================================== --}}
    {{-- EQUIPA SECTION --}}
    {{-- ============================================== --}}
    @if($professionals->count() > 0)
    <section id="equipa" class="py-20 md:py-32 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
            <div class="text-center mb-16">
                <span class="inline-block px-4 py-1 rounded-full text-sm font-bold mb-4"
                      style="background: {{ $settings->primary_color ?? '#ec4899' }}20; color: {{ $settings->primary_color ?? '#ec4899' }}">
                    Nossa Equipa
                </span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 mb-4">
                    Profissionais Especializados
                </h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Conheça os talentos por trás de cada transformação
                </p>
            </div>

            {{-- Team Grid --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach($professionals->take(8) as $professional)
                    <div class="group text-center">
                        <div class="relative mb-4 overflow-hidden rounded-2xl">
                            @if($professional->photo)
                                <img src="{{ Storage::url($professional->photo) }}" alt="{{ $professional->name }}" 
                                     class="w-full aspect-square object-cover group-hover:scale-110 transition-transform duration-500">
                            @else
                                <div class="w-full aspect-square flex items-center justify-center text-white text-4xl font-black"
                                     style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                                    {{ strtoupper(substr($professional->name, 0, 2)) }}
                                </div>
                            @endif
                            
                            {{-- Overlay --}}
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end justify-center pb-4">
                                <button @click="showBookingModal = true; $wire.selectProfessional({{ $professional->id }})"
                                        class="px-4 py-2 bg-white text-gray-900 rounded-full text-sm font-bold hover:scale-105 transition">
                                    Agendar
                                </button>
                            </div>
                        </div>
                        <h3 class="font-bold text-gray-900">{{ $professional->nickname ?? $professional->name }}</h3>
                        @if($professional->specialization)
                            <p class="text-sm text-gray-500">{{ $professional->specialization }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ============================================== --}}
    {{-- GALERIA SECTION --}}
    {{-- ============================================== --}}
    @if($settings->gallery_images && count($settings->gallery_images) > 0)
    <section id="galeria" class="py-20 md:py-32">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Header --}}
            <div class="text-center mb-16">
                <span class="inline-block px-4 py-1 rounded-full text-sm font-bold mb-4"
                      style="background: {{ $settings->primary_color ?? '#ec4899' }}20; color: {{ $settings->primary_color ?? '#ec4899' }}">
                    Galeria
                </span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 mb-4">
                    Nossos Trabalhos
                </h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Veja algumas das nossas transformações
                </p>
            </div>

            {{-- Gallery Grid --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($settings->gallery_images as $index => $image)
                    <div class="group relative aspect-square rounded-xl overflow-hidden cursor-pointer"
                         @click="galleryModal = true; galleryIndex = {{ $index }}">
                        <img src="{{ Storage::url($image) }}" alt="Galeria {{ $index + 1 }}" 
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <i class="fas fa-search-plus text-white text-2xl"></i>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Gallery Modal --}}
    <div x-show="galleryModal" x-cloak
         class="fixed inset-0 z-[100] bg-black/95 flex items-center justify-center"
         @keydown.escape.window="galleryModal = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        {{-- Close Button --}}
        <button @click="galleryModal = false" class="absolute top-4 right-4 w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-white hover:bg-white/30 transition">
            <i class="fas fa-times text-xl"></i>
        </button>

        {{-- Navigation --}}
        <button @click="galleryIndex = (galleryIndex - 1 + {{ count($settings->gallery_images) }}) % {{ count($settings->gallery_images) }}"
                class="absolute left-4 w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-white hover:bg-white/30 transition">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button @click="galleryIndex = (galleryIndex + 1) % {{ count($settings->gallery_images) }}"
                class="absolute right-4 w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-white hover:bg-white/30 transition">
            <i class="fas fa-chevron-right"></i>
        </button>

        {{-- Image --}}
        <div class="max-w-4xl max-h-[80vh] px-16">
            @foreach($settings->gallery_images as $index => $image)
                <img x-show="galleryIndex === {{ $index }}" 
                     src="{{ Storage::url($image) }}" 
                     alt="Galeria {{ $index + 1 }}" 
                     class="max-w-full max-h-[80vh] object-contain rounded-lg">
            @endforeach
        </div>

        {{-- Counter --}}
        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-white/70 text-sm">
            <span x-text="galleryIndex + 1"></span> / {{ count($settings->gallery_images) }}
        </div>
    </div>
    @endif

    {{-- ============================================== --}}
    {{-- CTA SECTION --}}
    {{-- ============================================== --}}
    <section class="py-20 md:py-32 relative overflow-hidden">
        <div class="absolute inset-0" style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)"></div>
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.4\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
        </div>

        <div class="relative z-10 max-w-4xl mx-auto text-center px-4">
            <h2 class="text-3xl md:text-5xl font-black text-white mb-6">
                Pronto para Transformar seu Visual?
            </h2>
            <p class="text-xl text-white/80 mb-8 max-w-2xl mx-auto">
                Agende agora mesmo e deixe nossos profissionais cuidarem de você
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <button @click="showBookingModal = true; $nextTick(() => document.body.style.overflow = 'hidden')"
                        class="px-10 py-5 bg-white text-gray-900 rounded-full font-bold text-lg shadow-2xl hover:shadow-xl transition-all hover:scale-105 flex items-center gap-3">
                    <i class="fas fa-calendar-check text-xl"></i>
                    Agendar Online
                </button>
                @if($settings->salon_whatsapp)
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $settings->salon_whatsapp) }}?text=Olá! Gostaria de agendar um serviço." 
                       target="_blank"
                       class="px-10 py-5 bg-green-500 text-white rounded-full font-bold text-lg shadow-2xl hover:shadow-xl transition-all hover:scale-105 flex items-center gap-3">
                        <i class="fab fa-whatsapp text-xl"></i>
                        WhatsApp
                    </a>
                @endif
            </div>
        </div>
    </section>

    {{-- ============================================== --}}
    {{-- CONTACTO SECTION --}}
    {{-- ============================================== --}}
    <section id="contacto" class="py-20 md:py-32 bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                {{-- Info Side --}}
                <div class="text-white">
                    <span class="inline-block px-4 py-1 rounded-full text-sm font-bold mb-4"
                          style="background: {{ $settings->primary_color ?? '#ec4899' }}; color: white;">
                        Contacto
                    </span>
                    <h2 class="text-3xl md:text-4xl font-black mb-6">
                        Visite-nos ou Entre em Contacto
                    </h2>
                    <p class="text-gray-400 mb-8">
                        Estamos à sua espera. Entre em contacto connosco para mais informações ou agende a sua visita.
                    </p>

                    {{-- Contact Info --}}
                    <div class="space-y-6">
                        @if($settings->salon_address)
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                                     style="background: {{ $settings->primary_color ?? '#ec4899' }}20">
                                    <i class="fas fa-map-marker-alt text-xl" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-white">Morada</p>
                                    <p class="text-gray-400">{{ $settings->salon_address }}</p>
                                </div>
                            </div>
                        @endif

                        @if($settings->salon_phone)
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                                     style="background: {{ $settings->primary_color ?? '#ec4899' }}20">
                                    <i class="fas fa-phone text-xl" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-white">Telefone</p>
                                    <a href="tel:{{ $settings->salon_phone }}" class="text-gray-400 hover:text-white transition">
                                        {{ $settings->salon_phone }}
                                    </a>
                                </div>
                            </div>
                        @endif

                        @if($settings->salon_email)
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                                     style="background: {{ $settings->primary_color ?? '#ec4899' }}20">
                                    <i class="fas fa-envelope text-xl" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-white">Email</p>
                                    <a href="mailto:{{ $settings->salon_email }}" class="text-gray-400 hover:text-white transition">
                                        {{ $settings->salon_email }}
                                    </a>
                                </div>
                            </div>
                        @endif

                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                                 style="background: {{ $settings->primary_color ?? '#ec4899' }}20">
                                <i class="fas fa-clock text-xl" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
                            </div>
                            <div>
                                <p class="font-bold text-white">Horário</p>
                                <p class="text-gray-400">{{ $settings->working_days_formatted ?? 'Segunda a Sábado' }}</p>
                                <p class="text-gray-400">{{ $settings->schedule_formatted ?? '09:00 - 19:00' }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Social Links --}}
                    <div class="flex items-center gap-4 mt-8">
                        @if($settings->salon_instagram)
                            <a href="https://instagram.com/{{ $settings->salon_instagram }}" target="_blank"
                               class="w-12 h-12 rounded-full flex items-center justify-center text-white transition-all hover:scale-110"
                               style="background: linear-gradient(45deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%)">
                                <i class="fab fa-instagram text-xl"></i>
                            </a>
                        @endif
                        @if($settings->salon_facebook)
                            <a href="{{ $settings->salon_facebook }}" target="_blank"
                               class="w-12 h-12 rounded-full bg-blue-600 flex items-center justify-center text-white hover:scale-110 transition-all">
                                <i class="fab fa-facebook-f text-xl"></i>
                            </a>
                        @endif
                        @if($settings->salon_tiktok)
                            <a href="https://tiktok.com/@{{ $settings->salon_tiktok }}" target="_blank"
                               class="w-12 h-12 rounded-full bg-black flex items-center justify-center text-white hover:scale-110 transition-all border border-white/20">
                                <i class="fab fa-tiktok text-xl"></i>
                            </a>
                        @endif
                        @if($settings->salon_whatsapp)
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $settings->salon_whatsapp) }}" target="_blank"
                               class="w-12 h-12 rounded-full bg-green-500 flex items-center justify-center text-white hover:scale-110 transition-all">
                                <i class="fab fa-whatsapp text-xl"></i>
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Map Side --}}
                <div class="relative">
                    @if($settings->salon_google_maps_url)
                        <div class="rounded-2xl overflow-hidden shadow-2xl h-96 lg:h-full">
                            <iframe 
                                src="{{ str_replace('/maps/', '/maps/embed?pb=', $settings->salon_google_maps_url) }}"
                                width="100%" 
                                height="100%" 
                                style="border:0;" 
                                allowfullscreen="" 
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade"
                                class="grayscale hover:grayscale-0 transition-all duration-500"></iframe>
                        </div>
                    @else
                        <div class="rounded-2xl h-96 lg:h-full flex items-center justify-center"
                             style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }}30 0%, {{ $settings->secondary_color ?? '#8b5cf6' }}30 100%)">
                            <div class="text-center">
                                <i class="fas fa-map-marked-alt text-6xl mb-4" style="color: {{ $settings->primary_color ?? '#ec4899' }}"></i>
                                <p class="text-gray-600">Mapa não configurado</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================== --}}
    {{-- FOOTER --}}
    {{-- ============================================== --}}
    <footer class="bg-gray-950 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    @if($settings->logo_url)
                        <img src="{{ $settings->logo_url }}" alt="{{ $settings->salon_name }}" class="h-8 w-8 rounded-lg object-cover">
                    @endif
                    <span class="text-white font-bold">{{ $settings->salon_name ?? 'Salão' }}</span>
                </div>
                <p class="text-gray-500 text-sm">
                    © {{ date('Y') }} {{ $settings->salon_name ?? 'Salão' }}. Todos os direitos reservados.
                </p>
                <p class="text-gray-600 text-xs">
                    Powered by <span class="text-gray-500">SOSERP</span>
                </p>
            </div>
        </div>
    </footer>

    {{-- ============================================== --}}
    {{-- BOOKING MODAL --}}
    {{-- ============================================== --}}
    <div x-show="showBookingModal" x-cloak
         class="fixed inset-0 z-[100] overflow-y-auto"
         @keydown.escape.window="showBookingModal = false; document.body.style.overflow = 'auto'">
        
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" 
             @click="showBookingModal = false; document.body.style.overflow = 'auto'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"></div>

        {{-- Modal Content --}}
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 @click.stop>
                
                {{-- Modal Header --}}
                <div class="sticky top-0 z-10 px-6 py-4 border-b border-gray-100 flex items-center justify-between"
                     style="background: linear-gradient(135deg, {{ $settings->primary_color ?? '#ec4899' }} 0%, {{ $settings->secondary_color ?? '#8b5cf6' }} 100%)">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-calendar-check text-white text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white">Agendar Serviço</h3>
                            <p class="text-xs text-white/70">{{ $settings->salon_name ?? 'Salão' }}</p>
                        </div>
                    </div>
                    <button @click="showBookingModal = false; document.body.style.overflow = 'auto'"
                            class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white hover:bg-white/30 transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="overflow-y-auto max-h-[calc(90vh-80px)]">
                    @include('livewire.salon.partials.booking-steps')
                </div>
            </div>
        </div>
    </div>

    {{-- Toast Notifications --}}
    <div x-data="{ show: false, message: '', type: 'success' }"
         x-on:error.window="show = true; message = $event.detail.message; type = 'error'; setTimeout(() => show = false, 5000)"
         x-on:success.window="show = true; message = $event.detail.message; type = 'success'; setTimeout(() => show = false, 4000)"
         x-show="show"
         x-transition
         class="fixed bottom-4 right-4 z-[110]">
        <div :class="type === 'error' ? 'bg-red-500' : 'bg-green-500'" 
             class="px-6 py-3 rounded-xl text-white shadow-lg flex items-center gap-2">
            <i :class="type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-check-circle'"></i>
            <span x-text="message"></span>
        </div>
    </div>

    {{-- Floating WhatsApp Button --}}
    @if($settings->salon_whatsapp)
    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $settings->salon_whatsapp) }}" target="_blank"
       class="fixed bottom-6 right-6 z-50 w-14 h-14 bg-green-500 rounded-full flex items-center justify-center text-white shadow-2xl hover:scale-110 transition-all hover:bg-green-600"
       x-show="!showBookingModal">
        <i class="fab fa-whatsapp text-2xl"></i>
    </a>
    @endif
</div>
