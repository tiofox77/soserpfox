<div class="relative" x-data="{ open: @entangle('showDropdown') }" @click.away="open = false">
    <!-- Sino de Notificações -->
    <button @click="open = !open" class="relative text-gray-600 hover:text-gray-900 transition">
        <i class="fas fa-bell text-xl"></i>
        @if($this->unreadCount > 0)
            <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full text-xs text-white flex items-center justify-center animate-pulse font-bold">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </button>
    
    <!-- Dropdown de Notificações -->
    <div x-show="open" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 mt-2 w-96 bg-white rounded-2xl shadow-2xl border-2 border-gray-200 z-50 overflow-hidden"
         wire:poll.60s>
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-4 py-3">
            <div class="flex items-center justify-between">
                <h3 class="text-white font-bold text-lg">
                    <i class="fas fa-bell mr-2"></i>Notificações
                </h3>
                <span class="text-white text-sm bg-white/20 px-2 py-1 rounded-full">
                    {{ $this->unreadCount }}
                </span>
            </div>
        </div>
        
        <!-- Lista de Notificações -->
        <div class="max-h-96 overflow-y-auto">
            @if($this->notifications()->isEmpty())
                <div class="p-8 text-center">
                    <i class="fas fa-bell-slash text-gray-300 text-5xl mb-3"></i>
                    <p class="text-gray-500 font-medium">Sem notificações</p>
                    <p class="text-gray-400 text-sm">Você está em dia!</p>
                </div>
            @else
                @foreach($this->notifications() as $notification)
                    @php
                        $bgColors = [
                            'green' => 'bg-green-50 hover:bg-green-100 border-green-200',
                            'yellow' => 'bg-yellow-50 hover:bg-yellow-100 border-yellow-200',
                            'orange' => 'bg-orange-50 hover:bg-orange-100 border-orange-200',
                            'red' => 'bg-red-50 hover:bg-red-100 border-red-200',
                            'blue' => 'bg-blue-50 hover:bg-blue-100 border-blue-200',
                        ];
                        $iconColors = [
                            'green' => 'text-green-600',
                            'yellow' => 'text-yellow-600',
                            'orange' => 'text-orange-600',
                            'red' => 'text-red-600',
                            'blue' => 'text-blue-600',
                        ];
                    @endphp
                    
                    <a href="{{ $notification['link'] }}" 
                       class="block p-4 border-b border-gray-100 {{ $bgColors[$notification['color']] ?? 'bg-gray-50 hover:bg-gray-100' }} transition-colors"
                       wire:click="closeDropdown">
                        <div class="flex items-start space-x-3">
                            <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center flex-shrink-0 shadow-sm">
                                <i class="fas {{ $notification['icon'] }} {{ $iconColors[$notification['color']] ?? 'text-gray-600' }} text-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <h4 class="text-sm font-bold text-gray-900 truncate">
                                        {{ $notification['title'] }}
                                    </h4>
                                    <span class="text-xs text-gray-500 ml-2">{{ $notification['time'] }}</span>
                                </div>
                                <p class="text-sm text-gray-700 line-clamp-2">
                                    {{ $notification['message'] }}
                                </p>
                            </div>
                        </div>
                    </a>
                @endforeach
            @endif
        </div>
        
        <!-- Footer -->
        @if($this->notifications()->isNotEmpty())
            <div class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                <a href="{{ route('my-account') }}" 
                   class="block text-center text-sm text-blue-600 hover:text-blue-700 font-semibold"
                   wire:click="closeDropdown">
                    <i class="fas fa-cog mr-2"></i>Ver Configurações
                </a>
            </div>
        @endif
    </div>
</div>
