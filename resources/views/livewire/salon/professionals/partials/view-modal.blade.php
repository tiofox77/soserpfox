@if($showViewModal && $viewingProfessional)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" wire:click="closeViewModal"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <!-- Header -->
            <div class="bg-gradient-to-r from-orange-500 to-amber-500 px-6 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center text-orange-600 text-2xl font-bold shadow-lg">
                            {{ strtoupper(substr($viewingProfessional->name, 0, 1)) }}
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-white">{{ $viewingProfessional->name }}</h3>
                            <p class="text-orange-100">
                                {{ $viewingProfessional->specialization ?? 'Profissional' }}
                                @if(!$viewingProfessional->is_active)
                                    <span class="px-2 py-0.5 bg-red-400 text-white text-xs rounded-full ml-2">Inativo</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <button wire:click="closeViewModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <!-- Stats -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="text-center p-4 bg-purple-50 rounded-xl">
                        <p class="text-2xl font-bold text-purple-600">{{ $viewingProfessional->services->count() }}</p>
                        <p class="text-xs text-gray-500 font-semibold">Serviços</p>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-xl">
                        <p class="text-2xl font-bold text-green-600">{{ $viewingProfessional->commission_percent }}%</p>
                        <p class="text-xs text-gray-500 font-semibold">Comissão</p>
                    </div>
                    <div class="text-center p-4 bg-blue-50 rounded-xl">
                        <p class="text-2xl font-bold text-blue-600">{{ count($viewingProfessional->working_days ?? []) }}</p>
                        <p class="text-xs text-gray-500 font-semibold">Dias/Semana</p>
                    </div>
                </div>

                <!-- Detalhes -->
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-address-card text-orange-500 mr-2"></i>Contacto
                        </h4>
                        <div class="space-y-2 text-sm">
                            @if($viewingProfessional->phone)
                                <p class="flex items-center">
                                    <i class="fas fa-phone text-blue-500 mr-2 w-4"></i>
                                    <span>{{ $viewingProfessional->phone }}</span>
                                </p>
                            @endif
                            @if($viewingProfessional->email)
                                <p class="flex items-center">
                                    <i class="fas fa-envelope text-gray-500 mr-2 w-4"></i>
                                    <span class="truncate">{{ $viewingProfessional->email }}</span>
                                </p>
                            @endif
                            @if($viewingProfessional->nickname)
                                <p class="flex items-center">
                                    <i class="fas fa-tag text-orange-500 mr-2 w-4"></i>
                                    <span>{{ $viewingProfessional->nickname }}</span>
                                </p>
                            @endif
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-clock text-green-500 mr-2"></i>Horário
                        </h4>
                        <div class="space-y-2 text-sm">
                            <p class="flex items-center justify-between">
                                <span class="text-gray-500">Trabalho:</span>
                                <span class="font-semibold">
                                    {{ $viewingProfessional->work_start ? \Carbon\Carbon::parse($viewingProfessional->work_start)->format('H:i') : '09:00' }} - 
                                    {{ $viewingProfessional->work_end ? \Carbon\Carbon::parse($viewingProfessional->work_end)->format('H:i') : '18:00' }}
                                </span>
                            </p>
                            @if($viewingProfessional->lunch_start && $viewingProfessional->lunch_end)
                                <p class="flex items-center justify-between">
                                    <span class="text-gray-500">Almoço:</span>
                                    <span class="font-semibold">
                                        {{ \Carbon\Carbon::parse($viewingProfessional->lunch_start)->format('H:i') }} - 
                                        {{ \Carbon\Carbon::parse($viewingProfessional->lunch_end)->format('H:i') }}
                                    </span>
                                </p>
                            @endif
                            <p class="flex items-center justify-between">
                                <span class="text-gray-500">Online:</span>
                                @if($viewingProfessional->accepts_online_booking)
                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold">Sim</span>
                                @else
                                    <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-lg text-xs font-semibold">Não</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Dias de Trabalho -->
                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                    <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-calendar-week text-blue-500 mr-2"></i>Dias de Trabalho
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        @php
                            $days = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 0 => 'Dom'];
                            $workDays = $viewingProfessional->working_days ?? [];
                        @endphp
                        @foreach($days as $num => $name)
                            <span class="px-3 py-1 rounded-full text-xs font-bold {{ in_array($num, $workDays) ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-400' }}">
                                {{ $name }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <!-- Serviços -->
                @if($viewingProfessional->services->count() > 0)
                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                    <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-spa text-purple-500 mr-2"></i>Serviços que Realiza
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($viewingProfessional->services as $service)
                            <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">
                                {{ $service->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($viewingProfessional->bio)
                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                    <h4 class="text-sm font-bold text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-align-left text-gray-500 mr-2"></i>Biografia
                    </h4>
                    <p class="text-sm text-gray-600">{{ $viewingProfessional->bio }}</p>
                </div>
                @endif

                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-between">
                    <button wire:click="openModal({{ $viewingProfessional->id }})" class="px-6 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-semibold transition">
                        <i class="fas fa-edit mr-2"></i>Editar
                    </button>
                    <button wire:click="closeViewModal" class="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 rounded-xl font-semibold transition">
                        <i class="fas fa-times mr-2"></i>Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
