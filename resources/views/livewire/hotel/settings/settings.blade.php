<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-cog text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Configurações do Hotel</h2>
                    <p class="text-blue-100 text-sm">Configure o seu hotel e página de reservas online</p>
                </div>
            </div>
            <button wire:click="save" 
                    class="px-6 py-3 bg-white text-blue-600 rounded-xl font-bold hover:bg-blue-50 transition shadow-lg flex items-center gap-2">
                <i class="fas fa-save"></i>
                <span>Guardar Alterações</span>
            </button>
        </div>
    </div>

    {{-- Link de Reserva Destacado --}}
    @if($booking_slug)
    <div class="mb-6 bg-gradient-to-r from-emerald-500 to-teal-500 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <i class="fas fa-link"></i>
                    Link de Reserva Online
                </h3>
                <p class="text-emerald-100 text-sm mt-1">Partilhe este link para os clientes reservarem online</p>
                <div class="mt-3 flex items-center gap-3">
                    <code class="bg-white/20 backdrop-blur-sm px-4 py-2 rounded-lg font-mono text-sm">
                        {{ $bookingUrl }}
                    </code>
                    <button wire:click="copyBookingUrl" 
                            class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg transition flex items-center gap-2 text-sm font-medium">
                        <i class="fas fa-copy"></i> Copiar
                    </button>
                    <a href="{{ $bookingUrl }}" target="_blank" 
                       class="px-4 py-2 bg-white text-emerald-600 hover:bg-emerald-50 rounded-lg transition flex items-center gap-2 text-sm font-medium">
                        <i class="fas fa-external-link-alt"></i> Ver Página
                    </a>
                </div>
            </div>
            <button wire:click="regenerateSlug" wire:confirm="Tem certeza? O link atual deixará de funcionar."
                    class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg transition flex items-center gap-2 text-sm font-medium">
                <i class="fas fa-sync-alt"></i> Regenerar
            </button>
        </div>
    </div>
    @endif

    {{-- Tabs --}}
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        {{-- Tab Navigation --}}
        <div class="border-b border-gray-200 bg-gray-50/50">
            <div class="flex overflow-x-auto">
                @foreach([
                    'general' => ['icon' => 'hotel', 'label' => 'Geral'],
                    'rooms' => ['icon' => 'bed', 'label' => 'Quartos'],
                    'schedule' => ['icon' => 'clock', 'label' => 'Horários'],
                    'booking' => ['icon' => 'calendar-check', 'label' => 'Reservas'],
                    'branding' => ['icon' => 'palette', 'label' => 'Aparência'],
                    'amenities' => ['icon' => 'concierge-bell', 'label' => 'Comodidades'],
                    'social' => ['icon' => 'share-alt', 'label' => 'Redes Sociais'],
                    'policies' => ['icon' => 'file-contract', 'label' => 'Políticas'],
                ] as $tab => $info)
                    <button wire:click="setTab('{{ $tab }}')"
                            class="flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition whitespace-nowrap
                                   {{ $activeTab === $tab 
                                      ? 'border-blue-500 text-blue-600 bg-white' 
                                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        <i class="fas fa-{{ $info['icon'] }}"></i>
                        {{ $info['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Tab Content --}}
        <div class="p-6">
            {{-- Tab: Geral --}}
            @if($activeTab === 'general')
            @include('livewire.hotel.settings.partials.tab-general')
            @endif

            {{-- Tab: Quartos --}}
            @if($activeTab === 'rooms')
            @include('livewire.hotel.settings.partials.tab-rooms')
            @endif

            {{-- Tab: Horários --}}
            @if($activeTab === 'schedule')
            @include('livewire.hotel.settings.partials.tab-schedule')
            @endif

            {{-- Tab: Reservas --}}
            @if($activeTab === 'booking')
            @include('livewire.hotel.settings.partials.tab-booking')
            @endif

            {{-- Tab: Aparência --}}
            @if($activeTab === 'branding')
            @include('livewire.hotel.settings.partials.tab-branding')
            @endif

            {{-- Tab: Comodidades --}}
            @if($activeTab === 'amenities')
            @include('livewire.hotel.settings.partials.tab-amenities')
            @endif

            {{-- Tab: Redes Sociais --}}
            @if($activeTab === 'social')
            @include('livewire.hotel.settings.partials.tab-social')
            @endif

            {{-- Tab: Políticas --}}
            @if($activeTab === 'policies')
            @include('livewire.hotel.settings.partials.tab-policies')
            @endif
        </div>
    </div>

    {{-- Toast --}}
    @include('livewire.hotel.settings.partials.toast')
</div>
