<div>
    {{-- Header --}}
    <div class="mb-6 bg-gradient-to-r from-pink-600 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-cog text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Configurações do Salão</h2>
                    <p class="text-pink-100 text-sm">Configure o seu salão e página de agendamento online</p>
                </div>
            </div>
            <button wire:click="save" 
                    class="px-6 py-3 bg-white text-pink-600 rounded-xl font-bold hover:bg-pink-50 transition shadow-lg flex items-center gap-2">
                <i class="fas fa-save"></i>
                <span>Guardar Alterações</span>
            </button>
        </div>
    </div>

    {{-- Link de Agendamento Destacado --}}
    @if($booking_slug)
    <div class="mb-6 bg-gradient-to-r from-emerald-500 to-teal-500 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold flex items-center gap-2">
                    <i class="fas fa-link"></i>
                    Link de Agendamento Online
                </h3>
                <p class="text-emerald-100 text-sm mt-1">Partilhe este link com os seus clientes para agendarem online</p>
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
                <i class="fas fa-sync-alt"></i> Regenerar Link
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
                    'general' => ['icon' => 'store', 'label' => 'Geral'],
                    'schedule' => ['icon' => 'clock', 'label' => 'Horários'],
                    'booking' => ['icon' => 'calendar-check', 'label' => 'Agendamento'],
                    'branding' => ['icon' => 'palette', 'label' => 'Aparência'],
                    'social' => ['icon' => 'share-alt', 'label' => 'Redes Sociais'],
                    'policies' => ['icon' => 'file-contract', 'label' => 'Políticas'],
                    'seo' => ['icon' => 'search', 'label' => 'SEO'],
                ] as $tab => $info)
                    <button wire:click="setTab('{{ $tab }}')"
                            class="flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition whitespace-nowrap
                                   {{ $activeTab === $tab 
                                      ? 'border-pink-500 text-pink-600 bg-white' 
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
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-store mr-1 text-pink-500"></i> Nome do Salão *
                        </label>
                        <input wire:model="salon_name" type="text" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                               placeholder="Ex: Beauty Studio">
                        @error('salon_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-envelope mr-1 text-pink-500"></i> Email
                        </label>
                        <input wire:model="salon_email" type="email" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                               placeholder="contato@salao.com">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-phone mr-1 text-pink-500"></i> Telefone
                        </label>
                        <input wire:model="salon_phone" type="text" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                               placeholder="+244 923 456 789">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fab fa-whatsapp mr-1 text-green-500"></i> WhatsApp
                        </label>
                        <input wire:model="salon_whatsapp" type="text" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                               placeholder="+244 923 456 789">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-1 text-pink-500"></i> Endereço
                    </label>
                    <input wire:model="salon_address" type="text" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                           placeholder="Rua Principal, 123 - Bairro - Cidade">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-align-left mr-1 text-pink-500"></i> Descrição do Salão
                    </label>
                    <textarea wire:model="salon_description" rows="4" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                              placeholder="Descreva o seu salão, serviços especiais, diferenciais..."></textarea>
                </div>
            </div>
            @endif

            {{-- Tab: Horários --}}
            @if($activeTab === 'schedule')
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-door-open mr-1 text-pink-500"></i> Horário de Abertura
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-clock text-gray-400"></i>
                            </div>
                            <input wire:model="opening_time" type="text" 
                                   x-data x-init="
                                       $el.addEventListener('input', (e) => {
                                           let value = e.target.value.replace(/[^0-9]/g, '');
                                           if (value.length >= 2) {
                                               value = value.slice(0, 2) + ':' + value.slice(2, 4);
                                           }
                                           e.target.value = value.slice(0, 5);
                                       });
                                       $el.addEventListener('blur', (e) => {
                                           let parts = e.target.value.split(':');
                                           if (parts[0] && parts[0].length === 1) parts[0] = '0' + parts[0];
                                           if (parts[1] && parts[1].length === 1) parts[1] = parts[1] + '0';
                                           if (parts[0] && parseInt(parts[0]) > 23) parts[0] = '23';
                                           if (parts[1] && parseInt(parts[1]) > 59) parts[1] = '59';
                                           e.target.value = parts.join(':');
                                           $wire.set('opening_time', e.target.value);
                                       });
                                   "
                                   class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                                   placeholder="09:00" maxlength="5">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Formato 24h: 00:00 - 23:59</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-door-closed mr-1 text-pink-500"></i> Horário de Fecho
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-clock text-gray-400"></i>
                            </div>
                            <input wire:model="closing_time" type="text" 
                                   x-data x-init="
                                       $el.addEventListener('input', (e) => {
                                           let value = e.target.value.replace(/[^0-9]/g, '');
                                           if (value.length >= 2) {
                                               value = value.slice(0, 2) + ':' + value.slice(2, 4);
                                           }
                                           e.target.value = value.slice(0, 5);
                                       });
                                       $el.addEventListener('blur', (e) => {
                                           let parts = e.target.value.split(':');
                                           if (parts[0] && parts[0].length === 1) parts[0] = '0' + parts[0];
                                           if (parts[1] && parts[1].length === 1) parts[1] = parts[1] + '0';
                                           if (parts[0] && parseInt(parts[0]) > 23) parts[0] = '23';
                                           if (parts[1] && parseInt(parts[1]) > 59) parts[1] = '59';
                                           e.target.value = parts.join(':');
                                           $wire.set('closing_time', e.target.value);
                                       });
                                   "
                                   class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                                   placeholder="19:00" maxlength="5">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Formato 24h: 00:00 - 23:59</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-3">
                        <i class="fas fa-calendar-week mr-1 text-pink-500"></i> Dias de Funcionamento
                    </label>
                    <div class="flex flex-wrap gap-3">
                        @foreach([1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'] as $day => $name)
                            <label class="flex items-center gap-2 px-4 py-2 border rounded-xl cursor-pointer transition
                                          {{ in_array($day, $working_days) ? 'bg-pink-100 border-pink-500 text-pink-700' : 'border-gray-300 text-gray-600 hover:border-pink-300' }}">
                                <input type="checkbox" wire:model.live="working_days" value="{{ $day }}" class="sr-only">
                                <span class="font-medium text-sm">{{ $name }}</span>
                                @if(in_array($day, $working_days))
                                    <i class="fas fa-check text-pink-600 text-xs"></i>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="bg-pink-50 rounded-xl p-4 border border-pink-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-info-circle text-pink-500"></i>
                        </div>
                        <div>
                            <p class="font-medium text-pink-800">Horário configurado</p>
                            <p class="text-sm text-pink-600">
                                {{ collect($working_days)->sort()->map(fn($d) => ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'][$d-1] ?? '')->filter()->implode(', ') }}
                                das {{ $opening_time }} às {{ $closing_time }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Tab: Agendamento --}}
            @if($activeTab === 'booking')
            <div class="space-y-6">
                {{-- Toggle Online Booking --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-globe text-pink-500"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-800">Agendamento Online</p>
                            <p class="text-sm text-gray-500">Permitir que clientes agendem pelo link</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="online_booking_enabled" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-pink-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-pink-500"></div>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-clock mr-1 text-pink-500"></i> Intervalo entre Slots (minutos)
                        </label>
                        <select wire:model="slot_interval" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition">
                            <option value="15">15 minutos</option>
                            <option value="30">30 minutos</option>
                            <option value="45">45 minutos</option>
                            <option value="60">1 hora</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-hourglass-start mr-1 text-pink-500"></i> Antecedência Mínima (horas)
                        </label>
                        <input wire:model="min_advance_booking_hours" type="number" min="0" max="72"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                               placeholder="2">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-calendar-plus mr-1 text-pink-500"></i> Máximo de Dias para Agendar
                        </label>
                        <input wire:model="max_advance_booking_days" type="number" min="1" max="365"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                               placeholder="30">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-bell mr-1 text-pink-500"></i> Lembrete (horas antes)
                        </label>
                        <input wire:model="reminder_hours" type="number" min="1" max="72"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                               placeholder="24">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-ban mr-1 text-pink-500"></i> Cancelamento (horas antes)
                        </label>
                        <input wire:model="cancellation_hours" type="number" min="0" max="72"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                               placeholder="24">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-user-slash mr-1 text-pink-500"></i> Taxa No-Show (%)
                        </label>
                        <input wire:model="no_show_fee_percent" type="number" min="0" max="100" step="0.01"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                               placeholder="0">
                    </div>
                </div>

                {{-- Toggle Confirmação --}}
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-double text-blue-500"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-800">Exigir Confirmação</p>
                            <p class="text-sm text-gray-500">Agendamentos precisam ser confirmados antes</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="require_confirmation" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-pink-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-pink-500"></div>
                    </label>
                </div>
            </div>
            @endif

            {{-- Tab: Branding --}}
            @if($activeTab === 'branding')
            <div class="space-y-6">
                {{-- Cores --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-palette mr-1 text-pink-500"></i> Cor Principal
                        </label>
                        <div class="flex items-center gap-3">
                            <input wire:model.live="primary_color" type="color" 
                                   class="w-16 h-12 border-2 border-gray-300 rounded-xl cursor-pointer">
                            <input wire:model.live="primary_color" type="text" 
                                   class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition uppercase"
                                   placeholder="#ec4899" maxlength="7">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-fill-drip mr-1 text-purple-500"></i> Cor Secundária
                        </label>
                        <div class="flex items-center gap-3">
                            <input wire:model.live="secondary_color" type="color" 
                                   class="w-16 h-12 border-2 border-gray-300 rounded-xl cursor-pointer">
                            <input wire:model.live="secondary_color" type="text" 
                                   class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition uppercase"
                                   placeholder="#8b5cf6" maxlength="7">
                        </div>
                    </div>
                </div>

                {{-- Preview de cores --}}
                <div class="p-4 rounded-xl" style="background: linear-gradient(135deg, {{ $primary_color }} 0%, {{ $secondary_color }} 100%)">
                    <p class="text-white font-bold text-center">Preview do Gradiente</p>
                </div>

                {{-- Logo --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-image mr-1 text-pink-500"></i> Logo do Salão
                    </label>
                    <div class="flex items-start gap-4">
                        @if($currentLogo)
                            <div class="w-24 h-24 rounded-xl overflow-hidden border-2 border-gray-200 bg-gray-50">
                                <img src="{{ $currentLogo }}" alt="Logo" class="w-full h-full object-contain">
                            </div>
                        @endif
                        <div class="flex-1">
                            <input wire:model="newLogo" type="file" accept="image/*" 
                                   class="w-full border-2 border-dashed border-gray-300 rounded-xl p-4 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100">
                            @error('newLogo') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            <p class="text-xs text-gray-500 mt-1">PNG, JPG ou SVG. Máximo 2MB.</p>
                        </div>
                    </div>
                </div>

                {{-- Cover Image --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-panorama mr-1 text-pink-500"></i> Imagem de Capa
                    </label>
                    <div class="space-y-3">
                        @if($currentCoverImage)
                            <div class="w-full h-48 rounded-xl overflow-hidden border-2 border-gray-200 bg-gray-50">
                                <img src="{{ $currentCoverImage }}" alt="Capa" class="w-full h-full object-cover">
                            </div>
                        @endif
                        <input wire:model="newCoverImage" type="file" accept="image/*" 
                               class="w-full border-2 border-dashed border-gray-300 rounded-xl p-4 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100">
                        @error('newCoverImage') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-500">PNG ou JPG. Dimensões recomendadas: 1920x600px. Máximo 5MB.</p>
                    </div>
                </div>

                {{-- Mensagem de Boas-vindas --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-comment-dots mr-1 text-pink-500"></i> Mensagem de Boas-vindas
                    </label>
                    <textarea wire:model="welcome_message" rows="3" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                              placeholder="Bem-vindo ao nosso salão! Escolha o serviço ideal para você..."></textarea>
                </div>
            </div>
            @endif

            {{-- Tab: Redes Sociais --}}
            @if($activeTab === 'social')
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fab fa-instagram mr-1 text-pink-500"></i> Instagram
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <span class="text-gray-400">@</span>
                            </div>
                            <input wire:model="salon_instagram" type="text" 
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                                   placeholder="meusalao">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fab fa-facebook mr-1 text-blue-600"></i> Facebook
                        </label>
                        <input wire:model="salon_facebook" type="url" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                               placeholder="https://facebook.com/meusalao">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fab fa-tiktok mr-1 text-gray-800"></i> TikTok
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <span class="text-gray-400">@</span>
                            </div>
                            <input wire:model="salon_tiktok" type="text" 
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                                   placeholder="meusalao">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-globe mr-1 text-green-500"></i> Website
                        </label>
                        <input wire:model="salon_website" type="url" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                               placeholder="https://www.meusalao.ao">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-map-marked-alt mr-1 text-red-500"></i> Link do Google Maps
                    </label>
                    <input wire:model="salon_google_maps_url" type="url" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                           placeholder="https://maps.google.com/?q=...">
                    <p class="text-xs text-gray-500 mt-1">Cole o link de partilha do Google Maps</p>
                </div>
            </div>
            @endif

            {{-- Tab: Políticas --}}
            @if($activeTab === 'policies')
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-file-contract mr-1 text-pink-500"></i> Termos de Agendamento
                    </label>
                    <textarea wire:model="booking_terms" rows="5" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                              placeholder="Ao agendar, você concorda com os seguintes termos..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-ban mr-1 text-pink-500"></i> Política de Cancelamento
                    </label>
                    <textarea wire:model="cancellation_policy" rows="5" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                              placeholder="Cancelamentos devem ser feitos com pelo menos 24 horas de antecedência..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-1 text-pink-500"></i> Mensagem de Confirmação
                    </label>
                    <textarea wire:model="confirmation_message" rows="4" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                              placeholder="Obrigado pelo seu agendamento! Aguardamos você..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">Variáveis disponíveis: {cliente}, {data}, {hora}, {servico}, {profissional}</p>
                </div>
            </div>
            @endif

            {{-- Tab: SEO --}}
            @if($activeTab === 'seo')
            <div class="space-y-6">
                <div class="bg-blue-50 rounded-xl p-4 border border-blue-100 mb-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-search text-blue-500"></i>
                        </div>
                        <div>
                            <p class="font-medium text-blue-800">Otimização para Motores de Busca</p>
                            <p class="text-sm text-blue-600">Melhore a visibilidade da sua página de agendamento no Google e outros buscadores.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-heading mr-1 text-pink-500"></i> Título da Página
                    </label>
                    <input wire:model="meta_title" type="text" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                           placeholder="Beauty Studio - Agendamento Online | Luanda"
                           maxlength="60">
                    <p class="text-xs text-gray-500 mt-1">Máximo 60 caracteres. {{ strlen($meta_title ?? '') }}/60</p>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-file-alt mr-1 text-pink-500"></i> Descrição da Página
                    </label>
                    <textarea wire:model="meta_description" rows="3" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-pink-500 focus:border-transparent transition"
                              placeholder="Agende online no Beauty Studio. Serviços de cabelo, unhas, estética e muito mais..."
                              maxlength="160"></textarea>
                    <p class="text-xs text-gray-500 mt-1">Máximo 160 caracteres. {{ strlen($meta_description ?? '') }}/160</p>
                </div>

                {{-- Preview do Google --}}
                <div class="border border-gray-200 rounded-xl p-4 bg-white">
                    <p class="text-xs text-gray-500 mb-2 font-medium">Preview no Google:</p>
                    <div class="text-blue-600 text-lg font-medium hover:underline cursor-pointer">
                        {{ $meta_title ?: $salon_name ?: 'Título da Página' }}
                    </div>
                    <div class="text-green-700 text-sm">
                        {{ $bookingUrl ?: url('/agendar/meu-salao') }}
                    </div>
                    <div class="text-gray-600 text-sm mt-1">
                        {{ $meta_description ?: 'Descrição da página de agendamento do salão...' }}
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Script para copiar URL --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('copyToClipboard', (data) => {
                navigator.clipboard.writeText(data.url);
            });
        });
    </script>
</div>
