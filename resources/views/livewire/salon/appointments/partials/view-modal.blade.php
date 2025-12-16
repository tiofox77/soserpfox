@if($showViewModal && $viewingAppointment)
<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" wire:click="closeViewModal"></div>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <!-- Header -->
            <div class="bg-gradient-to-r from-pink-500 to-purple-500 px-6 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center text-pink-600 text-2xl font-bold shadow-lg">
                            {{ $viewingAppointment->date->format('d') }}
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-white">Agendamento #{{ $viewingAppointment->id }}</h3>
                            <p class="text-pink-100">
                                {{ $viewingAppointment->date->format('d/m/Y') }} às {{ \Carbon\Carbon::parse($viewingAppointment->start_time)->format('H:i') }}
                            </p>
                        </div>
                    </div>
                    <button wire:click="closeViewModal" class="text-white hover:text-gray-200 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <!-- Status Badge -->
                <div class="flex justify-center mb-6">
                    @php
                        $statusColors = [
                            'scheduled' => 'bg-blue-100 text-blue-700 border-blue-300',
                            'confirmed' => 'bg-indigo-100 text-indigo-700 border-indigo-300',
                            'arrived' => 'bg-purple-100 text-purple-700 border-purple-300',
                            'in_progress' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
                            'completed' => 'bg-green-100 text-green-700 border-green-300',
                            'cancelled' => 'bg-red-100 text-red-700 border-red-300',
                            'no_show' => 'bg-gray-100 text-gray-700 border-gray-300',
                        ];
                    @endphp
                    <span class="px-6 py-2 rounded-full text-lg font-bold border-2 {{ $statusColors[$viewingAppointment->status] ?? '' }}">
                        {{ $viewingAppointment->status_label }}
                    </span>
                </div>

                <!-- Cliente e Profissional -->
                <div class="grid grid-cols-2 gap-6 mb-6">
                    <div class="bg-blue-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-blue-700 mb-3 flex items-center">
                            <i class="fas fa-user mr-2"></i>Cliente
                        </h4>
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold text-lg">
                                {{ strtoupper(substr($viewingAppointment->client->name ?? 'C', 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-bold text-gray-900">{{ $viewingAppointment->client->name ?? 'Cliente Avulso' }}</p>
                                @if($viewingAppointment->client?->phone)
                                    <p class="text-sm text-gray-500"><i class="fas fa-phone text-xs mr-1"></i>{{ $viewingAppointment->client->phone }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-purple-50 rounded-xl p-4">
                        <h4 class="text-sm font-bold text-purple-700 mb-3 flex items-center">
                            <i class="fas fa-user-tie mr-2"></i>Profissional
                        </h4>
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white font-bold text-lg">
                                {{ strtoupper(substr($viewingAppointment->professional->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-bold text-gray-900">{{ $viewingAppointment->professional->display_name }}</p>
                                <p class="text-sm text-gray-500">{{ $viewingAppointment->professional->specialization ?? 'Profissional' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Serviços -->
                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                    <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-spa text-pink-500 mr-2"></i>Serviços
                    </h4>
                    <div class="space-y-2">
                        @foreach($viewingAppointment->services as $svc)
                            <div class="flex justify-between items-center bg-white p-3 rounded-lg">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $svc->service->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $svc->duration }} minutos</p>
                                </div>
                                <p class="font-bold text-pink-600">{{ number_format($svc->price, 0, ',', '.') }} Kz</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Detalhes -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="text-center p-4 bg-pink-50 rounded-xl">
                        <p class="text-2xl font-bold text-pink-600">{{ $viewingAppointment->total_duration }}</p>
                        <p class="text-xs text-gray-500 font-semibold">minutos</p>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-xl">
                        <p class="text-2xl font-bold text-green-600">{{ number_format($viewingAppointment->total, 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-500 font-semibold">Kz Total</p>
                    </div>
                    <div class="text-center p-4 bg-blue-50 rounded-xl">
                        <p class="text-2xl font-bold text-blue-600">{{ \App\Models\Salon\Appointment::SOURCES[$viewingAppointment->source] ?? $viewingAppointment->source }}</p>
                        <p class="text-xs text-gray-500 font-semibold">Fonte</p>
                    </div>
                </div>

                @if($viewingAppointment->notes)
                    <div class="bg-yellow-50 rounded-xl p-4 mb-6">
                        <h4 class="text-sm font-bold text-yellow-700 mb-2 flex items-center">
                            <i class="fas fa-sticky-note mr-2"></i>Observações
                        </h4>
                        <p class="text-sm text-gray-700">{{ $viewingAppointment->notes }}</p>
                    </div>
                @endif

                <!-- Ações Rápidas -->
                <div class="pt-4 border-t border-gray-200 flex justify-between">
                    <div class="flex gap-2">
                        @if($viewingAppointment->status === 'scheduled')
                            <button wire:click="confirm({{ $viewingAppointment->id }})" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-semibold transition text-sm">
                                <i class="fas fa-check mr-1"></i>Confirmar
                            </button>
                        @endif
                        @if($viewingAppointment->status === 'confirmed')
                            <button wire:click="markArrived({{ $viewingAppointment->id }})" class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-xl font-semibold transition text-sm">
                                <i class="fas fa-user-check mr-1"></i>Chegou
                            </button>
                        @endif
                        @if(in_array($viewingAppointment->status, ['confirmed', 'arrived']))
                            <button wire:click="start({{ $viewingAppointment->id }})" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-xl font-semibold transition text-sm">
                                <i class="fas fa-play mr-1"></i>Iniciar
                            </button>
                        @endif
                        @if($viewingAppointment->status === 'in_progress')
                            <button wire:click="complete({{ $viewingAppointment->id }})" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-semibold transition text-sm">
                                <i class="fas fa-check-circle mr-1"></i>Concluir
                            </button>
                        @endif
                    </div>
                    <button wire:click="closeViewModal" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 rounded-xl font-semibold transition">
                        <i class="fas fa-times mr-2"></i>Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
