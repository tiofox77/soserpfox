<div>
@if($this->subscriptionData)
    @php
        $data = $this->subscriptionData;
        $colorClasses = [
            'red' => 'from-red-500 to-red-600 border-red-400',
            'orange' => 'from-orange-500 to-orange-600 border-orange-400',
            'yellow' => 'from-yellow-500 to-yellow-600 border-yellow-400',
            'green' => 'from-green-500 to-green-600 border-green-400',
        ];
        $textColorClasses = [
            'red' => 'text-red-700',
            'orange' => 'text-orange-700',
            'yellow' => 'text-yellow-700',
            'green' => 'text-green-700',
        ];
        $bgColorClasses = [
            'red' => 'bg-red-50',
            'orange' => 'bg-orange-50',
            'yellow' => 'bg-yellow-50',
            'green' => 'bg-green-50',
        ];
    @endphp
    
    <div class="relative group" wire:poll.60s>
        <!-- Timer Compacto (visível sempre) -->
        <div class="flex items-center space-x-2 px-3 py-2 rounded-xl bg-gradient-to-r {{ $colorClasses[$data['color']] ?? 'from-gray-500 to-gray-600 border-gray-400' }} border-2 shadow-lg cursor-pointer hover:scale-105 transition-transform">
            <div class="w-8 h-8 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center {{ $data['expired'] ? 'animate-pulse' : '' }}">
                <i class="fas {{ $data['expired'] ? 'fa-exclamation-triangle' : 'fa-clock' }} text-white text-sm"></i>
            </div>
            <div class="text-white">
                <div class="text-xs font-medium opacity-90">{{ $data['expired'] ? 'Expirado!' : 'Plano' }}</div>
                <div class="text-lg font-bold leading-none">
                    @if($data['expired'])
                        0d
                    @else
                        {{ $data['days'] }}d {{ $data['hours'] }}h
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Tooltip Detalhado (hover) -->
        <div class="absolute top-full right-0 mt-2 w-80 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50">
            <div class="bg-white rounded-2xl shadow-2xl border-2 {{ $colorClasses[$data['color']] ?? 'border-gray-400' }} overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r {{ $colorClasses[$data['color']] ?? 'from-gray-500 to-gray-600' }} px-4 py-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-crown text-white text-lg"></i>
                            <span class="text-white font-bold">{{ $data['plan_name'] }}</span>
                        </div>
                        <span class="text-white text-xs opacity-75 uppercase">{{ $data['billing_cycle'] ?? 'monthly' }}</span>
                    </div>
                </div>
                
                <!-- Body -->
                <div class="p-4 {{ $bgColorClasses[$data['color']] ?? 'bg-gray-50' }}">
                    @if($data['expired'])
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-3"></i>
                            <p class="text-red-700 font-bold text-lg mb-2">Subscription Expirada!</p>
                            <p class="text-red-600 text-sm mb-4">Renove seu plano para continuar usando o sistema.</p>
                            <a href="{{ route('my-account') }}?tab=plan" class="inline-block px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold text-sm transition">
                                <i class="fas fa-arrow-up mr-2"></i>Renovar Agora
                            </a>
                        </div>
                    @else
                        <!-- Contador Grande -->
                        <div class="text-center mb-4">
                            <div class="flex items-center justify-center space-x-4 mb-3">
                                <!-- Dias -->
                                <div class="bg-white rounded-lg p-3 shadow-md min-w-[80px]">
                                    <div class="text-3xl font-bold {{ $textColorClasses[$data['color']] ?? 'text-gray-700' }}">
                                        {{ $data['days'] }}
                                    </div>
                                    <div class="text-xs text-gray-500 font-medium">
                                        {{ $data['days'] == 1 ? 'Dia' : 'Dias' }}
                                    </div>
                                </div>
                                
                                <!-- Horas -->
                                <div class="bg-white rounded-lg p-3 shadow-md min-w-[80px]">
                                    <div class="text-3xl font-bold {{ $textColorClasses[$data['color']] ?? 'text-gray-700' }}">
                                        {{ $data['hours'] }}
                                    </div>
                                    <div class="text-xs text-gray-500 font-medium">
                                        {{ $data['hours'] == 1 ? 'Hora' : 'Horas' }}
                                    </div>
                                </div>
                            </div>
                            
                            <p class="text-xs text-gray-600">
                                <i class="fas fa-calendar mr-1"></i>
                                Expira em: <strong>{{ $data['ends_at'] }}</strong>
                            </p>
                        </div>
                        
                        <!-- Status Alert -->
                        <div class="p-3 bg-white rounded-lg border-2 {{ $colorClasses[$data['color']] ?? 'border-gray-400' }} mb-3">
                            <div class="flex items-start space-x-2">
                                <i class="fas {{ $data['status'] == 'critical' ? 'fa-exclamation-triangle' : ($data['status'] == 'warning' ? 'fa-exclamation-circle' : 'fa-info-circle') }} {{ $textColorClasses[$data['color']] ?? 'text-gray-700' }} mt-1"></i>
                                <div class="flex-1">
                                    <p class="text-sm {{ $textColorClasses[$data['color']] ?? 'text-gray-700' }} font-semibold">
                                        @if($data['status'] == 'critical')
                                            ⚠️ Atenção Urgente!
                                        @elseif($data['status'] == 'warning')
                                            ⚠️ Renovação Próxima
                                        @elseif($data['status'] == 'attention')
                                            💡 Lembre-se de Renovar
                                        @else
                                            ✅ Tudo em Ordem
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-600 mt-1">
                                        @if($data['days'] <= 3)
                                            Sua subscription expira em breve! Renove agora para evitar interrupções.
                                        @elseif($data['days'] <= 7)
                                            Planeje renovar sua subscription nos próximos dias.
                                        @elseif($data['days'] <= 15)
                                            Sua subscription está se aproximando da data de renovação.
                                        @else
                                            Sua subscription está ativa e válida.
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botão Renovar -->
                        @if($data['days'] <= 15)
                            <a href="{{ route('my-account') }}?tab=plan" class="block w-full text-center px-4 py-2 bg-gradient-to-r {{ $colorClasses[$data['color']] ?? 'from-gray-500 to-gray-600' }} text-white rounded-lg font-semibold text-sm hover:scale-105 transition shadow-lg">
                                <i class="fas fa-arrow-up mr-2"></i>Renovar Subscription
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
</div>
