<!-- View Client Modal -->
@if($showViewModal && $viewingClient)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" wire:click="closeViewModal"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
            <!-- Header do Cliente -->
            <div class="bg-gradient-to-r from-pink-600 to-purple-600 px-6 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center text-pink-600 text-2xl font-bold shadow-lg">
                            {{ strtoupper(substr($viewingClient->name, 0, 2)) }}
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-white flex items-center gap-2">
                                {{ $viewingClient->name }}
                                @if($viewingClient->is_vip)
                                    <span class="px-2 py-0.5 bg-yellow-400 text-yellow-900 text-xs rounded-full font-semibold">
                                        <i class="fas fa-crown mr-1"></i>VIP
                                    </span>
                                @endif
                            </h3>
                            <p class="text-pink-100">
                                {{ $viewingClient->phone }} 
                                {{ $viewingClient->email ? '• ' . $viewingClient->email : '' }}
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
                <div class="grid grid-cols-4 gap-4 mb-6">
                    <div class="text-center p-4 bg-pink-50 rounded-xl">
                        <p class="text-3xl font-bold text-pink-600">{{ $viewingClient->total_visits ?? 0 }}</p>
                        <p class="text-xs text-gray-500 font-semibold">Visitas</p>
                    </div>
                    <div class="text-center p-4 bg-purple-50 rounded-xl">
                        <p class="text-2xl font-bold text-purple-600">{{ number_format($viewingClient->total_spent ?? 0, 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-500 font-semibold">Total Gasto (Kz)</p>
                    </div>
                    <div class="text-center p-4 bg-yellow-50 rounded-xl">
                        <p class="text-3xl font-bold text-yellow-600">{{ $viewingClient->loyalty_points ?? 0 }}</p>
                        <p class="text-xs text-gray-500 font-semibold">Pontos</p>
                    </div>
                    <div class="text-center p-4 bg-blue-50 rounded-xl">
                        <p class="text-3xl font-bold text-blue-600">{{ $viewingClient->appointments_count ?? 0 }}</p>
                        <p class="text-xs text-gray-500 font-semibold">Agendamentos</p>
                    </div>
                </div>

                <!-- Informações -->
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-address-card text-pink-500 mr-2"></i>Informações de Contato
                        </h4>
                        <div class="space-y-2 text-sm">
                            @if($viewingClient->phone)
                            <p class="flex items-center text-gray-600">
                                <i class="fas fa-phone text-green-500 mr-2 w-5"></i>{{ $viewingClient->phone }}
                            </p>
                            @endif
                            @if($viewingClient->mobile)
                            <p class="flex items-center text-gray-600">
                                <i class="fab fa-whatsapp text-green-600 mr-2 w-5"></i>{{ $viewingClient->mobile }}
                            </p>
                            @endif
                            @if($viewingClient->email)
                            <p class="flex items-center text-gray-600">
                                <i class="fas fa-envelope text-purple-500 mr-2 w-5"></i>{{ $viewingClient->email }}
                            </p>
                            @endif
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>Localização
                        </h4>
                        <div class="space-y-2 text-sm">
                            @if($viewingClient->address)
                            <p class="flex items-center text-gray-600">
                                <i class="fas fa-home text-gray-400 mr-2 w-5"></i>{{ $viewingClient->address }}
                            </p>
                            @endif
                            @if($viewingClient->city || $viewingClient->province)
                            <p class="flex items-center text-gray-600">
                                <i class="fas fa-city text-indigo-500 mr-2 w-5"></i>
                                {{ $viewingClient->city }}{{ $viewingClient->city && $viewingClient->province ? ', ' : '' }}{{ $viewingClient->province }}
                            </p>
                            @endif
                            @if($viewingClient->country)
                            <p class="flex items-center text-gray-600">
                                <i class="fas fa-globe text-blue-500 mr-2 w-5"></i>{{ $viewingClient->country }}
                            </p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Últimos Agendamentos -->
                @if($viewingClient->appointments && $viewingClient->appointments->count() > 0)
                <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>Últimos Agendamentos
                    </h4>
                    <div class="space-y-2">
                        @foreach($viewingClient->appointments->take(5) as $appointment)
                        <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-gray-200">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-pink-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-calendar-check text-pink-600"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 text-sm">
                                        {{ $appointment->date?->format('d/m/Y') }} às {{ $appointment->time?->format('H:i') ?? $appointment->time }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ $appointment->professional?->name ?? 'Profissional não definido' }}
                                    </p>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full
                                {{ $appointment->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                {{ $appointment->status === 'scheduled' ? 'bg-blue-100 text-blue-700' : '' }}
                                {{ $appointment->status === 'confirmed' ? 'bg-purple-100 text-purple-700' : '' }}
                                {{ $appointment->status === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}
                            ">
                                {{ ucfirst($appointment->status) }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-between">
                    <button wire:click="openModal({{ $viewingClient->id }})" class="px-6 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-semibold transition">
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
